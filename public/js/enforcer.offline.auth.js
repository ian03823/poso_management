// enforcer.offline.auth.js
(function(){
  const DB = {
    db: null,
    open() {
      return new Promise((resolve, reject) => {
        const r = indexedDB.open('poso_enforcer_auth', 1);
        r.onupgradeneeded = e => {
          const db = e.target.result;
          if (!db.objectStoreNames.contains('auth')) db.createObjectStore('auth', { keyPath: 'key' });
        };
        r.onsuccess = () => { DB.db = r.result; resolve(DB.db); };
        r.onerror   = () => reject(r.error);
      });
    },
    put(v) {
      return (DB.db ? Promise.resolve(DB.db) : DB.open()).then(db => new Promise((res, rej)=>{
        const tx = db.transaction('auth','readwrite');
        tx.objectStore('auth').put(v);
        tx.oncomplete = () => res(true);
        tx.onerror    = () => rej(tx.error);
      }));
    },
    get(k) {
      return (DB.db ? Promise.resolve(DB.db) : DB.open()).then(db => new Promise((res, rej)=>{
        const tx = db.transaction('auth','readonly');
        const rq = tx.objectStore('auth').get(k);
        rq.onsuccess = () => res(rq.result || null);
        rq.onerror   = () => rej(rq.error);
      }));
    }
  };

  async function sha256(text) {
    const enc = new TextEncoder().encode(text);
    const buf = await crypto.subtle.digest('SHA-256', enc);
    return [...new Uint8Array(buf)].map(b => b.toString(16).padStart(2,'0')).join('');
  }

  window.EnforcerOfflineAuth = {
    async cacheLogin(username, password, profile) {
      const pw_hash = await sha256(`${username}:${password}`);
      await DB.put({ key:'session', username, pw_hash, profile, last_verified_at: Date.now() });
    },
    async offlineLogin(username, password, maxDays=7) {
      const rec = await DB.get('session');
      if (!rec) return { ok:false, reason:'no_cache' };
      const pw_hash = await sha256(`${username}:${password}`);
      const fresh = (Date.now() - (rec.last_verified_at||0)) < maxDays*24*60*60*1000;
      if (rec.username === username && rec.pw_hash === pw_hash && fresh) {
        sessionStorage.setItem('offline_enforcer','1');
        localStorage.setItem('offline_profile', JSON.stringify(rec.profile||{}));
        return { ok:true, offline:true, profile:rec.profile };
      }
      return { ok:false, reason:'mismatch_or_expired' };
    },
    async revalidateOnline() {
      try {
        const res = await fetch('/api/enforcer/me', { credentials:'include' });
        if (!res.ok) return;
        const profile = await res.json();
        const rec = await DB.get('session');
        if (rec) {
          rec.profile = profile;
          rec.last_verified_at = Date.now();
          await DB.put(rec);
        }
        sessionStorage.removeItem('offline_enforcer');
      } catch {}
    }
  };
})();
