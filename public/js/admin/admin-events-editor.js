(() => {
  const form = document.getElementById('admin-event-form')
  const editor = document.getElementById('event-body')

  if (!form || !editor || typeof tinymce === 'undefined') {
    return
  }

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
    },
  })
})()
