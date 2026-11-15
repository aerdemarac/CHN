(function () {
    'use strict';

    var notice = document.getElementById('appointments-notice');

    function showNotice(message, type) {
        if (!notice) {
            return;
        }
        notice.className = 'alert alert--' + (type || 'info') + ' appointments-notice';
        notice.textContent = message;
        notice.hidden = false;
        notice.scrollIntoView({ block: 'nearest' });
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
        return fromKey(key).toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' });
    }

    function initBooking() {
        var form = document.getElementById('appointments-form');
        if (!form) {
            return;
        }

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

        function isTaken(doctorId, dateKey, slot) {
            var text = doctorId + '|' + dateKey + '|' + slot;
            var hash = 0;
            for (var i = 0; i < text.length; i += 1) {
                hash = (hash * 31 + text.charCodeAt(i)) % 1000003;
            }
            return hash % 3 === 0;
        }

        function updateSlots() {
            var doctor = checked('doctor_id');
            var dateKey = dateInput.value;
            var ready = doctor !== null && dateKey !== '';

            slotsHint.hidden = ready;
            slotInputs.forEach(function (input) {
                input.disabled = !ready || isTaken(doctor.value, dateKey, input.value);
                if (input.disabled) {
                    input.checked = false;
                }
            });
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
                ? 'Choose a case to see the doctors of its department.'
                : visibleDoctors + (visibleDoctors === 1 ? ' doctor' : ' doctors') + ' in the department of this case.';

            updateSlots();
            updateSummary();
        }

        function updateSummary() {
            var parts = [];
            var doctor = checked('doctor_id');
            var caseId = checked('case_id');
            var time = checked('appointment_time');

            if (caseId) {
                parts.push(caseId.getAttribute('data-label'));
            }
            if (doctor) {
                parts.push(doctor.getAttribute('data-label'));
            }
            if (dateInput.value !== '') {
                parts.push(formatDay(dateInput.value));
            }
            if (time) {
                parts.push(time.value);
            }

            summary.textContent = parts.length ? parts.join(' · ') : 'Nothing selected yet.';
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
            var weekdays = [
                ['Mo', 'Monday'], ['Tu', 'Tuesday'], ['We', 'Wednesday'], ['Th', 'Thursday'],
                ['Fr', 'Friday'], ['Sa', 'Saturday'], ['Su', 'Sunday']
            ];

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

            var prev = arrow('Previous month', 'chevron-left');
            var next = arrow('Next month', 'chevron-right');

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

                monthTitle.textContent = first.toLocaleDateString('en-GB', { month: 'long', year: 'numeric' });
                prev.disabled = year === minMonth.getFullYear() && month === minMonth.getMonth();
                next.disabled = year === maxMonth.getFullYear() && month === maxMonth.getMonth();

                while (body.firstChild) {
                    body.removeChild(body.firstChild);
                }

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
                    button.setAttribute('aria-label', date.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }));
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
                    while (row.children.length < 7) {
                        row.appendChild(document.createElement('td'));
                    }
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
                if (!button || button.disabled) {
                    return;
                }
                dateInput.value = button.getAttribute('data-date');
                setError('date', '');
                render();
                var selected = body.querySelector('[data-date="' + dateInput.value + '"]');
                if (selected) {
                    selected.focus();
                }
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
            if (caseId) {
                caseId.checked = true;
            }
            updateCase();

            var doctor = form.querySelector('input[name="doctor_id"][value="' + button.getAttribute('data-doctor-id') + '"]');
            if (doctor) {
                doctor.checked = true;
            }

            title.textContent = 'Reschedule appointment #' + id;
            subtitle.textContent = 'Pick a new date and time. You can also switch to another doctor of the same department.';
            submit.textContent = 'Confirm new time';
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

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            clearErrors();

            var doctor = checked('doctor_id');
            var caseId = checked('case_id');
            var time = checked('appointment_time');
            var firstInvalid = null;

            if (!caseId) {
                setError('caseId', 'Choose one of your open cases.');
                firstInvalid = firstInvalid || caseInputs[0];
            }
            if (!doctor) {
                setError('doctor', caseId ? 'Choose a doctor.' : 'Choose a case first, then a doctor.');
                firstInvalid = firstInvalid || doctorChoices.map(function (choice) {
                    return choice.hidden ? null : choice.querySelector('input');
                }).filter(Boolean)[0] || null;
            }
            if (dateInput.value === '') {
                setError('date', 'Choose a date.');
                firstInvalid = firstInvalid || calendar.querySelector('.appointments-calendar__day:not(:disabled)');
            }
            if (!time) {
                setError('time', 'Choose a time.');
                firstInvalid = firstInvalid || slotInputs.filter(function (input) { return !input.disabled; })[0] || null;
            }

            if (firstInvalid) {
                firstInvalid.focus();
                return;
            }

            var when = formatDay(dateInput.value) + ' at ' + time.value;
            var doctorLabel = doctor.getAttribute('data-label');

            if (appointmentId.value !== '') {
                showNotice('Demo only: appointment #' + appointmentId.value + ' would move to ' + when + ' with ' + doctorLabel
                    + '. Nothing was saved because the backend is not ready yet.', 'success');
            } else {
                showNotice('Demo only: your appointment with ' + doctorLabel + ' on ' + when
                    + ' was not saved. Booking will work once the backend is ready.', 'success');
            }
        });

        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-appointment-reschedule]');
            if (button) {
                setRescheduleMode(button);
            }
        });

        buildCalendar();
        updateCase();
    }

    function initRowActions() {
        document.addEventListener('click', function (event) {
            var cancel = event.target.closest('[data-appointment-cancel]');
            var start = event.target.closest('[data-appointment-start]');

            if (cancel) {
                var id = cancel.getAttribute('data-appointment-cancel');
                var label = cancel.getAttribute('data-appointment-label');

                if (!window.confirm('Cancel the appointment on ' + label + '?')) {
                    return;
                }

                var row = cancel.closest('[data-appointment-row]');
                var statusCell = row.querySelector('[data-appointment-status]');
                var actions = row.querySelector('[data-appointment-actions]');
                var badge = document.createElement('span');
                var none = document.createElement('span');

                badge.className = 'badge badge--danger';
                badge.textContent = 'Cancelled';
                statusCell.replaceChildren(badge);

                none.className = 'appointments-actions__none';
                none.textContent = '—';
                actions.replaceChildren(none);

                showNotice('Demo only: appointment #' + id + ' is shown as cancelled on this page. Nothing was saved.', 'warning');
            }

            if (start) {
                showNotice('Demo only: the visit with ' + start.getAttribute('data-appointment-start')
                    + ' would open here once the medical records page is ready.', 'info');
            }
        });
    }

    initBooking();
    initRowActions();
}());
