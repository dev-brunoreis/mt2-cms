'use strict'

document.addEventListener('DOMContentLoaded', () => {
  const nav = document.querySelector('[data-k-nav]')
  const toggle = nav?.querySelector('[data-k-nav-toggle]')
  const menu = nav?.querySelector('[data-k-nav-menu]')

  if (!nav || !toggle || !menu) {
    return
  }

  nav.classList.add('k-nav-enhanced')

  const desktopQuery = window.matchMedia('(min-width: 960px)')

  const setOpen = (open) => {
    const isDesktop = desktopQuery.matches
    const expanded = open && !isDesktop

    nav.classList.toggle('is-open', expanded)
    toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false')

    const openLabel = toggle.getAttribute('data-label-open')
    const closeLabel = toggle.getAttribute('data-label-close')
    const label = expanded ? closeLabel : openLabel

    if (label) {
      toggle.setAttribute('aria-label', label)
    }
  }

  const close = () => setOpen(false)

  toggle.addEventListener('click', () => {
    setOpen(!nav.classList.contains('is-open'))
  })

  menu.addEventListener('click', (event) => {
    if (event.target instanceof Element && event.target.closest('a')) {
      close()
    }
  })

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      close()
    }
  })

  document.addEventListener('click', (event) => {
    if (!nav.classList.contains('is-open')) {
      return
    }

    if (event.target instanceof Node && !nav.contains(event.target)) {
      close()
    }
  })

  if (typeof desktopQuery.addEventListener === 'function') {
    desktopQuery.addEventListener('change', close)
  } else if (typeof desktopQuery.addListener === 'function') {
    desktopQuery.addListener(close)
  }
})
