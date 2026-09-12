(function () {
    'use strict';

    function disableEmptyFilters(form) {
        var selector = '[form="' + form.id + '"][name^="filter"]';

        document.querySelectorAll(selector).forEach(function (el) {
            el.disabled = !el.value;
        });
    }

    document.querySelectorAll('[data-admin-grid]').forEach(function (root) {
        var limitSelect = root.querySelector('[data-grid-limit]');

        if (limitSelect) {
            limitSelect.addEventListener('change', function () {
                var url = limitSelect.value;

                if (url) {
                    window.location.href = url;
                }
            });
        }

        root.querySelectorAll('[data-grid-filter-change]').forEach(function (el) {
            el.addEventListener('change', function () {
                var form = el.form;

                if (form) {
                    disableEmptyFilters(form);
                    form.submit();
                }
            });
        });

        root.querySelectorAll('form[id^="admin-grid-filter-"]').forEach(function (form) {
            form.addEventListener('submit', function () {
                disableEmptyFilters(form);
            });
        });

        var massForm = root.querySelector('[data-grid-mass-form]');

        if (!massForm) {
            return;
        }

        var selectAll = root.querySelector('[data-grid-select-all]');
        var rowChecks = root.querySelectorAll('[data-grid-row-check]');
        var massAction = massForm.querySelector('[data-grid-mass-action]');
        var massSubmit = massForm.querySelector('[data-grid-mass-submit]');
        var selectedCount = massForm.querySelector('[data-grid-selected-count]');

        function updateMassState() {
            var checked = root.querySelectorAll('[data-grid-row-check]:checked');
            var count = checked.length;

            if (massSubmit) {
                massSubmit.disabled = count === 0 || !massAction || massAction.value === '';
            }

            if (selectedCount) {
                if (count > 0) {
                    selectedCount.hidden = false;
                    selectedCount.textContent = selectedCount.dataset.template
                        ? selectedCount.dataset.template.replace('__COUNT__', String(count))
                        : String(count) + ' selected';
                } else {
                    selectedCount.hidden = true;
                }
            }

            if (selectAll && rowChecks.length > 0) {
                selectAll.indeterminate = count > 0 && count < rowChecks.length;
                selectAll.checked = count === rowChecks.length;
            }
        }

        if (selectAll) {
            selectAll.addEventListener('change', function () {
                rowChecks.forEach(function (cb) {
                    cb.checked = selectAll.checked;
                });
                updateMassState();
            });
        }

        rowChecks.forEach(function (cb) {
            cb.addEventListener('change', updateMassState);
        });

        if (massAction) {
            massAction.addEventListener('change', updateMassState);
        }

        massForm.addEventListener('submit', function (event) {
            var checked = root.querySelectorAll('[data-grid-row-check]:checked');

            if (checked.length === 0) {
                event.preventDefault();
                return;
            }

            if (!massAction || massAction.value === '') {
                event.preventDefault();
                return;
            }

            var option = massAction.options[massAction.selectedIndex];
            var confirmMsg = option ? option.getAttribute('data-confirm') : null;

            if (confirmMsg && !window.confirm(confirmMsg)) {
                event.preventDefault();
            }
        });

        updateMassState();
    });
})();
