'use strict'

document.addEventListener('DOMContentLoaded', () => {
  const link = document.getElementById('donate-checkout-continue')

  if (!(link instanceof HTMLAnchorElement)) {
    return
  }

  let target

  try {
    target = new URL(link.href)
  } catch {
    return
  }

  if (target.protocol !== 'https:') {
    return
  }

  window.location.replace(target.href)
})
