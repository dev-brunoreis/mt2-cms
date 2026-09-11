(() => {
  const root = document.querySelector('[data-category-tree]')
  if (!root) return

  const moveUrl = root.getAttribute('data-move-url') || ''
  const csrf = root.getAttribute('data-csrf') || ''
  const moveError = root.getAttribute('data-move-error') || 'Move failed'
  let dragItem = null

  function clearDropMarkers() {
    root.querySelectorAll('.is-drop-before, .is-drop-after, .is-drop-into, .is-drop-target').forEach((el) => {
      el.classList.remove('is-drop-before', 'is-drop-after', 'is-drop-into', 'is-drop-target')
    })
    root.querySelectorAll('[data-drop-line]').forEach((el) => {
      el.hidden = true
    })
  }

  async function postMove(id, parentId, position) {
    if (!id || !moveUrl) return
    const body = new URLSearchParams()
    body.set('_csrf', csrf)
    body.set('id', id)
    body.set('parent_id', parentId == null ? '' : String(parentId))
    body.set('position', String(Math.max(0, position)))

    try {
      const response = await fetch(moveUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          Accept: 'application/json',
        },
        body: body.toString(),
        credentials: 'same-origin',
      })
      const data = await response.json().catch(() => ({}))
      if (!response.ok || !data.ok) {
        window.alert(data.error || moveError)
        return
      }
      window.location.reload()
    } catch (_) {
      window.alert(moveError)
    }
  }

  root.querySelectorAll('[data-tree-toggle]').forEach((btn) => {
    btn.addEventListener('click', (event) => {
      event.preventDefault()
      event.stopPropagation()
      const item = btn.closest('[data-tree-item]')
      if (!item) return
      const list = item.querySelector(':scope > [data-tree-list]')
      if (!list) return
      const open = btn.getAttribute('aria-expanded') !== 'false'
      btn.setAttribute('aria-expanded', open ? 'false' : 'true')
      list.hidden = open
      item.classList.toggle('is-collapsed', open)
    })
  })

  root.querySelectorAll('[data-tree-item]').forEach((item) => {
    const handle = item.querySelector('[data-tree-handle]')
    const row = item.querySelector('[data-tree-row]')
    if (!handle || !row) return

    handle.addEventListener('dragstart', (event) => {
      dragItem = item
      item.classList.add('is-dragging')
      if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move'
        event.dataTransfer.setData('text/plain', item.getAttribute('data-id') || '')
      }
      event.stopPropagation()
    })

    handle.addEventListener('dragend', () => {
      item.classList.remove('is-dragging')
      clearDropMarkers()
      dragItem = null
    })

    row.addEventListener('dragover', (event) => {
      if (!dragItem || dragItem === item || dragItem.contains(item)) return
      event.preventDefault()
      event.stopPropagation()
      clearDropMarkers()

      const rect = row.getBoundingClientRect()
      const y = event.clientY - rect.top
      const ratio = y / rect.height

      if (ratio < 0.28) {
        row.classList.add('is-drop-before')
        const line = item.querySelector('[data-drop-line="before"]')
        if (line) line.hidden = false
      } else if (ratio > 0.72) {
        row.classList.add('is-drop-after')
        const line = item.querySelector('[data-drop-line="after"]')
        if (line) line.hidden = false
      } else {
        row.classList.add('is-drop-into')
      }
    })

    row.addEventListener('dragleave', (event) => {
      if (!row.contains(event.relatedTarget)) {
        row.classList.remove('is-drop-before', 'is-drop-after', 'is-drop-into')
        item.querySelectorAll('[data-drop-line]').forEach((el) => {
          el.hidden = true
        })
      }
    })

    row.addEventListener('drop', async (event) => {
      event.preventDefault()
      event.stopPropagation()
      if (!dragItem || dragItem === item || dragItem.contains(item)) return

      const id = dragItem.getAttribute('data-id') || ''
      const targetParent = item.getAttribute('data-parent-id') || ''
      const list = item.parentElement
      const siblings = list
        ? [...list.querySelectorAll(':scope > [data-tree-item]')].filter((el) => el !== dragItem)
        : []
      const targetIndex = siblings.indexOf(item)

      const rect = row.getBoundingClientRect()
      const ratio = (event.clientY - rect.top) / rect.height
      clearDropMarkers()

      if (ratio < 0.28) {
        await postMove(id, targetParent, Math.max(0, targetIndex))
      } else if (ratio > 0.72) {
        await postMove(id, targetParent, Math.max(0, targetIndex + 1))
      } else {
        await postMove(id, item.getAttribute('data-id') || '', 9999)
      }
    })

    const up = item.querySelector('[data-move-up]')
    const down = item.querySelector('[data-move-down]')
    const parentId = item.getAttribute('data-parent-id') || ''
    const list = item.parentElement
    const siblings = list ? [...list.querySelectorAll(':scope > [data-tree-item]')] : []
    const index = siblings.indexOf(item)

    if (up) {
      up.disabled = index <= 0
      up.addEventListener('click', (event) => {
        event.preventDefault()
        event.stopPropagation()
        if (index <= 0) return
        postMove(item.getAttribute('data-id') || '', parentId, index - 1)
      })
    }
    if (down) {
      down.disabled = index < 0 || index >= siblings.length - 1
      down.addEventListener('click', (event) => {
        event.preventDefault()
        event.stopPropagation()
        if (index >= siblings.length - 1) return
        postMove(item.getAttribute('data-id') || '', parentId, index + 1)
      })
    }
  })

  // Allow dropping into empty child lists / root list gaps
  root.querySelectorAll('[data-tree-list]').forEach((list) => {
    list.addEventListener('dragover', (event) => {
      if (!dragItem) return
      if (event.target !== list && event.target.closest('[data-tree-row]')) return
      event.preventDefault()
      list.classList.add('is-drop-target')
    })
    list.addEventListener('dragleave', (event) => {
      if (!list.contains(event.relatedTarget)) list.classList.remove('is-drop-target')
    })
    list.addEventListener('drop', async (event) => {
      if (event.target !== list && event.target.closest('[data-tree-row]')) return
      event.preventDefault()
      list.classList.remove('is-drop-target')
      if (!dragItem) return
      if (dragItem.contains(list)) return
      const id = dragItem.getAttribute('data-id') || ''
      const parentId = list.getAttribute('data-parent-id') || ''
      const siblings = [...list.querySelectorAll(':scope > [data-tree-item]')].filter((el) => el !== dragItem)
      await postMove(id, parentId, siblings.length)
    })
  })
})()
