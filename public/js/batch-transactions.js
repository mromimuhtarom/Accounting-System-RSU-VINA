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
    const currency = new Intl.NumberFormat('id-ID', {style:'currency', currency:'IDR', minimumFractionDigits:2, maximumFractionDigits:2});
    let importing = false;
    function refreshTotal() {
        const total = entries().reduce((sum, row) => {
            const match = field(row, 'amount').value.match(/^(\d+)(?:\.(\d{1,2}))?$/);
            return match ? sum + BigInt(match[1]) * 100n + BigInt((match[2] || '').padEnd(2, '0')) : sum;
        }, 0n);
        const fraction = String(total % 100n).padStart(2, '0');
        const formatted = currency.formatToParts(total / 100n).map(part => part.type === 'fraction' ? fraction : part.value).join('');
        form.querySelector('[data-batch-total]').textContent = `Total: ${formatted}`;
    }
    function refresh() {
        const list = entries();
        list.forEach((row, index) => {
            row.querySelector('[data-batch-title]').textContent = `Transaksi ${index + 1}`;
            row.querySelector('[data-remove-batch-row]').disabled = list.length === 1;
        });
        add.disabled = list.length >= 20;
        form.querySelector('[data-batch-count]').textContent = `${list.length} / 20 transaksi`;
        refreshTotal();
    }
    function append(refreshAfter = true) {
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
        if (refreshAfter) refresh();
        return row;
    }
    add.addEventListener('click', () => append()?.querySelector('input,select,textarea')?.focus());
    rows.addEventListener('click', event => {
        const button = event.target.closest('[data-remove-batch-row]');
        if (!button || entries().length < 2) return;
        button.closest('[data-batch-row]').remove();
        refresh();
    });
    rows.addEventListener('input', event => {
        if (!importing && event.target.matches('[name$="[amount]"],[data-money-field$="[amount]"]')) refreshTotal();
    });
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
        importing = true;
        try {
            parsed.forEach(item => {
                const row = empty.shift() || append(false);
                (field(row, 'notes') || field(row, 'description')).value = item.description;
                const visibleAmount = row.querySelector('[data-money-field$="[amount]"]') || field(row, 'amount');
                visibleAmount.value = visibleAmount.dataset.moneyField ? window.RsuveMoney.formatCanonical(item.amount) : item.amount;
                visibleAmount.dispatchEvent(new Event('input', {bubbles:true}));
            });
        } finally { importing = false; }
        input.value = '';
        status.textContent = `${parsed.length} baris ditambahkan. Periksa kategori, rincian dan total, lalu Simpan Semua Transaksi.`;
        refresh();
    });
    refresh();
})();
