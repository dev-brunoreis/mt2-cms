(() => {
  const form = document.getElementById('admin-proto-form')

  if (!form || form.dataset.protoForm !== 'item') {
    return
  }

  let subtypesByType = {}
  let valueLabelsByType = {}

  try {
    subtypesByType = JSON.parse(form.dataset.subtypesByType || '{}')
    valueLabelsByType = JSON.parse(form.dataset.valueLabelsByType || '{}')
  } catch {
    return
  }

  const typeSelect = form.querySelector('[data-proto-item-type]')
  const subtypeSelect = form.querySelector('[data-proto-subtype]')

  const labeledEntries = (type) => {
    const raw = subtypesByType[type] || []

    return raw.map((entry) => (
      typeof entry === 'string' ? { value: entry, label: entry } : entry
    ))
  }

  const labelFor = (type, subtype, key) => {
    const subtypeKey = subtype ? `${type}:${subtype}` : ''
    const fromSubtype = subtypeKey && valueLabelsByType[subtypeKey]?.[key]
    const fromType = valueLabelsByType[type]?.[key]

    return fromSubtype || fromType || key
  }

  const refreshSubtypeOptions = () => {
    if (!typeSelect || !subtypeSelect) {
      return
    }

    const type = typeSelect.value
    const current = subtypeSelect.value
    const entries = labeledEntries(type)
    const values = entries.map((entry) => entry.value)

    subtypeSelect.innerHTML = ''

    if (entries.length === 0) {
      const opt = document.createElement('option')
      opt.value = current === '' ? '0' : current
      opt.textContent = current === '' ? '0' : current
      opt.selected = true
      subtypeSelect.appendChild(opt)
    } else {
      if (current !== '' && current !== '0' && !values.includes(current)) {
        const currentOption = document.createElement('option')
        currentOption.value = current
        currentOption.textContent = current
        currentOption.selected = true
        subtypeSelect.appendChild(currentOption)
      }

      entries.forEach((entry) => {
        const opt = document.createElement('option')
        opt.value = entry.value
        opt.textContent = entry.label
        if (entry.value === current) {
          opt.selected = true
        }
        subtypeSelect.appendChild(opt)
      })
    }

    refreshValueLabels()
  }

  const refreshValueLabels = () => {
    const type = typeSelect?.value || ''
    const subtype = subtypeSelect?.value || ''

    form.querySelectorAll('.proto-value-field').forEach((wrap) => {
      const key = wrap.dataset.valueKey
      const label = wrap.querySelector('label')

      if (!key || !label) {
        return
      }

      label.textContent = labelFor(type, subtype, key)
    })
  }

  typeSelect?.addEventListener('change', refreshSubtypeOptions)
  subtypeSelect?.addEventListener('change', refreshValueLabels)

  refreshSubtypeOptions()
})()
