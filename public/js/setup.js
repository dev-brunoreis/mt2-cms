(() => {
  const form = document.querySelector('[data-setup-db-form]')
  if (!form) return

  const csrf = form.querySelector('input[name="_csrf"]')?.value || ''

  const setStatus = (id, state, message) => {
    const el = form.querySelector(`[data-setup-status-el="${id}"]`)
    if (!el) return
    el.hidden = false
    el.classList.remove('is-ok', 'is-fail', 'is-pending')
    el.classList.add(`is-${state}`)
    el.textContent = message
  }

  form.querySelectorAll('[data-setup-test]').forEach((button) => {
    button.addEventListener('click', async () => {
      const target = button.getAttribute('data-setup-test')
      const statusId = button.getAttribute('data-setup-status')
      if (!target || !statusId) return

      button.disabled = true
      setStatus(statusId, 'pending', button.getAttribute('data-testing-label') || '…')

      const body = new FormData()
      body.append('_csrf', csrf)
      body.append('target', target)

      if (target === 'game') {
        body.append('db_host', form.db_host?.value || '')
        body.append('db_port', form.db_port?.value || '')
        body.append('db_user', form.db_user?.value || '')
        body.append('db_password', form.db_password?.value || '')
      } else {
        body.append('cms_db_host', form.cms_db_host?.value || '')
        body.append('cms_db_port', form.cms_db_port?.value || '')
        body.append('cms_db_user', form.cms_db_user?.value || '')
        body.append('cms_db_password', form.cms_db_password?.value || '')
      }

      try {
        const response = await fetch('/setup/test-connection', {
          method: 'POST',
          body,
          credentials: 'same-origin',
          headers: { Accept: 'application/json' },
        })
        const data = await response.json().catch(() => ({}))
        const message = typeof data.message === 'string' && data.message !== ''
          ? data.message
          : (response.ok ? 'OK' : 'Failed')
        setStatus(statusId, data.ok ? 'ok' : 'fail', message)
      } catch {
        setStatus(statusId, 'fail', 'Request failed')
      } finally {
        button.disabled = false
      }
    })
  })
})()
