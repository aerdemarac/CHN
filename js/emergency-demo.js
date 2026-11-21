(() => {
    'use strict';
    const table = document.getElementById('emergency-table');
    const search = document.getElementById('emergency-search');
    const status = document.getElementById('emergency-status');
    const empty = document.getElementById('emergency-empty');
    if (!table || !search || !status || !empty) return;
    const dialog = document.getElementById('emergency-action-dialog');
    const title = document.getElementById('emergency-action-title');
    const description = document.getElementById('emergency-action-description');
    const result = document.getElementById('emergency-action-result');
    const cancel = document.getElementById('emergency-action-cancel');
    const confirm = document.getElementById('emergency-action-confirm');
    let actionTrigger = null;
    const messages = JSON.parse(table.dataset.messages || '{}');
    const msg = (key, params = {}) => (messages[key] || key).replace(/\{(\w+)\}/g, (m, k) => (k in params ? String(params[k]) : m));

    // No request is sent: authenticated, authorized server handlers are required for either action.
    const openAction = (trigger, action) => {
        const id = action === 'ban' ? trigger.dataset.userId : trigger.dataset.emergencyId;
        const validId = /^[1-9]\d*$/.test(id || '');
        actionTrigger = trigger;
        result.hidden = true;
        result.textContent = '';
        cancel.textContent = msg('cancel');
        confirm.hidden = !validId;
        confirm.className = action === 'ban' ? 'btn btn--danger' : 'btn btn--primary';
        title.textContent = action === 'ban' ? msg('confirmBan') : msg('flagTitle');
        description.textContent = !validId
            ? (action === 'ban' ? msg('banNoUser') : msg('flagNoId'))
            : (action === 'ban' ? msg('banQuestion', { id }) : msg('flagQuestion', { id }));
        confirm.textContent = action === 'ban' ? msg('confirmBan') : msg('confirmFlag');
        dialog.showModal();
        cancel.focus();
    };
    if (dialog && title && description && result && cancel && confirm) {
        table.addEventListener('click', (event) => {
            const flag = event.target.closest('.emergency-demo__flag');
            const ban = event.target.closest('.emergency-demo__ban');
            if (flag || ban) openAction(flag || ban, ban ? 'ban' : 'flag');
        });
        cancel.addEventListener('click', () => dialog.close());
        confirm.addEventListener('click', () => {
            result.textContent = msg('notConnected');
            result.hidden = false;
            confirm.hidden = true;
            cancel.textContent = msg('close');
            cancel.focus();
        });
        dialog.addEventListener('close', () => { if (actionTrigger) actionTrigger.focus(); });
    }
    const rows = [...table.querySelectorAll('tr[data-status]')];
    const apply = () => {
        const query = search.value.trim().toLocaleLowerCase();
        let count = 0;
        rows.forEach((row) => {
            const button = row.querySelector('.emergency-demo__details-button');
            const detail = document.getElementById(button.getAttribute('aria-controls'));
            const matches = (!query || (row.textContent + ' ' + detail.textContent).toLocaleLowerCase().includes(query)) && (!status.value || row.dataset.status === status.value);
            row.hidden = !matches;
            detail.hidden = !matches || button.getAttribute('aria-expanded') !== 'true';
            if (matches) count++;
        });
        empty.hidden = count !== 0;
    };
    search.addEventListener('input', apply);
    status.addEventListener('change', apply);
    rows.forEach((row) => {
        const button = row.querySelector('.emergency-demo__details-button');
        button.addEventListener('click', () => {
            const expanded = button.getAttribute('aria-expanded') === 'true';
            button.setAttribute('aria-expanded', String(!expanded));
            button.textContent = expanded ? msg('viewDetails') : msg('hideDetails');
            document.getElementById(button.getAttribute('aria-controls')).hidden = expanded;
        });
    });
})();
