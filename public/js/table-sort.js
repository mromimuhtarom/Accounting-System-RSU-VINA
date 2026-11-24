(() => {
    const fixedRows = '[data-sort-fixed], .group-row, .subtotal-row, .grand-row, .grand-result-row';
    const monthNames = 'jan feb mar apr mei jun jul agu sep okt nov des'.split(' ');
    const englishMonths = 'jan feb mar apr may jun jul aug sep oct nov dec'.split(' ');
    let collator;

    function sortKey(cell, kind) {
        const explicit = cell.dataset.sortValue;
        const value = (explicit ?? cell.querySelector('time[datetime]')?.dateTime ?? cell.textContent).trim().replace(/\s+/g, ' ');
        if (!value || /^[-–—]$/.test(value)) return null;
        if (kind === 'text') return value;

        let date = value.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?(?:Z)?$/);
        if (date) return Date.UTC(+date[1], +date[2] - 1, +date[3], +(date[4] || 0), +(date[5] || 0), +(date[6] || 0));

        date = value.match(/^(\d{1,2})[/-](\d{1,2})[/-](\d{4})(?:\s+(\d{2}):(\d{2})(?::(\d{2}))?)?$/);
        if (date) return Date.UTC(+date[3], +date[2] - 1, +date[1], +(date[4] || 0), +(date[5] || 0), +(date[6] || 0));

        date = value.match(/^(\d{1,2})\s+([a-z]{3,})\s+(\d{4})(?:\s+(\d{2}):(\d{2})(?::(\d{2}))?)?$/i);
        if (date) {
            const month = date[2].slice(0, 3).toLowerCase();
            const index = Math.max(monthNames.indexOf(month), englishMonths.indexOf(month));
            if (index >= 0) return Date.UTC(+date[3], index, +date[1], +(date[4] || 0), +(date[5] || 0), +(date[6] || 0));
        }

        date = value.match(/^(\d{1,2})\/(\d{4})$/);
        if (date) return Date.UTC(+date[2], +date[1] - 1, 1);

        const numeric = value.replace(/^Rp\s*/i, '').replace(/%$/, '').replace(/^\((.*)\)$/, '-$1');
        if (/^-?\d{1,3}(\.\d{3})+(,\d+)?$/.test(numeric)) return Number(numeric.replaceAll('.', '').replace(',', '.'));
        if (/^-?\d+(?:[.,]\d+)?$/.test(numeric)) return Number(numeric.replace(',', '.'));
        return value;
    }

    function sortBody(body, column, columnCount, direction, kind) {
        let rows = [];
        function flush(before) {
            if (rows.length > 1) {
                const sorted = rows.map((row, index) => ({ row, index, key: sortKey(row.cells[column], kind) }));
                sorted.sort((a, b) => {
                    if (a.key === null || b.key === null) return Number(a.key === null) - Number(b.key === null) || a.index - b.index;
                    const aIsNumber = typeof a.key === 'number';
                    const bIsNumber = typeof b.key === 'number';
                    if (aIsNumber !== bIsNumber) return aIsNumber ? -1 : 1;
                    const order = aIsNumber ? a.key - b.key : collator.compare(a.key, b.key);
                    return direction * order || a.index - b.index;
                });
                if (sorted.some(({ index }, i) => index !== i)) {
                    const fragment = document.createDocumentFragment();
                    for (const { row } of sorted) fragment.append(row);
                    body.insertBefore(fragment, before);
                }
            }
            rows = [];
        }

        for (const row of Array.from(body.rows)) {
            const cell = row.cells[column];
            if (row.hidden || row.matches(fixedRows) || !cell || row.cells.length !== columnCount
                || Array.from(row.cells).some(item => item.colSpan !== 1 || item.rowSpan !== 1)) {
                flush(row);
            } else {
                rows.push(row);
            }
        }
        flush(null);
    }

    for (const table of document.querySelectorAll('.app-shell table:not([data-sort-disabled])')) {
        const headers = table.tHead?.rows;
        if (headers?.length !== 1 || !table.tBodies.length) continue;
        const cells = Array.from(headers[0].cells);
        if (!cells.length || cells.some(cell => cell.colSpan !== 1 || cell.rowSpan !== 1)) continue;
        if (!Array.from(table.tBodies).some(body => body.rows.length > 1)) continue;
        if (!table.hasAttribute('data-sort-preview')
            && table.querySelector('tbody :is(input:not([type="hidden"]):not([type="checkbox"]),select,textarea)')) continue;

        cells.forEach((header, column) => {
            const label = header.textContent.trim();
            if (header.tagName !== 'TH' || !label || /^(aksi|tindakan|pilih|lewati|koreksi master|lampiran|bukti|foto|qr)$/i.test(label)
                || header.querySelector('a,button,input,select') || header.hasAttribute('data-sort-disabled')) return;

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'table-sort-button';
            button.title = 'Urutkan baris pada halaman ini';
            button.append(...header.childNodes);
            const arrow = document.createElement('span');
            arrow.dataset.pdfExclude = '';
            arrow.setAttribute('aria-hidden', 'true');
            arrow.textContent = '↕';
            button.append(arrow);
            header.append(button);
            header.scope = 'col';

            const kind = header.dataset.sortType ?? (/^(kode|code|no\.?\s+(rm|transaksi)|nomor\s+(rm|transaksi))\b/i.test(label) ? 'text' : undefined);
            button.addEventListener('click', () => {
                collator ??= new Intl.Collator('id', { numeric: true, sensitivity: 'base' });
                const rising = header.getAttribute('aria-sort') !== 'ascending';
                for (const cell of cells) {
                    cell.removeAttribute('aria-sort');
                    const marker = cell.querySelector('.table-sort-button > span[data-pdf-exclude]');
                    if (marker) marker.textContent = '↕';
                }
                header.setAttribute('aria-sort', rising ? 'ascending' : 'descending');
                arrow.textContent = rising ? '↑' : '↓';
                for (const body of table.tBodies) sortBody(body, column, cells.length, rising ? 1 : -1, kind);
            });
        });
    }
})();
