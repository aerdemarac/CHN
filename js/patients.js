(function () {
    'use strict';

    var form = document.getElementById('patients-form');
    if (!form) return;

    var input = document.getElementById('patients-q');
    var label = document.getElementById('patients-q-label');
    var hint = document.getElementById('patients-q-hint');
    var error = document.getElementById('patients-q-error');
    var submitBtn = document.getElementById('patients-submit');
    var clearBtn = document.getElementById('patients-clear');
    var resultsSection = document.getElementById('patients-results');
    var radios = Array.prototype.slice.call(form.querySelectorAll('input[name="search_by"]'));

    var messages = {};
    try {
        messages = JSON.parse(form.dataset.messages || '{}');
    } catch (e) {
        messages = {};
    }

    var tests = {
        phone: function (value) {
            var digits = value.replace(/\D/g, '');
            return /^\+?[0-9\s()-]+$/.test(value) && digits.length >= 7 && digits.length <= 15;
        },
        email: function (value) {
            return value.length <= 254 && /^[A-Za-z0-9._%+\-@]+$/.test(value) && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
        },
        national_id: function (value) {
            var clean = value.replace(/[\s-]/g, '');
            return /^[A-Za-z0-9\s-]+$/.test(value) && clean.length >= 5 && clean.length <= 50;
        },
        name: function (value) {
            var clean = value.replace(/\s+/g, ' ');
            return /^[A-Za-zçÇğĞıİöÖşŞüÜ][A-Za-zçÇğĞıİöÖşŞüÜ '.-]*$/.test(clean) && clean.length >= 2 && clean.length <= 100;
        }
    };

    function msg(key, params) {
        var text = messages[key] || key;
        params = params || {};
        return text.replace(/\{(\w+)\}/g, function (m, k) {
            return k in params ? String(params[k]) : m;
        });
    }

    function selectedType() {
        for (var i = 0; i < radios.length; i += 1) {
            if (radios[i].checked) return radios[i];
        }
        return radios[0];
    }

    function showError(message) {
        error.textContent = message;
        error.hidden = message === '';
        if (message) {
            input.setAttribute('aria-invalid', 'true');
        } else {
            input.removeAttribute('aria-invalid');
        }
    }

    function applyType(radio) {
        label.textContent = radio.dataset.label;
        hint.textContent = radio.dataset.hint;
        input.type = radio.dataset.type;
        input.setAttribute('inputmode', radio.dataset.inputmode);
    }

    function validate() {
        var radio = selectedType();
        var value = input.value.trim();
        var test = Object.prototype.hasOwnProperty.call(tests, radio.value) ? tests[radio.value] : null;
        if (!value) return radio.dataset.empty;
        return test && test(value) ? '' : radio.dataset.invalid;
    }

    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined && text !== null) node.textContent = text;
        return node;
    }

    function formatDate(isoDate) {
        if (!isoDate) return '';
        var dt = new Date(isoDate + 'T00:00:00');
        if (Number.isNaN(dt.getTime())) return isoDate;
        return dt.toLocaleDateString(document.documentElement.lang || 'en', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }

    function renderResults(patients, radio) {
        resultsSection.textContent = '';
        resultsSection.hidden = false;

        if (!patients || patients.length === 0) {
            var emptyDiv = el('div', 'empty-state');
            var emptyTitle = el('h2', 'empty-state__title', msg('no_result_title'));
            emptyTitle.id = 'patients-results-title';
            var tpl = form.dataset.noResultTpl || '';
            var emptyText = el('p', 'empty-state__text', tpl.replace('{field}', radio.dataset.field || ''));
            emptyDiv.appendChild(emptyTitle);
            emptyDiv.appendChild(emptyText);
            resultsSection.appendChild(emptyDiv);
            return;
        }

        var header = el('header', 'card__header');
        var countText = patients.length === 1
            ? msg('found_one', { count: 1 })
            : msg('found_other', { count: patients.length });
        var title = el('h2', 'card__title', countText);
        title.id = 'patients-results-title';
        header.appendChild(title);
        resultsSection.appendChild(header);

        var role = form.dataset.role || 'doctor';
        var casesLabel = form.dataset.casesLabel || 'Cases';

        patients.forEach(function (patient) {
            var fullName = (patient.name + ' ' + patient.surname).trim();
            var initials = ((patient.name || '').charAt(0) + (patient.surname || '').charAt(0)).toUpperCase() || 'P';

            var article = el('article', 'patients-result');
            article.setAttribute('aria-labelledby', 'patients-result-' + patient.patient_id);

            var artHeader = el('header', 'patients-result__header');
            var avatar = el('span', 'patients-result__avatar', initials);
            avatar.setAttribute('aria-hidden', 'true');
            var headInfo = el('div');
            var nameEl = el('h3', 'patients-result__name', fullName);
            nameEl.id = 'patients-result-' + patient.patient_id;

            var metaEl = el('p', 'patients-result__meta');
            if (patient.birth_date && patient.age !== null) {
                var ageTpl = patient.age === 1 ? msg('age_one', { count: patient.age }) : msg('age_other', { count: patient.age });
                metaEl.textContent = ageTpl.replace('{date}', formatDate(patient.birth_date));
            } else {
                metaEl.textContent = msg('birth_missing');
            }

            headInfo.appendChild(nameEl);
            headInfo.appendChild(metaEl);
            artHeader.appendChild(avatar);
            artHeader.appendChild(headInfo);
            article.appendChild(artHeader);

            var dl = el('dl', 'patients-result__list');

            function addRow(iconClass, termText, buildValue) {
                var item = el('div', 'patients-result__item');
                var dt = el('dt', 'patients-result__term');
                var icon = el('span', 'icon ' + iconClass);
                icon.setAttribute('aria-hidden', 'true');
                dt.appendChild(icon);
                dt.appendChild(document.createTextNode(termText));
                var dd = el('dd', 'patients-result__value');
                buildValue(dd);
                item.appendChild(dt);
                item.appendChild(dd);
                dl.appendChild(item);
            }

            addRow('icon--phone', msg('phone'), function (dd) {
                if (patient.phone) {
                    var a = el('a', 'patients-result__link', patient.phone);
                    a.href = 'tel:' + patient.phone.replace(/[^0-9+]/g, '');
                    dd.appendChild(a);
                } else {
                    dd.textContent = msg('not_provided');
                }
            });

            addRow('icon--envelope', msg('email'), function (dd) {
                var a = el('a', 'patients-result__link', patient.email);
                a.href = 'mailto:' + patient.email;
                dd.appendChild(a);
            });

            addRow('icon--geo', msg('address'), function (dd) {
                dd.textContent = patient.full_address || msg('not_provided');
            });

            addRow('icon--cases', casesLabel, function (dd) {
                var ul = el('ul', 'patients-result__cases');
                (patient.cases || []).forEach(function (c) {
                    var li = el('li');
                    var labelText = '#' + c.case_id + ' ';
                    if (role === 'healthcare_staff' && c.department_label) {
                        labelText += '· ' + c.department_label + ' ';
                    }
                    li.appendChild(document.createTextNode(labelText));
                    var badgeClass = c.case_state === 'open' ? 'badge badge--success' : 'badge badge--neutral';
                    var badgeText = c.case_state === 'open' ? msg('state_open') : msg('state_closed');
                    li.appendChild(el('span', badgeClass, badgeText));
                    ul.appendChild(li);
                });
                dd.appendChild(ul);
            });

            article.appendChild(dl);
            resultsSection.appendChild(article);
        });
    }

    radios.forEach(function (radio) {
        radio.addEventListener('change', function () {
            applyType(radio);
            showError('');
        });
    });

    input.addEventListener('input', function () {
        if (!error.hidden || selectedType().value === 'name') {
            showError(validate());
        }
    });

    input.addEventListener('paste', function () {
        setTimeout(function () {
            showError(validate());
        }, 0);
    });

    async function fetchPatients(searchBy, queryText, scrollToResults) {
        var searchUrl = form.dataset.searchUrl;
        if (!searchUrl) return;

        submitBtn.disabled = true;
        submitBtn.setAttribute('aria-busy', 'true');

        try {
            var response = await fetch(searchUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    search_by: searchBy,
                    q: queryText
                })
            });

            var body = null;
            try {
                body = await response.json();
            } catch (e) {
                body = null;
            }

            if (response.ok && body && body.ok === true) {
                renderResults(body.data.patients || [], selectedType());
                if (scrollToResults) {
                    resultsSection.scrollIntoView({ block: 'nearest' });
                }
                return;
            }

            showError((body && body.message) || selectedType().dataset.invalid);
        } catch (e) {
            showError(selectedType().dataset.invalid);
        } finally {
            submitBtn.disabled = false;
            submitBtn.removeAttribute('aria-busy');
        }
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            input.value = '';
            showError('');
            fetchPatients(selectedType().value, '', false);
            input.focus();
        });
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        if (submitBtn.disabled) return;

        var message = validate();
        showError(message);
        if (message) {
            input.focus();
            return;
        }

        fetchPatients(selectedType().value, input.value.trim(), true);
    });

    // Load all associated patients by default on initial page load.
    fetchPatients(selectedType().value, '', false);
})();
