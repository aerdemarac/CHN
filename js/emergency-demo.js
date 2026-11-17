(() => {
    'use strict';
    const table = document.getElementById('emergency-table');
    const search = document.getElementById('emergency-search');
    const status = document.getElementById('emergency-status');
    const empty = document.getElementById('emergency-empty');
    if (!table || !search || !status || !empty) return;
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
            button.textContent = expanded ? 'View details' : 'Hide details';
            document.getElementById(button.getAttribute('aria-controls')).hidden = expanded;
        });
    });
})();
