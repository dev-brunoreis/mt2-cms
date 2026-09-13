(() => {
  const form = document.getElementById('admin-news-form')
  const editor = document.getElementById('news-body')

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
    plugins: 'lists link image autoresize',
    toolbar: 'blocks | bold italic underline | bullist numlist | link image | removeformat',
    block_formats: 'Paragraph=p; Heading 2=h2; Heading 3=h3; Heading 4=h4',
    valid_elements: 'p,br,h2,h3,h4,strong/b,em/i,u,ul,ol,li,blockquote,a[href|target|rel|title],img[src|alt|title|width|height]',
    images_upload_handler: (blobInfo) => new Promise((resolve, reject) => {
      const data = new FormData()
      data.append('_csrf', csrf)
      data.append('file', blobInfo.blob(), blobInfo.filename())

      fetch('/admin/content/news/posts/upload', {
        method: 'POST',
        body: data,
        credentials: 'same-origin',
      })
        .then(async (response) => {
          const payload = await response.json().catch(() => ({}))

          if (!response.ok || !payload.location) {
            reject(payload.error || 'Upload failed')
            return
          }

          resolve(payload.location)
        })
        .catch(() => reject('Upload failed'))
    }),
    setup: (ed) => {
      form.addEventListener('submit', () => {
        editor.value = ed.getContent()
      })
    },
  })

  const bindImageUpload = (fileId, inputId, previewId) => {
    const input = document.getElementById(inputId)
    const fileField = document.getElementById(fileId)
    const preview = document.getElementById(previewId)

    if (!input || !fileField) {
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

      fetch('/admin/content/news/posts/upload', {
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
  }

  bindImageUpload('news-cover-file', 'news-cover-image', 'news-cover-preview')
  bindImageUpload('news-seo-og-file', 'news-seo-og-image', 'news-seo-og-preview')
})()
