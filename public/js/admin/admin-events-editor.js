(() => {
  const form = document.getElementById('admin-event-form')
  const editor = document.getElementById('event-body')

  if (!form || !editor || typeof tinymce === 'undefined') {
    return
  }

  const csrf = form.querySelector('input[name="_csrf"]')?.value || ''

  tinymce.init({
    base_url: '/vendor/tinymce',
    suffix: '.min',
    target: editor,
    menubar: false,
    branding: false,
    height: 420,
    plugins: 'lists link autoresize',
    toolbar: 'blocks | bold italic underline | bullist numlist | link | removeformat',
    block_formats: 'Paragraph=p; Heading 2=h2; Heading 3=h3; Heading 4=h4',
    valid_elements: 'p,br,h2,h3,h4,strong/b,em/i,u,ul,ol,li,blockquote,a[href|target|rel|title]',
    setup: (ed) => {
      form.addEventListener('submit', () => {
        editor.value = ed.getContent()
      })

      form.querySelectorAll('[role="tab"][data-tab="data"]').forEach((tab) => {
        tab.addEventListener('click', () => {
          requestAnimationFrame(() => {
            ed.execCommand('mceAutoResize')
          })
        })
      })
    },
  })

  const fileField = document.getElementById('event-seo-og-file')
  const input = document.getElementById('event-seo-og-image')
  const preview = document.getElementById('event-seo-og-preview')

  if (!fileField || !input) {
    return
  }

  fileField.addEventListener('change', () => {
    const file = fileField.files?.[0]

    if (!file) {
      return
    }

    const data = new FormData()
    data.append('_csrf', csrf)
    data.append('file', file)

    fetch('/admin/content/events/upload', {
      method: 'POST',
      body: data,
      credentials: 'same-origin',
    })
      .then(async (response) => {
        const payload = await response.json().catch(() => ({}))

        if (!response.ok || !payload.location) {
          window.alert(payload.error || 'Upload failed')
          return
        }

        input.value = payload.location

        if (preview) {
          preview.src = payload.location
          preview.hidden = false
        }
      })
      .catch(() => window.alert('Upload failed'))
  })
})()
