(function () {
    'use strict';

    function toggle(control) {
        const select = control.querySelector('[data-exid-type-select]');
        const custom = control.querySelector('[data-exid-type-custom]');
        const button = control.querySelector('[data-exid-type-toggle]');
        const name = control.dataset.exidTypeName;

        if (!select || !custom || !button || !name) {
            return;
        }

        const customMode = custom.classList.contains('d-none');
        if (customMode) {
            select.classList.add('d-none');
            select.removeAttribute('name');
            custom.classList.remove('d-none');
            custom.setAttribute('name', name);
            custom.focus();
            button.textContent = '−';
        } else {
            custom.classList.add('d-none');
            custom.removeAttribute('name');
            select.classList.remove('d-none');
            select.setAttribute('name', name);
            select.focus();
            button.textContent = '+';
        }
    }

    document.addEventListener('click', function (event) {
        const button = event.target.closest('[data-exid-type-toggle]');
        const control = button && button.closest('[data-exid-type-control]');

        if (control) {
            toggle(control);
        }
    });
}());
