(function () {
    'use strict';
    const form = document.querySelector('[data-batch-form]');
    if (!form) return;
    const rows = form.querySelector('[data-batch-rows]');
    const add = form.querySelector('[data-add-batch-row]');
    const template = form.querySelector('[data-batch-template]');
    function refresh() {
        const entries = [...rows.querySelectorAll('[data-batch-row]')];
        entries.forEach((row) => {
            const name = row.querySelector('[name^="rows["]')?.name;
            const index = Number(name?.match(/^rows\[(\d+)\]/)?.[1] || 0);
            row.querySelector('[data-batch-title]').textContent = `Transaksi ${index + 1}`;
            row.querySelector('[data-remove-batch-row]').disabled = entries.length === 1;
        });
        add.disabled = entries.length >= 20;
        form.querySelector('[data-batch-count]').textContent = `${entries.length} / 20 transaksi`;
    }
    // Reuse deleted indices to keep field names, error messages, and old input aligned.
    add.addEventListener('click', () => {
        const used = new Set([...rows.querySelectorAll('[name^="rows["]')].map(e => Number(e.name.match(/^rows\[(\d+)\]/)?.[1])));
        let index = 0;
        while (used.has(index)) index++;
        if (rows.children.length >= 20) return;
        const wrapper = document.createElement('div');
        wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(index));
        const row = wrapper.firstElementChild;
        rows.append(row);
        refresh();
        row.querySelector('input,select,textarea')?.focus();
    });
    rows.addEventListener('click', (event) => {
        const button = event.target.closest('[data-remove-batch-row]');
        if (!button || rows.children.length < 2) return;
        button.closest('[data-batch-row]').remove();
        refresh();
    });
    refresh();
})();
