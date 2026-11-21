(function () {
    'use strict';

    var notice = document.getElementById('users-notice');
    var formCard = document.getElementById('users-form');
    var form = document.getElementById('users-account-form');
    var namePattern = /^\p{L}[\p{L}\p{M}\s'.-]*$/u;
    var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    var phonePattern = /^\+?[0-9\s()-]+$/;
    var documentPattern = /^[A-Za-z0-9\s-]+$/;
    var messages = readMessages();

    function readMessages() {
        try {
            return JSON.parse((notice && notice.dataset.messages) || '{}');
        } catch (error) {
            return {};
        }
    }

    function msg(key, params) {
        var text = messages[key] || key;

        params = params || {};
        return text.replace(/\{(\w+)\}/g, function (match, name) {
            return name in params ? String(params[name]) : match;
        });
    }

    function showNotice(message) {
        if (!notice) {
            return;
        }
        notice.textContent = message;
        notice.hidden = false;
        notice.scrollIntoView({ block: 'nearest' });
        notice.focus({ preventScroll: true });
    }

    function isValidTcKimlikNo(value) {
        if (!/^[1-9][0-9]{10}$/.test(value)) {
            return false;
        }

        var digits = value.split('').map(Number);
        var odd = digits[0] + digits[2] + digits[4] + digits[6] + digits[8];
        var even = digits[1] + digits[3] + digits[5] + digits[7];
        var tenth = (((odd * 7 - even) % 10) + 10) % 10;
        var eleventh = digits.slice(0, 10).reduce(function (sum, digit) {
            return sum + digit;
        }, 0) % 10;

        return digits[9] === tenth && digits[10] === eleventh;
    }

    function initForm() {
        if (!form) {
            return;
        }

        var role = form.elements.user_role;
        var hospital = form.elements.hospital_id;
        var department = form.elements.department_id;
        var nationality = form.elements.nationality;
        var nationalId = form.elements.national_id;
        var password = form.elements.password;
        var passwordConfirm = form.elements.password_confirm;
        var workplaceGroups = Array.prototype.slice.call(form.querySelectorAll('[data-users-workplace]'));
        var departmentOptions = Array.prototype.slice.call(department.options);
        var toggles = Array.prototype.slice.call(form.querySelectorAll('.users-form__toggle'));

        function hasWorkplace() {
            return role.value === 'doctor' || role.value === 'healthcare_staff';
        }

        function setError(control, message) {
            var error = document.getElementById(control.id + '-error');

            error.textContent = message;
            error.hidden = message === '';
            if (message) {
                control.setAttribute('aria-invalid', 'true');
            } else {
                control.removeAttribute('aria-invalid');
            }
        }

        function updateDepartments() {
            departmentOptions.forEach(function (option) {
                var visible = option.value === '' || (hospital.value !== '' && option.dataset.hospitalId === hospital.value);

                option.hidden = !visible;
                option.disabled = !visible;
                if (!visible && option.selected) {
                    department.value = '';
                }
            });
        }

        function updateWorkplace() {
            var visible = hasWorkplace();

            workplaceGroups.forEach(function (group) {
                group.hidden = !visible;
            });
            hospital.disabled = !visible;
            department.disabled = !visible;
            if (!visible) {
                setError(hospital, '');
                setError(department, '');
            }
            updateDepartments();
        }

        function updateNationalIdKeyboard() {
            if (nationality.value === 'TR') {
                nationalId.setAttribute('inputmode', 'numeric');
            } else {
                nationalId.removeAttribute('inputmode');
            }
        }

        function nameError(value, field) {
            if (!value) {
                return msg(field + '_required');
            }
            return namePattern.test(value) ? '' : msg(field + '_invalid');
        }

        function nationalIdError(value) {
            var clean = value.replace(/[\s-]/g, '');

            if (!value) {
                return msg('national_id_required');
            }
            if (nationality.value === 'TR') {
                return isValidTcKimlikNo(value.replace(/\s/g, '')) ? '' : msg('tc_invalid');
            }
            return documentPattern.test(value) && clean.length >= 5 && clean.length <= 50
                ? ''
                : msg('national_id_invalid');
        }

        var checks = [
            [role, function (value) {
                return value ? '' : msg('role_required');
            }],
            [form.elements.first_name, function (value) {
                return nameError(value, 'first_name');
            }],
            [form.elements.last_name, function (value) {
                return nameError(value, 'last_name');
            }],
            [form.elements.email, function (value) {
                if (!value) {
                    return msg('email_required');
                }
                return value.length <= 254 && emailPattern.test(value) ? '' : msg('email_invalid');
            }],
            [hospital, function (value) {
                return value ? '' : msg('hospital_required');
            }],
            [department, function (value) {
                return value ? '' : msg('department_required');
            }],
            [form.elements.phone, function (value) {
                var digits = value.replace(/\D/g, '');
                var length = digits.length + (value.charAt(0) === '+' ? 1 : 0);

                if (!value) {
                    return msg('phone_required');
                }
                return phonePattern.test(value) && digits.length >= 7 && length <= 16 ? '' : msg('phone_invalid');
            }],
            [form.elements.birth_date, function (value, control) {
                if (control.validity.badInput) {
                    return msg('birth_date_invalid');
                }
                if (!value) {
                    return msg('birth_date_required');
                }
                return control.validity.valid ? '' : msg('birth_date_invalid');
            }],
            [nationality, function (value) {
                return value ? '' : msg('nationality_required');
            }],
            [nationalId, nationalIdError],
            [password, function () {
                var value = password.value;

                if (!value) {
                    return msg('password_required');
                }
                return value.length >= 8 && /\p{L}/u.test(value) && /\d/.test(value) ? '' : msg('password_weak');
            }],
            [passwordConfirm, function () {
                if (!passwordConfirm.value) {
                    return msg('password_confirm_required');
                }
                return passwordConfirm.value === password.value ? '' : msg('password_mismatch');
            }]
        ];

        function validate() {
            var firstInvalid = null;

            checks.forEach(function (check) {
                var control = check[0];
                var message = control.disabled ? '' : check[1](control.value.trim(), control);

                setError(control, message);
                if (message && !firstInvalid) {
                    firstInvalid = control;
                }
            });

            return firstInvalid;
        }

        role.addEventListener('change', function () {
            updateWorkplace();
            setError(role, '');
        });
        hospital.addEventListener('change', function () {
            updateDepartments();
            setError(hospital, '');
        });
        nationality.addEventListener('change', function () {
            updateNationalIdKeyboard();
            if (nationalId.hasAttribute('aria-invalid')) {
                setError(nationalId, nationalIdError(nationalId.value.trim()));
            }
        });

        checks.forEach(function (check) {
            var control = check[0];

            control.addEventListener('input', function () {
                if (control.hasAttribute('aria-invalid')) {
                    setError(control, check[1](control.value.trim(), control));
                }
            });
        });

        toggles.forEach(function (toggle) {
            var input = document.getElementById(toggle.getAttribute('aria-controls'));
            var icon = toggle.querySelector('.icon');

            toggle.addEventListener('click', function () {
                var show = input.type === 'password';

                input.type = show ? 'text' : 'password';
                toggle.setAttribute('aria-pressed', show ? 'true' : 'false');
                toggle.setAttribute('aria-label', show ? msg('hide_password') : msg('show_password'));
                icon.classList.toggle('icon--eye', !show);
                icon.classList.toggle('icon--eye-slash', show);
            });
        });

        form.addEventListener('submit', function (event) {
            var firstInvalid = validate();
            var name = (form.elements.first_name.value.trim() + ' ' + form.elements.last_name.value.trim()).trim();
            var roleLabel = role.options[role.selectedIndex] ? role.options[role.selectedIndex].textContent.trim() : '';

            event.preventDefault();
            if (firstInvalid) {
                firstInvalid.focus();
                return;
            }

            password.value = '';
            passwordConfirm.value = '';
            formCard.hidden = true;
            showNotice(msg('create_notice', { name: name, role: roleLabel, email: form.elements.email.value.trim() }));
        });

        updateWorkplace();
        updateNationalIdKeyboard();
    }

    function initDelete() {
        var forms = Array.prototype.slice.call(document.querySelectorAll('[data-users-delete]'));
        var table = document.getElementById('users-table');
        var empty = document.getElementById('users-empty');

        function decrease(key) {
            var counter = document.querySelector('[data-users-count="' + key + '"]');

            if (counter) {
                counter.textContent = String(Math.max(0, Number(counter.textContent) - 1));
            }
        }

        forms.forEach(function (deleteForm) {
            deleteForm.addEventListener('submit', function (event) {
                var row = deleteForm.closest('[data-users-row]');
                var name = deleteForm.dataset.usersName;

                event.preventDefault();
                if (!window.confirm(msg('delete_confirm', { name: name }))) {
                    return;
                }

                decrease('shown');
                decrease('all');
                decrease(row.dataset.usersRole);
                row.parentNode.removeChild(row);

                if (!table.querySelector('[data-users-row]')) {
                    table.hidden = true;
                    empty.hidden = false;
                }

                showNotice(msg('delete_notice', { name: name }));
            });
        });
    }

    initForm();
    initDelete();
})();
