/* Export the displayed report, without requesting or changing transaction data. */
(() => {
    const button = document.getElementById('export-report-pdf');
    if (!button) return;
    button.addEventListener('click', async () => {
        const status = document.getElementById('report-pdf-status');
        let stage;
        button.disabled = true;
        button.textContent = 'Memproses PDF…';
        status.textContent = 'Menyiapkan laporan…';
        try {
            if (typeof html2pdf !== 'function') throw new Error('PDF library unavailable');
            await document.fonts.ready;
            const landscape = button.dataset.landscape === '1';
            const width = landscape ? 1600 : 1000;
            const paperWidth = landscape ? 297 : 210;
            const paperHeight = landscape ? 210 : 297;
            const usableWidth = paperWidth - 20;
            const maxHeight = (paperHeight - 30) * width / usableWidth;
            stage = document.createElement('div');
            stage.style.cssText = `position:absolute;left:-20000px;top:0;width:${width}px;background:white;color:#203c39;`;
            stage.className = 'pdf-export-stage';
            const styles = document.createElement('style');
            styles.textContent = `
                .pdf-export-stage *{animation:none!important;transition:none!important;box-sizing:border-box}
                .pdf-export-stage .period-filter{display:none!important}
                .pdf-export-stage .page-head{display:block}
                .pdf-export-stage .report-stats{display:grid;grid-template-columns:repeat(3,1fr)}
                .pdf-export-stage .stat{font-size:28px}
                .pdf-export-stage .card{box-shadow:none;max-width:100%;overflow:visible}
                .pdf-export-stage .table-responsive,.pdf-export-stage .yearly-table-wrap,.pdf-export-stage .rsuve-claim-table,.pdf-export-stage .rsuve-year-scroll{overflow:visible!important}
                .pdf-export-stage table{width:100%!important;min-width:0!important;table-layout:fixed}
                .pdf-export-stage th,.pdf-export-stage td{white-space:normal!important;overflow-wrap:anywhere;min-width:0!important;position:static!important}
                .pdf-export-stage .yearly-table th,.pdf-export-stage .yearly-table td{font-size:12px;padding:9px 5px}
                .pdf-export-stage .yearly-table th:first-child,.pdf-export-stage .yearly-table td:first-child{width:190px;min-width:0!important}
                .pdf-export-stage .rsuve-year-columns{min-width:0;grid-template-columns:repeat(12,1fr)}
                .pdf-export-stage .rsuve-month-chart{grid-template-columns:1fr 1fr}
                .pdf-export-stage .claim-chart-row{grid-template-columns:100px minmax(0,1fr) 180px}
                .pdf-export-stage .claim-chart-track{grid-row:auto;grid-column:auto}
                .pdf-export-stage .claim-chart-value{grid-row:auto;grid-column:auto}
            `;
            stage.append(styles);
            document.body.append(stage);
            const source = document.getElementById('report-pdf-content').cloneNode(true);
            source.removeAttribute('id');
            source.querySelectorAll('form,script,style').forEach(node => node.remove());
            if (button.dataset.empty === '1') {
                const empty = document.createElement('p');
                empty.textContent = 'Belum ada transaksi pada periode ini.';
                source.querySelector('.page-head').after(empty);
            }
            const identity = document.createElement('div');
            identity.style.cssText = 'display:flex;align-items:center;gap:15px;margin-bottom:18px';
            const logo = document.createElement('img');
            logo.src = button.dataset.logo;
            logo.style.cssText = 'width:48px;height:56px;object-fit:contain';
            const name = document.createElement('strong');
            name.textContent = `${button.dataset.hospital} — Periode ${button.dataset.period}`;
            identity.append(logo, name);
            stage.append(identity);
            stage.append(source);
            await Promise.all([...stage.querySelectorAll('img')].map(img => img.decode()));
            // Flatten only layout wrappers so each card can flow across PDF pages.
            source.querySelectorAll('.report-grid > section').forEach(node => node.replaceWith(...node.children));
            source.querySelectorAll('.report-grid,.rsuve-month-chart').forEach(node => node.replaceWith(...node.children));
            const units = [identity, ...source.children];
            const fragments = [];
            for (const unit of units) {
                if (unit.offsetHeight <= maxHeight) { fragments.push(unit); continue; }
                // Split long tables and claim charts at row/group boundaries, repeating headers.
                const table = unit.querySelector('table');
                const chart = unit.querySelector('.insurance-claims-chart');
                const parts = [];
                if (chart) {
                    const chartOnly = unit.cloneNode(true);
                    chartOnly.querySelectorAll('.rsuve-claim-table').forEach(node => node.remove());
                    const groups = [...chartOnly.querySelectorAll('.claim-chart-group')];
                    if (groups.length) {
                        groups.forEach(group => {
                            const part = chartOnly.cloneNode(true);
                            part.querySelectorAll('.claim-chart-group').forEach(node => node.remove());
                            part.querySelector('.insurance-claims-chart').append(group.cloneNode(true));
                            parts.push(part);
                        });
                    } else parts.push(chartOnly);
                }
                if (table) {
                    const base = unit.cloneNode(true);
                    base.querySelector('.insurance-claims-chart')?.remove();
                    base.querySelectorAll('tbody,tfoot').forEach(body => body.replaceChildren());
                    let part = base.cloneNode(true);
                    stage.append(part);
                    for (const row of table.querySelectorAll('tbody > tr,tfoot > tr')) {
                        const copy = row.cloneNode(true);
                        const target = part.querySelector(row.parentElement.tagName.toLowerCase());
                        target.append(copy);
                        if (part.offsetHeight > maxHeight && part.querySelectorAll('tbody > tr,tfoot > tr').length > 1) {
                            copy.remove();
                            parts.push(part);
                            part = base.cloneNode(true);
                            stage.append(part);
                            part.querySelector(row.parentElement.tagName.toLowerCase()).append(copy);
                        }
                    }
                    parts.push(part);
                }
                if (!parts.length) throw new Error('Report section exceeds page size');
                parts.forEach(part => { stage.append(part); fragments.push(part); });
                unit.remove();
            }
            let pdf;
            let cursor = 10;
            for (let index = 0; index < fragments.length; index++) {
                status.textContent = `Memproses bagian ${index + 1}/${fragments.length}…`;
                const fragment = fragments[index];
                const height = fragment.offsetHeight * usableWidth / width;
                if (height > paperHeight - 30) throw new Error('Report row exceeds page size');
                const renderRoot = document.createElement('div');
                renderRoot.className = 'pdf-export-stage';
                renderRoot.style.width = `${width}px`;
                renderRoot.append(fragment.cloneNode(true));
                const worker = html2pdf().set({
                    margin: 0, image: { type: 'png' },
                    html2canvas: {
                        scale: 2, backgroundColor: '#ffffff', windowWidth: width,
                        scrollX: 0, scrollY: 0, logging: false,
                    },
                    jsPDF: { unit: 'mm', format: 'a4', orientation: landscape ? 'landscape' : 'portrait' },
                }).from(renderRoot);
                const container = await worker.toContainer().get('container');
                // html2pdf centers its container using auto margins. html2canvas then
                // clones it into a different viewport width, moving the content while
                // retaining the original crop coordinates. Anchor both viewports at 0.
                Object.assign(container.style, {
                    width: `${width}px`, left: '0', right: 'auto', margin: '0',
                });
                const canvas = await worker.toCanvas().get('canvas');
                if (!canvas.width || !canvas.height) throw new Error('Empty PDF canvas');
                if (!pdf) pdf = await worker.toPdf().get('pdf');
                else {
                    if (cursor + height > paperHeight - 20) { pdf.addPage(); cursor = 10; }
                }
                // The first worker creates a page; replace its default full-width rendering.
                if (index === 0) { pdf.deletePage(1); pdf.addPage(); }
                pdf.addImage(canvas.toDataURL('image/jpeg', 0.95), 'JPEG', 10, cursor, usableWidth, height);
                cursor += height + 3;
                await new Promise(resolve => setTimeout(resolve, 0));
            }
            const count = pdf.internal.getNumberOfPages();
            for (let page = 1; page <= count; page++) {
                pdf.setPage(page);
                pdf.setFontSize(9);
                pdf.setTextColor(82, 102, 121);
                pdf.text(`${button.dataset.period} | ${page} / ${count}`, paperWidth - 10, paperHeight - 8, { align: 'right' });
            }
            await pdf.save(button.dataset.filename, { returnPromise: true });
            status.textContent = 'PDF berhasil diunduh.';
        } catch (error) {
            console.error('Report PDF export failed', error);
            status.textContent = 'Export PDF gagal. Silakan coba kembali.';
        } finally {
            stage?.remove();
            button.disabled = false;
            button.textContent = 'Export PDF';
        }
    });
})();
