(function () {
    'use strict';

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
