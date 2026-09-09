'use strict'

const select = document.getElementById('admin-map-select')

if (select) {
  select.addEventListener('change', () => {
    window.location.href = '/admin/maps?map=' + encodeURIComponent(select.value)
  })
}

const filter = document.getElementById('admin-map-filter')
const nav = document.querySelector('[data-admin-map-nav]')
const empty = document.querySelector('[data-admin-map-empty]')

if (filter && nav) {
  filter.addEventListener('input', () => {
    const q = filter.value.trim().toLowerCase()
    let visible = 0

    nav.querySelectorAll('[data-map-item]').forEach((item) => {
      const hay = (item.getAttribute('data-map-search') || '').toLowerCase()
      const show = q === '' || hay.includes(q)
      item.hidden = !show
      if (show) {
        visible += 1
      }
    })

    nav.querySelectorAll('[data-map-group]').forEach((group) => {
      let next = group.nextElementSibling
      let showGroup = false

      while (next && !next.hasAttribute('data-map-group')) {
        if (next.hasAttribute('data-map-item') && !next.hidden) {
          showGroup = true
          break
        }
        next = next.nextElementSibling
      }

      group.hidden = !showGroup
    })

    if (empty) {
      empty.classList.toggle('hidden', visible > 0)
    }
  })
}

function setPlayerHover(id, on) {
  document.querySelectorAll('[data-player-id="' + id + '"]').forEach((el) => {
    el.classList.toggle('is-hover', on)
  })
}

document.querySelectorAll('[data-player-id]').forEach((el) => {
  const id = el.getAttribute('data-player-id')
  if (!id) {
    return
  }

  el.addEventListener('mouseenter', () => setPlayerHover(id, true))
  el.addEventListener('mouseleave', () => setPlayerHover(id, false))
  el.addEventListener('focus', () => setPlayerHover(id, true))
  el.addEventListener('blur', () => setPlayerHover(id, false))
})
