(() => {
    'use strict';

    const table = document.getElementById('emergency-table');
    const search = document.getElementById('emergency-search');
    const statusSelect = document.getElementById('emergency-status');
    const queueSelect = document.getElementById('emergency-queue');
    const empty = document.getElementById('emergency-empty');
    const feedback = document.getElementById('emergency-feedback');

    if (!table || !search || !statusSelect || !empty) return;

    const dialog = document.getElementById('emergency-action-dialog');
    const title = document.getElementById('emergency-action-title');
    const description = document.getElementById('emergency-action-description');
    const result = document.getElementById('emergency-action-result');
    const cancel = document.getElementById('emergency-action-cancel');
    const confirmBtn = document.getElementById('emergency-action-confirm');

    const manageUrl = table.dataset.manageUrl || '../backend/manage_emergency.php';
    const isAdmin = table.dataset.isAdmin === 'true';
    const statusLabels = JSON.parse(table.dataset.statusLabels || '{}');
    const messages = JSON.parse(table.dataset.messages || '{}');
    const msg = (key, params = {}) => (messages[key] || key).replace(/\{(\w+)\}/g, (m, k) => (k in params ? String(params[k]) : m));

    let pendingAction = null;
    let actionTrigger = null;

    const showFeedback = (text, isError = false) => {
        if (!feedback) return;
        feedback.textContent = text;
        feedback.className = 'alert emergency-demo__feedback ' + (isError ? 'alert--danger' : 'alert--success');
        feedback.hidden = !text;
    };

    const updateMetrics = () => {
        const allRows = [...table.querySelectorAll('tr[data-status]')];
        const totalEl = document.getElementById('metric-total');
        const pendingEl = document.getElementById('metric-pending');
        const fraudEl = document.getElementById('metric-fraud');
        const handledEl = document.getElementById('metric-handled');

        if (totalEl) totalEl.textContent = String(allRows.length);
        if (pendingEl) pendingEl.textContent = String(allRows.filter((r) => r.dataset.status === 'pending').length);
        if (fraudEl) fraudEl.textContent = String(allRows.filter((r) => r.dataset.status === 'fraud').length);
        if (handledEl) handledEl.textContent = String(allRows.filter((r) => r.dataset.queue === 'handled').length);
    };

    const applyFilters = () => {
        const rows = [...table.querySelectorAll('tr[data-status]')];
        const query = search.value.trim().toLocaleLowerCase();
        const queueVal = queueSelect ? queueSelect.value : '';
        let count = 0;

        rows.forEach((row) => {
            const button = row.querySelector('.emergency-demo__details-button');
            const detail = document.getElementById(button.getAttribute('aria-controls'));
            const matchesQuery = !query || (row.textContent + ' ' + detail.textContent).toLocaleLowerCase().includes(query);
            const matchesStatus = !statusSelect.value || row.dataset.status === statusSelect.value;
            const matchesQueue = !queueVal || row.dataset.queue === queueVal;
            const matches = matchesQuery && matchesStatus && matchesQueue;

            row.hidden = !matches;
            detail.hidden = !matches || button.getAttribute('aria-expanded') !== 'true';
            if (matches) count++;
        });

        empty.hidden = count !== 0;
    };

    const updateRowAfterAction = (action, payloadData) => {
        if (action === 'ban_user') {
            const bannedUserId = String(payloadData.user_id || '');
            table.querySelectorAll(`tr[data-user-id="${bannedUserId}"]`).forEach((row) => {
                const banBtn = document.getElementById(`emergency-detail-${row.dataset.emergencyId}`)?.querySelector('.emergency-demo__ban');
                if (banBtn) {
                    banBtn.disabled = true;
                    banBtn.textContent = msg('bannedBadge');
                }
            });
            return;
        }

        const emId = String(payloadData.emergency_id || '');
        const row = table.querySelector(`tr[data-emergency-id="${emId}"]`);
        const detailRow = document.getElementById(`emergency-detail-${emId}`);
        if (!row || !detailRow) return;

        const newStatus = payloadData.status;
        if (newStatus) {
            row.dataset.status = newStatus;
            if (newStatus !== 'pending') {
                row.dataset.queue = 'handled';
            }
            const badge = row.querySelector('[data-role="status-badge"]');
            if (badge) {
                badge.className = `emergency-demo__status emergency-demo__status--${newStatus}`;
                badge.textContent = statusLabels[newStatus] || newStatus;
            }
        }

        if (payloadData.hospital_name || payloadData.handled_by_staff_name) {
            const hName = payloadData.hospital_name || '';
            const sName = payloadData.handled_by_staff_name || '';
            const assignmentCell = row.querySelector('[data-role="assignment-cell"]');
            const detailHospital = detailRow.querySelector('[data-role="detail-hospital"]');
            const detailStaff = detailRow.querySelector('[data-role="detail-staff"]');
            if (assignmentCell) {
                assignmentCell.textContent = [hName, sName].filter(Boolean).join(' · ');
            }
            if (detailHospital && hName) detailHospital.textContent = hName;
            if (detailStaff && sName) detailStaff.textContent = sName;
        }

        if (payloadData.handled_at) {
            const handledAtEl = detailRow.querySelector('[data-role="detail-handled-at"]');
            if (handledAtEl) handledAtEl.textContent = payloadData.handled_at;
        }

        if (isAdmin && newStatus === 'fraud') {
            const fraudNote = detailRow.querySelector('[data-role="fraud-note"]');
            if (fraudNote) {
                fraudNote.textContent = `${msg('fraudTitle')} ${msg('fraudText')}`;
            }
        }

        const actionBar = detailRow.querySelector('[data-role="action-bar"]');
        if (actionBar) {
            const startBtn = actionBar.querySelector('.emergency-demo__start');
            const resolveBtn = actionBar.querySelector('.emergency-demo__resolve');
            const flagBtn = actionBar.querySelector('.emergency-demo__flag');

            if (action === 'start_handling') {
                if (startBtn) {
                    startBtn.className = 'btn btn--primary emergency-demo__resolve';
                    startBtn.textContent = msg('markResolved');
                }
            } else if (action === 'mark_resolved') {
                if (resolveBtn) resolveBtn.remove();
                if (flagBtn) flagBtn.remove();
            } else if (action === 'flag_fraud') {
                if (startBtn) startBtn.remove();
                if (resolveBtn) resolveBtn.remove();
                if (flagBtn) flagBtn.remove();
            }
        }

        updateMetrics();
        applyFilters();
    };

    const openAction = (trigger, action) => {
        const id = action === 'ban_user' ? trigger.dataset.userId : trigger.dataset.emergencyId;
        const validId = /^[1-9]\d*$/.test(id || '');

        actionTrigger = trigger;
        pendingAction = validId ? { action, id: Number(id) } : null;

        result.hidden = true;
        result.textContent = '';
        cancel.textContent = msg('cancel');
        confirmBtn.hidden = !validId;
        confirmBtn.disabled = false;

        if (action === 'ban_user') {
            confirmBtn.className = 'btn btn--danger';
            title.textContent = msg('confirmBan');
            description.textContent = validId ? msg('banQuestion', { id }) : msg('banNoUser');
            confirmBtn.textContent = msg('confirmBan');
        } else if (action === 'flag_fraud') {
            confirmBtn.className = 'btn btn--primary';
            title.textContent = msg('flagTitle');
            description.textContent = validId ? msg('flagQuestion', { id }) : msg('flagNoId');
            confirmBtn.textContent = msg('confirmFlag');
        } else if (action === 'start_handling') {
            confirmBtn.className = 'btn btn--primary';
            title.textContent = msg('startTitle');
            description.textContent = msg('startQuestion', { id });
            confirmBtn.textContent = msg('confirmStart');
        } else if (action === 'mark_resolved') {
            confirmBtn.className = 'btn btn--primary';
            title.textContent = msg('resolveTitle');
            description.textContent = msg('resolveQuestion', { id });
            confirmBtn.textContent = msg('confirmResolve');
        }

        dialog.showModal();
        cancel.focus();
    };

    if (dialog && title && description && result && cancel && confirmBtn) {
        table.addEventListener('click', (event) => {
            const startBtn = event.target.closest('.emergency-demo__start');
            const resolveBtn = event.target.closest('.emergency-demo__resolve');
            const flagBtn = event.target.closest('.emergency-demo__flag');
            const banBtn = event.target.closest('.emergency-demo__ban');

            if (startBtn && !startBtn.disabled) openAction(startBtn, 'start_handling');
            else if (resolveBtn && !resolveBtn.disabled) openAction(resolveBtn, 'mark_resolved');
            else if (flagBtn && !flagBtn.disabled) openAction(flagBtn, 'flag_fraud');
            else if (banBtn && !banBtn.disabled) openAction(banBtn, 'ban_user');
        });

        cancel.addEventListener('click', () => {
            pendingAction = null;
            dialog.close();
        });

        confirmBtn.addEventListener('click', async () => {
            if (!pendingAction || confirmBtn.disabled) return;
            confirmBtn.disabled = true;
            result.hidden = true;

            const body = pendingAction.action === 'ban_user'
                ? { action: 'ban_user', user_id: pendingAction.id }
                : { action: pendingAction.action, emergency_id: pendingAction.id };

            try {
                const response = await fetch(manageUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(body),
                });
                const payload = await response.json().catch(() => null);

                if (!response.ok || !payload || !payload.ok) {
                    result.textContent = (payload && payload.message) ? payload.message : msg('requestFailed');
                    result.hidden = false;
                    confirmBtn.disabled = false;
                    return;
                }

                const executedAction = pendingAction.action;
                pendingAction = null;
                dialog.close();
                updateRowAfterAction(executedAction, payload.data || {});
                showFeedback(payload.message || '', false);
            } catch {
                result.textContent = msg('requestFailed');
                result.hidden = false;
                confirmBtn.disabled = false;
            }
        });

        dialog.addEventListener('close', () => {
            if (actionTrigger) actionTrigger.focus();
        });
    }

    search.addEventListener('input', applyFilters);
    statusSelect.addEventListener('change', applyFilters);
    if (queueSelect) queueSelect.addEventListener('change', applyFilters);

    table.addEventListener('click', (event) => {
        const button = event.target.closest('.emergency-demo__details-button');
        if (!button) return;
        const expanded = button.getAttribute('aria-expanded') === 'true';
        button.setAttribute('aria-expanded', String(!expanded));
        button.textContent = expanded ? msg('viewDetails') : msg('hideDetails');
        const detail = document.getElementById(button.getAttribute('aria-controls'));
        if (detail) detail.hidden = expanded;
    });
})();
