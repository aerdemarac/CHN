/* Visual demo only. No fetch, POST, storage, authentication or persistence.
 * Keep schema-aligned field names for a future authenticated backend adapter.
 */
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

    const notice = document.getElementById('case-demo-result');
    const messages = JSON.parse((notice && notice.dataset.messages) || '{}');
    const msg = (key, params = {}) => (messages[key] || key).replace(/\{(\w+)\}/g, (m, k) => (k in params ? String(params[k]) : m));
    const showNotice = (message) => {
        notice.textContent = message;
        notice.hidden = false;
    };
    const setDiagnosisEligibility = setupDiagnosis();
    const department = document.getElementById('create-department_id');
    const doctor = document.getElementById('create-doctor_id');
    if (department && doctor) {
        const options = Array.from(doctor.options).slice(1);
        const refresh = () => {
            doctor.replaceChildren(new Option(msg('selectDoctor'), ''));
            options.filter(option => option.dataset.department === department.value).forEach(option => doctor.add(option));
            document.getElementById('doctor-availability').textContent = doctor.options.length > 1
                ? msg('doctorRequired')
                : msg('noDoctors');
        };
        department.addEventListener('change', refresh);
        refresh();
    }
    document.querySelectorAll('form[data-case-demo]').forEach((form) => {
        form.querySelector('[data-case-submit-button]').disabled = false;
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            if (!form.reportValidity()) return;
            const values = new FormData(form);
            const action = values.get('action');
            if (action === 'patient_create' || action === 'create_doctor') {
                const title = values.get('case_title').trim();
                const description = values.get('case_description').trim();
                if (!title || !description || new TextEncoder().encode(description).length > 65535) {
                    showNotice(msg('formInvalid'));
                    return;
                }
                showNotice(msg('formChecked'));
                return;
            }
            const caseId = values.get('case_id');
            const row = Array.from(document.querySelectorAll('[data-case-id]')).find(row => row.dataset.caseId === caseId);
            const editor = form.closest('details');
            const label = editor.querySelector('summary > span');
            if (action === 'update_status' || action === 'update_doctor_status') {
                const state = values.get('case_state');
                if (!['open', 'closed'].includes(state)) return;
                label.textContent = msg(state);
                const badge = row?.querySelector('[data-case-state]');
                if (badge) {
                    badge.textContent = label.textContent;
                    badge.className = `badge ${state === 'open' ? 'badge--info' : 'badge--neutral'}`;
                }
                document.querySelectorAll('[data-case-count]').forEach(count => {
                    const original = Number(count.dataset.initialCount);
                    const delta = (count.dataset.caseCount === state ? 1 : 0) - (count.dataset.caseCount === form.dataset.originalState ? 1 : 0);
                    count.textContent = original + delta;
                });
            } else if (action === 'update_triage') {
                const color = values.get('tier_color');
                if (!['green', 'yellow', 'orange'].includes(color)) return;
                label.textContent = msg(color);
                const badge = row?.querySelector('[data-case-triage]');
                if (badge) {
                    badge.textContent = label.textContent;
                    badge.className = `badge medical-cases__triage medical-cases__triage--${color}`;
                }
            } else return;
            editor.open = false;
            if (action === 'update_doctor_status') {
                setDiagnosisEligibility?.(values.get('case_state') === 'open');
            }
            showNotice(msg('updated'));
        });
    });

    function setupDiagnosis() {
        const form = document.getElementById('add-diagnosis');
        if (!form) return;

        const trigger = document.getElementById('open-diagnosis');
        const description = form.elements.diagnosis_text;
        const icd = form.elements.icd_code;
        const descriptionError = document.getElementById('diagnosis-text-error');
        const icdError = document.getElementById('diagnosis-icd-error');
        const result = document.getElementById('diagnosis-result');
        const history = document.getElementById('diagnosis-history');
        const doctorName = document.getElementById('diagnosis-doctor').textContent;
        const copy = JSON.parse(form.dataset.messages);
        // Printable ASCII, Turkish Latin letters and line breaks for multiline notes.
        const unsupported = /[^ -~çÇğĞıİöÖşŞüÜ\n]/u;
        let attempted = false;

        function report(field, error, message) {
            field.setAttribute('aria-invalid', String(Boolean(message)));
            error.textContent = message;
            return Boolean(message);
        }

        function validateDescription() {
            const value = description.value;
            const message = unsupported.test(value) ? copy.characters
                : !value.trim() && attempted ? copy.required
                : new TextEncoder().encode(value.trim()).length > 65535 ? copy.descriptionLength : '';
            return report(description, descriptionError, message);
        }

        function validateIcd() {
            const value = icd.value;
            const message = unsupported.test(value) ? copy.characters
                : value.length > 20 ? copy.icdLength : '';
            return report(icd, icdError, message);
        }

        function closeForm(restoreFocus) {
            form.hidden = true;
            form.reset();
            attempted = false;
            validateDescription();
            validateIcd();
            trigger.setAttribute('aria-expanded', 'false');
            if (restoreFocus) trigger.focus();
        }

        description.addEventListener('input', validateDescription);
        icd.addEventListener('input', validateIcd);
        trigger.addEventListener('click', () => {
            if (!form.hidden) {
                closeForm(true);
                return;
            }
            result.textContent = '';
            form.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
            description.focus();
        });
        document.getElementById('cancel-diagnosis').addEventListener('click', () => closeForm(true));
        form.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !event.isComposing) closeForm(true);
        });
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            // Closing synchronously after adding consumes this form submission.
            if (form.hidden || trigger.hidden) return;
            attempted = true;
            const invalidDescription = validateDescription();
            const invalidIcd = validateIcd();
            if (invalidDescription || invalidIcd) {
                (invalidDescription ? description : icd).focus();
                return;
            }

            const values = new FormData(form);
            const entry = document.createElement('li');
            const text = document.createElement('p');
            const attribution = document.createElement('p');
            text.textContent = values.get('diagnosis_text').trim();
            attribution.className = 'card__subtitle';
            attribution.textContent = copy.preview.replace('{doctor}', () => doctorName);
            entry.append(text, attribution);
            const code = values.get('icd_code').trim();
            if (code) {
                const codeLine = document.createElement('p');
                codeLine.textContent = copy.icd.replace('{code}', () => code);
                entry.append(codeLine);
            }
            history.prepend(entry);
            history.hidden = false;
            document.getElementById('diagnosis-empty').hidden = true;
            closeForm(true);
            result.textContent = copy.added;
        });

        form.querySelector('[type="submit"]').disabled = false;
        trigger.hidden = false;
        return (isOpen) => {
            if (!isOpen) closeForm(false);
            trigger.hidden = !isOpen;
        };
    }
})();
