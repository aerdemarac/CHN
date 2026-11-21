(function () {
    'use strict';

    var notice = document.getElementById('complaints-notice');
    var forms = Array.prototype.slice.call(document.querySelectorAll('[data-complaints-form]'));
    var limit = 255;
    var messages = {};

    try {
        messages = JSON.parse((notice && notice.dataset.messages) || '{}');
    } catch (e) {
        messages = {};
    }

    function msg(key, params) {
        var text = typeof messages[key] === 'string' ? messages[key] : key;

        params = params || {};
        return text.replace(/\{(\w+)\}/g, function (m, k) {
            return k in params ? String(params[k]) : m;
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

    function changeCount(key, amount) {
        var counter = document.querySelector('[data-complaints-count="' + key + '"]');

        if (counter) {
            counter.textContent = String(Math.max(0, Number(counter.textContent) + amount));
        }
    }

    forms.forEach(function (form) {
        var item = form.closest('[data-complaints-item]');
        var textarea = form.elements.response_msg;
        var counter = form.querySelector('[data-complaints-counter]');
        var error = document.getElementById(textarea.id + '-error');

        function setError(message) {
            error.textContent = message;
            error.hidden = message === '';
            if (message) {
                textarea.setAttribute('aria-invalid', 'true');
            } else {
                textarea.removeAttribute('aria-invalid');
            }
        }

        function validate() {
            var value = textarea.value.trim();

            if (!value) {
                return msg('required');
            }
            return value.length > limit ? msg('tooLong', { max: limit }) : '';
        }

        textarea.addEventListener('input', function () {
            counter.textContent = String(textarea.value.length);
            if (textarea.hasAttribute('aria-invalid')) {
                setError(validate());
            }
        });

        form.addEventListener('submit', function (event) {
            var message = validate();
            var badge = item.querySelector('[data-complaints-badge]');
            var sender = item.querySelector('[data-complaints-sender]').textContent;
            var response = document.createElement('div');
            var title = document.createElement('p');
            var text = document.createElement('p');

            event.preventDefault();
            setError(message);
            if (message) {
                textarea.focus();
                return;
            }

            response.className = 'complaints-item__response';
            title.className = 'complaints-item__response-title';
            title.textContent = msg('responseToday');
            text.className = 'complaints-item__message';
            text.textContent = textarea.value.trim();
            response.appendChild(title);
            response.appendChild(text);
            form.parentNode.replaceChild(response, form);

            badge.className = 'badge badge--success';
            badge.textContent = msg('answered');
            var wait = item.querySelector('[data-complaints-wait]');
            if (wait) wait.textContent = wait.getAttribute('data-complaints-wait');
            item.setAttribute('data-complaints-status', 'answered');
            changeCount('open', -1);
            changeCount('answered', 1);

            showNotice(msg('saved', { name: sender }));
        });

        counter.textContent = String(textarea.value.length);
    });
})();
