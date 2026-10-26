(function () {
    'use strict';
    const settings = document.currentScript.dataset;
    const timeout = Number(settings.timeout);
    const storageKey = settings.activityKey;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    let lastActivity = Date.now();
    let lastSent = lastActivity;
    let lastStored = 0;
    let inFlight = false;
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

    async function sendActivity() {
        if (expired || inFlight || lastActivity <= lastSent) return;
        inFlight = true;
        const sentAt = lastActivity;
        try {
            const response = await fetch(settings.activityUrl, { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } });
            if ([401, 419].includes(response.status) || response.redirected) {
                await expire();
            } else if (response.ok) {
                lastSent = sentAt;
            }
        } catch (_) {
            // Retry only while there has actually been activity; never keep an idle session alive.
        } finally { inFlight = false; }
    }

    function check() {
        readActivity();
        if (Date.now() - lastActivity >= timeout) { expire(); return false; }
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
    document.addEventListener('submit', (event) => { if (!check()) event.preventDefault(); }, true);
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
