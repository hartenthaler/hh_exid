(function () {
    'use strict';

    function knownTypeUri(text) {
        const knownTypeUris = window.hhExidTypeUris || [];
        const candidate = String(text || '');

        return knownTypeUris.find(function (uri) {
            return candidate.includes(uri);
        }) || '';
    }

    function typeControlValue(control) {
        if (!control) {
            return '';
        }

        const select = control.querySelector('[data-exid-type-select]');
        const custom = control.querySelector('[data-exid-type-custom]');
        const selected = select && !select.classList.contains('d-none') ? select.value : (custom && !custom.classList.contains('d-none') ? custom.value : '');

        return selected || knownTypeUri(control.textContent);
    }

    function typeControlFollowing(valueInput) {
        const form = valueInput.closest('form');

        if (!form) {
            return null;
        }

        return Array.from(form.querySelectorAll('[data-exid-type-control]')).find(function (control) {
            return Boolean(valueInput.compareDocumentPosition(control) & Node.DOCUMENT_POSITION_FOLLOWING);
        }) || null;
    }

    function validateValue(valueInput) {
        const type = typeControlValue(typeControlFollowing(valueInput));
        const pattern = (window.hhExidValuePatterns || {})[type];

        valueInput.setCustomValidity('');
        valueInput.removeAttribute('pattern');

        if (!pattern || valueInput.value === '') {
            return true;
        }

        try {
            const valid = new RegExp('^(?:' + pattern + ')$', 'u').test(valueInput.value);

            if (!valid) {
                valueInput.setCustomValidity(window.hhExidPatternError || 'The external identifier does not match the selected authority.');
            }

            return valid;
        } catch (error) {
            // Invalid catalogue expressions are rejected when the catalogue is saved.
            return true;
        }
    }

    function initializeValueValidation() {
        const labels = window.hhExidFactLabels || [];

        // The core webtrees element is used for INDI:EXID. Mark its editor
        // input as well, so the same validation applies there as for custom
        // EXID elements registered by this module.
        document.querySelectorAll('form .row').forEach(function (row) {
            const label = row.querySelector('label');
            const input = row.querySelector('input:not([type="hidden"]), textarea');

            if (label && input && labels.includes(label.textContent.trim())) {
                input.dataset.exidValueInput = 'true';
            }
        });

        document.querySelectorAll('[data-exid-value-input]').forEach(validateValue);
    }

    function linkValue(valueNode, type) {
        const value = valueNode.textContent.trim();

        if (!type || value === '' || valueNode.dataset.exidLinkified === 'true') {
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

    function typeInScope(scope) {
        if (!scope) {
            return '';
        }

        const typeNodes = scope.querySelectorAll('[data-exid-type-value], .wt-fact-type .value, .wt-fact-type');

        for (const node of typeNodes) {
            const type = knownTypeUri(node.textContent);

            if (type) {
                return type;
            }
        }

        return '';
    }

    function linkifyExternalIdentifiers() {
        document.querySelectorAll('[data-exid-value]').forEach(function (valueNode) {
            linkValue(valueNode, typeInScope(valueNode.closest('tr, .wt-fact, .fact, .wt-fact-main-attributes')));
        });

        // GEDCOM EXID is a core element on individual pages, so webtrees may
        // render it without the module's data-exid-value marker. In that
        // layout the value and TYPE are still available in the same fact row.
        document.querySelectorAll('tr, .wt-fact, .fact').forEach(function (row) {
            const valueNode = row.querySelector('.wt-fact-value');
            const type = typeInScope(row);

            // A known TYPE URI is unambiguous here and also covers translated
            // labels and themes that use a different label markup.
            if (!valueNode || !type) {
                return;
            }

            const valueElement = valueNode.querySelector('[data-exid-value], .ut, bdi') || valueNode;
            linkValue(valueElement, type);
        });
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

    document.addEventListener('input', function (event) {
        if (event.target.matches('[data-exid-value-input]')) {
            validateValue(event.target);
        }
    });

    document.addEventListener('change', function (event) {
        if (event.target.matches('[data-exid-type-select], [data-exid-type-custom]')) {
            initializeValueValidation();
        }
    });

    document.addEventListener('submit', function (event) {
        let valid = true;

        document.querySelectorAll('[data-exid-value-input]').forEach(function (valueInput) {
            valid = validateValue(valueInput) && valid;
        });

        if (!valid) {
            event.preventDefault();
            document.querySelector('[data-exid-value-input]:invalid')?.reportValidity();
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initializeValueValidation();
            linkifyExternalIdentifiers();
        });
    } else {
        initializeValueValidation();
        linkifyExternalIdentifiers();
    }
}());
