'use strict'

document.addEventListener('DOMContentLoaded', () => {
  const sidebar = document.getElementById('admin-sidebar')
  const backdrop = document.getElementById('admin-sidebar-backdrop')
  const toggle = document.getElementById('admin-sidebar-toggle')

  const closeMobileSidebar = () => {
    if (!sidebar || !backdrop) return
    sidebar.classList.add('-translate-x-full')
    backdrop.classList.add('hidden')
    toggle?.setAttribute('aria-expanded', 'false')
  }

  const openMobileSidebar = () => {
    if (!sidebar || !backdrop) return
    sidebar.classList.remove('-translate-x-full')
    backdrop.classList.remove('hidden')
    toggle?.setAttribute('aria-expanded', 'true')
  }

  toggle?.addEventListener('click', () => {
    if (sidebar?.classList.contains('-translate-x-full')) {
      openMobileSidebar()
    } else {
      closeMobileSidebar()
    }
  })

  backdrop?.addEventListener('click', closeMobileSidebar)

  document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      const message = form.getAttribute('data-confirm')

      if (message && !window.confirm(message)) {
        event.preventDefault()
      }
    })
  })
})
