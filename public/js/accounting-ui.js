(function () {
    'use strict';

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function initReveal() {
        const content = document.querySelector('.content');
        if (!content || reduceMotion) return;
        const items = content.querySelectorAll('.page-head, .stats-grid, .card');
        items.forEach((el, index) => {
            el.classList.add('ui-reveal');
            el.style.setProperty('--reveal-delay', `${Math.min(index * 38, 260)}ms`);
        });
        requestAnimationFrame(() => document.body.classList.add('ui-ready'));
    }

    function initTilt() {
        if (reduceMotion || !window.matchMedia('(hover: hover) and (pointer: fine)').matches) return;

        const targets = document.querySelectorAll('.stat-card, .quick-link, .login-card');
        targets.forEach((el) => {
            el.classList.add('ui-tilt');
            el.addEventListener('pointermove', (event) => {
                const rect = el.getBoundingClientRect();
                const x = (event.clientX - rect.left) / rect.width;
                const y = (event.clientY - rect.top) / rect.height;
                const rotateY = (x - 0.5) * 4.5;
                const rotateX = (0.5 - y) * 4.5;
                el.style.setProperty('--tilt-x', `${rotateX.toFixed(2)}deg`);
                el.style.setProperty('--tilt-y', `${rotateY.toFixed(2)}deg`);
                el.style.setProperty('--glow-x', `${(x * 100).toFixed(1)}%`);
                el.style.setProperty('--glow-y', `${(y * 100).toFixed(1)}%`);
            });
            el.addEventListener('pointerleave', () => {
                el.style.setProperty('--tilt-x', '0deg');
                el.style.setProperty('--tilt-y', '0deg');
            });
        });
    }

    function initPageTransition() {
        const overlay = document.getElementById('pageTransition');
        if (!overlay || reduceMotion) return;

        const show = () => {
            overlay.classList.add('show');
            overlay.setAttribute('aria-hidden', 'false');
        };

        document.addEventListener('click', (event) => {
            const link = event.target.closest('a[href]');
            if (!link) return;
            const href = link.getAttribute('href');
            if (!href || href.startsWith('#') || href.startsWith('javascript:')) return;
            if (link.target === '_blank' || link.hasAttribute('download')) return;
            try {
                const url = new URL(link.href, window.location.href);
                if (url.origin !== window.location.origin) return;
                if (url.pathname === window.location.pathname && url.search === window.location.search) return;
                show();
            } catch (_) {}
        });

        document.addEventListener('submit', (event) => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement)) return;
            if (form.dataset.noTransition === 'true') return;
            window.setTimeout(show, 30);
        });

        window.addEventListener('pageshow', () => {
            overlay.classList.remove('show');
            overlay.setAttribute('aria-hidden', 'true');
        });
    }

    function initSidebarAutoClose() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('mobileOverlay');
        if (!sidebar || !overlay) return;
        sidebar.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 820) {
                    sidebar.classList.remove('open');
                    overlay.classList.remove('show');
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        initReveal();
        initTilt();
        initPageTransition();
        initSidebarAutoClose();
    });
})();
