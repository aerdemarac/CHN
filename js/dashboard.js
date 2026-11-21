(() => {
    'use strict';

    function initCaseFilter() {
        const table = document.getElementById('dashboard-cases-table');
        const search = document.getElementById('dashboard-case-search');
        const tier = document.getElementById('dashboard-case-tier');
        const empty = document.getElementById('dashboard-cases-empty');
        if (!table || !search || !tier || !empty) return;

        const rows = [...table.tBodies[0].rows];

        const apply = () => {
            const query = search.value.trim().toLocaleLowerCase(document.documentElement.lang || undefined);
            const selectedTier = tier.value;
            let visible = 0;

            rows.forEach((row) => {
                const matchesText = query === '' || row.textContent.toLocaleLowerCase(document.documentElement.lang || undefined).includes(query);
                const matchesTier = selectedTier === '' || row.dataset.tier === selectedTier;
                row.hidden = !(matchesText && matchesTier);
                if (!row.hidden) visible += 1;
            });

            empty.hidden = visible !== 0;
        };

        search.addEventListener('input', apply);
        tier.addEventListener('change', apply);
    }

    initCaseFilter();
})();
