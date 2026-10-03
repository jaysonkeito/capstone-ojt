/*
|--------------------------------------------------------------------------
| OJT Tracker client app
|--------------------------------------------------------------------------
|
| The Blade app is server-rendered with per-page inline scripts; this bundle
| carries the one layer every page shares: offline support. It registers the
| service worker (which keeps recently viewed intern pages — above all My QR
| Code — available without a connection), shows an offline banner while the
| device has no network, and queues journal drafts in IndexedDB so an intern
| can document a duty day offline and have it upload itself when the
| connection comes back.
|
| Nothing here changes server behavior: drafts replay through the regular
| intern.photo.store endpoint with the session cookie and the current CSRF
| token, so the server stays the single source of truth.
|
*/

import { Capacitor } from '@capacitor/core';
import { Network } from '@capacitor/network';

// ---------------------------------------------------------------- connectivity

let isOffline = !navigator.onLine;

const OfflineBanner = {
    el: null,
    show() {
        if (!this.el) {
            this.el = document.createElement('div');
            this.el.id = 'offline-banner';
            this.el.className =
                'fixed top-0 inset-x-0 z-[70] bg-amber-400 text-amber-950 text-[11px] font-medium ' +
                'text-center px-4 py-1.5 shadow';
            this.el.textContent = 'You\u2019re offline — showing your last synced pages. Journal entries you save now will upload automatically.';
            document.body.appendChild(this.el);
        }
    },
    hide() {
        this.el?.remove();
        this.el = null;
    },
};

async function refreshConnectivity() {
    const offline = Capacitor.isNativePlatform()
        ? !(await Network.getStatus()).connected
        : !navigator.onLine;

    if (offline === isOffline) return;
    isOffline = offline;

    if (offline) {
        OfflineBanner.show();
    } else {
        OfflineBanner.hide();
        replayJournalDrafts();
    }
}

// The Capacitor Network plugin gives the WebView real connectivity state;
// the browser events are the plain-web fallback.
Network.addListener('networkStatusChange', (status) => {
    refreshConnectivity();
});
window.addEventListener('online', refreshConnectivity);
window.addEventListener('offline', refreshConnectivity);

// ------------------------------------------------------------- service worker

if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1')) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // Offline support is an enhancement — never block the page on it.
        });
    });
}

// Cached pages outlive sessions. When the account changes (logout, or a fresh
// login screen), drop everything so another user can't reach a previous
// user's cached pages while offline.
function dropOfflineCache() {
    if (window.caches) {
        caches.keys().then((keys) => keys.forEach((key) => caches.delete(key)));
    }
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.getRegistrations().then((registrations) => {
            // The registration itself survives; only its data is cleared.
            registrations.forEach((registration) => registration.update());
        });
    }
}

document.addEventListener('submit', (event) => {
    if (event.target.matches('form[action*="/logout"]')) {
        dropOfflineCache();
    }
}, true);

if (location.pathname === '/login') {
    dropOfflineCache();
}

// ------------------------------------------------------ journal draft queue
//
// A journal is a multipart POST to intern.photo.store (photo + notes + CSRF)
// guarded by the duty day's log id. Offline, the submit is captured, the
// draft lands in IndexedDB, and a chip on that day's card says so. Replays
// happen on reconnect and on every page load while online.

const DB_NAME = 'ojt-offline';
const DRAFT_STORE = 'journalDrafts';

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

function openDraftDb() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, 1);
        request.onupgradeneeded = () => {
            if (!request.result.objectStoreNames.contains(DRAFT_STORE)) {
                request.result.createObjectStore(DRAFT_STORE, { keyPath: 'key' });
            }
        };
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

async function draftStore(mode) {
    const db = await openDraftDb();
    return db.transaction(DRAFT_STORE, mode).objectStore(DRAFT_STORE);
}

async function putDraft(draft) {
    const store = await draftStore('readwrite');
    return new Promise((resolve, reject) => {
        const request = store.put(draft);
        request.onsuccess = resolve;
        request.onerror = () => reject(request.error);
    });
}

async function allDrafts() {
    const store = await draftStore('readonly');
    return new Promise((resolve, reject) => {
        const request = store.getAll();
        request.onsuccess = () => resolve(request.result ?? []);
        request.onerror = () => reject(request.error);
    });
}

async function deleteDraft(key) {
    const store = await draftStore('readwrite');
    return new Promise((resolve) => {
        const request = store.delete(key);
        request.onsuccess = resolve;
        request.onerror = resolve; // a stuck draft we can't delete isn't fatal
    });
}

function toast(message, tone = 'ok') {
    const el = document.createElement('div');
    el.className =
        'fixed bottom-4 inset-x-4 sm:left-auto sm:right-4 sm:w-80 z-[80] rounded-lg px-4 py-3 text-xs ' +
        'font-medium shadow-lg transition-opacity duration-300 ' +
        (tone === 'ok' ? 'bg-gray-900 text-white' : 'bg-red-600 text-white');
    el.textContent = message;
    el.style.opacity = '0';
    document.body.appendChild(el);
    requestAnimationFrame(() => (el.style.opacity = '1'));
    setTimeout(() => {
        el.style.opacity = '0';
        setTimeout(() => el.remove(), 400);
    }, 4200);
}

// A draft's day card may be on this page (the journal view lists every duty
// day) — surface its queued state right where the form used to be.
function markQueuedForm(key) {
    const form = document.getElementById(`journalForm${key}`);
    if (!form || form.dataset.queued === '1') return;
    form.dataset.queued = '1';
    const chip = document.createElement('p');
    chip.className = 'text-[11px] font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-md px-2 py-1';
    chip.textContent = 'Saved on this device — uploads automatically when you\u2019re back online.';
    form.replaceChildren(chip);
    form.classList.remove('hidden');
}

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!form.matches('form[data-offline-queue]') || !isOffline) return;

    event.preventDefault();
    const notes = form.querySelector('[name="notes"]')?.value ?? '';
    if (!notes.trim()) {
        toast('Write your journal message first — it\u2019s required.', 'error');
        return;
    }

    const photoInput = form.querySelector('[name="photo"]');
    const photo = photoInput?.files?.[0] ?? null;
    if (photoInput?.required && !photo) {
        toast('Attach your duty photo first — it\u2019s required for this day.', 'error');
        return;
    }

    const key = form.getAttribute('data-offline-queue');
    putDraft({
        key,
        url: form.getAttribute('action'),
        notes,
        photo,
        photoName: photo?.name ?? null,
        token: form.querySelector('input[name="_token"]')?.value ?? '',
        savedAt: Date.now(),
    })
        .then(() => {
            markQueuedForm(key);
            form.reset();
            toast('No connection — journal saved on this device. It uploads automatically when you\u2019re back online.');
        })
        .catch(() => toast('Couldn\u2019t save the draft on this device. Try again when online.', 'error'));
}, true);

async function replayJournalDrafts() {
    if (isOffline) return;
    let drafts;
    try {
        drafts = await allDrafts();
    } catch {
        return;
    }

    for (const draft of drafts) {
        const body = new FormData();
        body.append('notes', draft.notes);
        body.append('_token', csrfToken() || draft.token);
        if (draft.photo) {
            body.append('photo', new File([draft.photo], draft.photoName ?? 'journal-photo', { type: draft.photo.type }));
        }

        let response;
        try {
            response = await fetch(draft.url, {
                method: 'POST',
                body,
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
        } catch {
            return; // connection dropped mid-replay — try again on the next one
        }

        if (response.ok || response.redirected) {
            await deleteDraft(draft.key);
            toast('Saved journal uploaded to your OJT record.');
        } else if (response.status === 419) {
            return; // session expired while offline — the post-login page load retries
        } else if (response.status === 422) {
            await deleteDraft(draft.key);
            const detail = await response.json().catch(() => ({}));
            const first = detail.errors ? Object.values(detail.errors)[0]?.[0] : null;
            toast(first ?? 'A saved journal was rejected by the server — please re-enter it.', 'error');
        }
        // Any other status (404 for a removed duty day, 500s): keep the draft
        // so nothing the intern wrote is silently destroyed.
    }
}

// Surface queued drafts on the pages where their day card lives, and try to
// sync whatever is pending as soon as the app opens online.
window.addEventListener('load', () => {
    refreshConnectivity();
    allDrafts()
        .then((drafts) => {
            drafts.forEach((draft) => markQueuedForm(draft.key));
            if (drafts.length && !isOffline) replayJournalDrafts();
        })
        .catch(() => {});
});

// ------------------------------------------------------------- push (FCM)
//
// The Android app registers its Firebase token after login; the server then
// pushes review decisions and request updates to this device. The native
// plugin is optional at build time: this module no-ops until
// @capacitor/push-notifications is installed (docs/push-notifications.md
// walks through enabling it), so the same bundle works before and after.
async function registerPushNotifications() {
    if (!Capacitor.isNativePlatform() || !csrfToken()) return;

    let PushNotifications;
    try {
        ({ PushNotifications } = await import(/* @vite-ignore */ '@capacitor/push-notifications'));
    } catch {
        return; // plugin not installed yet — pushes stay disabled
    }

    try {
        const status = await PushNotifications.checkPermissions();
        if (status.receive !== 'granted') {
            const requested = await PushNotifications.requestPermissions();
            if (requested.receive !== 'granted') return;
        }

        await PushNotifications.register();

        PushNotifications.addListener('registration', ({ value }) => {
            fetch('/devices', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ token: value, platform: Capacitor.getPlatform() }),
            }).catch(() => {});
        });

        PushNotifications.addListener('registrationError', (error) => {
            console.warn('Push registration failed', error);
        });

        // A push arriving while the app is open doesn't become a system
        // notification — surface it as the same toast the sync flow uses.
        PushNotifications.addListener('pushNotificationReceived', (notification) => {
            toast(notification.title ? `${notification.title} — ${notification.body}` : (notification.body ?? 'You have a new update'));
        });

        // Tapping a system notification opens the app: follow its deep link.
        PushNotifications.addListener('pushNotificationActionPerformed', ({ notification }) => {
            const url = notification?.data?.url;
            if (typeof url === 'string' && url.startsWith('/')) {
                window.location.href = url;
            }
        });
    } catch (error) {
        console.warn('Push notifications unavailable', error);
    }
}

window.addEventListener('load', registerPushNotifications);
