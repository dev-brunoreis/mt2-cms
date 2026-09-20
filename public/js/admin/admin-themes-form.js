/**
 * Themes settings: show layout columns/sidebar only for themes that declare the feature.
 * Re-run after admin tab AJAX loads (see admin-tabs.js).
 */
(function () {
  function parseSupport(form) {
    var raw = form.getAttribute('data-layout-support') || '{}'
    try {
      return JSON.parse(raw) || {}
    } catch (e) {
      return {}
    }
  }

  function syncForm(form) {
    var support = parseSupport(form)
    var select = form.querySelector('[name="active_theme"]')
    var layoutBox = form.querySelector('[data-theme-layout-options]')
    var sidebarFieldset = form.querySelector('[data-theme-layout-sidebar]')
    var layoutInputs = form.querySelectorAll('[name="layout_columns"], [name="layout_sidebar"]')
    var theme = select ? select.value : ''
    var layoutOn = !!support[theme]

    if (layoutBox) {
      layoutBox.hidden = !layoutOn
    }

    layoutInputs.forEach(function (el) {
      el.disabled = !layoutOn
    })

    var two = form.querySelector('[name="layout_columns"][value="2"]')
    var sidebarOn = layoutOn && two && two.checked

    if (sidebarFieldset) {
      sidebarFieldset.hidden = !sidebarOn
    }
  }

  function bindForm(form) {
    if (!(form instanceof HTMLElement) || form.getAttribute('data-theme-layout-bound') === '1') {
      return
    }

    form.setAttribute('data-theme-layout-bound', '1')

    var select = form.querySelector('[name="active_theme"]')
    if (select) {
      select.addEventListener('change', function () {
        syncForm(form)
      })
    }

    form.querySelectorAll('[name="layout_columns"]').forEach(function (el) {
      el.addEventListener('change', function () {
        syncForm(form)
      })
    })

    syncForm(form)
  }

  window.initAdminThemesForm = function (root) {
    var scope = root instanceof HTMLElement ? root : document
    scope.querySelectorAll('form[data-theme-layout-form]').forEach(bindForm)
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      window.initAdminThemesForm(document)
    })
  } else {
    window.initAdminThemesForm(document)
  }
})()
