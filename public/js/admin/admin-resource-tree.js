(function () {
    'use strict';

    function syncParent(prefix, root) {
        var children = root.querySelectorAll('input[data-resource-id^="' + prefix + '/"]');
        var parent = root.querySelector('input.admin-resource-parent[data-resource-prefix="' + prefix + '"]');

        if (!parent || children.length === 0) {
            return;
        }

        var checked = 0;

        children.forEach(function (input) {
            if (input.checked) {
                checked++;
            }
        });

        parent.indeterminate = checked > 0 && checked < children.length;
        parent.checked = checked === children.length;
    }

    function setChildren(prefix, root, checked) {
        root.querySelectorAll('input[data-resource-id^="' + prefix + '/"]').forEach(function (input) {
            input.checked = checked;
        });
    }

    document.addEventListener('change', function (event) {
        var target = event.target;

        if (!(target instanceof HTMLInputElement)) {
            return;
        }

        var tree = target.closest('[data-admin-resource-tree]');

        if (!tree) {
            return;
        }

        if (target.classList.contains('admin-resource-parent')) {
            var prefix = target.getAttribute('data-resource-prefix');

            if (!prefix) {
                return;
            }

            setChildren(prefix, tree, target.checked);
            syncParent(prefix, tree);

            return;
        }

        if (target.matches('input[data-resource-id]')) {
            var resourceId = target.getAttribute('data-resource-id') || '';
            var parts = resourceId.split('/');

            for (var i = parts.length - 1; i >= 2; i--) {
                syncParent(parts.slice(0, i).join('/'), tree);
            }
        }
    });

    document.querySelectorAll('[data-admin-resource-tree]').forEach(function (tree) {
        tree.querySelectorAll('input.admin-resource-parent[data-resource-prefix]').forEach(function (parent) {
            var prefix = parent.getAttribute('data-resource-prefix');

            if (prefix) {
                syncParent(prefix, tree);
            }
        });
    });
})();
