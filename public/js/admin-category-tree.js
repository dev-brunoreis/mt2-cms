(() => {
  const root = document.querySelector('[data-category-tree]')
  if (!root) return

  const moveUrl = root.getAttribute('data-move-url') || ''
  const csrf = root.getAttribute('data-csrf') || ''
  const moveError = root.getAttribute('data-move-error') || 'Move failed'
  let dragItem = null

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
    item.addEventListener('dragstart', (event) => {
      dragItem = item
      item.classList.add('is-dragging')
      if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move'
        event.dataTransfer.setData('text/plain', item.getAttribute('data-id') || '')
      }
      event.stopPropagation()
    })

    item.addEventListener('dragend', () => {
      item.classList.remove('is-dragging')
      root.querySelectorAll('.is-drop-target').forEach((el) => el.classList.remove('is-drop-target'))
      dragItem = null
    })

    const row = item.querySelector(':scope > .admin-category-tree-row')
    if (row) {
      row.addEventListener('dragover', (event) => {
        if (!dragItem || dragItem === item || dragItem.contains(item)) return
        event.preventDefault()
        event.stopPropagation()
        row.classList.add('is-drop-target')
      })
      row.addEventListener('dragleave', () => row.classList.remove('is-drop-target'))
      row.addEventListener('drop', async (event) => {
        event.preventDefault()
        event.stopPropagation()
        row.classList.remove('is-drop-target')
        if (!dragItem || dragItem === item || dragItem.contains(item)) return
        const parentId = item.getAttribute('data-id') || ''
        const id = dragItem.getAttribute('data-id') || ''
        await postMove(id, parentId, 9999)
      })
    }
  })

  async function postMove(id, parentId, position) {
    if (!id || !moveUrl) return
    const body = new URLSearchParams()
    body.set('_csrf', csrf)
    body.set('id', id)
    body.set('parent_id', parentId)
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

  root.querySelectorAll('[data-tree-list]').forEach((list) => {
    list.addEventListener('dragover', (event) => {
      if (!dragItem) return
      event.preventDefault()
      list.classList.add('is-drop-target')
      if (list.hidden) list.hidden = false
    })

    list.addEventListener('dragleave', (event) => {
      if (!list.contains(event.relatedTarget)) {
        list.classList.remove('is-drop-target')
      }
    })

    list.addEventListener('drop', async (event) => {
      event.preventDefault()
      list.classList.remove('is-drop-target')
      if (!dragItem || !moveUrl) return

      const parentId = list.getAttribute('data-parent-id') || ''
      const id = dragItem.getAttribute('data-id') || ''
      if (!id) return
      if (dragItem.contains(list)) return

      const siblings = [...list.querySelectorAll(':scope > [data-tree-item]')]
      let position = siblings.length
      const after = event.target.closest('[data-tree-item]')
      if (after && after.parentElement === list && after !== dragItem) {
        position = siblings.indexOf(after)
      }

      await postMove(id, parentId, position)
    })
  })
})()
