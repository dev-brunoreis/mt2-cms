'use strict'

const SIDEBAR_NAV_SCROLL_KEY = 'mt2cms.admin.sidebarNavScroll'

const readStoredSidebarNavScroll = () => {
  try {
    const raw = sessionStorage.getItem(SIDEBAR_NAV_SCROLL_KEY)
    if (raw === null) {
      return null
    }

    const top = Number.parseInt(raw, 10)
    return Number.isFinite(top) ? Math.max(0, top) : null
  } catch {
    return null
  }
}

const writeStoredSidebarNavScroll = (top) => {
  try {
    sessionStorage.setItem(SIDEBAR_NAV_SCROLL_KEY, String(Math.max(0, Math.round(top))))
  } catch {
    // sessionStorage can throw in private mode
  }
}

const persistSidebarNavScroll = (nav) => {
  const applySaved = () => {
    const saved = readStoredSidebarNavScroll()
    if (saved !== null) {
      nav.scrollTop = saved
    }
  }

  const persist = () => {
    writeStoredSidebarNavScroll(nav.scrollTop)
  }

  applySaved()
  requestAnimationFrame(() => {
    applySaved()
    requestAnimationFrame(applySaved)
  })
  document.addEventListener('DOMContentLoaded', applySaved)

  nav.addEventListener('pointerdown', (event) => {
    if (event.target instanceof Element && event.target.closest('a[href]')) {
      persist()
    }
  }, true)
  window.addEventListener('pagehide', persist)
}

const sidebarNav = document.querySelector('[data-admin-sidebar-nav]')
if (sidebarNav) {
  persistSidebarNavScroll(sidebarNav)
}

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
