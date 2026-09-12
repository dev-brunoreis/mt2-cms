'use strict'

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-locale-switcher]').forEach((root) => {
    if (!(root instanceof HTMLFormElement)) {
      return
    }

    const toggle = root.querySelector('[data-locale-toggle]')
    const menu = root.querySelector('[data-locale-menu]')

    if (!(toggle instanceof HTMLButtonElement) || !(menu instanceof HTMLElement)) {
      return
    }

    root.classList.add('is-enhanced')

    const setOpen = (open) => {
      root.classList.toggle('is-open', open)
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false')
    }

    toggle.addEventListener('click', (event) => {
      event.preventDefault()
      event.stopPropagation()
      setOpen(!root.classList.contains('is-open'))
    })

    document.addEventListener('click', (event) => {
      if (!root.classList.contains('is-open')) {
        return
      }

      if (event.target instanceof Node && !root.contains(event.target)) {
        setOpen(false)
      }
    })

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        setOpen(false)
      }
    })
  })
})
