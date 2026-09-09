'use strict'

const select = document.getElementById('admin-map-select')

if (select) {
  select.addEventListener('change', () => {
    window.location.href = '/admin/maps?map=' + encodeURIComponent(select.value)
  })
}
