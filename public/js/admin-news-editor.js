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

  const coverInput = document.getElementById('news-cover-image')
  const coverFile = document.getElementById('news-cover-file')
  const coverPreview = document.getElementById('news-cover-preview')

  if (!coverInput || !coverFile) {
    return
  }

  coverFile.addEventListener('change', () => {
    const file = coverFile.files?.[0]

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

        coverInput.value = payload.location

        if (coverPreview) {
          coverPreview.src = payload.location
          coverPreview.hidden = false
        }
      })
      .catch(() => window.alert('Upload failed'))
  })
})()
