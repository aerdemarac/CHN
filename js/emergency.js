(() => {
    'use strict';

    const page = document.querySelector('.emergency');
    const start = document.getElementById('emergency-start');
    const initiate = document.getElementById('emergency-initiate');
    const status = document.getElementById('emergency-status');
    const review = document.getElementById('emergency-review');
    const reviewTitle = document.getElementById('emergency-review-title');
    const source = document.getElementById('emergency-source');
    const location = document.getElementById('emergency-location');
    const instructions = document.getElementById('emergency-instructions');
    const confirm = document.getElementById('emergency-confirm');
    const result = document.getElementById('emergency-result');
    const resultTitle = document.getElementById('emergency-result-title');
    let state = 'idle';
    let requestNumber = 0;
    let draft = null;

    async function readSavedAddress() {
        const { authenticated, addressScope, addressDigest } = page.dataset;
        if (authenticated !== 'true' || !addressScope || !addressDigest || !window.crypto?.subtle) {
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

    function reset(message = '') {
        requestNumber++;
        state = 'idle';
        draft = null;
        location.textContent = '';
        review.hidden = true;
        result.hidden = true;
        start.hidden = false;
        initiate.removeAttribute('aria-disabled');
        initiate.textContent = 'Start request';
        confirm.disabled = false;
        status.textContent = message;
    }

    function showReview(locationData) {
        draft = {
            authenticated: page.dataset.authenticated === 'true',
            location: locationData,
            confirmed: false,
        };
        state = 'review';
        const isGps = locationData.source === 'gps';
        source.textContent = isGps ? 'Current GPS position' : 'Saved account address';
        location.textContent = isGps
            ? `Latitude ${locationData.latitude.toFixed(6)}, longitude ${locationData.longitude.toFixed(6)}`
            : locationData.address;
        instructions.textContent = isGps
            ? 'Confirm that you want to use this position for the request preview.'
            : 'Use this saved address only if it describes where help is needed. It is separate from your current GPS position.';
        status.textContent = isGps ? '' : status.textContent;
        start.hidden = true;
        review.hidden = false;
        reviewTitle.focus();
    }

    initiate.addEventListener('click', () => {
        if (state !== 'idle') return;
        state = 'locating';
        const currentRequest = ++requestNumber;
        initiate.setAttribute('aria-disabled', 'true');
        initiate.textContent = 'Finding location…';
        status.textContent = 'Waiting for your browser to provide your location.';

        async function handleUnavailable(code) {
            if (currentRequest !== requestNumber || state !== 'locating') return;
            const address = await readSavedAddress();
            if (currentRequest !== requestNumber || state !== 'locating') return;
            const messages = {
                1: 'Location permission was denied.',
                2: 'Your current location is unavailable.',
                3: 'The location request timed out.',
            };
            const reason = messages[code] ?? messages[2];
            if (address) {
                status.textContent = reason;
                showReview({ source: 'saved_address', address });
            } else {
                reset(`${reason} No eligible saved address is available. Try again or call 112 for immediate help.`);
            }
        }

        if (!navigator.geolocation) {
            void handleUnavailable(2);
            return;
        }

        navigator.geolocation.getCurrentPosition(({ coords }) => {
            if (currentRequest !== requestNumber || state !== 'locating') return;
            const { latitude, longitude } = coords;
            if (!Number.isFinite(latitude) || Math.abs(latitude) > 90
                || !Number.isFinite(longitude) || Math.abs(longitude) > 180) {
                void handleUnavailable(2);
                return;
            }
            showReview({ source: 'gps', latitude, longitude });
        }, (error) => void handleUnavailable(error.code), { maximumAge: 0, timeout: 10000 });
    });

    document.getElementById('emergency-cancel').addEventListener('click', () => {
        reset('Request preview cancelled.');
        initiate.focus();
    });

    confirm.addEventListener('click', () => {
        if (state !== 'review' || !draft || draft.confirmed) return;
        state = 'confirmed';
        draft.confirmed = true;
        // The future backend must derive identity from its session and revalidate location.
        // Phase 1 does not transmit the draft.
        draft = null;
        confirm.disabled = true;
        location.textContent = '';
        status.textContent = '';
        review.hidden = true;
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
