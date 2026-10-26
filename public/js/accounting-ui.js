(function () {
    'use strict';

    // Navigation renders immediately; avoid pointer-driven layout and paint work.
    document.addEventListener('DOMContentLoaded', () => {
        document.body.classList.add('ui-ready');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('mobileOverlay');
        if (!sidebar || !overlay) return;
        sidebar.addEventListener('click', (event) => {
            if (event.target.closest('a') && window.innerWidth <= 1024) {
                sidebar.classList.remove('open');
                overlay.classList.remove('show');
            }
        });
    });
})();
