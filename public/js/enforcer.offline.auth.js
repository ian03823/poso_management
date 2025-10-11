(function(){
  if (window.EnforcerOfflineAuth) return;

  const DB_NAME = 'enforcerAuthDB';
  const DB_VER  = 1;
  const STORE   = 'logins';
  const hasDexie = !!window.Dexie;

  // --- tiny IDB wrapper (Dexie if present, otherwise null) ---
  let db = null;
  if (hasDexie) {
    try {
      db = new Dexie(DB_NAME);
      db.version(DB_VER).stores({ [STORE]: 'username' });
    } catch (e) { db = null; }
  }
  const LS_KEY = 'enforcer_offline_logins'; // map username->{salt,hash,profile,updatedAt}

  const now = () => Date.now();
  const norm = (u) => String(u||'').trim().toLowerCase();

  // --- crypto helpers (PBKDF2-SHA-256) ---
  function toBytes(str){ return new TextEncoder().encode(str); }
  function toHex(u8){ return [...u8].map(b=>b.toString(16).padStart(2,'0')).join(''); }
  function fromHex(hex){
    if (!hex) return new Uint8Array();
    const out = new Uint8Array(hex.length/2);
    for (let i=0;i<out.length;i++) out[i] = parseInt(hex.substr(i*2,2),16);
    return out;
  }
  async function pbkdf2(password, saltHex, iterations=60000, length=32) {
    const pwKey = await crypto.subtle.importKey('raw', toBytes(password), 'PBKDF2', false, ['deriveBits']);
    const bits = await crypto.subtle.deriveBits(
      { name:'PBKDF2', hash:'SHA-256', salt: fromHex(saltHex), iterations },
      pwKey,
      length*8
    );
    return toHex(new Uint8Array(bits));
  }
  function randomHex(len=16){
    const u8 = new Uint8Array(len);
    crypto.getRandomValues(u8);
    return toHex(u8);
  }

  // --- storage helpers ---
  async function saveRecord(username, rec){
    username = norm(username);
    if (db) {
      await db[STORE].put({ username, ...rec });
    } else {
      const map = JSON.parse(localStorage.getItem(LS_KEY) || '{}');
      map[username] = rec;
      localStorage.setItem(LS_KEY, JSON.stringify(map));
    }
  }
  async function getRecord(username){
    username = norm(username);
    if (db) {
      return await db[STORE].get(username) || null;
    } else {
      const map = JSON.parse(localStorage.getItem(LS_KEY) || '{}');
      return map[username] || null;
    }
  }
  async function delRecord(username){
    username = norm(username);
    if (db) {
      await db[STORE].where('username').equals(username).delete();
    } else {
      const map = JSON.parse(localStorage.getItem(LS_KEY) || '{}');
      delete map[username];
      localStorage.setItem(LS_KEY, JSON.stringify(map));
    }
  }

  // --- public API ---
  const API = {
    // Call once after a successful ONLINE login (right after redirect to dashboard)
    async cacheLogin(username, password, profile={}){
      username = norm(username);
      if (!username || !password) throw new Error('Missing username/password');
      const salt = randomHex(16);
      const hash = await pbkdf2(password, salt);
      const payload = { username, salt, hash, profile, updatedAt: Date.now() };
      await saveRecord(username, payload);
      return true;
    },

    // Try to login OFFLINE
    async tryOfflineLogin(username, password){
      username = norm(username);
      if (!username || !password) return null;
      const rec = await getRecord(username);
      if (!rec || !rec.salt || !rec.hash) return null;
      const calc = await pbkdf2(password, rec.salt);
      if (calc !== rec.hash) return null;
      return rec.profile || { username };
    },

    async canOfflineLogin(username){ return !!(await getRecord(norm(username))); },

    async revalidateOnline(username, profile={}){
      const rec = await getRecord(username);
      if (!rec) return false;
      rec.profile = { ...(rec.profile||{}), ...profile };
      rec.updatedAt = Date.now();
      await saveRecord(username, rec);
      return true;
    },

    async clear(username){ await delRecord(username); },
  };

  window.EnforcerOfflineAuth = API;
})();