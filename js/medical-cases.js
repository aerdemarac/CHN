/* Visual demo only. No fetch, POST, storage, authentication or persistence.
 * Keep schema-aligned field names for a future authenticated backend adapter.
 */
(() => {
    'use strict';
    const notice = document.getElementById('case-demo-result');
    const messages = JSON.parse((notice && notice.dataset.messages) || '{}');
    const msg = (key, params = {}) => (messages[key] || key).replace(/\{(\w+)\}/g, (m, k) => (k in params ? String(params[k]) : m));
    const showNotice = (message) => {
        notice.textContent = message;
        notice.hidden = false;
    };
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
            showNotice(msg('updated'));
        });
    });
})();
