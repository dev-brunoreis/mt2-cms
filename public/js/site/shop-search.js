(() => {
  const form = document.querySelector('[data-shop-search]')
  if (!form) return

  const input = form.querySelector('[data-shop-search-input]')
  const list = document.querySelector('[data-shop-list]')
  const empty = document.querySelector('[data-shop-search-empty]')
  if (!(input instanceof HTMLInputElement) || !list) return

  const products = [...list.querySelectorAll('[data-shop-product]')]

  const apply = () => {
    const query = input.value.trim().toLowerCase()
    let visible = 0

    products.forEach((item) => {
      const haystack = (item.getAttribute('data-shop-search') || '').toLowerCase()
      const match = query === '' || haystack.includes(query)
      item.classList.toggle('hidden', !match)
      if (match) visible++
    })

    list.classList.toggle('hidden', visible === 0)
    if (empty) empty.classList.toggle('hidden', visible > 0)
  }

  input.addEventListener('input', apply)
})()
