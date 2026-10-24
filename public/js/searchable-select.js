(function () {
    'use strict';

    const instances = new WeakMap();
    let active = null;

    const normalize = (value) => String(value ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLocaleLowerCase('id-ID')
        .trim();

    function closeActive(except) {
        if (active && active !== except) active.close();
    }

    class SearchableSelect {
        constructor(select) {
            this.select = select;
            this.highlightIndex = -1;
            this.visibleOptions = [];
            this.build();
            this.bind();
            this.refresh();
            instances.set(select, this);
        }

        build() {
            const select = this.select;

            const wrapper = document.createElement('div');
            wrapper.className = 'searchable-select';

            const control = document.createElement('button');
            control.type = 'button';
            control.className = 'ss-control';
            control.setAttribute('aria-haspopup', 'listbox');
            control.setAttribute('aria-expanded', 'false');

            const value = document.createElement('span');
            value.className = 'ss-value';

            const chevron = document.createElement('span');
            chevron.className = 'ss-chevron';
            chevron.textContent = '▼';

            control.append(value, chevron);

            const panel = document.createElement('div');
            panel.className = 'ss-panel';

            const searchWrap = document.createElement('div');
            searchWrap.className = 'ss-search-wrap';

            const search = document.createElement('input');
            search.type = 'search';
            search.className = 'ss-search';
            search.placeholder = select.dataset.searchPlaceholder || 'Cari pilihan...';
            search.autocomplete = 'off';
            search.setAttribute('aria-label', 'Cari pilihan');

            const searchIcon = document.createElement('span');
            searchIcon.className = 'ss-search-icon';
            searchIcon.textContent = '⌕';

            searchWrap.append(search, searchIcon);

            const options = document.createElement('div');
            options.className = 'ss-options';
            options.setAttribute('role', 'listbox');

            panel.append(searchWrap, options);

            select.parentNode.insertBefore(wrapper, select);
            wrapper.append(select, control, panel);
            select.classList.add('ss-native');

            this.wrapper = wrapper;
            this.control = control;
            this.valueEl = value;
            this.panel = panel;
            this.search = search;
            this.optionsEl = options;
        }

        bind() {
            this.control.addEventListener('click', () => {
                if (this.select.disabled) return;
                this.wrapper.classList.contains('open') ? this.close() : this.open();
            });

            this.search.addEventListener('input', () => {
                this.highlightIndex = -1;
                this.renderOptions(this.search.value);
            });

            this.search.addEventListener('keydown', (event) => this.onKeydown(event));
            this.control.addEventListener('keydown', (event) => {
                if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(event.key)) {
                    event.preventDefault();
                    this.open();
                }
            });

            this.select.addEventListener('change', () => this.syncValue());
            this.select.addEventListener('invalid', () => {
                this.wrapper.classList.add('ss-invalid');
                this.control.focus();
            });
            this.select.addEventListener('input', () => this.wrapper.classList.remove('ss-invalid'));

            this.observer = new MutationObserver(() => this.refresh());
            this.observer.observe(this.select, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['hidden', 'disabled', 'selected', 'label']
            });
        }

        open() {
            closeActive(this);
            active = this;
            this.wrapper.classList.add('open');
            this.control.setAttribute('aria-expanded', 'true');
            this.search.value = '';
            this.renderOptions('');
            window.setTimeout(() => this.search.focus(), 0);
        }

        close() {
            this.wrapper.classList.remove('open');
            this.control.setAttribute('aria-expanded', 'false');
            this.highlightIndex = -1;
            if (active === this) active = null;
        }

        refresh() {
            this.control.disabled = this.select.disabled;
            this.syncValue();
            if (this.wrapper.classList.contains('open')) this.renderOptions(this.search.value);
        }

        syncValue() {
            const selected = this.select.options[this.select.selectedIndex];
            const text = selected ? selected.text.trim() : '';
            const isPlaceholder = !selected || selected.value === '';
            this.valueEl.textContent = text || 'Pilih';
            this.valueEl.classList.toggle('ss-placeholder', isPlaceholder);
            if (this.select.value) this.wrapper.classList.remove('ss-invalid');
        }

        getOptions(query) {
            const needle = normalize(query);
            return Array.from(this.select.options).filter((option) => {
                if (option.hidden) return false;
                if (!needle) return true;
                return normalize(option.text).includes(needle);
            });
        }

        renderOptions(query) {
            this.optionsEl.innerHTML = '';
            this.visibleOptions = this.getOptions(query);

            if (!this.visibleOptions.length) {
                const empty = document.createElement('div');
                empty.className = 'ss-empty';
                empty.textContent = 'Pilihan tidak ditemukan';
                this.optionsEl.appendChild(empty);
                return;
            }

            this.visibleOptions.forEach((option, index) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'ss-option';
                button.setAttribute('role', 'option');
                button.textContent = option.text.trim();
                button.dataset.index = String(index);
                button.classList.toggle('selected', option.selected);
                button.setAttribute('aria-selected', option.selected ? 'true' : 'false');

                if (option.disabled) {
                    button.disabled = true;
                    button.classList.add('disabled');
                } else {
                    button.addEventListener('click', () => this.choose(option));
                }

                this.optionsEl.appendChild(button);
            });

            this.applyHighlight();
        }

        choose(option) {
            if (option.disabled) return;
            this.select.value = option.value;
            this.select.dispatchEvent(new Event('input', { bubbles: true }));
            this.select.dispatchEvent(new Event('change', { bubbles: true }));
            this.syncValue();
            this.close();
            this.control.focus();
        }

        onKeydown(event) {
            const enabled = this.visibleOptions
                .map((option, index) => ({ option, index }))
                .filter((item) => !item.option.disabled);

            if (event.key === 'Escape') {
                event.preventDefault();
                this.close();
                this.control.focus();
                return;
            }

            if (!enabled.length) return;

            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                const direction = event.key === 'ArrowDown' ? 1 : -1;
                const currentPosition = enabled.findIndex(item => item.index === this.highlightIndex);
                let nextPosition = currentPosition + direction;
                if (currentPosition === -1) nextPosition = direction === 1 ? 0 : enabled.length - 1;
                if (nextPosition < 0) nextPosition = enabled.length - 1;
                if (nextPosition >= enabled.length) nextPosition = 0;
                this.highlightIndex = enabled[nextPosition].index;
                this.applyHighlight(true);
                return;
            }

            if (event.key === 'Enter' && this.highlightIndex >= 0) {
                event.preventDefault();
                const option = this.visibleOptions[this.highlightIndex];
                if (option && !option.disabled) this.choose(option);
            }
        }

        applyHighlight(scroll) {
            const buttons = this.optionsEl.querySelectorAll('.ss-option');
            buttons.forEach((button, index) => button.classList.toggle('highlighted', index === this.highlightIndex));
            if (scroll && this.highlightIndex >= 0 && buttons[this.highlightIndex]) {
                buttons[this.highlightIndex].scrollIntoView({ block: 'nearest' });
            }
        }
    }

    function enhance(root = document) {
        root.querySelectorAll('select:not(.ss-native)').forEach((select) => {
            if (!instances.has(select)) new SearchableSelect(select);
        });
    }

    document.addEventListener('click', (event) => {
        if (active && !active.wrapper.contains(event.target)) active.close();
    });

    document.addEventListener('DOMContentLoaded', () => {
        enhance();

        const pageObserver = new MutationObserver((mutations) => {
            for (const mutation of mutations) {
                for (const node of mutation.addedNodes) {
                    if (!(node instanceof Element)) continue;
                    if (node.matches('select:not(.ss-native)')) enhance(node.parentElement || document);
                    else if (node.querySelector?.('select:not(.ss-native)')) enhance(node);
                }
            }
        });
        pageObserver.observe(document.body, { childList: true, subtree: true });
    });
})();
