(() => {
  if (typeof tinymce === 'undefined') {
    return
  }

  const editors = document.querySelectorAll('textarea[data-html-editor]')

  if (editors.length === 0) {
    return
  }

  editors.forEach((editor) => {
    const form = editor.closest('form')

    tinymce.init({
      target: editor,
      menubar: false,
      branding: false,
      height: Number(editor.getAttribute('data-editor-height') || 280),
      plugins: 'lists link autoresize',
      toolbar: 'blocks | bold italic underline | bullist numlist | link | removeformat',
      block_formats: 'Paragraph=p; Heading 2=h2; Heading 3=h3; Heading 4=h4',
      valid_elements: 'p,br,h2,h3,h4,strong/b,em/i,u,ul,ol,li,blockquote,a[href|target|rel|title]',
      convert_urls: false,
      setup: (ed) => {
        if (!form) {
          return
        }

        form.addEventListener('submit', () => {
          editor.value = ed.getContent()
        })
      },
    })
  })
})()
