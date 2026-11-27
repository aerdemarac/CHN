/* Medical Cases authenticated interactions (fetch + JSON). */
(() => {
    'use strict';

    const appointmentToggle = document.getElementById('toggle-appointments');
    if (appointmentToggle) {
        const panel = document.getElementById(appointmentToggle.getAttribute('aria-controls'));
        const showLabel = appointmentToggle.querySelector('[data-show-appointments]');
        const hideLabel = appointmentToggle.querySelector('[data-hide-appointments]');
        appointmentToggle.addEventListener('click', () => {
            const expanded = panel.hidden;
            panel.hidden = !expanded;
            appointmentToggle.setAttribute('aria-expanded', String(expanded));
            showLabel.hidden = expanded;
            hideLabel.hidden = !expanded;
        });
        appointmentToggle.hidden = false;
    }

    const notice = document.getElementById('case-result-notice');
    const messages = JSON.parse((notice && notice.dataset.messages) || '{}');
    const msg = (key, params = {}) => (messages[key] || key).replace(/\{(\w+)\}/g, (m, k) => (k in params ? String(params[k]) : m));
    const unsupportedText = /[^ -~çÇğĞıİöÖşŞüÜ\n\r\t]/u;

    const showNotice = (message, isError = false) => {
        if (!notice) return;
        notice.classList.toggle('alert--danger', isError);
        notice.classList.toggle('alert--info', !isError);
        notice.textContent = message;
        notice.hidden = false;
        notice.scrollIntoView({ block: 'nearest' });
        notice.focus({ preventScroll: true });
    };

    const flash = sessionStorage.getItem('chn_cases_flash');
    if (flash) {
        sessionStorage.removeItem('chn_cases_flash');
        showNotice(flash, false);
    }

    async function postJson(url, payload) {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(payload),
        });
        let body = null;
        try {
            body = await response.json();
        } catch {
            body = null;
        }
        return { ok: response.ok && body && body.ok === true, body };
    }

    // Cascading Hospital -> Department -> Doctor for patient case creation
    const hospitalSelect = document.getElementById('create-hospital_id');
    const departmentSelect = document.getElementById('create-department_id');
    const doctorSelect = document.getElementById('create-doctor_id');
    if (hospitalSelect && departmentSelect && doctorSelect) {
        const deptOptions = Array.from(departmentSelect.options).slice(1);
        const docOptions = Array.from(doctorSelect.options).slice(1);
        const availability = document.getElementById('doctor-availability');

        const refreshDoctors = () => {
            doctorSelect.replaceChildren(new Option(msg('selectDoctor'), ''));
            const filtered = docOptions.filter(opt => opt.dataset.department === departmentSelect.value);
            filtered.forEach(opt => doctorSelect.add(opt));
            doctorSelect.disabled = !departmentSelect.value || filtered.length === 0;
            if (availability) {
                availability.textContent = !departmentSelect.value
                    ? msg('doctorRequired')
                    : (filtered.length > 0 ? msg('doctorRequired') : msg('noDoctors'));
            }
        };

        const refreshDepartments = () => {
            departmentSelect.replaceChildren(new Option(msg('selectDepartment'), ''));
            const filtered = deptOptions.filter(opt => opt.dataset.hospital === hospitalSelect.value);
            filtered.forEach(opt => departmentSelect.add(opt));
            departmentSelect.disabled = !hospitalSelect.value || filtered.length === 0;
            refreshDoctors();
        };

        hospitalSelect.addEventListener('change', refreshDepartments);
        departmentSelect.addEventListener('change', refreshDoctors);
        refreshDepartments();
    }

    // Create case form
    const createForm = document.getElementById('create-case-form');
    if (createForm) {
        const descInput = createForm.elements.case_description;
        const submitBtn = document.getElementById('create-case-submit');

        createForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (submitBtn.disabled) return;
            if (!createForm.reportValidity()) return;

            const description = descInput.value.trim();
            if (!description || unsupportedText.test(description) || new TextEncoder().encode(description).length > 65535) {
                showNotice(msg('formInvalid'), true);
                descInput.focus();
                return;
            }

            const payload = { case_description: description };
            if (createForm.elements.national_id) {
                payload.national_id = createForm.elements.national_id.value.trim();
            } else {
                payload.hospital_id = Number(createForm.elements.hospital_id.value);
                payload.department_id = Number(createForm.elements.department_id.value);
                payload.doctor_id = Number(createForm.elements.doctor_id.value);
            }

            submitBtn.disabled = true;
            try {
                const res = await postJson(createForm.dataset.createUrl, payload);
                if (res.ok && res.body?.data?.case_id) {
                    sessionStorage.setItem('chn_cases_flash', msg('createdSuccess'));
                    window.location.assign(`medical-cases.php?case=${encodeURIComponent(res.body.data.case_id)}#case-details`);
                    return;
                }
                showNotice(res.body?.message || msg('requestFailed'), true);
            } catch {
                showNotice(msg('requestFailed'), true);
            } finally {
                submitBtn.disabled = false;
            }
        });
    }

    // Update case status / triage
    document.querySelectorAll('form[data-case-update]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn && submitBtn.disabled) return;

            const values = new FormData(form);
            const action = values.get('action');
            const caseId = Number(values.get('case_id'));
            const payload = { action, case_id: caseId };
            if (action === 'update_status') payload.case_state = values.get('case_state');
            else if (action === 'update_triage') payload.tier_color = values.get('tier_color');

            if (submitBtn) submitBtn.disabled = true;
            try {
                const res = await postJson(form.dataset.updateUrl, payload);
                if (res.ok) {
                    sessionStorage.setItem('chn_cases_flash', msg('updatedSuccess'));
                    window.location.reload();
                    return;
                }
                showNotice(res.body?.message || msg('requestFailed'), true);
            } catch {
                showNotice(msg('requestFailed'), true);
            } finally {
                if (submitBtn) submitBtn.disabled = false;
            }
        });
    });

    // Treatments: doctor-only toggle, add & mark completed
    const treatmentForm = document.getElementById('add-treatment-form');
    const treatmentTrigger = document.getElementById('open-treatment');
    if (treatmentForm && treatmentTrigger) {
        const descInput = treatmentForm.elements.treatment_desc;
        const descError = document.getElementById('treatment-desc-error');
        const cancelBtn = document.getElementById('cancel-treatment');

        const closeTreatmentForm = (restoreFocus) => {
            treatmentForm.hidden = true;
            treatmentForm.reset();
            descError.textContent = '';
            treatmentTrigger.setAttribute('aria-expanded', 'false');
            if (restoreFocus) treatmentTrigger.focus();
        };

        treatmentTrigger.hidden = false;
        treatmentTrigger.addEventListener('click', () => {
            if (!treatmentForm.hidden) {
                closeTreatmentForm(true);
                return;
            }
            treatmentForm.hidden = false;
            treatmentTrigger.setAttribute('aria-expanded', 'true');
            descInput.focus();
        });
        if (cancelBtn) {
            cancelBtn.addEventListener('click', () => closeTreatmentForm(true));
        }
        treatmentForm.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !event.isComposing) closeTreatmentForm(true);
        });

        treatmentForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const val = descInput.value.trim();
            if (!val || val.length > 255 || unsupportedText.test(val)) {
                descError.textContent = msg('formInvalid');
                descInput.focus();
                return;
            }
            descError.textContent = '';
            const btn = treatmentForm.querySelector('button[type="submit"]');
            btn.disabled = true;
            try {
                const res = await postJson(treatmentForm.dataset.treatmentUrl, {
                    action: 'add',
                    case_id: Number(treatmentForm.elements.case_id.value),
                    treatment_desc: val,
                });
                if (res.ok) {
                    sessionStorage.setItem('chn_cases_flash', msg('updatedSuccess'));
                    window.location.reload();
                    return;
                }
                descError.textContent = res.body?.message || msg('requestFailed');
            } catch {
                descError.textContent = msg('requestFailed');
            } finally {
                btn.disabled = false;
            }
        });
    }

    document.querySelectorAll('[data-complete-treatment]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const treatmentId = Number(btn.dataset.treatmentId);
            if (!treatmentId) return;
            btn.disabled = true;
            try {
                const res = await postJson('../backend/manage_treatment.php', {
                    action: 'deactivate',
                    treatment_id: treatmentId,
                });
                if (res.ok) {
                    sessionStorage.setItem('chn_cases_flash', msg('updatedSuccess'));
                    window.location.reload();
                    return;
                }
                showNotice(res.body?.message || msg('requestFailed'), true);
            } catch {
                showNotice(msg('requestFailed'), true);
            } finally {
                btn.disabled = false;
            }
        });
    });

    // Add diagnosis (doctor only, open cases)
    const diagForm = document.getElementById('add-diagnosis');
    if (diagForm) {
        const trigger = document.getElementById('open-diagnosis');
        const description = diagForm.elements.diagnosis_text;
        const icd = diagForm.elements.icd_code;
        const descriptionError = document.getElementById('diagnosis-text-error');
        const icdError = document.getElementById('diagnosis-icd-error');
        const result = document.getElementById('diagnosis-result');
        const copy = JSON.parse(diagForm.dataset.messages || '{}');
        let attempted = false;

        function report(field, error, message) {
            field.setAttribute('aria-invalid', String(Boolean(message)));
            error.textContent = message;
            return Boolean(message);
        }

        function validateDescription() {
            const value = description.value;
            const message = unsupportedText.test(value) ? copy.characters
                : !value.trim() && attempted ? copy.required
                : new TextEncoder().encode(value.trim()).length > 65535 ? copy.descriptionLength : '';
            return report(description, descriptionError, message);
        }

        function validateIcd() {
            const value = icd.value;
            const message = unsupportedText.test(value) ? copy.characters
                : value.length > 20 ? copy.icdLength : '';
            return report(icd, icdError, message);
        }

        function closeForm(restoreFocus) {
            diagForm.hidden = true;
            diagForm.reset();
            attempted = false;
            validateDescription();
            validateIcd();
            trigger.setAttribute('aria-expanded', 'false');
            if (restoreFocus) trigger.focus();
        }

        description.addEventListener('input', validateDescription);
        icd.addEventListener('input', validateIcd);
        trigger.hidden = false;
        trigger.addEventListener('click', () => {
            if (!diagForm.hidden) { closeForm(true); return; }
            result.textContent = '';
            diagForm.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
            description.focus();
        });
        document.getElementById('cancel-diagnosis').addEventListener('click', () => closeForm(true));
        diagForm.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !event.isComposing) closeForm(true);
        });

        diagForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            attempted = true;
            const badDesc = validateDescription();
            const badIcd = validateIcd();
            if (badDesc || badIcd) {
                (badDesc ? description : icd).focus();
                return;
            }

            const submitBtn = diagForm.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            try {
                const res = await postJson(diagForm.dataset.diagnosisUrl, {
                    case_id: Number(diagForm.elements.case_id.value),
                    diagnosis_text: description.value.trim(),
                    icd_code: icd.value.trim(),
                });
                if (res.ok) {
                    sessionStorage.setItem('chn_cases_flash', copy.added || msg('updatedSuccess'));
                    window.location.reload();
                    return;
                }
                result.textContent = res.body?.message || copy.failed || msg('requestFailed');
            } catch {
                result.textContent = copy.failed || msg('requestFailed');
            } finally {
                submitBtn.disabled = false;
            }
        });
    }
})();
