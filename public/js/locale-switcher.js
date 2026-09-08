document.addEventListener('DOMContentLoaded', function () {
    var select = document.getElementById('locale-switcher');

    if (!(select instanceof HTMLSelectElement) || select.form === null) {
        return;
    }

    select.addEventListener('change', function () {
        select.form.submit();
    });
});
