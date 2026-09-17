'use strict'

document.addEventListener('click', (event) => {
  const trigger = event.target instanceof Element
    ? event.target.closest('[data-admin-image-preview]')
    : null

  if (!(trigger instanceof HTMLElement)) {
    return
  }

  const src = trigger.getAttribute('data-admin-image-preview') || ''

  if (src === '') {
    return
  }

  const dialog = document.getElementById('admin-image-preview')
  const image = document.getElementById('admin-image-preview-img')

  if (!(dialog instanceof HTMLDialogElement) || !(image instanceof HTMLImageElement)) {
    return
  }

  event.preventDefault()
  image.src = src
  image.alt = trigger.getAttribute('data-admin-image-preview-alt') || ''
  dialog.showModal()
})

document.addEventListener('DOMContentLoaded', () => {
  const dialog = document.getElementById('admin-image-preview')

  if (!(dialog instanceof HTMLDialogElement)) {
    return
  }

  dialog.addEventListener('click', (event) => {
    if (event.target === dialog) {
      dialog.close()
    }
  })

  dialog.addEventListener('close', () => {
    const image = document.getElementById('admin-image-preview-img')

    if (image instanceof HTMLImageElement) {
      image.removeAttribute('src')
      image.alt = ''
    }
  })
})
