(function () {
    'use strict';

    var form = document.getElementById('patients-form');
    if (!form) {
        return;
    }

    var input = document.getElementById('patients-q');
    var label = document.getElementById('patients-q-label');
    var hint = document.getElementById('patients-q-hint');
    var error = document.getElementById('patients-q-error');
    var radios = Array.prototype.slice.call(form.querySelectorAll('input[name="search_by"]'));
    var examples = Array.prototype.slice.call(document.querySelectorAll('[data-patients-example]'));

    var tests = {
        phone: function (value) {
            var digits = value.replace(/\D/g, '');
            return /^\+?[0-9\s()-]+$/.test(value) && digits.length >= 7 && digits.length <= 15;
        },
        email: function (value) {
            return value.length <= 254 && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
        },
        national_id: function (value) {
            var clean = value.replace(/[\s-]/g, '');
            return /^[A-Za-z0-9\s-]+$/.test(value) && clean.length >= 5 && clean.length <= 50;
        }
    };

    function selectedType() {
        for (var i = 0; i < radios.length; i += 1) {
            if (radios[i].checked) {
                return radios[i];
            }
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

        if (!value) {
            return radio.dataset.empty;
        }
        return test && test(value) ? '' : radio.dataset.invalid;
    }

    radios.forEach(function (radio) {
        radio.addEventListener('change', function () {
            applyType(radio);
            showError('');
        });
    });

    input.addEventListener('input', function () {
        if (!error.hidden) {
            showError(validate());
        }
    });

    form.addEventListener('submit', function (event) {
        var message = validate();

        showError(message);
        if (message) {
            event.preventDefault();
            input.focus();
        }
    });

    examples.forEach(function (button) {
        button.addEventListener('click', function () {
            radios.forEach(function (radio) {
                radio.checked = radio.value === button.dataset.searchBy;
            });
            applyType(selectedType());
            input.value = button.dataset.q;
            showError('');
            form.submit();
        });
    });
})();
