(function () {
    'use strict';

    var notice = document.getElementById('appointments-notice');
    var locale = document.documentElement.lang || 'en';
    var messages = {};

    if (locale === 'en') {
        locale = 'en-GB';
    }

    try {
        messages = JSON.parse((notice && notice.dataset.messages) || '{}');
    } catch (error) {
        messages = {};
    }

    function msg(key, params) {
        var text = Object.prototype.hasOwnProperty.call(messages, key) ? messages[key] : key;
        params = params || {};
        return text.replace(/\{(\w+)\}/g, function (match, name) {
            return Object.prototype.hasOwnProperty.call(params, name) ? String(params[name]) : match;
        });
    }

    function capitalize(text) {
        return text.charAt(0).toLocaleUpperCase(locale) + text.slice(1);
    }

    function showNotice(message, type) {
        if (!notice) return;
        notice.className = 'alert alert--' + (type || 'info') + ' appointments-notice';
        notice.textContent = message;
        notice.hidden = false;
        notice.scrollIntoView({ block: 'nearest' });
    }

    var flashRaw = sessionStorage.getItem('chn_appointments_flash');
    if (flashRaw) {
        sessionStorage.removeItem('chn_appointments_flash');
        try {
            var flash = JSON.parse(flashRaw);
            showNotice(flash.message, flash.type || 'success');
        } catch (e) {
            showNotice(flashRaw, 'success');
        }
    }

    function pad(value) {
        return value < 10 ? '0' + value : String(value);
    }

    function toKey(date) {
        return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
    }

    function fromKey(key) {
        var parts = key.split('-');
        return new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
    }

    function formatDay(key) {
        return fromKey(key).toLocaleDateString(locale, { weekday: 'short', day: 'numeric', month: 'short' });
    }

    async function postJson(url, payload) {
        var response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(payload)
        });
        var body = null;
        try {
            body = await response.json();
        } catch (e) {
            body = null;
        }
        return { ok: response.ok && body && body.ok === true, body: body };
    }

    function initBooking() {
        var form = document.getElementById('appointments-form');
        if (!form) return;

        var doctorHint = document.getElementById('appointments-doctor-hint');
        var doctorChoices = Array.prototype.slice.call(document.querySelectorAll('#appointments-doctors .appointments-choice'));
        var caseInputs = Array.prototype.slice.call(form.querySelectorAll('input[name="case_id"]'));
        var calendar = document.getElementById('appointments-calendar');
        var dateInput = document.getElementById('appointments-date');
        var slotInputs = Array.prototype.slice.call(form.querySelectorAll('input[name="appointment_time"]'));
        var slotsHint = document.getElementById('appointments-slots-hint');
        var summary = document.getElementById('appointments-summary');
        var submit = document.getElementById('appointments-submit');
        var appointmentId = document.getElementById('appointments-appointment-id');
        var title = document.getElementById('appointments-book-title');
        var subtitle = document.getElementById('appointments-book-subtitle');
        var resetButton = document.getElementById('appointments-book-reset');
        var defaults = {
            title: title.textContent,
            subtitle: subtitle.textContent,
            submit: submit.textContent
        };
        var errors = {
            doctor: document.getElementById('appointments-doctor-error'),
            caseId: document.getElementById('appointments-case-error'),
            date: document.getElementById('appointments-date-error'),
            time: document.getElementById('appointments-time-error')
        };

        var minKey = calendar.getAttribute('data-min');
        var maxKey = calendar.getAttribute('data-max');
        var todayKey = toKey(new Date());
        var viewMonth = fromKey(minKey);
        var renderCalendar = function () {};
        var slotRequestSeq = 0;
        viewMonth.setDate(1);

        function checked(name) {
            return form.querySelector('input[name="' + name + '"]:checked');
        }

        function setError(key, message, control) {
            errors[key].textContent = message;
            errors[key].hidden = message === '';
            if (control) {
                control.setAttribute('aria-invalid', message === '' ? 'false' : 'true');
            }
        }

        function clearErrors() {
            setError('doctor', '');
            setError('caseId', '');
            setError('date', '');
            setError('time', '');
        }

        async function updateSlots() {
            var doctor = checked('doctor_id');
            var dateKey = dateInput.value;
            var ready = doctor !== null && dateKey !== '';

            slotsHint.hidden = ready;
            if (!ready) {
                slotInputs.forEach(function (input) {
                    input.disabled = true;
                    input.checked = false;
                });
                return;
            }

            var seq = ++slotRequestSeq;
            var slotsUrl = form.dataset.slotsUrl + '?doctor_id=' + encodeURIComponent(doctor.value) + '&date=' + encodeURIComponent(dateKey);
            if (appointmentId.value) {
                slotsUrl += '&exclude_appointment_id=' + encodeURIComponent(appointmentId.value);
            }

            try {
                var res = await fetch(slotsUrl, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
                var body = await res.json();
                if (seq !== slotRequestSeq) return;
                var taken = (res.ok && body && body.ok && Array.isArray(body.data.taken_slots)) ? body.data.taken_slots : [];
                slotInputs.forEach(function (input) {
                    input.disabled = taken.indexOf(input.value) !== -1;
                    if (input.disabled) input.checked = false;
                });
            } catch (e) {
                if (seq !== slotRequestSeq) return;
                slotInputs.forEach(function (input) { input.disabled = false; });
            }
        }

        function updateCase() {
            var caseId = checked('case_id');
            var value = caseId ? caseId.getAttribute('data-department') : '';
            var visibleDoctors = 0;

            doctorChoices.forEach(function (choice) {
                var show = value !== '' && choice.getAttribute('data-department') === value;
                choice.hidden = !show;
                if (show) {
                    visibleDoctors += 1;
                } else {
                    choice.querySelector('input').checked = false;
                }
            });

            doctorHint.textContent = value === ''
                ? msg('doctorHint')
                : msg('doctorsFound', { count: visibleDoctors });

            updateSlots();
            updateSummary();
        }

        function updateSummary() {
            var parts = [];
            var doctor = checked('doctor_id');
            var caseId = checked('case_id');
            var time = checked('appointment_time');

            if (caseId) parts.push(caseId.getAttribute('data-label'));
            if (doctor) parts.push(doctor.getAttribute('data-label'));
            if (dateInput.value !== '') parts.push(formatDay(dateInput.value));
            if (time) parts.push(time.value);

            summary.textContent = parts.length ? parts.join(' · ') : msg('nothingSelected');
        }

        function buildCalendar() {
            var group = dateInput.closest('.form-group');
            var header = document.createElement('div');
            var monthTitle = document.createElement('p');
            var nav = document.createElement('div');
            var table = document.createElement('table');
            var head = document.createElement('thead');
            var headRow = document.createElement('tr');
            var body = document.createElement('tbody');
            var weekdays = [];

            for (var w = 0; w < 7; w += 1) {
                var weekdayDate = new Date(2024, 0, 1 + w);
                weekdays.push([
                    weekdayDate.toLocaleDateString(locale, { weekday: 'short' }),
                    weekdayDate.toLocaleDateString(locale, { weekday: 'long' })
                ]);
            }

            function arrow(label, icon) {
                var button = document.createElement('button');
                var span = document.createElement('span');
                button.type = 'button';
                button.className = 'appointments-calendar__arrow';
                button.setAttribute('aria-label', label);
                span.className = 'icon icon--' + icon;
                span.setAttribute('aria-hidden', 'true');
                button.appendChild(span);
                return button;
            }

            var prev = arrow(msg('prevMonth'), 'chevron-left');
            var next = arrow(msg('nextMonth'), 'chevron-right');

            dateInput.type = 'hidden';
            calendar.insertBefore(dateInput, group);
            group.hidden = true;

            header.className = 'appointments-calendar__header';
            monthTitle.className = 'appointments-calendar__title';
            monthTitle.id = 'appointments-calendar-title';
            monthTitle.setAttribute('aria-live', 'polite');
            nav.className = 'appointments-calendar__nav';
            nav.appendChild(prev);
            nav.appendChild(next);
            header.appendChild(monthTitle);
            header.appendChild(nav);

            table.className = 'appointments-calendar__table';
            table.setAttribute('aria-labelledby', 'appointments-calendar-title');
            weekdays.forEach(function (day) {
                var th = document.createElement('th');
                var abbr = document.createElement('abbr');
                th.scope = 'col';
                abbr.title = day[1];
                abbr.textContent = day[0];
                th.appendChild(abbr);
                headRow.appendChild(th);
            });
            head.appendChild(headRow);
            table.appendChild(head);
            table.appendChild(body);

            calendar.appendChild(header);
            calendar.appendChild(table);

            function render() {
                var year = viewMonth.getFullYear();
                var month = viewMonth.getMonth();
                var first = new Date(year, month, 1);
                var daysInMonth = new Date(year, month + 1, 0).getDate();
                var offset = (first.getDay() + 6) % 7;
                var minMonth = fromKey(minKey);
                var maxMonth = fromKey(maxKey);
                var row = document.createElement('tr');

                monthTitle.textContent = capitalize(first.toLocaleDateString(locale, { month: 'long', year: 'numeric' }));
                prev.disabled = year === minMonth.getFullYear() && month === minMonth.getMonth();
                next.disabled = year === maxMonth.getFullYear() && month === maxMonth.getMonth();

                while (body.firstChild) body.removeChild(body.firstChild);

                for (var i = 0; i < offset; i += 1) {
                    row.appendChild(document.createElement('td'));
                }

                for (var day = 1; day <= daysInMonth; day += 1) {
                    var date = new Date(year, month, day);
                    var key = toKey(date);
                    var weekday = date.getDay();
                    var cell = document.createElement('td');
                    var button = document.createElement('button');

                    button.type = 'button';
                    button.className = 'appointments-calendar__day' + (key === todayKey ? ' appointments-calendar__day--today' : '');
                    button.textContent = String(day);
                    button.setAttribute('data-date', key);
                    button.setAttribute('aria-label', date.toLocaleDateString(locale, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }));
                    button.setAttribute('aria-pressed', key === dateInput.value ? 'true' : 'false');
                    button.disabled = key < minKey || key > maxKey || weekday === 0 || weekday === 6;
                    cell.appendChild(button);
                    row.appendChild(cell);

                    if ((offset + day) % 7 === 0) {
                        body.appendChild(row);
                        row = document.createElement('tr');
                    }
                }

                if (row.children.length) {
                    while (row.children.length < 7) row.appendChild(document.createElement('td'));
                    body.appendChild(row);
                }
            }

            prev.addEventListener('click', function () {
                viewMonth.setMonth(viewMonth.getMonth() - 1);
                render();
            });

            next.addEventListener('click', function () {
                viewMonth.setMonth(viewMonth.getMonth() + 1);
                render();
            });

            body.addEventListener('click', function (event) {
                var button = event.target.closest('.appointments-calendar__day');
                if (!button || button.disabled) return;
                dateInput.value = button.getAttribute('data-date');
                setError('date', '');
                render();
                var selected = body.querySelector('[data-date="' + dateInput.value + '"]');
                if (selected) selected.focus();
                updateSlots();
                updateSummary();
            });

            renderCalendar = render;
            render();
        }

        function setRescheduleMode(button) {
            var id = button.getAttribute('data-appointment-reschedule');
            form.reset();
            clearErrors();
            dateInput.value = '';
            appointmentId.value = id;

            var caseId = form.querySelector('input[name="case_id"][value="' + button.getAttribute('data-case-id') + '"]');
            if (caseId) caseId.checked = true;
            updateCase();

            var doctor = form.querySelector('input[name="doctor_id"][value="' + button.getAttribute('data-doctor-id') + '"]');
            if (doctor) doctor.checked = true;

            title.textContent = msg('rescheduleTitle', { id: id });
            subtitle.textContent = msg('rescheduleSubtitle');
            submit.textContent = msg('rescheduleSubmit');
            resetButton.hidden = false;

            updateSlots();
            updateSummary();
            renderCalendar();
            title.scrollIntoView({ block: 'start' });
            title.focus({ preventScroll: true });
        }

        function leaveRescheduleMode() {
            form.reset();
            clearErrors();
            dateInput.value = '';
            appointmentId.value = '';
            title.textContent = defaults.title;
            subtitle.textContent = defaults.subtitle;
            submit.textContent = defaults.submit;
            resetButton.hidden = true;
            updateCase();
            renderCalendar();
        }

        form.addEventListener('change', function (event) {
            var name = event.target.name;
            if (name === 'case_id') {
                setError('caseId', '');
                setError('doctor', '');
                updateCase();
            }
            if (name === 'doctor_id') {
                setError('doctor', '');
                updateSlots();
            }
            if (name === 'appointment_time') {
                setError('time', '');
            }
            updateSummary();
        });

        resetButton.addEventListener('click', leaveRescheduleMode);

        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            if (submit.disabled) return;
            clearErrors();

            var doctor = checked('doctor_id');
            var caseId = checked('case_id');
            var time = checked('appointment_time');
            var firstInvalid = null;

            if (!caseId) {
                setError('caseId', msg('errorCase'));
                firstInvalid = firstInvalid || caseInputs[0];
            }
            if (!doctor) {
                setError('doctor', caseId ? msg('errorDoctor') : msg('errorCaseFirst'));
                firstInvalid = firstInvalid || doctorChoices.map(function (choice) {
                    return choice.hidden ? null : choice.querySelector('input');
                }).filter(Boolean)[0] || null;
            }
            if (dateInput.value === '') {
                setError('date', msg('errorDate'));
                firstInvalid = firstInvalid || calendar.querySelector('.appointments-calendar__day:not(:disabled)');
            }
            if (!time) {
                setError('time', msg('errorTime'));
                firstInvalid = firstInvalid || slotInputs.filter(function (input) { return !input.disabled; })[0] || null;
            }

            if (firstInvalid) {
                firstInvalid.focus();
                return;
            }

            var payload = {
                appointment_id: appointmentId.value ? Number(appointmentId.value) : 0,
                case_id: Number(caseId.value),
                doctor_id: Number(doctor.value),
                appointment_date: dateInput.value,
                appointment_time: time.value
            };

            submit.disabled = true;
            submit.setAttribute('aria-busy', 'true');

            try {
                var res = await postJson(form.dataset.bookUrl, payload);
                if (res.ok) {
                    var params = {
                        id: appointmentId.value || (res.body.data && res.body.data.appointment_id),
                        date: formatDay(dateInput.value),
                        time: time.value,
                        doctor: doctor.getAttribute('data-label')
                    };
                    var successMsg = appointmentId.value !== ''
                        ? msg('noticeReschedule', params)
                        : msg('noticeBook', params);
                    sessionStorage.setItem('chn_appointments_flash', JSON.stringify({ message: successMsg, type: 'success' }));
                    window.location.assign('appointments.php?view=upcoming#appointments-list');
                    return;
                }
                showNotice((res.body && res.body.message) || msg('requestFailed'), 'danger');
            } catch (e) {
                showNotice(msg('requestFailed'), 'danger');
            } finally {
                submit.disabled = false;
                submit.removeAttribute('aria-busy');
            }
        });

        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-appointment-reschedule]');
            if (button) setRescheduleMode(button);
        });

        buildCalendar();
        updateCase();
    }

    function initRowActions() {
        document.addEventListener('click', async function (event) {
            var cancel = event.target.closest('[data-appointment-cancel]');
            var completeBtn = event.target.closest('[data-appointment-complete]');

            if (cancel) {
                var id = Number(cancel.getAttribute('data-appointment-cancel'));
                if (!window.confirm(cancel.getAttribute('data-appointment-confirm'))) return;
                cancel.disabled = true;
                try {
                    var res = await postJson('../backend/manage_appointment.php', { action: 'cancel', appointment_id: id });
                    if (res.ok) {
                        sessionStorage.setItem('chn_appointments_flash', JSON.stringify({ message: msg('noticeCancel', { id: id }), type: 'warning' }));
                        window.location.reload();
                        return;
                    }
                    showNotice((res.body && res.body.message) || msg('requestFailed'), 'danger');
                } catch (e) {
                    showNotice(msg('requestFailed'), 'danger');
                } finally {
                    cancel.disabled = false;
                }
            }

            if (completeBtn) {
                var compId = Number(completeBtn.getAttribute('data-appointment-complete'));
                completeBtn.disabled = true;
                try {
                    var compRes = await postJson('../backend/manage_appointment.php', { action: 'complete', appointment_id: compId });
                    if (compRes.ok) {
                        sessionStorage.setItem('chn_appointments_flash', JSON.stringify({ message: msg('noticeStart', { id: compId }), type: 'info' }));
                        window.location.reload();
                        return;
                    }
                    showNotice((compRes.body && compRes.body.message) || msg('requestFailed'), 'danger');
                } catch (e) {
                    showNotice(msg('requestFailed'), 'danger');
                } finally {
                    completeBtn.disabled = false;
                }
            }
        });
    }

    initBooking();
    initRowActions();
}());
