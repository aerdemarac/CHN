(function () {
    'use strict';

    var notice = document.getElementById('hospitals-notice');
    var hospitalForm = document.getElementById('hospital-form');
    var departmentForm = document.getElementById('department-form');
    var textPattern = /^[A-Za-zçÇğĞıİöÖşŞüÜ0-9\s,.\-\/:#'()]+$/;
    var deptPattern = /^[A-Za-zçÇğĞıİöÖşŞüÜ0-9\s,.\-\/&()]+$/;
    var phonePattern = /^\+?[0-9\s()-]+$/;

    function readMessages() {
        try {
            return JSON.parse((notice && notice.dataset.messages) || '{}');
        } catch (e) {
            return {};
        }
    }

    var messages = readMessages();
    function msg(key, params) {
        var text = messages[key] || key;
        params = params || {};
        return text.replace(/\{(\w+)\}/g, function (m, k) {
            return k in params ? String(params[k]) : m;
        });
    }

    function showNotice(text, isError) {
        if (!notice) return;
        notice.classList.toggle('alert--danger', Boolean(isError));
        notice.classList.toggle('alert--info', !isError);
        notice.textContent = text;
        notice.hidden = false;
        notice.scrollIntoView({ block: 'nearest' });
        notice.focus({ preventScroll: true });
    }

    var flash = sessionStorage.getItem('chn_hospitals_flash');
    if (flash) {
        sessionStorage.removeItem('chn_hospitals_flash');
        showNotice(flash, false);
    }

    async function postJson(url, payload) {
        var res = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(payload)
        });
        var body = null;
        try {
            body = await res.json();
        } catch (e) {
            body = null;
        }
        return { ok: res.ok && body && body.ok === true, body: body };
    }

    function setError(input, message) {
        var err = document.getElementById(input.id + '-error');
        if (!err) return;
        err.textContent = message;
        err.hidden = message === '';
        if (message) input.setAttribute('aria-invalid', 'true');
        else input.removeAttribute('aria-invalid');
    }

    if (hospitalForm) {
        var hTitle = document.getElementById('hospital-form-title');
        var hId = document.getElementById('hospital-id');
        var hName = document.getElementById('hospital-name');
        var hCity = document.getElementById('hospital-city');
        var hPhone = document.getElementById('hospital-phone');
        var hAddress = document.getElementById('hospital-address');
        var hCancel = document.getElementById('hospital-cancel');
        var hSubmit = document.getElementById('hospital-submit');

        function validateHospital() {
            var first = null;
            var nameVal = hName.value.trim();
            var cityVal = hCity.value.trim();
            var phoneVal = hPhone.value.trim();
            var addrVal = hAddress.value.trim();

            var eName = !nameVal ? msg('required') : (!textPattern.test(nameVal) ? msg('invalid_chars') : '');
            var eCity = !cityVal ? msg('required') : (!textPattern.test(cityVal) ? msg('invalid_chars') : '');
            var ePhone = '';
            if (phoneVal) {
                var digits = phoneVal.replace(/\D/g, '');
                var len = digits.length + (phoneVal.charAt(0) === '+' ? 1 : 0);
                if (!phonePattern.test(phoneVal) || digits.length < 7 || len > 16) ePhone = msg('phone_invalid');
            }
            var eAddr = addrVal && !textPattern.test(addrVal) ? msg('invalid_chars') : '';

            setError(hName, eName); if (eName && !first) first = hName;
            setError(hCity, eCity); if (eCity && !first) first = hCity;
            setError(hPhone, ePhone); if (ePhone && !first) first = hPhone;
            setError(hAddress, eAddr); if (eAddr && !first) first = hAddress;
            return first;
        }

        [hName, hCity, hPhone, hAddress].forEach(function (el) {
            el.addEventListener('input', validateHospital);
            el.addEventListener('paste', function () { setTimeout(validateHospital, 0); });
        });

        function resetHospitalForm() {
            hospitalForm.reset();
            hospitalForm.dataset.mode = 'create';
            hId.value = '';
            hTitle.textContent = msg('add_title');
            hSubmit.textContent = msg('save_add');
            hCancel.hidden = true;
            [hName, hCity, hPhone, hAddress].forEach(function (el) { setError(el, ''); });
        }

        hCancel.addEventListener('click', resetHospitalForm);

        document.querySelectorAll('[data-hospital-edit]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                try {
                    var data = JSON.parse(btn.dataset.hospital || '{}');
                    hospitalForm.dataset.mode = 'edit';
                    hId.value = String(data.hospital_id || '');
                    hName.value = data.hospital_name || '';
                    hCity.value = data.city || '';
                    hPhone.value = data.phone || '';
                    hAddress.value = data.address || '';
                    hTitle.textContent = msg('edit_title');
                    hSubmit.textContent = msg('save_edit');
                    hCancel.hidden = false;
                    hospitalForm.scrollIntoView({ block: 'nearest' });
                    hName.focus();
                } catch (e) {
                    showNotice(msg('request_failed'), true);
                }
            });
        });

        hospitalForm.addEventListener('submit', async function (event) {
            event.preventDefault();
            var invalid = validateHospital();
            if (invalid) { invalid.focus(); return; }

            var isEdit = hospitalForm.dataset.mode === 'edit';
            var url = isEdit ? hospitalForm.dataset.updateUrl : hospitalForm.dataset.createUrl;
            var payload = {
                hospital_name: hName.value.trim(),
                city: hCity.value.trim(),
                phone: hPhone.value.trim(),
                address: hAddress.value.trim()
            };
            if (isEdit) payload.hospital_id = Number(hId.value);

            hSubmit.disabled = true;
            try {
                var res = await postJson(url, payload);
                if (res.ok) {
                    sessionStorage.setItem('chn_hospitals_flash', msg(isEdit ? 'hospital_updated' : 'hospital_created'));
                    window.location.reload();
                    return;
                }
                showNotice((res.body && res.body.message) || msg('request_failed'), true);
            } catch (e) {
                showNotice(msg('request_failed'), true);
            } finally {
                hSubmit.disabled = false;
            }
        });
    }

    if (departmentForm) {
        var dHosp = document.getElementById('dept-hospital-id');
        var dName = document.getElementById('dept-name');
        var dSubmit = document.getElementById('department-submit');

        function validateDept() {
            var first = null;
            var eHosp = !dHosp.value ? msg('required') : '';
            var dVal = dName.value.trim();
            var eName = !dVal ? msg('required') : (!deptPattern.test(dVal) ? msg('invalid_chars') : '');
            setError(dHosp, eHosp); if (eHosp && !first) first = dHosp;
            setError(dName, eName); if (eName && !first) first = dName;
            return first;
        }

        dName.addEventListener('input', validateDept);
        dName.addEventListener('paste', function () { setTimeout(validateDept, 0); });
        dHosp.addEventListener('change', validateDept);

        departmentForm.addEventListener('submit', async function (event) {
            event.preventDefault();
            var invalid = validateDept();
            if (invalid) { invalid.focus(); return; }

            dSubmit.disabled = true;
            try {
                var res = await postJson(departmentForm.dataset.createUrl, {
                    hospital_id: Number(dHosp.value),
                    department_name: dName.value.trim()
                });
                if (res.ok) {
                    sessionStorage.setItem('chn_hospitals_flash', msg('department_created'));
                    window.location.reload();
                    return;
                }
                showNotice((res.body && res.body.message) || msg('request_failed'), true);
            } catch (e) {
                showNotice(msg('request_failed'), true);
            } finally {
                dSubmit.disabled = false;
            }
        });
    }

    document.querySelectorAll('[data-department-delete]').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            var name = btn.dataset.departmentName || '';
            var id = Number(btn.dataset.departmentId || 0);
            if (!id || !window.confirm(msg('delete_department_confirm', { name: name }))) return;

            btn.disabled = true;
            try {
                var res = await postJson('../backend/delete_department.php', { department_id: id });
                if (res.ok) {
                    sessionStorage.setItem('chn_hospitals_flash', msg('department_deleted', { name: name }));
                    window.location.reload();
                    return;
                }
                showNotice((res.body && res.body.message) || msg('request_failed'), true);
            } catch (e) {
                showNotice(msg('request_failed'), true);
            } finally {
                btn.disabled = false;
            }
        });
    });
})();
