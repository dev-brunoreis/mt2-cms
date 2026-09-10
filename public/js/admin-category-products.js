(() => {
  const root = document.querySelector('[data-category-products]')
  if (!root) return

  const searchUrl = root.getAttribute('data-search-url') || ''
  const labelPrev = root.getAttribute('data-label-prev') || 'Previous'
  const labelNext = root.getAttribute('data-label-next') || 'Next'
  const labelAlready = root.getAttribute('data-label-already') || 'already added'
  const searchInput = root.querySelector('[data-item-search]')
  const searchBtn = root.querySelector('[data-item-search-btn]')
  const selectAll = root.querySelector('[data-select-all]')
  const continueBtn = root.querySelector('[data-continue-pricing]')
  const resultsEmpty = root.querySelector('[data-item-empty]')
  const resultsTable = root.querySelector('[data-item-table]')
  const tbody = root.querySelector('[data-item-tbody]')
  const pager = root.querySelector('[data-item-pager]')
  const stepSearch = root.querySelector('[data-picker-step="search"]')
  const stepPricing = root.querySelector('[data-picker-step="pricing"]')
  const pricingBody = root.querySelector('[data-pricing-tbody]')
  const bulkPrice = root.querySelector('[data-bulk-price]')
  const applyBulk = root.querySelector('[data-apply-bulk-price]')
  const backBtn = root.querySelector('[data-back-to-search]')

  let page = 1
  let selected = new Map()

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
      return '<span class="admin-game-icon admin-game-icon-lg admin-game-icon-empty" aria-hidden="true"></span>'
    }
    return `<img src="/game/icon/item/${id}" alt="" width="72" height="72" class="admin-game-icon admin-game-icon-lg" loading="lazy" onerror="this.style.visibility='hidden'">`
  }

  function updateContinue() {
    if (continueBtn) continueBtn.disabled = selected.size === 0
  }

  function syncRowChecks() {
    if (!tbody) return
    tbody.querySelectorAll('input[data-vnum]').forEach((input) => {
      const vnum = Number(input.getAttribute('data-vnum'))
      input.checked = selected.has(vnum)
    })
    if (selectAll) {
      const boxes = [...tbody.querySelectorAll('input[data-vnum]:not(:disabled)')]
      selectAll.checked = boxes.length > 0 && boxes.every((box) => box.checked)
    }
    updateContinue()
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

    const items = Array.isArray(data.items) ? data.items : []
    tbody.innerHTML = items.map((item) => {
      const disabled = item.in_category ? 'disabled' : ''
      const note = item.in_category ? ` <span class="text-xs text-slate-400">(${escapeHtml(labelAlready)})</span>` : ''
      return `<tr>
        <td><input type="checkbox" data-vnum="${item.vnum}" data-name="${escapeHtml(item.name)}" ${disabled}></td>
        <td class="text-slate-500">${item.vnum}</td>
        <td>
          <div class="admin-game-icon-row">
            ${itemIconHtml(item.vnum)}
            <span>${escapeHtml(item.name)}${note}</span>
          </div>
        </td>
      </tr>`
    }).join('')

    if (resultsEmpty) resultsEmpty.hidden = items.length > 0
    if (resultsTable) resultsTable.hidden = items.length === 0

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

    syncRowChecks()
  }

  if (tbody) {
    tbody.addEventListener('change', (event) => {
      const input = event.target
      if (!(input instanceof HTMLInputElement) || !input.hasAttribute('data-vnum')) return
      const vnum = Number(input.getAttribute('data-vnum'))
      const name = input.getAttribute('data-name') || String(vnum)
      if (input.checked) selected.set(vnum, name)
      else selected.delete(vnum)
      updateContinue()
      if (selectAll) {
        const boxes = [...tbody.querySelectorAll('input[data-vnum]:not(:disabled)')]
        selectAll.checked = boxes.length > 0 && boxes.every((box) => box.checked)
      }
    })
  }

  if (selectAll) {
    selectAll.addEventListener('change', () => {
      if (!tbody) return
      tbody.querySelectorAll('input[data-vnum]:not(:disabled)').forEach((input) => {
        input.checked = selectAll.checked
        const vnum = Number(input.getAttribute('data-vnum'))
        const name = input.getAttribute('data-name') || String(vnum)
        if (selectAll.checked) selected.set(vnum, name)
        else selected.delete(vnum)
      })
      updateContinue()
    })
  }

  function showPricing() {
    if (!pricingBody || !stepSearch || !stepPricing) return
    pricingBody.innerHTML = [...selected.entries()].map(([vnum, name], index) => `
      <tr>
        <td class="text-slate-500">${vnum}</td>
        <td>
          <input type="hidden" name="items[${index}][vnum]" value="${vnum}">
          <div class="admin-game-icon-row">
            ${itemIconHtml(vnum)}
            <span class="font-medium text-slate-800">${escapeHtml(name)}</span>
          </div>
        </td>
        <td><input type="number" name="items[${index}][count]" min="1" max="200" value="1" class="admin-input admin-input-compact w-20"></td>
        <td><input type="number" name="items[${index}][price]" min="1" value="${bulkPrice ? bulkPrice.value || 1 : 1}" data-price-input class="admin-input admin-input-compact w-28" required></td>
      </tr>
    `).join('')
    stepSearch.hidden = true
    stepPricing.hidden = false
  }

  if (continueBtn) continueBtn.addEventListener('click', showPricing)
  if (backBtn) {
    backBtn.addEventListener('click', () => {
      if (stepSearch) stepSearch.hidden = false
      if (stepPricing) stepPricing.hidden = true
    })
  }
  if (applyBulk && bulkPrice && pricingBody) {
    applyBulk.addEventListener('click', () => {
      const value = bulkPrice.value
      pricingBody.querySelectorAll('[data-price-input]').forEach((input) => {
        input.value = value
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
