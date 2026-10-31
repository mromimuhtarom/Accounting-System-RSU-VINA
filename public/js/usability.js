(() => {
    'use strict';
    const root = document.documentElement;
    const readPreference = key => { try { return localStorage.getItem(key); } catch (_) { return null; } };
    const savePreference = (key, value) => { try { localStorage.setItem(key, value); } catch (_) { /* Controls still work without storage. */ } };
    const sizeButtons = document.querySelectorAll('[data-reading-size-button]');
    const contrastControl = document.getElementById('high-contrast');
    function setSize(size) {
        const value = size === 'large' ? 'large' : 'standard';
        root.dataset.readingSize = value;
        sizeButtons.forEach(button => button.setAttribute('aria-pressed', String(button.dataset.readingSizeButton === value)));
        savePreference('rsuve-reading-size', value);
    }
    setSize(readPreference('rsuve-reading-size'));
    sizeButtons.forEach(button => button.addEventListener('click', () => setSize(button.dataset.readingSizeButton)));
    function setContrast(enabled) {
        root.dataset.highContrast = String(enabled);
        if (contrastControl) contrastControl.checked = enabled;
        savePreference('rsuve-high-contrast', String(enabled));
    }
    setContrast(readPreference('rsuve-high-contrast') === 'true');
    contrastControl?.addEventListener('change', () => setContrast(contrastControl.checked));
    document.querySelector('[data-reset-reading]')?.addEventListener('click',()=>{setSize('standard');setContrast(false);});
    document.querySelectorAll('.menu a.active').forEach(link => link.setAttribute('aria-current', 'page'));

    const pageTitle=document.querySelector('.content .page-title,.content .page-head h1,.content>.card h2')?.textContent.trim()
        || document.querySelector('.menu a.active')?.textContent.trim();
    if(pageTitle && pageTitle!=='Dashboard'){
        const location=document.querySelector('[data-current-location]');
        if(location){location.hidden=false;location.querySelector('[data-location-title]').textContent=pageTitle;}
        const guidance=document.querySelector('.nav-guidance');
        if(guidance) guidance.textContent=pageTitle;
    }

    const menuSearch=document.getElementById('navigation-search');
    const menu=document.getElementById('primary-menu');
    if(menuSearch && menu){
        const links=[...menu.querySelectorAll('a[href]')];
        const groups=[...menu.querySelectorAll('details')];
        const clear=document.querySelector('[data-clear-navigation]');
        const results=document.querySelector('[data-navigation-results]');
        const normalize=value=>value.toLocaleLowerCase('id').replace(/\s+/g,' ').trim();
        const words=link=>normalize(link.textContent+' '+(link.dataset.menuKeywords||'')+' '+(
            link.textContent.includes('Ruang Kerja')?'input transaksi nonkas utang modal saldo awal bantuan':
            link.textContent.includes('Inventaris')?'barang aktiva aset alat':
            link.textContent.includes('Neraca')?'aktiva pasiva saldo pembukuan':''));
        let savedState=null;
        function filterMenu(){
            const query=normalize(menuSearch.value);
            if(query && !savedState) savedState=new Map(groups.map(group=>[group,group.open]));
            links.forEach(link=>{link.hidden=!!query && !query.split(' ').every(word=>words(link).includes(word));});
            groups.forEach(group=>{
                const matches=[...group.querySelectorAll('a')].some(link=>!link.hidden);
                group.hidden=!!query && !matches;
                if(query) group.open=matches;
                else if(savedState) group.open=savedState.get(group);
            });
            menu.querySelectorAll('.menu-label').forEach(label=>{
                const siblings=[];let next=label.nextElementSibling;
                while(next && !next.matches('.menu-label,details')){if(next.matches('a')) siblings.push(next);next=next.nextElementSibling;}
                label.hidden=!!query && !siblings.some(link=>!link.hidden);
            });
            clear.hidden=!query;
            results.hidden=!query;
            const count=links.filter(link=>!link.hidden).length;
            results.textContent=count?`${count} menu ditemukan.`:'Menu tidak ditemukan. Coba kata lain atau hapus pencarian.';
            if(!query) savedState=null;
        }
        menuSearch.addEventListener('input',filterMenu);
        menuSearch.addEventListener('search',filterMenu);
        clear.addEventListener('click',()=>{menuSearch.value='';filterMenu();menuSearch.focus();});
        menuSearch.addEventListener('keydown',event=>{
            if(event.key==='Escape' && menuSearch.value){event.preventDefault();event.stopPropagation();menuSearch.value='';filterMenu();}
        });
    }

    // Give existing form labels and hints explicit associations, including older forms.
    let sequence = 0;
    function ensureId(node, prefix) {
        if (node.id) return node.id;
        let candidate;
        do { sequence++; candidate = `${prefix}-${sequence}`; } while (document.getElementById(candidate));
        node.id = candidate;
        return node.id;
    }
    document.querySelectorAll('.field').forEach(field => {
        const input = field.querySelector('input:not([type=hidden]),select,textarea');
        const label = field.querySelector('label');
        if (!input) return;
        if(label && input.required && !label.textContent.includes('*')){
            const required=document.createElement('span');required.textContent=' *';required.setAttribute('aria-hidden','true');label.append(required);
        }
        if (label && !label.htmlFor) label.htmlFor = ensureId(input, 'form-field');
        const enhancedControl = input.closest('.searchable-select')?.querySelector('.ss-control');
        if (label && enhancedControl) enhancedControl.setAttribute('aria-labelledby', ensureId(label, 'field-label'));
        if(input.required && enhancedControl) enhancedControl.setAttribute('aria-required','true');
        const hints = [...field.querySelectorAll('small,.help-text')].map(hint => ensureId(hint, 'field-help'));
        if (hints.length) input.setAttribute('aria-describedby', [...new Set([...(input.getAttribute('aria-describedby') || '').split(' ').filter(Boolean), ...hints])].join(' '));
        if (hints.length && enhancedControl) enhancedControl.setAttribute('aria-describedby', hints.join(' '));
    });

    document.querySelectorAll('table[data-responsive-cards]').forEach(table => {
        const headers = [...table.querySelectorAll('thead th')].map(header => header.textContent.trim());
        table.querySelectorAll('tbody tr').forEach(row => {
            if (row.cells.length !== headers.length) return;
            [...row.cells].forEach((cell, index) => {
                cell.dataset.label = headers[index];
                const value = document.createElement('div');
                value.className = 'mobile-cell-value';
                value.append(...cell.childNodes);
                cell.append(value);
            });
        });
        table.dataset.cardsReady = 'true';
    });
    const tableRegions = [];
    document.querySelectorAll('.app-shell table').forEach(table => {
        if (table.closest('.table-wrap,.table-responsive')) return;
        const wrapper = document.createElement('div');
        wrapper.className = 'table-wrap';
        table.before(wrapper);
        wrapper.append(table);
    });
    document.querySelectorAll('.app-shell .table-wrap,.app-shell .table-responsive').forEach(region => {
        if (!region.querySelector('table') || region.parentElement.closest('.table-wrap,.table-responsive')) return;
        const hint = document.createElement('p');
        hint.className = 'table-scroll-hint';
        hint.dataset.pdfExclude = '';
        hint.textContent = 'Geser tabel ke kanan atau kiri untuk melihat semua kolom. Dengan keyboard, gunakan tombol panah.';
        hint.hidden = true;
        ensureId(hint, 'table-help');
        region.before(hint);
        region.setAttribute('aria-describedby', [region.getAttribute('aria-describedby'), hint.id].filter(Boolean).join(' '));
        function refresh() {
            const overflowing = region.scrollWidth > region.clientWidth + 2;
            hint.hidden = !overflowing;
            if (overflowing) {
                region.tabIndex = 0;
                if (!region.hasAttribute('role')) region.setAttribute('role', 'region');
                if (!region.hasAttribute('aria-label')) region.setAttribute('aria-label', 'Tabel data yang dapat digeser');
            } else region.removeAttribute('tabindex');
        }
        tableRegions.push(refresh);
        if (typeof ResizeObserver === 'function') new ResizeObserver(refresh).observe(region);
        refresh();
    });
    window.addEventListener('resize', () => tableRegions.forEach(refresh => refresh()));

    const sidebar = document.getElementById('sidebar');
    const shell = document.querySelector('.app-shell');
    const toggle = document.getElementById('sidebarToggleBtn');
    const mobile = window.matchMedia('(max-width:1024px)');
    if (sidebar && shell && toggle) {
        let wasOpen = false;
        const focusable = () => [...sidebar.querySelectorAll('a[href],button:not(:disabled),input:not(:disabled),summary,[tabindex="0"]')].filter(node => {
            if(!node.getClientRects().length) return false;
            for(let parent=node.parentElement;parent && parent!==sidebar;parent=parent.parentElement){
                if(parent.matches('details:not([open])') && node!==parent.querySelector(':scope > summary')) return false;
            }
            return true;
        });
        function syncNavigation() {
            const open = mobile.matches && sidebar.classList.contains('open');
            const hidden = mobile.matches ? !open : shell.classList.contains('sidebar-collapsed');
            sidebar.inert = hidden;
            sidebar.setAttribute('aria-hidden', String(hidden));
            root.classList.toggle('nav-open', open);
            if (open && !wasOpen) focusable()[0]?.focus();
            if (!open && wasOpen) toggle.focus();
            wasOpen = open;
        }
        document.querySelector('[data-close-navigation]')?.addEventListener('click', () => { if (sidebar.classList.contains('open')) window.toggleSidebar(); });
        document.addEventListener('keydown', event => {
            if (event.key !== 'Tab' || !mobile.matches || !sidebar.classList.contains('open')) return;
            const items = focusable();
            if (!items.length) return;
            if (event.shiftKey && document.activeElement === items[0]) { event.preventDefault(); items.at(-1).focus(); }
            else if (!event.shiftKey && document.activeElement === items.at(-1)) { event.preventDefault(); items[0].focus(); }
        });
        new MutationObserver(syncNavigation).observe(sidebar, { attributes: true, attributeFilter: ['class'] });
        new MutationObserver(syncNavigation).observe(shell, { attributes: true, attributeFilter: ['class'] });
        if (mobile.addEventListener) mobile.addEventListener('change', syncNavigation);
        else mobile.addListener(syncNavigation);
        syncNavigation();
    }

    const errors = document.getElementById('form-errors');
    if (errors) { errors.tabIndex = -1; errors.focus(); }
})();
