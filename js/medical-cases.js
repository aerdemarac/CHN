/* Progressive enhancement only; validation and persistence are enforced in PHP/MySQL. */
(() => {
    'use strict';
    document.querySelectorAll('form[data-case-submit]').forEach((form) => {
        const save = form.querySelector('[data-case-submit-button]');
        if (!save) return;
        const originalLabel = save.textContent;
        const unavailable = save.disabled;
        form.addEventListener('submit', (event) => {
            if (unavailable) { event.preventDefault(); return; }
            if (!form.checkValidity()) return;
            save.disabled = true;
            save.textContent = save.dataset.busyLabel || 'Submitting…';
        });
        window.addEventListener('pageshow', () => {
            save.disabled = unavailable;
            save.textContent = originalLabel;
        });
    });
})();

// Keep the full database-provided option set for department changes.
(() => {
    const department = document.getElementById('create-department_id');
    const doctor = document.getElementById('create-doctor_id');
    if (!department || !doctor) return;
    const options = Array.from(doctor.options).slice(1);
    const refresh = (reset) => {
        const selected = reset ? '' : doctor.value;
        doctor.replaceChildren(new Option('Select doctor', ''));
        options.filter(option => option.dataset.department === department.value).forEach(option => doctor.add(option));
        doctor.value = Array.from(doctor.options).some(option => option.value === selected) ? selected : '';
        document.getElementById('doctor-availability').textContent = doctor.options.length > 1
            ? 'Select a preferred doctor (required).' : 'No available doctors in this department. Choose another department to create a case.';
    };
    department.addEventListener('change', () => refresh(true));
    refresh(false);
})();
