(function () {
    'use strict';
    const form = document.querySelector('[data-batch-form]');
    if (!form) return;
    const rows = form.querySelector('[data-batch-rows]');
    const add = form.querySelector('[data-add-batch-row]');
    const template = form.querySelector('[data-batch-template]');
    const entries = () => [...rows.querySelectorAll('[data-batch-row]')];
    const field = (row, key) => row.querySelector(`[name$="[${key}]"]`);
    const shared = ['transaction_date', 'expense_date', 'chart_account_id', 'expense_category_id', 'guarantor_id', 'service_type', 'payment_method', 'cash_bank_account_id'];
    function refresh() {
        const list = entries();
        list.forEach((row, index) => {
            row.querySelector('[data-batch-title]').textContent = `Transaksi ${index + 1}`;
            row.querySelector('[data-remove-batch-row]').disabled = list.length === 1;
        });
        add.disabled = list.length >= 20;
        form.querySelector('[data-batch-count]').textContent = `${list.length} / 20 transaksi`;
        const total = list.reduce((sum, row) => sum + (Number(field(row, 'amount').value) || 0), 0);
        form.querySelector('[data-batch-total]').textContent = `Total: ${new Intl.NumberFormat('id-ID', {style:'currency', currency:'IDR'}).format(total)}`;
    }
    function append() {
        if (entries().length >= 20) return null;
        const used = new Set([...rows.querySelectorAll('[name^="rows["]')].map(e => Number(e.name.match(/^rows\[(\d+)\]/)?.[1])));
        let index = 0;
        while (used.has(index)) index++;
        const wrapper = document.createElement('div');
        wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(index));
        const row = wrapper.firstElementChild;
        const doctor = field(row, 'doctor_name');
        const existingDoctor = field(entries()[0], 'doctor_name');
        if (doctor && existingDoctor) {
            Array.from(existingDoctor.options).forEach(option => {
                if (!Array.from(doctor.options).some(item => item.value === option.value)) doctor.add(new Option(option.text, option.value));
            });
        }
        const source = entries()[0];
        shared.forEach(key => {
            if (field(source, key) && field(row, key)) field(row, key).value = field(source, key).value;
        });
        rows.append(row);
        refresh();
        return row;
    }
    add.addEventListener('click', () => append()?.querySelector('input,select,textarea')?.focus());
    rows.addEventListener('click', event => {
        const button = event.target.closest('[data-remove-batch-row]');
        if (!button || entries().length < 2) return;
        button.closest('[data-batch-row]').remove();
        refresh();
    });
    rows.addEventListener('input', refresh);
    function parseAmount(raw) {
        let value = raw.trim().replace(/^Rp\.?\s*/i, '').replace(/\s/g, '');
        if (/^\d{1,3}(\.\d{3})+(,\d{1,2})?$/.test(value)) value = value.replaceAll('.', '').replace(',', '.');
        else if (/^\d+(,\d{1,2})$/.test(value)) value = value.replace(',', '.');
        else if (!/^\d+(\.\d{1,2})?$/.test(value)) return null;
        const amount = Number(value);
        return Number.isFinite(amount) && amount > 0 ? value : null;
    }
    form.querySelector('[data-import-batch]')?.addEventListener('click', () => {
        const input = form.querySelector('[data-batch-paste]');
        const status = form.querySelector('[data-paste-status]');
        const lines = input.value.replace(/\r\n?/g, '\n').split('\n').filter(line => line.trim());
        if (!lines.length) { status.textContent = 'Tempel data Excel terlebih dahulu.'; return; }
        const parsed = [];
        for (const [index, line] of lines.entries()) {
            const cells = line.split('\t');
            const amount = cells.length === 2 ? parseAmount(cells[1]) : null;
            if (!cells[0].trim() || amount === null) {
                status.textContent = `Baris ${index + 1} tidak valid. Gunakan tepat 2 kolom: keterangan dan nominal positif. Belum ada data ditambahkan.`;
                return;
            }
            parsed.push({description: cells[0].trim(), amount});
        }
        // Only reuse a pristine row: never overwrite an existing description or optional detail.
        const empty = entries().filter(row => [...row.querySelectorAll('input,textarea,select')].every(el =>
            el.type === 'date' || shared.some(key => el.name.endsWith(`[${key}]`)) || !el.value));
        if (entries().length - empty.length + parsed.length > 20) {
            status.textContent = 'Jumlah melebihi 20 transaksi. Bagi data menjadi beberapa penyimpanan.';
            return;
        }
        parsed.forEach(item => {
            const row = empty.shift() || append();
            (field(row, 'notes') || field(row, 'description')).value = item.description;
            field(row, 'amount').value = item.amount;
        });
        input.value = '';
        status.textContent = `${parsed.length} baris ditambahkan. Periksa kategori, rincian dan total, lalu Simpan Semua Transaksi.`;
        refresh();
    });
    refresh();
})();
