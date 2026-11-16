(function () {
    'use strict';

    var detail = document.getElementById('records-detail');
    var notice = document.getElementById('records-notice');

    if (!detail || !detail.hasAttribute('data-case-id')) {
        return;
    }

    var caseId = detail.getAttribute('data-case-id');
    var doctorName = detail.getAttribute('data-doctor-name') || '';
    var tiers = ['orange', 'yellow', 'green'];
    var icdPattern = /^[A-Z][0-9]{2}(\.[0-9A-Z]{1,4})?$/;

    function showNotice(message, type) {
        if (!notice) {
            return;
        }
        notice.className = 'alert alert--' + (type || 'info') + ' records-notice';
        notice.textContent = message;
        notice.hidden = false;
        notice.scrollIntoView({ block: 'nearest' });
    }

    function pad(value) {
        return value < 10 ? '0' + value : String(value);
    }

    function todayKey() {
        var date = new Date();
        return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
    }

    function formatDate(key) {
        var parts = key.split('-');
        var date = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
        return date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
    }

    function isValidDateKey(key) {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(key)) {
            return false;
        }
        var parts = key.split('-');
        var date = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
        return date.getFullYear() === Number(parts[0]) && date.getMonth() === Number(parts[1]) - 1 && date.getDate() === Number(parts[2]);
    }

    function setError(control, message) {
        var error = document.getElementById(control.id + '-error');
        if (error) {
            error.textContent = message;
            error.hidden = message === '';
        }
        control.setAttribute('aria-invalid', message === '' ? 'false' : 'true');

        var describedBy = (control.getAttribute('aria-describedby') || '').split(' ').filter(function (id) {
            return id !== '' && id !== control.id + '-error';
        });
        if (message !== '' && error) {
            describedBy.push(error.id);
        }
        if (describedBy.length) {
            control.setAttribute('aria-describedby', describedBy.join(' '));
        } else {
            control.removeAttribute('aria-describedby');
        }
    }

    function makeBadge(label, type) {
        var badge = document.createElement('span');
        badge.className = 'badge badge--' + type;
        badge.textContent = label;
        return badge;
    }

    function makeEntry(title, meta) {
        var item = document.createElement('li');
        var main = document.createElement('div');
        var titleNode = document.createElement('p');
        var metaNode = document.createElement('p');

        item.className = 'records-entry';
        main.className = 'records-entry__main';
        titleNode.className = 'records-entry__title';
        titleNode.textContent = title;
        metaNode.className = 'records-entry__meta';
        metaNode.textContent = meta;
        main.appendChild(titleNode);
        main.appendChild(metaNode);
        item.appendChild(main);

        return item;
    }

    function updateCount(kind) {
        var list = detail.querySelector('[data-records-list="' + kind + '"]');
        var count = detail.querySelector('[data-records-count="' + kind + '"]');
        var empty = detail.querySelector('[data-records-empty="' + kind + '"]');
        var total = list.children.length;

        count.textContent = '(' + total + ')';
        empty.hidden = total > 0;
    }

    function updateCloseState() {
        var button = document.getElementById('records-close');
        var hint = document.getElementById('records-close-hint');
        if (!button) {
            return;
        }
        var active = detail.querySelectorAll('[data-records-list="treatments"] [data-treatment-status="active"]').length;

        button.disabled = active > 0;
        hint.textContent = active > 0
            ? 'Mark every active treatment inactive before closing the case.'
            : 'No active treatments. The case can be closed.';
    }

    function closeDetails(form) {
        var details = form.closest('details');
        if (details) {
            details.open = false;
            details.querySelector('summary').focus();
        }
    }

    function submitDiagnosis(form) {
        var text = form.elements.diagnosis_text;
        var icd = form.elements.icd_code;
        var textValue = text.value.trim();
        var icdValue = icd.value.trim().toUpperCase();
        var firstInvalid = null;

        setError(text, '');
        setError(icd, '');

        if (textValue === '') {
            setError(text, 'Enter the diagnosis.');
            firstInvalid = text;
        } else if (textValue.length > 1000) {
            setError(text, 'Use at most 1000 characters.');
            firstInvalid = text;
        }
        if (icdValue !== '' && !icdPattern.test(icdValue)) {
            setError(icd, 'Enter a code like I10 or I11.9, or leave it empty.');
            firstInvalid = firstInvalid || icd;
        }
        if (firstInvalid) {
            firstInvalid.focus();
            return;
        }

        var item = makeEntry(textValue, doctorName + ' · ' + formatDate(todayKey()));
        if (icdValue !== '') {
            var code = document.createElement('span');
            code.className = 'records-code';
            code.title = 'ICD-10 code';
            code.textContent = 'ICD-10 ' + icdValue;
            item.appendChild(code);
        }

        var list = detail.querySelector('[data-records-list="diagnoses"]');
        list.insertBefore(item, list.firstChild);
        updateCount('diagnoses');
        form.reset();
        closeDetails(form);
        showNotice('Demo only: the diagnosis was added to case #' + caseId + ' on this page. Nothing was saved.', 'success');
    }

    function submitTreatment(form) {
        var desc = form.elements.treatment_desc;
        var date = form.elements.treatment_date;
        var descValue = desc.value.trim();
        var dateValue = date.value;
        var firstInvalid = null;

        setError(desc, '');
        setError(date, '');

        if (descValue === '') {
            setError(desc, 'Describe the treatment.');
            firstInvalid = desc;
        } else if (descValue.length > 1000) {
            setError(desc, 'Use at most 1000 characters.');
            firstInvalid = desc;
        }
        if (!isValidDateKey(dateValue)) {
            setError(date, 'Choose a start date.');
            firstInvalid = firstInvalid || date;
        } else if (dateValue < date.min || dateValue > date.max) {
            setError(date, 'The start date must be between the case opening date and today.');
            firstInvalid = firstInvalid || date;
        }
        if (firstInvalid) {
            firstInvalid.focus();
            return;
        }

        var item = makeEntry(descValue, doctorName + ' · Started ' + formatDate(dateValue));
        var side = document.createElement('div');
        var status = document.createElement('span');
        var button = document.createElement('button');

        item.setAttribute('data-treatment-status', 'active');
        item.querySelector('.records-entry__meta').setAttribute('data-records-treatment-meta', '');
        side.className = 'records-entry__side';
        status.setAttribute('data-records-treatment-status', '');
        status.appendChild(makeBadge('Active', 'info'));
        button.type = 'button';
        button.className = 'btn btn--outline btn--sm';
        button.textContent = 'Mark inactive';
        button.setAttribute('data-records-editable', '');
        button.setAttribute('data-records-deactivate', 'new');
        button.setAttribute('data-records-label', descValue);
        side.appendChild(status);
        side.appendChild(button);
        item.appendChild(side);

        var list = detail.querySelector('[data-records-list="treatments"]');
        list.insertBefore(item, list.firstChild);
        updateCount('treatments');
        updateCloseState();
        form.reset();
        closeDetails(form);
        showNotice('Demo only: the treatment was added to case #' + caseId + ' on this page. Nothing was saved.', 'success');
    }

    function submitTier(form) {
        var select = form.elements.tier_color;
        var value = select.value;

        if (tiers.indexOf(value) === -1) {
            return;
        }

        var label = select.options[select.selectedIndex].textContent.trim();
        var badges = Array.prototype.slice.call(detail.querySelectorAll('.records-detail__title [data-records-tier]'));
        var listItem = document.querySelector('.records-case[aria-current="true"] [data-records-tier]');
        if (listItem) {
            badges.push(listItem);
        }

        badges.forEach(function (badge) {
            badge.className = 'tier-badge tier-badge--' + value;
            badge.textContent = label;
        });

        showNotice('Demo only: case #' + caseId + ' is shown as ' + label + ' on this page. Nothing was saved.', 'success');
    }

    function submitClose(form) {
        var button = form.querySelector('button[type="submit"]');
        if (button.disabled) {
            return;
        }
        if (!window.confirm('Close case #' + caseId + '? A closed case is read-only.')) {
            return;
        }

        var state = detail.querySelector('[data-records-state]');
        state.replaceChildren(makeBadge('Closed', 'neutral'));

        Array.prototype.slice.call(detail.querySelectorAll('[data-records-editable]')).forEach(function (node) {
            node.hidden = true;
        });

        showNotice('Demo only: case #' + caseId + ' is shown as closed on this page. Nothing was saved.', 'warning');
    }

    detail.addEventListener('submit', function (event) {
        var form = event.target.closest('[data-records-form]');
        if (!form) {
            return;
        }
        event.preventDefault();

        var kind = form.getAttribute('data-records-form');
        if (kind === 'diagnosis') {
            submitDiagnosis(form);
        } else if (kind === 'treatment') {
            submitTreatment(form);
        } else if (kind === 'tier') {
            submitTier(form);
        } else if (kind === 'close') {
            submitClose(form);
        }
    });

    detail.addEventListener('click', function (event) {
        var button = event.target.closest('[data-records-deactivate]');
        if (!button) {
            return;
        }

        var label = button.getAttribute('data-records-label');
        if (!window.confirm('Mark this treatment as inactive?\n\n' + label)) {
            return;
        }

        var item = button.closest('[data-treatment-status]');
        var meta = item.querySelector('[data-records-treatment-meta]');

        item.setAttribute('data-treatment-status', 'inactive');
        item.querySelector('[data-records-treatment-status]').replaceChildren(makeBadge('Inactive', 'neutral'));
        meta.textContent = meta.textContent.replace(/\s+/g, ' ').trim() + ' · Ended ' + formatDate(todayKey());
        button.remove();
        updateCloseState();

        showNotice('Demo only: the treatment is shown as inactive on this page. Nothing was saved.', 'info');
    });

    detail.addEventListener('input', function (event) {
        var control = event.target;
        if (control.getAttribute('aria-invalid') === 'true') {
            setError(control, '');
        }
    });
}());
