(() => {
    'use strict';

    const page = document.querySelector('.emergency');
    const start = document.getElementById('emergency-start');
    const initiate = document.getElementById('emergency-initiate');
    const status = document.getElementById('emergency-status');
    const review = document.getElementById('emergency-review');
    const reviewTitle = document.getElementById('emergency-review-title');
    const gpsField = document.getElementById('emergency-gps');
    const addressField = document.getElementById('emergency-address');
    const instructions = document.getElementById('emergency-instructions');
    const advance = document.getElementById('emergency-continue');
    const warning = document.getElementById('emergency-warning');
    const warningTitle = document.getElementById('emergency-warning-title');
    const warningLocation = document.getElementById('emergency-warning-location');
    const confirm = document.getElementById('emergency-confirm');
    const result = document.getElementById('emergency-result');
    const resultTitle = document.getElementById('emergency-result-title');
    const unlock = document.getElementById('emergency-unlock');
    const enable = document.getElementById('emergency-enable');
    const protectedAddressKey = 'chn.emergency.protected-address.v1';
    const authenticated = page.dataset.authenticated === 'true';
    const messages = JSON.parse(page.dataset.messages || '{}');
    const msg = (key, params = {}) => (messages[key] || key).replace(/\{(\w+)\}/g, (match, name) => (name in params ? String(params[name]) : match));

    let state = 'idle';
    let requestNumber = 0;
    let gps = null;
    let address = null;
    let draft = null;

    async function readSavedAddress() {
        const { addressScope, addressDigest } = page.dataset;
        if (!authenticated || !addressScope || !addressDigest || !window.crypto?.subtle) {
            return null;
        }

        try {
            const address = localStorage.getItem(`chn.emergency.address.${addressScope}`);
            if (!address || !address.trim() || address.length > 255) return null;

            const digest = await window.crypto.subtle.digest('SHA-256', new TextEncoder().encode(address));
            const hex = Array.from(new Uint8Array(digest), (byte) => byte.toString(16).padStart(2, '0')).join('');
            return hex === addressDigest ? address : null;
        } catch {
            return null;
        }
    }

    function readProtectedAddress() {
        if (!window.crypto?.subtle) return null;
        try {
            const raw = localStorage.getItem(protectedAddressKey);
            if (!raw || raw.length > 2048) return null;
            const record = JSON.parse(raw);
            const salt = Uint8Array.from(atob(record.salt), (value) => value.charCodeAt(0));
            const iv = Uint8Array.from(atob(record.iv), (value) => value.charCodeAt(0));
            const ciphertext = Uint8Array.from(atob(record.ciphertext), (value) => value.charCodeAt(0));
            return salt.length === 16 && iv.length === 12 && ciphertext.length > 16
                ? { salt, iv, ciphertext } : null;
        } catch {
            return null;
        }
    }

    async function deriveAddressKey(passphrase, salt) {
        const subtle = window.crypto.subtle;
        const material = await subtle.importKey('raw', new TextEncoder().encode(passphrase), 'PBKDF2', false, ['deriveKey']);
        return subtle.deriveKey(
            { name: 'PBKDF2', salt, iterations: 600000, hash: 'SHA-256' },
            material, { name: 'AES-GCM', length: 256 }, false, ['encrypt', 'decrypt']
        );
    }

    function reset(message = '') {
        requestNumber++;
        state = 'idle';
        gps = null;
        address = null;
        draft = null;
        gpsField.textContent = '';
        addressField.textContent = '';
        warningLocation.textContent = '';
        for (const panel of [unlock, enable]) {
            panel.open = false;
            panel.hidden = true;
            panel.querySelector('form').reset();
        }
        review.hidden = true;
        warning.hidden = true;
        result.hidden = true;
        start.hidden = false;
        confirm.disabled = false;
        status.textContent = message;
    }

    function updateReview() {
        const pending = state === 'locating';
        addressField.textContent = address ?? msg('noAddress');
        if (!address && !unlock.hidden) addressField.textContent = msg('addressLocked');
        advance.disabled = pending || (!gps && !address);
        advance.textContent = !pending && !gps && address
            ? msg('useAddress')
            : msg('continue');
        instructions.textContent = pending
            ? msg('waitLocating')
            : gps ? msg('usesGps')
                : address ? msg('usesAddress')
                    : msg('needLocation');
    }

    initiate.addEventListener('click', async () => {
        if (state !== 'idle') return;
        state = 'locating';
        const currentRequest = ++requestNumber;
        start.hidden = true;
        review.hidden = false;
        gpsField.textContent = msg('finding');
        status.textContent = '';
        unlock.hidden = authenticated || !readProtectedAddress();
        updateReview();
        reviewTitle.focus();

        const gpsResult = new Promise((resolve) => {
            if (!navigator.geolocation) {
                resolve({ code: 2 });
                return;
            }
            navigator.geolocation.getCurrentPosition(
                ({ coords }) => resolve(coords), ({ code }) => resolve({ code }),
                { maximumAge: 0, timeout: 10000 }
            );
        });

        const savedAddress = await readSavedAddress();
        if (currentRequest !== requestNumber) return;
        if (savedAddress) {
            address = savedAddress;
            enable.hidden = false;
            updateReview();
        }

        const { latitude, longitude, code } = await gpsResult;
        if (currentRequest !== requestNumber) return;
        const messages = {
            1: msg('denied'),
            2: msg('unavailable'),
            3: msg('timeout'),
        };
        if (Number.isFinite(latitude) && Math.abs(latitude) <= 90
            && Number.isFinite(longitude) && Math.abs(longitude) <= 180) {
            gps = { source: 'gps', latitude, longitude };
        }
        gpsField.textContent = gps
            ? msg('coords', { lat: latitude.toFixed(6), lng: longitude.toFixed(6) })
            : messages[code] ?? messages[2];
        status.textContent = gps ? msg('gpsReady') : gpsField.textContent;
        state = 'review';
        updateReview();
    });

    for (const panel of [unlock, enable]) {
        const form = panel.querySelector('form');
        const input = form.querySelector('input');
        const button = form.querySelector('button');
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (panel.hidden || button.disabled) return;
            const currentRequest = requestNumber;
            const passphrase = input.value;
            const isUnlock = panel === unlock;
            const copy = address;
            form.reset();
            button.disabled = true;
            try {
                if (isUnlock) {
                    const record = readProtectedAddress();
                    if (!record) throw new Error('Missing protected address');
                    const key = await deriveAddressKey(passphrase, record.salt);
                    const plaintext = await window.crypto.subtle.decrypt(
                        { name: 'AES-GCM', iv: record.iv }, key, record.ciphertext
                    );
                    if (currentRequest !== requestNumber || review.hidden) return;
                    const unlocked = new TextDecoder('utf-8', { fatal: true }).decode(plaintext);
                    if (!unlocked.trim() || unlocked.length > 255) throw new Error('Invalid address');
                    address = unlocked;
                    unlock.open = false;
                    unlock.hidden = true;
                    updateReview();
                    reviewTitle.focus();
                    status.textContent = msg('unlocked');
                } else {
                    if (!authenticated || !copy || passphrase.length < 12) return;
                    const salt = window.crypto.getRandomValues(new Uint8Array(16));
                    const iv = window.crypto.getRandomValues(new Uint8Array(12));
                    const key = await deriveAddressKey(passphrase, salt);
                    const ciphertext = await window.crypto.subtle.encrypt(
                        { name: 'AES-GCM', iv }, key, new TextEncoder().encode(copy)
                    );
                    if (currentRequest !== requestNumber || review.hidden) return;
                    const encode = (value) => btoa(String.fromCharCode(...new Uint8Array(value)));
                    localStorage.setItem(protectedAddressKey, JSON.stringify({
                        salt: encode(salt), iv: encode(iv), ciphertext: encode(ciphertext),
                    }));
                    status.textContent = msg('saved');
                }
            } catch {
                if (currentRequest === requestNumber && !review.hidden) {
                    status.textContent = isUnlock
                        ? msg('unlockFailed')
                        : msg('saveFailed');
                }
            } finally {
                button.disabled = false;
            }
        });
    }

    function cancel() {
        reset(msg('cancelled'));
        initiate.focus();
    }

    document.getElementById('emergency-cancel').addEventListener('click', cancel);
    document.getElementById('emergency-warning-cancel').addEventListener('click', cancel);

    advance.addEventListener('click', () => {
        if (state !== 'review' || (!gps && !address)) return;
        draft = {
            authenticated,
            location: gps ?? { source: 'saved_address', address },
            confirmed: false,
        };
        state = 'warning';
        warningLocation.textContent = gps
            ? msg('locGps')
            : msg('locAddress');
        for (const panel of [unlock, enable]) {
            panel.open = false;
            panel.querySelector('form').reset();
        }
        status.textContent = '';
        review.hidden = true;
        warning.hidden = false;
        warningTitle.focus();
    });

    confirm.addEventListener('click', () => {
        if (state !== 'warning' || !draft || draft.confirmed) return;
        state = 'confirmed';
        draft.confirmed = true;
        // The future backend must derive identity from its session and revalidate location.
        // Phase 1 does not transmit the draft.
        draft = null;
        gps = null;
        address = null;
        gpsField.textContent = '';
        addressField.textContent = '';
        confirm.disabled = true;
        warning.hidden = true;
        result.hidden = false;
        resultTitle.focus();
    });

    document.getElementById('emergency-restart').addEventListener('click', () => {
        reset();
        initiate.focus();
    });

    window.addEventListener('pagehide', () => reset());
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) window.location.reload();
    });

    reset();
})();
