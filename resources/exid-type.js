(function () {
    'use strict';

    function linkifyExternalIdentifiers() {
        document.querySelectorAll('[data-exid-value]').forEach(function (valueNode) {
            if (valueNode.dataset.exidLinkified === 'true') {
                return;
            }

            const factRow = valueNode.closest('tr');
            const row = valueNode.closest('div');
            const typeRow = factRow || (row && row.nextElementSibling);
            const typeNode = typeRow && typeRow.querySelector('[data-exid-type-value]');
            const value = valueNode.textContent.trim();
            const type = typeNode && typeNode.textContent.trim();

            const knownTypeUris = window.hhExidTypeUris || [];
            if (!type || !knownTypeUris.includes(type) || value === '') {
                return;
            }

            const anchor = document.createElement('a');
            anchor.href = type + encodeURIComponent(value);
            anchor.target = '_blank';
            anchor.rel = 'noopener noreferrer';
            anchor.textContent = value;
            valueNode.replaceChildren(anchor);
            valueNode.dataset.exidLinkified = 'true';
        });

        // GEDCOM EXID is a core element on individual pages, so webtrees may
        // render it without the module's data-exid-value marker. In that
        // layout the value and TYPE are still available in the same fact row.
        const labels = window.hhExidFactLabels || [];
        document.querySelectorAll('tr').forEach(function (row) {
            const label = row.querySelector('.wt-fact-label');
            const valueNode = row.querySelector('.wt-fact-value');
            const typeNode = row.querySelector('.wt-fact-type [data-exid-type-value], .wt-fact-type .value');

            if (!label || !valueNode || !typeNode || !labels.includes(label.textContent.trim()) || valueNode.dataset.exidLinkified === 'true') {
                return;
            }

            linkifyValue(valueNode, typeNode.textContent.trim());
        });
    }

    function linkifyValue(valueNode, type) {
        const value = valueNode.textContent.trim();
        const knownTypeUris = window.hhExidTypeUris || [];

        if (!type || !knownTypeUris.includes(type) || value === '') {
            return;
        }

        const anchor = document.createElement('a');
        anchor.href = type + encodeURIComponent(value);
        anchor.target = '_blank';
        anchor.rel = 'noopener noreferrer';
        anchor.textContent = value;
        valueNode.replaceChildren(anchor);
        valueNode.dataset.exidLinkified = 'true';
    }

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

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', linkifyExternalIdentifiers);
    } else {
        linkifyExternalIdentifiers();
    }
}());
