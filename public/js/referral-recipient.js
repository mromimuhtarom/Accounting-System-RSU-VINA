(function () {
    'use strict';
    const dialog = document.getElementById('recipient-dialog');
    const form = document.getElementById('recipient-modal-form');
    if (!dialog || !form) return;
    const select = document.getElementById('referral-recipient');
    const message = document.getElementById('recipient-modal-message');
    const suggestions = document.getElementById('recipient-suggestions');
    const confirm = document.getElementById('recipient-confirm-label');
    const name = form.elements.namedItem('name');
    document.getElementById('add-referral-recipient').addEventListener('click', () => {
        dialog.showModal();
        name.focus();
    });
    document.getElementById('close-recipient-dialog').addEventListener('click', () => dialog.close());
    function choose(recipient) {
        let option = [...select.options].find(o => o.value === String(recipient.id));
        if (!option) {
            option = new Option(recipient.name, String(recipient.id));
            select.add(option);
        }
        select.value = String(recipient.id);
        select.dispatchEvent(new Event('change', { bubbles: true }));
        dialog.close();
        form.reset();
        message.textContent = '';
        suggestions.replaceChildren();
        confirm.hidden = true;
    }
    function showSuggestions(recipients) {
        suggestions.replaceChildren();
        for (const recipient of recipients) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn';
            button.textContent = `Gunakan ${recipient.name}${recipient.is_active ? '' : ' (Nonaktif)'}`;
            button.disabled = !recipient.is_active;
            button.addEventListener('click', () => choose(recipient));
            suggestions.append(button);
        }
    }
    let sequence = 0;
    name.addEventListener('input', () => {
        sequence++;
        confirm.hidden = true;
        form.elements.namedItem('confirm_distinct').checked = false;
        suggestions.replaceChildren();
        message.textContent = '';
    });
    name.addEventListener('blur', async () => {
        const ticket = ++sequence;
        if (!name.value.trim()) return;
        try {
            const url = new URL(form.dataset.similarUrl, window.location.href);
            url.searchParams.set('name', name.value);
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            const data = await response.json();
            if (ticket !== sequence || !response.ok) return;
            showSuggestions(data.recipients || []);
            if (data.recipients?.length) {
                message.textContent = 'Nama mirip penerima existing. Periksa sebelum menyimpan.';
                confirm.hidden = false;
            }
        } catch (_) { /* Submission repeats the server-side duplicate check. */ }
    });
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        sequence++;
        const submit = form.querySelector('[type="submit"]');
        submit.disabled = true;
        message.textContent = 'Menyimpan...';
        try {
            const response = await fetch(form.action, {
                method: 'POST', body: new FormData(form),
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await response.json();
            if (response.ok) { choose(data); return; }
            if (response.status === 409) {
                showSuggestions(data.recipients || []);
                confirm.hidden = false;
            }
            message.textContent = data.errors ? Object.values(data.errors).flat().join(' ') : (data.message || 'Gagal menyimpan.');
        } catch (_) {
            message.textContent = 'Koneksi gagal. Data transaksi tetap tersimpan di form; coba lagi.';
        } finally { submit.disabled = false; }
    });
})();
