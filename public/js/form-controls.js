(function (root) {
    'use strict';

    // Work with strings so DECIMAL(18,2) amounts never lose precision through floating point.
    function toCanonical(value) {
        const text = String(value).trim();
        if (text === '') return '';
        const ungrouped = text.replace(/\./g, '');
        if (!/^\d+(?:,\d{0,2})?$/.test(ungrouped)) return null;
        const [whole, fraction] = ungrouped.split(',');
        const integer = whole.replace(/^0+(?=\d)/, '');
        if (integer.length > 16) return null;
        return integer + (fraction ? '.' + fraction : '');
    }

    function formatCanonical(value) {
        const text = String(value);
        if (text === '') return '';
        if (!/^\d+(?:\.\d{0,2})?$/.test(text)) return text;
        const [whole, fraction] = text.split('.');
        const grouped = whole.replace(/^0+(?=\d)/, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        return grouped + (fraction !== undefined ? ',' + fraction : '');
    }

    function cents(value) {
        const [whole, fraction = ''] = value.split('.');
        return BigInt(whole) * 100n + BigInt(fraction.padEnd(2, '0'));
    }

    root.RsuveMoney = { toCanonical, formatCanonical };
    if (typeof document === 'undefined') return;

    const moneyFields = new WeakSet();
    const passwordFields = new WeakSet();
    const moneyNames = /^(amount|claimed_amount|approved_amount|rejected_amount|paid_amount|hospital_tariff)$|\[(amount|debit|credit)\]$/;
    const eye = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>';
    const eyeOff = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 3 18 18M10.6 5.1A11 11 0 0 1 12 5c6.5 0 10 7 10 7a19 19 0 0 1-3 3.9M6.2 6.2A20 20 0 0 0 2 12s3.5 7 10 7a12 12 0 0 0 5.8-1.8M10 10a3 3 0 0 0 4 4"/></svg>';

    function enhancePassword(input) {
        if (passwordFields.has(input)) return;
        passwordFields.add(input);
        const wrapper = document.createElement('div');
        wrapper.className = 'password-control';
        input.before(wrapper);
        wrapper.append(input);
        if (!input.id) input.id = 'password-' + Math.random().toString(36).slice(2);
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'password-toggle';
        button.setAttribute('aria-controls', input.id);
        function conceal() {
            input.type = 'password';
            button.innerHTML = eye;
            button.setAttribute('aria-label', 'Tampilkan kata sandi');
            button.setAttribute('aria-pressed', 'false');
            button.title = 'Tampilkan kata sandi';
        }
        conceal();
        button.addEventListener('click', () => {
            if (input.type === 'password') {
                input.type = 'text';
                button.innerHTML = eyeOff;
                button.setAttribute('aria-label', 'Sembunyikan kata sandi');
                button.setAttribute('aria-pressed', 'true');
                button.title = 'Sembunyikan kata sandi';
            } else conceal();
        });
        input.form?.addEventListener('submit', conceal);
        window.addEventListener('pageshow', conceal);
        wrapper.append(button);
    }

    function enhanceMoney(input) {
        if (moneyFields.has(input)) return;
        moneyFields.add(input);
        const name = input.name;
        const minimum = input.getAttribute('min');
        const maximum = input.getAttribute('max');
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = name;
        hidden.value = input.value;
        hidden.defaultValue = input.defaultValue;
        input.type = 'text';
        input.inputMode = 'decimal';
        input.classList.add('money-input');
        input.dataset.moneyField = name;
        input.removeAttribute('name');
        input.value = formatCanonical(hidden.value);
        input.after(hidden);

        function sync(format = false) {
            const raw = toCanonical(input.value);
            hidden.disabled = input.disabled;
            let error = '';
            if (raw === null) {
                error = 'Masukkan nominal yang valid, maksimal 16 angka sebelum koma dan 2 angka sesudah koma.';
                hidden.value = 'invalid';
            } else {
                hidden.value = raw;
                if (raw !== '' && minimum !== null && cents(raw) < cents(minimum)) error = 'Nominal minimal ' + formatCanonical(minimum) + '.';
                if (raw !== '' && maximum !== null && cents(raw) > cents(maximum)) error = 'Nominal maksimal ' + formatCanonical(maximum) + '.';
                if (format && raw !== '') {
                    const caret = input.selectionStart;
                    const beforeCaret = input.value.slice(0, caret).replace(/\./g, '').length;
                    const trailingComma = input.value.endsWith(',');
                    input.value = formatCanonical(raw) + (trailingComma ? ',' : '');
                    let position = 0;
                    let count = 0;
                    while (position < input.value.length && count < beforeCaret) {
                        if (input.value[position] !== '.') count++;
                        position++;
                    }
                    if (document.activeElement === input) input.setSelectionRange(position, position);
                }
            }
            input.setCustomValidity(error);
            if (error) input.setAttribute('aria-invalid', 'true');
            else input.removeAttribute('aria-invalid');
        }

        input.addEventListener('input', () => sync(true));
        input.addEventListener('change', () => sync(true));
        input.addEventListener('blur', () => {
            sync();
            if (hidden.value !== 'invalid') input.value = formatCanonical(hidden.value);
        });
        input.addEventListener('paste', (event) => {
            const text = event.clipboardData?.getData('text').trim();
            // Accept ungrouped decimal values copied from exports as well as Indonesian notation.
            if (/^\d+\.\d{1,2}$/.test(text || '')) {
                event.preventDefault();
                input.setRangeText(text.replace('.', ','), input.selectionStart, input.selectionEnd, 'end');
                input.dispatchEvent(new Event('input', { bubbles: true }));
            }
        });
        input.form?.addEventListener('formdata', (event) => {
            if (!input.isConnected || input.form !== event.target) return;
            sync();
            if (!input.disabled) event.formData.set(name, hidden.value);
            else event.formData.delete(name);
        });
        input.form?.addEventListener('reset', () => window.setTimeout(() => {
            input.value = formatCanonical(hidden.defaultValue);
            sync();
        }, 0));
        sync();
    }

    function enhance(container) {
        container.querySelectorAll('input[type="password"]').forEach(enhancePassword);
        container.querySelectorAll('input[type="number"][name]').forEach((input) => {
            if (moneyNames.test(input.name)) enhanceMoney(input);
        });
    }

    function initialize() {
        enhance(document);
        new MutationObserver((mutations) => {
            for (const mutation of mutations) {
                for (const node of mutation.addedNodes) {
                    if (!(node instanceof Element)) continue;
                    if (node.matches('input')) enhance(node.parentElement || document);
                    else if (node.querySelector('input')) enhance(node);
                }
            }
        }).observe(document.body, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true });
    else initialize();
})(globalThis);
