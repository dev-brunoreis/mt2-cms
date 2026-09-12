'use strict'

const MAX_DIGITS = 11

const digitsOf = (value) => String(value).replace(/\D/g, '')

const formatCents = (cents, decimal, thousands) => {
  const whole = String(Math.floor(cents / 100))
  const fraction = String(cents % 100).padStart(2, '0')
  const grouped = thousands === ''
    ? whole
    : whole.replace(/\B(?=(\d{3})+(?!\d))/g, thousands)

  return grouped + decimal + fraction
}

const applyMask = (input) => {
  const decimal = input.getAttribute('data-money-decimal') || '.'
  const thousands = input.getAttribute('data-money-thousands')
  const sep = thousands === null ? ',' : thousands
  let digits = digitsOf(input.value)

  if (digits.length > MAX_DIGITS) {
    digits = digits.slice(0, MAX_DIGITS)
  }

  const cents = digits === '' ? 0 : Number.parseInt(digits, 10)

  if (!Number.isFinite(cents) || cents === 0) {
    input.value = ''
    return
  }

  input.value = formatCents(cents, decimal, sep)
  const end = input.value.length

  if (typeof input.setSelectionRange === 'function') {
    input.setSelectionRange(end, end)
  }
}

const isMoneyInput = (el) => el instanceof HTMLInputElement && el.hasAttribute('data-money-mask')

document.addEventListener('input', (event) => {
  if (isMoneyInput(event.target)) {
    applyMask(event.target)
  }
})

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-money-mask]').forEach((input) => {
    if (!(input instanceof HTMLInputElement)) {
      return
    }

    input.setAttribute('inputmode', 'numeric')
    input.setAttribute('autocomplete', 'off')

    if (digitsOf(input.value) !== '') {
      applyMask(input)
    }
  })
})
