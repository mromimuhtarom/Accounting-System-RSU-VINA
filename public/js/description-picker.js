(function () {
    'use strict';
    document.addEventListener('change', function (event) {
        const picker = event.target.closest('select[data-description-picker]');
        if (!picker || !picker.value) return;
        const scope = picker.closest('[data-batch-row]') || picker.closest('form');
        const key = picker.dataset.descriptionPicker;
        const target = scope?.querySelector(`textarea[name="${key}"],textarea[name$="[${key}]"]`);
        if (!target) return;
        target.value = picker.value;
        target.dispatchEvent(new Event('input', { bubbles: true }));
        target.dispatchEvent(new Event('change', { bubbles: true }));
    });
})();
