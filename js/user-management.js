(function () {
    'use strict';

    var notice = document.getElementById('users-notice');
    var formCard = document.getElementById('users-form');
    var form = document.getElementById('users-account-form');
    var namePattern = /^\p{L}[\p{L}\p{M}\s'.-]*$/u;
    var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    var phonePattern = /^\+?[0-9\s()-]+$/;
    var documentPattern = /^[A-Za-z0-9\s-]+$/;

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

        function nameError(value, label) {
            if (!value) {
                return 'Please enter the ' + label + '.';
            }
            return namePattern.test(value) ? '' : 'Please enter a valid ' + label + '.';
        }

        function nationalIdError(value) {
            var clean = value.replace(/[\s-]/g, '');

            if (!value) {
                return 'Please enter a national ID or passport number.';
            }
            if (nationality.value === 'TR') {
                return isValidTcKimlikNo(value.replace(/\s/g, '')) ? '' : 'Please enter a valid T.C. Kimlik No.';
            }
            return documentPattern.test(value) && clean.length >= 5 && clean.length <= 50
                ? ''
                : 'Please enter a valid national ID or passport number.';
        }

        var checks = [
            [role, function (value) {
                return value ? '' : 'Please select a role.';
            }],
            [form.elements.first_name, function (value) {
                return nameError(value, 'first name');
            }],
            [form.elements.last_name, function (value) {
                return nameError(value, 'last name');
            }],
            [form.elements.email, function (value) {
                if (!value) {
                    return 'Please enter an email address.';
                }
                return value.length <= 254 && emailPattern.test(value) ? '' : 'Please enter a valid email address.';
            }],
            [hospital, function (value) {
                return value ? '' : 'Please select a hospital.';
            }],
            [department, function (value) {
                return value ? '' : 'Please select a department.';
            }],
            [form.elements.phone, function (value) {
                var digits = value.replace(/\D/g, '');
                var length = digits.length + (value.charAt(0) === '+' ? 1 : 0);

                if (!value) {
                    return 'Please enter a phone number.';
                }
                return phonePattern.test(value) && digits.length >= 7 && length <= 16 ? '' : 'Please enter a valid phone number.';
            }],
            [form.elements.birth_date, function (value, control) {
                if (control.validity.badInput) {
                    return 'Please enter a valid date of birth.';
                }
                if (!value) {
                    return 'Please enter the date of birth.';
                }
                return control.validity.valid ? '' : 'Please enter a valid date of birth.';
            }],
            [nationality, function (value) {
                return value ? '' : 'Please select a nationality.';
            }],
            [nationalId, nationalIdError],
            [password, function () {
                var value = password.value;

                if (!value) {
                    return 'Please enter a password.';
                }
                return value.length >= 8 && /\p{L}/u.test(value) && /\d/.test(value) ? '' : 'Password does not meet the requirements.';
            }],
            [passwordConfirm, function () {
                if (!passwordConfirm.value) {
                    return 'Please confirm the password.';
                }
                return passwordConfirm.value === password.value ? '' : 'Passwords do not match.';
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
                toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
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
            showNotice('Demo only: an account for ' + name + ' (' + roleLabel + ') with the email ' + form.elements.email.value.trim()
                + ' would be created. Nothing was saved.');
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
                if (!window.confirm('Delete the account of ' + name + '? This cannot be undone.')) {
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

                showNotice('Demo only: ' + name + ' was removed from this page. Nothing was deleted.');
            });
        });
    }

    initForm();
    initDelete();
})();
