(() => {
  const root = document.querySelector('[data-shop-category-tree]')
  if (!root) return

  root.querySelectorAll('[data-shop-cat-toggle]').forEach((btn) => {
    btn.addEventListener('click', (event) => {
      event.preventDefault()
      event.stopPropagation()

      const item = btn.closest('[data-shop-cat-item]')
      if (!item) return

      const children = item.querySelector(':scope > [data-shop-cat-children]')
      if (!children) return

      const open = btn.getAttribute('aria-expanded') !== 'false'
      btn.setAttribute('aria-expanded', open ? 'false' : 'true')
      item.classList.toggle('is-open', !open)
      item.classList.toggle('is-collapsed', open)
      children.classList.toggle('hidden', open)

      const icon = btn.querySelector('svg')
      if (icon) icon.classList.toggle('-rotate-90', open)
    })
  })
})()
