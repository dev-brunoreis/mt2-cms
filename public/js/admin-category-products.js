(() => {
  const root = document.querySelector('[data-category-products]')
  if (!root) return

  const searchUrl = root.getAttribute('data-search-url') || ''
  const searchInput = root.querySelector('[data-item-search]')
  const searchBtn = root.querySelector('[data-item-search-btn]')
  const selectAll = root.querySelector('[data-select-all]')
  const resultsEmpty = root.querySelector('[data-item-empty]')
  const resultsTable = root.querySelector('[data-item-table]')
  const tbody = root.querySelector('[data-item-tbody]')
  const pager = root.querySelector('[data-item-pager]')
  const labelPrev = root.getAttribute('data-label-prev') || 'Previous'
  const labelNext = root.getAttribute('data-label-next') || 'Next'

  let page = 1

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
  }

  function itemIconHtml(vnum) {
    const id = Number(vnum) || 0
    if (id < 1) {
      return '<span class="admin-game-icon admin-game-icon-empty" aria-hidden="true"></span>'
    }
    return `<img src="/game/icon/item/${id}" alt="" width="32" height="32" class="admin-game-icon" loading="lazy" onerror="this.style.visibility='hidden'">`
  }

  function toggleRow(row, checked) {
    row.classList.toggle('is-selected', checked)
    const price = row.querySelector('[data-price-cell] input')
    if (price instanceof HTMLInputElement) {
      price.disabled = !checked
      price.required = checked
    }
  }

  function syncSelectAll() {
    if (!selectAll || !tbody) return
    const boxes = [...tbody.querySelectorAll('input[data-vnum]')]
    selectAll.checked = boxes.length > 0 && boxes.every((box) => box.checked)
  }

  function rowHtml(item, assigned) {
    const vnum = Number(item.vnum) || 0
    const name = escapeHtml(item.name || String(vnum))
    const price = Number(item.price) > 0 ? Number(item.price) : 1
    const count = Number(item.count) > 0 ? Number(item.count) : 1
    const id = Number(item.id) || 0
    const checked = assigned ? 'checked' : ''
    const selectedClass = assigned ? ' is-selected' : ''
    const priceState = assigned ? 'required' : 'disabled'
    const idField = id > 0
      ? `<input type="hidden" name="items[${vnum}][id]" value="${id}">`
      : ''
    const shownField = id > 0
      ? `<input type="hidden" name="shown_ids[]" value="${id}">`
      : ''

    return `<tr class="${selectedClass.trim()}">
      <td>
        ${shownField}
        ${idField}
        <input type="hidden" name="items[${vnum}][count]" value="${count}">
        <input type="checkbox" name="selected[]" value="${vnum}" data-vnum="${vnum}" ${checked}>
      </td>
      <td class="text-slate-500">${vnum}</td>
      <td>
        <div class="admin-game-icon-row">
          ${itemIconHtml(vnum)}
          <span>${name}</span>
        </div>
      </td>
      <td data-price-cell>
        <input type="number" name="items[${vnum}][price]" min="1" value="${price}"
               class="admin-input admin-input-compact w-28" ${priceState}>
      </td>
    </tr>`
  }

  async function loadPage(nextPage) {
    if (!searchUrl || !tbody) return
    page = nextPage
    const q = searchInput ? searchInput.value.trim() : ''
    const url = new URL(searchUrl, window.location.origin)
    url.searchParams.set('page', String(page))
    if (q !== '') url.searchParams.set('q', q)

    const response = await fetch(url.toString(), {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    })
    const data = await response.json().catch(() => ({}))
    if (!response.ok || !data.ok) {
      tbody.innerHTML = ''
      if (resultsEmpty) {
        resultsEmpty.hidden = false
        resultsEmpty.textContent = data.error || 'Search failed'
      }
      if (resultsTable) resultsTable.hidden = true
      if (pager) pager.hidden = true
      return
    }

    const assigned = Array.isArray(data.assigned) ? data.assigned : []
    const items = Array.isArray(data.items) ? data.items : []
    tbody.innerHTML = [
      ...assigned.map((item) => rowHtml(item, true)),
      ...items.map((item) => rowHtml(item, false)),
    ].join('')

    const empty = assigned.length === 0 && items.length === 0
    if (resultsEmpty) resultsEmpty.hidden = !empty
    if (resultsTable) resultsTable.hidden = empty

    if (pager) {
      const totalPages = Math.max(1, Number(data.totalPages) || 1)
      if (totalPages <= 1) {
        pager.hidden = true
        pager.innerHTML = ''
      } else {
        pager.hidden = false
        pager.innerHTML = `
          <button type="button" class="admin-btn-secondary rounded-md px-3 py-1.5" data-page-prev ${page <= 1 ? 'disabled' : ''}>${escapeHtml(labelPrev)}</button>
          <span class="text-slate-500">${page} / ${totalPages}</span>
          <button type="button" class="admin-btn-secondary rounded-md px-3 py-1.5" data-page-next ${page >= totalPages ? 'disabled' : ''}>${escapeHtml(labelNext)}</button>
        `
        const prev = pager.querySelector('[data-page-prev]')
        const next = pager.querySelector('[data-page-next]')
        if (prev) prev.addEventListener('click', () => loadPage(page - 1))
        if (next) next.addEventListener('click', () => loadPage(page + 1))
      }
    }

    syncSelectAll()
  }

  if (tbody) {
    tbody.addEventListener('change', (event) => {
      const input = event.target
      if (!(input instanceof HTMLInputElement) || !input.hasAttribute('data-vnum')) return
      const row = input.closest('tr')
      if (row) toggleRow(row, input.checked)
      syncSelectAll()
    })
  }

  if (selectAll) {
    selectAll.addEventListener('change', () => {
      if (!tbody) return
      tbody.querySelectorAll('input[data-vnum]').forEach((input) => {
        input.checked = selectAll.checked
        const row = input.closest('tr')
        if (row) toggleRow(row, selectAll.checked)
      })
    })
  }

  if (searchBtn) searchBtn.addEventListener('click', () => loadPage(1))
  if (searchInput) {
    searchInput.addEventListener('keydown', (event) => {
      if (event.key === 'Enter') {
        event.preventDefault()
        loadPage(1)
      }
    })
  }

  loadPage(1)
})()
