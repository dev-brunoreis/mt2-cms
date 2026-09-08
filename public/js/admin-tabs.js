'use strict'

const TRACKED_FIELDS = 'input, select, textarea'

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

const initTabs = (root) => {
  const tabs = Array.from(root.querySelectorAll('[role="tab"][data-tab]'))
  const panels = Array.from(root.querySelectorAll('[data-tab-panel]'))
  const snapshots = new Map()

  if (tabs.length === 0 || panels.length === 0) {
    return
  }

  const activate = (id, { focus = false } = {}) => {
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

  const defaultTab = root.getAttribute('data-default-tab')
  const initial = tabs.find((tab) => tab.getAttribute('data-tab') === defaultTab)
    ?? tabs.find((tab) => tab.getAttribute('aria-selected') === 'true')
    ?? tabs[0]

  if (initial) {
    activate(initial.getAttribute('data-tab') ?? '')
  }
}

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-admin-tabs]').forEach(initTabs)
})
