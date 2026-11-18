(function () {
    'use strict';

    var notice = document.getElementById('complaints-notice');
    var forms = Array.prototype.slice.call(document.querySelectorAll('[data-complaints-form]'));
    var limit = 255;

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
                return 'Please write a response.';
            }
            return value.length > limit ? 'The response can be at most ' + limit + ' characters.' : '';
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
            title.textContent = 'Response · Today';
            text.className = 'complaints-item__message';
            text.textContent = textarea.value.trim();
            response.appendChild(title);
            response.appendChild(text);
            form.parentNode.replaceChild(response, form);

            badge.className = 'badge badge--success';
            badge.textContent = 'Answered';
            item.setAttribute('data-complaints-status', 'answered');
            changeCount('open', -1);
            changeCount('answered', 1);

            showNotice('Demo only: the response to ' + sender + ' is shown on this page but was not saved.');
        });

        counter.textContent = String(textarea.value.length);
    });
})();
