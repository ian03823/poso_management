export function wireRealtimeTable(tableName, tbodySelector) {
  const tbody = document.querySelector(tbodySelector)
  if (!tbody || !window.Echo) return

  // private-{table}
  window.Echo.private(`private-${tableName}`)
    .listen('.row.pushed', (e) => {
      const { action, rowHtml, id } = e
      const current = tbody.querySelector(`tr[data-id="${id}"]`)

      if (action === 'deleted') {
        current && current.remove()
        return
      }

      const tmp = document.createElement('tbody')
      tmp.innerHTML = rowHtml.trim()
      const newRow = tmp.firstElementChild

      if (current) current.replaceWith(newRow)
      else tbody.prepend(newRow) // newest first
    })
}
