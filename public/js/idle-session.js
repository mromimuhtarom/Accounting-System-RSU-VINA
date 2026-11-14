(function () {
    'use strict';
    const settings = document.currentScript.dataset;
    const timeout = Number(settings.timeout);
    const storageKey = settings.activityKey;
    const keepSignedIn = settings.keepSignedIn === '1';
    let csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    let lastActivity = Date.now();
    let lastSent = lastActivity;
    let lastStored = 0;
    let inFlight = false;
    let activityRequest = null;
    let expired = false;

    function readActivity() {
        try {
            const stored = Number(localStorage.getItem(storageKey));
            if (stored > lastActivity && stored <= Date.now()) lastActivity = stored;
        } catch (_) {}
    }

    async function expire() {
        if (expired) return;
        expired = true;
        try { localStorage.setItem(storageKey + '-expired', String(Date.now())); } catch (_) {}
        // Redirect even if the session cookie has already expired or the device is offline.
        const redirect = window.setTimeout(() => window.location.replace(settings.loginUrl), 1500);
        try {
            await fetch(settings.logoutUrl, { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ reason: 'idle_timeout' }) });
        } catch (_) {}
        window.clearTimeout(redirect);
        window.location.replace(settings.loginUrl);
    }

    function sendActivity() {
        if (inFlight) return activityRequest;
        if (expired || (!keepSignedIn && lastActivity <= lastSent)) return Promise.resolve(false);
        activityRequest = performActivity();
        return activityRequest;
    }

    async function performActivity() {
        inFlight = true;
        const sentAt = keepSignedIn ? Date.now() : lastActivity;
        try {
            const response = await fetch(settings.activityUrl, { method: keepSignedIn ? 'GET' : 'POST', credentials: 'same-origin', cache:'no-store', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } });
            if ([401, 419].includes(response.status) || response.redirected) {
                await expire();
            } else if (response.ok) {
                if (keepSignedIn) {
                    const data = await response.json();
                    if (data.csrf_token) {
                        csrf = data.csrf_token;
                        document.querySelector('meta[name="csrf-token"]')?.setAttribute('content', csrf);
                        document.querySelectorAll('input[name="_token"]').forEach(input => { input.value = csrf; });
                    }
                }
                lastSent = sentAt;
                return true;
            }
        } catch (_) {
            // Retry only while there has actually been activity; never keep an idle session alive.
        } finally { inFlight = false; }
        return false;
    }

    function check() {
        readActivity();
        if (!keepSignedIn && Date.now() - lastActivity >= timeout) { expire(); return false; }
        return true;
    }

    function activity(event) {
        if (!event.isTrusted || !check()) return;
        const now = Date.now();
        lastActivity = now;
        if (now - lastStored >= 1000) {
            try { localStorage.setItem(storageKey, String(now)); } catch (_) {}
            lastStored = now;
        }
        if (now - lastSent >= 60000) sendActivity();
    }

    // Tabs in the same browser share inactivity; other browsers/devices use a different key.
    readActivity();
    try { localStorage.setItem(storageKey, String(lastActivity)); } catch (_) {}
    ['pointerdown', 'pointermove', 'keydown', 'scroll', 'touchstart', 'input'].forEach((name) => {
        document.addEventListener(name, activity, { passive: true, capture: true });
    });
    const replaying = new WeakSet();
    document.addEventListener('submit', (event) => {
        if (!check()) { event.preventDefault(); return; }
        if (!keepSignedIn || replaying.has(event.target) || Date.now() - lastSent < 60000) return;
        // A sleeping tab may outlive the normal Laravel session. Restore it and
        // renew form CSRF tokens before sending the user's original submission.
        event.preventDefault();
        event.stopImmediatePropagation();
        const form = event.target;
        const submitter = event.submitter;
        sendActivity().then(ok => {
            if (!ok || expired) {
                if (!expired) window.alert('Sesi belum berhasil diperbarui. Periksa koneksi, lalu klik simpan kembali.');
                return;
            }
            replaying.add(form);
            try { form.requestSubmit(submitter); } finally { replaying.delete(form); }
        });
    }, true);
    document.addEventListener('visibilitychange', () => { if (!document.hidden && check()) sendActivity(); });
    window.addEventListener('pageshow', check);
    window.addEventListener('storage', (event) => {
        if (event.key === storageKey + '-expired') expire();
        else if (event.key === storageKey) readActivity();
    });
    window.setInterval(() => {
        if (check() && Date.now() - lastSent >= 60000) sendActivity();
    }, 15000);
})();
