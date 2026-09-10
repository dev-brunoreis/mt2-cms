'use strict'

const TAB_QUERY = 'tab'
const TAB_ID = /^[a-z0-9][a-z0-9_-]*$/i
const TRACKED_FIELDS = 'input, select, textarea'

const queryTab = () => {
  const value = new URL(window.location.href).searchParams.get(TAB_QUERY)

  return value && TAB_ID.test(value) ? value : null
}

const writeQueryTab = (id, isDefault) => {
  const url = new URL(window.location.href)
  const current = url.searchParams.get(TAB_QUERY)

  if (isDefault) {
    if (current === null) {
      return
    }

    url.searchParams.delete(TAB_QUERY)
  } else if (current === id) {
    return
  } else {
    url.searchParams.set(TAB_QUERY, id)
  }

  const search = url.searchParams.toString()

  history.replaceState(null, '', url.pathname + (search !== '' ? '?' + search : '') + url.hash)
}

const isTrackedField = (el) => {
  if (!(el instanceof HTMLElement) || !el.name || el.disabled) {
    return false
  }

  const type = el.type

  return type !== 'hidden'
    && type !== 'submit'
    && type !== 'button'
    && type !== 'reset'
    && type !== 'file'
}

const fieldSignature = (el) => {
  if (el instanceof HTMLInputElement && el.type === 'checkbox') {
    return el.name + ':' + el.value + '=' + (el.checked ? '1' : '0')
  }

  if (el instanceof HTMLInputElement && el.type === 'radio') {
    return el.name + '=' + (el.checked ? el.value : '')
  }

  return el.name + '=' + el.value
}

const snapshotPanel = (panel) => {
  return Array.from(panel.querySelectorAll(TRACKED_FIELDS))
    .filter(isTrackedField)
    .map(fieldSignature)
    .join('\n')
}

const escapeHtml = (value) => {
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
}

const isSafeTabSrc = (src) => {
  if (typeof src !== 'string' || src === '' || src.includes('\\') || src.includes('\n') || src.includes('\r')) {
    return false
  }

  if (!src.startsWith('/admin/') || src.startsWith('//')) {
    return false
  }

  try {
    const url = new URL(src, window.location.origin)

    return url.origin === window.location.origin && url.pathname.startsWith('/admin/')
  } catch {
    return false
  }
}

const initTabs = (root) => {
  const belongsTo = (el) => el.closest('[data-admin-tabs]') === root
  const persistUrl = !root.parentElement?.closest('[data-admin-tabs]')
  const tabs = Array.from(root.querySelectorAll('[role="tab"][data-tab]')).filter(belongsTo)
  const panels = Array.from(root.querySelectorAll('[data-tab-panel]')).filter(belongsTo)
  const snapshots = new Map()
  const pending = new Map()
  const defaultTab = root.getAttribute('data-default-tab')
  const loadingText = root.getAttribute('data-tab-loading') || 'Loading…'
  const errorText = root.getAttribute('data-tab-load-error') || 'Could not load this section.'

  if (tabs.length === 0 || panels.length === 0) {
    return
  }

  const showPanel = (id, { focus = false } = {}) => {
    tabs.forEach((tab) => {
      const selected = tab.getAttribute('data-tab') === id

      tab.classList.toggle('is-active', selected)
      tab.setAttribute('aria-selected', selected ? 'true' : 'false')
      tab.tabIndex = selected ? 0 : -1

      if (selected && focus) {
        tab.focus()
      }
    })

    panels.forEach((panel) => {
      panel.hidden = panel.getAttribute('data-tab-panel') !== id
    })

    if (persistUrl) {
      writeQueryTab(id, id === defaultTab)
    }

    const headerForm = root.getAttribute('data-header-form-' + id)
    const headerButton = document.querySelector('.admin-page-header button[form]')

    if (headerForm && headerButton instanceof HTMLButtonElement) {
      headerButton.setAttribute('form', headerForm)
    }
  }

  const loadPanel = async (panel) => {
    const src = panel.getAttribute('data-tab-src')

    if (!src || !isSafeTabSrc(src)) {
      return false
    }

    panel.classList.add('is-loading')
    panel.innerHTML = '<p class="admin-tab-status">' + escapeHtml(loadingText) + '</p>'

    try {
      const response = await fetch(src, {
        credentials: 'same-origin',
        redirect: 'manual',
        headers: {
          Accept: 'text/html',
          'X-Requested-With': 'XMLHttpRequest',
        },
      })

      if (response.type === 'opaqueredirect' || response.status === 401) {
        window.location.reload()
        return false
      }

      if (!response.ok) {
        throw new Error('bad status')
      }

      panel.innerHTML = await response.text()
      panel.removeAttribute('data-tab-src')
      panel.setAttribute('data-tab-loaded', '1')
      panel.querySelectorAll('[data-admin-tabs]').forEach(initTabs)
      snapshots.set(panel.getAttribute('data-tab-panel'), snapshotPanel(panel))
      return true
    } catch {
      panel.innerHTML = '<p class="admin-tab-status admin-tab-status-error">' + escapeHtml(errorText) + '</p>'
      return false
    } finally {
      panel.classList.remove('is-loading')
    }
  }

  const ensureLoaded = (panel) => {
    if (!(panel instanceof HTMLElement) || !panel.hasAttribute('data-tab-src')) {
      return Promise.resolve(true)
    }

    const id = panel.getAttribute('data-tab-panel') ?? ''

    if (pending.has(id)) {
      return pending.get(id)
    }

    const request = loadPanel(panel).finally(() => {
      pending.delete(id)
    })

    pending.set(id, request)

    return request
  }

  const activate = (id, { focus = false } = {}) => {
    showPanel(id, { focus })

    const panel = panels.find((item) => item.getAttribute('data-tab-panel') === id)

    if (panel) {
      ensureLoaded(panel)
    }
  }

  const markDirty = (panel) => {
    const id = panel.getAttribute('data-tab-panel')
    const tab = tabs.find((item) => item.getAttribute('data-tab') === id)

    if (!tab) {
      return
    }

    const dirty = snapshotPanel(panel) !== snapshots.get(id)

    tab.classList.toggle('is-dirty', dirty)

    const marker = tab.querySelector('.admin-tab-dirty')

    if (marker instanceof HTMLElement) {
      marker.hidden = !dirty
    }
  }

  panels.forEach((panel) => {
    snapshots.set(panel.getAttribute('data-tab-panel'), snapshotPanel(panel))
    panel.addEventListener('input', () => markDirty(panel))
    panel.addEventListener('change', () => markDirty(panel))
  })

  tabs.forEach((tab, index) => {
    tab.addEventListener('click', () => {
      activate(tab.getAttribute('data-tab') ?? '')
    })

    tab.addEventListener('keydown', (event) => {
      let next = null

      if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
        next = tabs[(index + 1) % tabs.length]
      } else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
        next = tabs[(index - 1 + tabs.length) % tabs.length]
      } else if (event.key === 'Home') {
        next = tabs[0]
      } else if (event.key === 'End') {
        next = tabs[tabs.length - 1]
      }

      if (next) {
        event.preventDefault()
        activate(next.getAttribute('data-tab') ?? '', { focus: true })
      }
    })
  })

  const fromUrl = persistUrl ? queryTab() : null
  const initial = tabs.find((tab) => tab.getAttribute('data-tab') === fromUrl)
    ?? tabs.find((tab) => tab.getAttribute('data-tab') === defaultTab)
    ?? tabs.find((tab) => tab.getAttribute('aria-selected') === 'true')
    ?? tabs[0]

  if (initial) {
    activate(initial.getAttribute('data-tab') ?? '')
  }
}

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-admin-tabs]').forEach(initTabs)
})
