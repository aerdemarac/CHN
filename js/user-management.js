(function () {
    'use strict';

    var notice = document.getElementById('users-notice');
    var formCard = document.getElementById('users-form');
    var form = document.getElementById('users-account-form');
    var formTitle = document.getElementById('users-form-title');
    var formSubtitle = document.getElementById('users-form-subtitle');
    var submitButton = document.getElementById('users-submit-form');
    var cancelButton = document.getElementById('users-cancel-form');
    var openCreateButton = document.getElementById('users-open-create');

    var namePattern = /^[A-Za-zçÇğĞıİöÖşŞüÜ][A-Za-zçÇğĞıİöÖşŞüÜ '.-]*$/;
    var emailPattern = /^[A-Za-z0-9._%+\-@]+$/;
    var emailFormatPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
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

    function showNotice(message, isError) {
        if (!notice) return;
        notice.classList.toggle('alert--danger', Boolean(isError));
        notice.classList.toggle('alert--info', !isError);
        notice.textContent = message;
        notice.hidden = false;
        notice.scrollIntoView({ block: 'nearest' });
        notice.focus({ preventScroll: true });
    }

    function isValidTcKimlikNo(value) {
        if (!/^[1-9][0-9]{10}$/.test(value)) return false;
        var digits = value.split('').map(Number);
        var odd = digits[0] + digits[2] + digits[4] + digits[6] + digits[8];
        var even = digits[1] + digits[3] + digits[5] + digits[7];
        var tenth = (((odd * 7 - even) % 10) + 10) % 10;
        var eleventh = digits.slice(0, 10).reduce(function (sum, digit) {
            return sum + digit;
        }, 0) % 10;
        return digits[9] === tenth && digits[10] === eleventh;
    }

    async function postJson(url, payload) {
        var response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
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

    var flashMessage = sessionStorage.getItem('chn_users_flash');
    if (flashMessage) {
        sessionStorage.removeItem('chn_users_flash');
        showNotice(flashMessage, false);
    }

    function initForm() {
        if (!form) return;

        var userIdInput = document.getElementById('users-user-id');
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

        function isEditMode() {
            return form.dataset.mode === 'edit';
        }

        function hasWorkplace() {
            return role.value === 'doctor' || role.value === 'healthcare_staff';
        }

        function setError(control, message) {
            var error = document.getElementById(control.id + '-error');
            if (!error) return;
            error.textContent = message;
            error.hidden = message === '';
            if (message) {
                control.setAttribute('aria-invalid', 'true');
            } else {
                control.removeAttribute('aria-invalid');
            }
        }

        function clearAllErrors() {
            Array.prototype.slice.call(form.elements).forEach(function (el) {
                if (el.id) setError(el, '');
            });
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
            if (!value) return msg(field + '_required');
            return namePattern.test(value) ? '' : msg(field + '_invalid');
        }

        function nationalIdError(value) {
            var clean = value.replace(/[\s-]/g, '');
            if (!value) {
                return isEditMode() && role.value === 'patient' ? '' : msg('national_id_required');
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
                if (isEditMode()) return '';
                return value ? '' : msg('role_required');
            }],
            [form.elements.first_name, function (value) {
                return nameError(value, 'first_name');
            }],
            [form.elements.last_name, function (value) {
                return nameError(value, 'last_name');
            }],
            [form.elements.email, function (value) {
                if (!value) return msg('email_required');
                return value.length <= 254 && emailPattern.test(value) && emailFormatPattern.test(value)
                    ? '' : msg('email_invalid');
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
                    return isEditMode() && role.value === 'patient' ? '' : msg('phone_required');
                }
                return phonePattern.test(value) && digits.length >= 7 && length <= 16 ? '' : msg('phone_invalid');
            }],
            [form.elements.birth_date, function (value, control) {
                if (control.validity.badInput) return msg('birth_date_invalid');
                if (!value) {
                    return isEditMode() && role.value === 'patient' ? '' : msg('birth_date_required');
                }
                return control.validity.valid ? '' : msg('birth_date_invalid');
            }],
            [form.elements.gender, function (value) {
                if (!value) {
                    return isEditMode() && role.value === 'patient' ? '' : msg('gender_required');
                }
                return '';
            }],
            [nationality, function (value) {
                if (!value) {
                    return isEditMode() && role.value === 'patient' ? '' : msg('nationality_required');
                }
                return '';
            }],
            [nationalId, nationalIdError],
            [password, function () {
                var value = password.value;
                if (!value && isEditMode() && !passwordConfirm.value) return '';
                if (!value) return msg('password_required');
                return value.length >= 8 && /[A-Za-zçÇğĞıİöÖşŞüÜ]/.test(value) && /\d/.test(value)
                    ? '' : msg('password_weak');
            }],
            [passwordConfirm, function () {
                if (!password.value && !passwordConfirm.value && isEditMode()) return '';
                if (!passwordConfirm.value) return msg('password_confirm_required');
                return passwordConfirm.value === password.value ? '' : msg('password_mismatch');
            }]
        ];

        function validate() {
            var firstInvalid = null;
            checks.forEach(function (check) {
                var control = check[0];
                var message = control.disabled && control !== role ? '' : check[1](control.value.trim(), control);
                setError(control, message);
                if (message && !firstInvalid) {
                    firstInvalid = control;
                }
            });
            return firstInvalid;
        }

        function setCreateMode() {
            form.reset();
            clearAllErrors();
            form.dataset.mode = 'create';
            userIdInput.value = '';
            role.disabled = false;
            password.required = true;
            passwordConfirm.required = true;
            formTitle.textContent = msg('create_title');
            formSubtitle.textContent = msg('create_subtitle');
            submitButton.textContent = msg('create_submit');
            updateWorkplace();
            updateNationalIdKeyboard();
        }

        function setEditMode(user) {
            form.reset();
            clearAllErrors();
            form.dataset.mode = 'edit';
            userIdInput.value = String(user.user_id || '');
            role.value = user.role || '';
            role.disabled = true;
            form.elements.first_name.value = user.first_name || '';
            form.elements.last_name.value = user.last_name || '';
            form.elements.email.value = user.email || '';
            form.elements.phone.value = user.phone || '';
            form.elements.birth_date.value = user.birth_date || '';
            form.elements.gender.value = user.gender || '';
            nationality.value = user.nationality || '';
            nationalId.value = user.national_id || '';
            password.required = false;
            passwordConfirm.required = false;
            password.value = '';
            passwordConfirm.value = '';

            updateWorkplace();
            if (hasWorkplace()) {
                hospital.value = user.hospital_id ? String(user.hospital_id) : '';
                updateDepartments();
                department.value = user.department_id ? String(user.department_id) : '';
            }
            updateNationalIdKeyboard();

            formTitle.textContent = msg('edit_title');
            formSubtitle.textContent = msg('edit_subtitle');
            submitButton.textContent = msg('edit_submit');
            formCard.hidden = false;
            formCard.scrollIntoView({ block: 'nearest' });
            form.elements.first_name.focus();
        }

        if (openCreateButton) {
            openCreateButton.addEventListener('click', function () {
                setCreateMode();
                formCard.hidden = false;
                formCard.scrollIntoView({ block: 'nearest' });
                role.focus();
            });
        }

        if (cancelButton) {
            cancelButton.addEventListener('click', function () {
                setCreateMode();
                formCard.hidden = true;
            });
        }

        Array.prototype.slice.call(document.querySelectorAll('[data-users-edit]')).forEach(function (button) {
            button.addEventListener('click', function () {
                try {
                    var userData = JSON.parse(button.dataset.user || '{}');
                    setEditMode(userData);
                } catch (e) {
                    showNotice(msg('request_failed'), true);
                }
            });
        });

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
                setError(control, check[1](control.value.trim(), control));
            });
            control.addEventListener('paste', function () {
                setTimeout(function () {
                    setError(control, check[1](control.value.trim(), control));
                }, 0);
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

        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            if (submitButton.disabled) return;

            var firstInvalid = validate();
            if (firstInvalid) {
                firstInvalid.focus();
                return;
            }

            var editMode = isEditMode();
            var endpoint = editMode ? form.dataset.updateUrl : form.dataset.createUrl;
            var fullName = (form.elements.first_name.value.trim() + ' ' + form.elements.last_name.value.trim()).trim();
            var roleLabel = role.options[role.selectedIndex] ? role.options[role.selectedIndex].textContent.trim() : role.value;

            var payload = {
                first_name: form.elements.first_name.value.trim(),
                last_name: form.elements.last_name.value.trim(),
                email: form.elements.email.value.trim(),
                phone: form.elements.phone.value.trim(),
                birth_date: form.elements.birth_date.value.trim(),
                gender: form.elements.gender.value,
                nationality: nationality.value,
                national_id: nationalId.value.trim(),
                password: password.value,
                password_confirm: passwordConfirm.value
            };

            if (editMode) {
                payload.user_id = Number(userIdInput.value);
            } else {
                payload.user_role = role.value;
            }

            if (hasWorkplace()) {
                payload.hospital_id = Number(hospital.value);
                payload.department_id = Number(department.value);
            }

            submitButton.disabled = true;
            submitButton.setAttribute('aria-busy', 'true');

            try {
                var result = await postJson(endpoint, payload);
                if (result.ok) {
                    var successText = editMode
                        ? msg('update_notice', { name: fullName })
                        : msg('create_notice', { name: fullName, role: roleLabel, email: payload.email });
                    sessionStorage.setItem('chn_users_flash', successText);
                    window.location.assign('user-management.php#users-list');
                    return;
                }
                showNotice((result.body && result.body.message) || msg('request_failed'), true);
            } catch (e) {
                showNotice(msg('request_failed'), true);
            } finally {
                submitButton.disabled = false;
                submitButton.removeAttribute('aria-busy');
            }
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
            deleteForm.addEventListener('submit', async function (event) {
                event.preventDefault();
                var row = deleteForm.closest('[data-users-row]');
                var name = deleteForm.dataset.usersName;
                var deleteBtn = deleteForm.querySelector('button[type="submit"]');
                var deleteUrl = deleteForm.dataset.deleteUrl;
                var userId = Number(deleteForm.elements.user_id.value);

                if (!window.confirm(msg('delete_confirm', { name: name }))) {
                    return;
                }

                if (deleteBtn) deleteBtn.disabled = true;

                try {
                    var result = await postJson(deleteUrl, { user_id: userId });
                    if (result.ok) {
                        decrease('shown');
                        decrease('all');
                        decrease(row.dataset.usersRole);
                        row.parentNode.removeChild(row);

                        if (!table.querySelector('[data-users-row]')) {
                            table.hidden = true;
                            empty.hidden = false;
                        }

                        showNotice(msg('delete_notice', { name: name }), false);
                        return;
                    }
                    showNotice((result.body && result.body.message) || msg('request_failed'), true);
                } catch (e) {
                    showNotice(msg('request_failed'), true);
                } finally {
                    if (deleteBtn) deleteBtn.disabled = false;
                }
            });
        });
    }

    initForm();
    initDelete();
})();
