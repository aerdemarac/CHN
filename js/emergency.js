(() => {
    'use strict';

    const page = document.querySelector('.emergency');
    if (!page) return;

    const start = document.getElementById('emergency-start');
    const initiate = document.getElementById('emergency-initiate');
    const status = document.getElementById('emergency-status');
    const review = document.getElementById('emergency-review');
    const reviewTitle = document.getElementById('emergency-review-title');
    const gpsField = document.getElementById('emergency-gps');
    const addressField = document.getElementById('emergency-address');
    const matchGroup = document.getElementById('emergency-match-group');
    const matchYes = document.getElementById('emergency-match-yes');
    const matchNo = document.getElementById('emergency-match-no');
    const responsibilityGroup = document.getElementById('emergency-responsibility-group');
    const responsibilityCheckbox = document.getElementById('emergency-responsibility');
    const manualGroup = document.getElementById('emergency-manual-group');
    const manualLabel = document.getElementById('emergency-manual-label');
    const manualHint = document.getElementById('emergency-manual-hint');
    const manualInput = document.getElementById('emergency-manual-address');
    const manualError = document.getElementById('emergency-manual-error');
    const typeSelect = document.getElementById('emergency-type');
    const anonNameInput = document.getElementById('emergency-anon-name');
    const anonNameError = document.getElementById('emergency-anon-name-error');
    const anonPhoneInput = document.getElementById('emergency-anon-phone');
    const anonPhoneError = document.getElementById('emergency-anon-phone-error');
    const anonDobInput = document.getElementById('emergency-anon-dob');
    const anonDobError = document.getElementById('emergency-anon-dob-error');
    const clearAccountBtn = document.getElementById('emergency-clear-account');
    const instructions = document.getElementById('emergency-instructions');
    const advance = document.getElementById('emergency-continue');
    const warning = document.getElementById('emergency-warning');
    const warningTitle = document.getElementById('emergency-warning-title');
    const warningLocation = document.getElementById('emergency-warning-location');
    const warningType = document.getElementById('emergency-warning-type');
    const submitError = document.getElementById('emergency-submit-error');
    const confirmBtn = document.getElementById('emergency-confirm');
    const result = document.getElementById('emergency-result');
    const resultTitle = document.getElementById('emergency-result-title');
    const resultRef = document.getElementById('emergency-result-ref');

    const authenticated = page.dataset.authenticated === 'true';
    const initialAccountAddress = (page.dataset.accountAddress || '').trim();
    const submitUrl = page.dataset.submitUrl || '../backend/create_emergency.php';
    const clearAccountUrl = page.dataset.clearAccountUrl || '../backend/clear_last_account.php';
    const messages = JSON.parse(page.dataset.messages || '{}');
    const msg = (key, params = {}) => (messages[key] || key).replace(/\{(\w+)\}/g, (match, name) => (name in params ? String(params[name]) : match));

    const namePattern = /^[A-Za-zçÇğĞıİöÖşŞüÜ][A-Za-zçÇğĞıİöÖşŞüÜ '.-]*$/;
    const addressPattern = /^[A-Za-zçÇğĞıİöÖşŞüÜ0-9\s,.\-\/:#'()]+$/;

    let state = 'idle';
    let requestNumber = 0;
    let gps = null;
    let accountAddress = initialAccountAddress;
    let useAccountAddress = initialAccountAddress !== '';
    let draft = null;

    function getMatchSelection() {
        if (matchYes.checked) return true;
        if (matchNo.checked) return false;
        return null;
    }

    function setFieldError(input, errorEl, text) {
        if (!input || !errorEl) return true;
        errorEl.textContent = text;
        input.setAttribute('aria-invalid', String(Boolean(text)));
        return !text;
    }

    function validateAnonName() {
        if (!anonNameInput) return true;
        const val = anonNameInput.value.trim();
        if (val === '') return setFieldError(anonNameInput, anonNameError, '');
        if (val.length > 100 || !namePattern.test(val)) {
            return setFieldError(anonNameInput, anonNameError, msg('errName'));
        }
        return setFieldError(anonNameInput, anonNameError, '');
    }

    function validateAnonPhone() {
        if (!anonPhoneInput) return true;
        const val = anonPhoneInput.value.trim();
        if (val === '') return setFieldError(anonPhoneInput, anonPhoneError, '');
        if (!/^\+?[0-9\s()-]+$/.test(val)) {
            return setFieldError(anonPhoneInput, anonPhoneError, msg('errPhone'));
        }
        const hasPlus = val.startsWith('+');
        const digits = val.replace(/\D/g, '');
        const normalizedLen = (hasPlus ? 1 : 0) + digits.length;
        if (digits.length < 7 || normalizedLen > 16) {
            return setFieldError(anonPhoneInput, anonPhoneError, msg('errPhone'));
        }
        return setFieldError(anonPhoneInput, anonPhoneError, '');
    }

    function validateAnonDob() {
        if (!anonDobInput) return true;
        const val = anonDobInput.value.trim();
        if (anonDobInput.validity.badInput) {
            return setFieldError(anonDobInput, anonDobError, msg('errDob'));
        }
        if (val === '') return setFieldError(anonDobInput, anonDobError, '');
        const today = new Date();
        const localDate = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
        if (Number.isNaN(Date.parse(val)) || val < '1900-01-01' || val > localDate) {
            return setFieldError(anonDobInput, anonDobError, msg('errDob'));
        }
        return setFieldError(anonDobInput, anonDobError, '');
    }

    function isManualRequired() {
        return state === 'review' && !gps && !useAccountAddress;
    }

    function validateManualAddress(showRequiredError = false) {
        if (!manualInput || manualGroup.hidden) {
            if (manualError) manualError.textContent = '';
            if (manualInput) manualInput.setAttribute('aria-invalid', 'false');
            return true;
        }
        const val = manualInput.value.trim();
        if (val === '') {
            if (isManualRequired() && showRequiredError) {
                return setFieldError(manualInput, manualError, msg('errAddressRequired'));
            }
            return setFieldError(manualInput, manualError, '');
        }
        if (val.length > 255 || !addressPattern.test(val)) {
            return setFieldError(manualInput, manualError, msg('errAddress'));
        }
        return setFieldError(manualInput, manualError, '');
    }

    function reset(message = '') {
        requestNumber++;
        state = 'idle';
        gps = null;
        draft = null;
        gpsField.textContent = '';
        addressField.textContent = '';
        warningLocation.textContent = '';
        warningType.textContent = '';
        if (resultRef) resultRef.textContent = '';
        if (submitError) {
            submitError.textContent = '';
            submitError.hidden = true;
        }

        matchYes.checked = false;
        matchNo.checked = false;
        matchGroup.hidden = true;

        responsibilityCheckbox.checked = false;
        responsibilityGroup.hidden = true;

        manualInput.value = '';
        manualGroup.hidden = true;
        setFieldError(manualInput, manualError, '');

        if (typeSelect) typeSelect.value = '';
        setFieldError(anonNameInput, anonNameError, '');
        setFieldError(anonPhoneInput, anonPhoneError, '');
        setFieldError(anonDobInput, anonDobError, '');

        review.hidden = true;
        warning.hidden = true;
        result.hidden = true;
        start.hidden = false;
        confirmBtn.disabled = false;
        status.textContent = message;
    }

    function updateReview() {
        const pending = state === 'locating';
        const hasAddress = useAccountAddress && accountAddress !== '';
        addressField.textContent = hasAddress ? accountAddress : msg('noAddress');

        if (pending) {
            matchGroup.hidden = true;
            responsibilityGroup.hidden = true;
            manualGroup.hidden = true;
            advance.disabled = true;
            advance.textContent = msg('continue');
            instructions.textContent = msg('waitLocating');
            return;
        }

        const hasGps = Boolean(gps);
        const matchVal = getMatchSelection();

        // Scenario 1: Account address + GPS active
        if (hasAddress && hasGps) {
            matchGroup.hidden = false;
            responsibilityGroup.hidden = true;
            if (matchVal === false) {
                manualGroup.hidden = false;
                manualLabel.textContent = msg('manualLabelOptional');
                manualHint.textContent = msg('manualHintOptional');
                manualInput.required = false;
            } else {
                manualGroup.hidden = true;
                manualInput.required = false;
            }
            const canContinue = matchVal !== null && validateManualAddress(false);
            advance.disabled = !canContinue;
            advance.textContent = msg('continue');
            instructions.textContent = matchVal === true
                ? msg('usesBoth')
                : matchVal === false
                    ? msg('usesGpsManual')
                    : msg('errMatchRequired');
            return;
        }

        // Scenario 2: Account address + GPS inactive
        if (hasAddress && !hasGps) {
            matchGroup.hidden = true;
            responsibilityGroup.hidden = false;
            manualGroup.hidden = true;
            manualInput.required = false;
            advance.disabled = !responsibilityCheckbox.checked;
            advance.textContent = msg('useAddress');
            instructions.textContent = responsibilityCheckbox.checked
                ? msg('usesAddress')
                : msg('errResponsibilityRequired');
            return;
        }

        // Scenario 3: No account address + GPS active
        if (!hasAddress && hasGps) {
            matchGroup.hidden = true;
            responsibilityGroup.hidden = true;
            manualGroup.hidden = false;
            manualLabel.textContent = msg('manualLabelOptional');
            manualHint.textContent = msg('manualHintOptional');
            manualInput.required = false;
            advance.disabled = !validateManualAddress(false);
            advance.textContent = msg('continue');
            instructions.textContent = msg('usesGpsManual');
            return;
        }

        // Scenario 4: No account address + GPS inactive (manual address required)
        matchGroup.hidden = true;
        responsibilityGroup.hidden = true;
        manualGroup.hidden = false;
        manualLabel.textContent = msg('manualLabelRequired');
        manualHint.textContent = msg('manualHintRequired');
        manualInput.required = true;
        const manualText = manualInput.value.trim();
        const manualValid = manualText !== '' && validateManualAddress(false);
        advance.disabled = !manualValid;
        advance.textContent = msg('continue');
        instructions.textContent = manualValid ? msg('usesManualOnly') : msg('needLocation');
    }

    initiate.addEventListener('click', async () => {
        if (state !== 'idle') return;
        state = 'locating';
        const currentRequest = ++requestNumber;
        start.hidden = true;
        review.hidden = false;
        gpsField.textContent = msg('finding');
        status.textContent = '';
        updateReview();
        reviewTitle.focus();

        const gpsResult = await new Promise((resolve) => {
            if (!navigator.geolocation) {
                resolve({ code: 2 });
                return;
            }
            navigator.geolocation.getCurrentPosition(
                ({ coords }) => resolve(coords),
                ({ code }) => resolve({ code }),
                { maximumAge: 0, timeout: 10000 }
            );
        });

        if (currentRequest !== requestNumber) return;

        const errMsgs = {
            1: msg('denied'),
            2: msg('unavailable'),
            3: msg('timeout'),
        };

        const { latitude, longitude, code } = gpsResult;
        if (Number.isFinite(latitude) && Math.abs(latitude) <= 90
            && Number.isFinite(longitude) && Math.abs(longitude) <= 180) {
            gps = { latitude, longitude };
        }

        gpsField.textContent = gps
            ? msg('coords', { lat: latitude.toFixed(6), lng: longitude.toFixed(6) })
            : (errMsgs[code] ?? errMsgs[2]);
        status.textContent = gps ? msg('gpsReady') : gpsField.textContent;
        state = 'review';
        updateReview();
    });

    matchYes.addEventListener('change', updateReview);
    matchNo.addEventListener('change', updateReview);
    responsibilityCheckbox.addEventListener('change', updateReview);

    manualInput.addEventListener('input', () => {
        validateManualAddress(false);
        updateReview();
    });
    manualInput.addEventListener('blur', () => {
        validateManualAddress(isManualRequired());
        updateReview();
    });

    if (anonNameInput) {
        anonNameInput.addEventListener('input', validateAnonName);
        anonNameInput.addEventListener('blur', validateAnonName);
    }
    if (anonPhoneInput) {
        anonPhoneInput.addEventListener('input', validateAnonPhone);
        anonPhoneInput.addEventListener('blur', validateAnonPhone);
    }
    if (anonDobInput) {
        anonDobInput.addEventListener('input', validateAnonDob);
        anonDobInput.addEventListener('change', validateAnonDob);
    }

    if (clearAccountBtn) {
        clearAccountBtn.addEventListener('click', async () => {
            accountAddress = '';
            useAccountAddress = false;
            if (anonNameInput) anonNameInput.value = '';
            if (anonPhoneInput) anonPhoneInput.value = '';
            if (anonDobInput) anonDobInput.value = '';
            clearAccountBtn.hidden = true;
            setFieldError(anonNameInput, anonNameError, '');
            setFieldError(anonPhoneInput, anonPhoneError, '');
            setFieldError(anonDobInput, anonDobError, '');
            status.textContent = msg('clearedLastAccount');
            updateReview();

            try {
                await fetch(clearAccountUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: '{}',
                });
            } catch {
                // Non-blocking UI clear
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
        if (state !== 'review') return;

        const hasAddress = useAccountAddress && accountAddress !== '';
        const hasGps = Boolean(gps);
        const matchVal = getMatchSelection();

        const validName = validateAnonName();
        const validPhone = validateAnonPhone();
        const validDob = validateAnonDob();
        const validManual = validateManualAddress(isManualRequired());

        if (!validName || !validPhone || !validDob || !validManual) {
            status.textContent = !validManual
                ? (manualError.textContent || msg('errAddressRequired'))
                : (anonNameError?.textContent || anonPhoneError?.textContent || anonDobError?.textContent || '');
            return;
        }

        if (hasAddress && hasGps && matchVal === null) {
            status.textContent = msg('errMatchRequired');
            return;
        }
        if (hasAddress && !hasGps && !responsibilityCheckbox.checked) {
            status.textContent = msg('errResponsibilityRequired');
            return;
        }
        if (!hasAddress && !hasGps && manualInput.value.trim() === '') {
            validateManualAddress(true);
            manualInput.focus();
            return;
        }

        const manualText = manualGroup.hidden ? '' : manualInput.value.trim();
        const emergencyType = typeSelect ? typeSelect.value : '';
        const selectedOption = typeSelect && typeSelect.selectedIndex >= 0
            ? typeSelect.options[typeSelect.selectedIndex].textContent
            : '';

        draft = {
            gps_latitude: gps ? gps.latitude : null,
            gps_longitude: gps ? gps.longitude : null,
            use_account_address: hasAddress,
            locations_match: (hasAddress && hasGps) ? matchVal : null,
            responsibility_accepted: (hasAddress && !hasGps) ? responsibilityCheckbox.checked : false,
            manual_address: manualText,
            emergency_type: emergencyType,
            anon_name: !authenticated && anonNameInput ? anonNameInput.value.trim() : '',
            anon_phone: !authenticated && anonPhoneInput ? anonPhoneInput.value.trim() : '',
            anon_dob: !authenticated && anonDobInput ? anonDobInput.value.trim() : '',
        };

        if (hasAddress && hasGps && matchVal === true) {
            warningLocation.textContent = msg('locBoth');
        } else if (hasGps && manualText !== '') {
            warningLocation.textContent = msg('locGpsWithNote', { address: manualText });
        } else if (hasGps) {
            warningLocation.textContent = msg('locGps');
        } else if (hasAddress) {
            warningLocation.textContent = msg('locSavedAddress', { address: accountAddress });
        } else {
            warningLocation.textContent = msg('locManual', { address: manualText });
        }

        warningType.textContent = emergencyType !== '' ? selectedOption : msg('notSpecified');

        if (submitError) {
            submitError.textContent = '';
            submitError.hidden = true;
        }
        status.textContent = '';
        state = 'warning';
        review.hidden = true;
        warning.hidden = false;
        warningTitle.focus();
    });

    confirmBtn.addEventListener('click', async () => {
        if (state !== 'warning' || !draft || confirmBtn.disabled) return;
        confirmBtn.disabled = true;
        if (submitError) {
            submitError.textContent = '';
            submitError.hidden = true;
        }
        status.textContent = msg('submitting');

        try {
            const response = await fetch(submitUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(draft),
            });
            const payload = await response.json().catch(() => null);

            if (!response.ok || !payload || !payload.ok) {
                const errMessage = (payload && payload.message) ? payload.message : msg('submitFailed');
                if (submitError) {
                    submitError.textContent = errMessage;
                    submitError.hidden = false;
                }
                status.textContent = '';
                confirmBtn.disabled = false;
                return;
            }

            state = 'confirmed';
            const emergencyId = payload.data?.emergency_id;
            if (resultRef) {
                resultRef.textContent = emergencyId ? msg('resultRef', { id: emergencyId }) : '';
            }
            draft = null;
            gps = null;
            gpsField.textContent = '';
            addressField.textContent = '';
            status.textContent = '';
            warning.hidden = true;
            result.hidden = false;
            resultTitle.focus();
        } catch {
            if (submitError) {
                submitError.textContent = msg('submitFailed');
                submitError.hidden = false;
            }
            status.textContent = '';
            confirmBtn.disabled = false;
        }
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
