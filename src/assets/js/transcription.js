(function () {
    'use strict';

    // Toggle API key visibility
    var toggleBtn = document.getElementById('toggle-api-key');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function () {
            var input = document.getElementById('api_key');
            var icon = this.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fa fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fa fa-eye';
            }
        });
    }

    // Show test result
    function showResult(success, message) {
        var container = document.getElementById('test-results');
        var alert = document.getElementById('test-result-alert');
        container.className = '';
        alert.className = 'alert ' + (success ? 'alert-success' : 'alert-danger');
        alert.textContent = message;
    }

    // FreePBX AJAX endpoint for this module
    var ajaxUrl = 'ajax.php?module=transcription&command=';

    // AJAX helper
    function runAjax(command, btn, callback) {
        var originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Testing...';

        var xhr = new XMLHttpRequest();
        xhr.open('GET', ajaxUrl + command);
        xhr.onload = function () {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
            try {
                var data = JSON.parse(xhr.responseText);
                callback(data);
            } catch (e) {
                var preview = xhr.responseText.substring(0, 300).replace(/<[^>]*>/g, ' ').trim();
                showResult(false, 'Server returned non-JSON (HTTP ' + xhr.status + '): ' + (preview || '(empty)'));
            }
        };
        xhr.onerror = function () {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
            showResult(false, 'Network error — could not reach FreePBX server');
        };
        xhr.send();
    }

    // Client-side validation before testing
    function validateRequired() {
        var host = document.getElementById('server_host');
        var key = document.getElementById('api_key');
        var missing = [];
        if (host && !host.value.trim()) missing.push('Server Host');
        if (key && !key.value.trim()) missing.push('API Key');
        if (missing.length > 0) {
            showResult(false, 'Please fill in required fields: ' + missing.join(', '));
            return false;
        }
        return true;
    }

    // Test API button
    var apiBtn = document.getElementById('btn-test-api');
    if (apiBtn) {
        apiBtn.addEventListener('click', function () {
            if (validateRequired()) runAjax('test_api', this, function (data) {
                showResult(data.success, data.message);
            });
        });
    }

    // Purge logs button
    var purgeBtn = document.getElementById('btn-purge-logs');
    if (purgeBtn) {
        purgeBtn.addEventListener('click', function () {
            if (!confirm('Purge log entries older than the retention period?')) {
                return;
            }

            runAjax('purge_logs', this, function (data) {
                if (data.success) {
                    location.reload();
                } else {
                    showResult(false, data.message);
                }
            });
        });
    }
})();
