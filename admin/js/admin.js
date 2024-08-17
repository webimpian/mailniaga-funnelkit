// admin/js/admin.js

jQuery(document).ready(function($) {
    var $apiKeyInput = $('#mailniaga_smtp_api_key');
    var $apiKeyMasked = $('#mailniaga_smtp_api_key_masked');
    var $toggleButton = $('#toggle_api_key');
    var $verifyButton = $('#verify_api_key');
    var $verificationResult = $('#api_key_verification_result');
    var isVisible = false;

    $apiKeyInput.hide();
    $apiKeyMasked.show();

    $toggleButton.on('click', function() {
        if (isVisible) {
            $apiKeyInput.hide();
            $apiKeyMasked.show();
            $toggleButton.text('Show API Key');
        } else {
            $apiKeyInput.show();
            $apiKeyMasked.hide();
            $toggleButton.text('Hide API Key');
        }
        isVisible = !isVisible;
    });

    $apiKeyInput.on('input', function() {
        var apiKey = $(this).val();
        var maskedKey = maskApiKey(apiKey);
        $apiKeyMasked.val(maskedKey);
    });

    $verifyButton.on('click', function() {
        var apiKey = $apiKeyInput.val();
        $.ajax({
            url: mailniaga_smtp_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'verify_mailniaga_api_key',
                nonce: mailniaga_smtp_ajax.nonce,
                api_key: apiKey
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    var html = '<div class="notice notice-success"><p>API Key verified successfully!</p>';
                    html += '<p>Organization: ' + data.organisation + '</p>';
                    html += '<p>Email: ' + data.email + '</p>';
                    html += '<p>SMTP Username: ' + data.smtp_username + '</p>';
                    html += '<p>Limit Quota: ' + data.limitQuota + '</p>';
                    html += '<p>Total Usages: ' + data.totalUsages + '</p>';
                    html += '<p>Credit Balance: ' + data.creditBalance + '</p></div>';
                    $verificationResult.html(html);
                } else {
                    $verificationResult.html('<div class="notice notice-error"><p>API Key verification failed: ' + response.data + '</p></div>');
                }
            },
            error: function() {
                $verificationResult.html('<div class="notice notice-error"><p>An error occurred while verifying the API key.</p></div>');
            }
        });
    });

    function maskApiKey(key) {
        if (key.length <= 8) {
            return '*'.repeat(key.length);
        }
        return key.substr(0, 4) + '*'.repeat(key.length - 8) + key.substr(-4);
    }

    $('#toggle_backup_password').on('click', function() {
        var passwordField = $('#mailniaga_smtp_backup_password');
        var maskedField = $('#mailniaga_smtp_backup_password_masked');
        
        if (passwordField.is(':visible')) {
            passwordField.hide();
            maskedField.show();
            $(this).text('Show Password');
        } else {
            passwordField.show();
            maskedField.hide();
            $(this).text('Hide Password');
        }
    });

    function updateSmtpTestButton() {
        var smtpEnabled = $('#mailniaga_smtp_enable_backup').is(':checked');
        var smtpUsername = $('#mailniaga_smtp_backup_username').val();
        var smtpPassword = $('#mailniaga_smtp_backup_password').val();
        var smtpPort = $('#mailniaga_smtp_backup_port').val();

        if (smtpEnabled && smtpUsername && smtpPassword && smtpPort) {
            $('input[name="send_test_email_smtp"]').prop('disabled', false).removeClass('disabled');
        } else {
            $('input[name="send_test_email_smtp"]').prop('disabled', true).addClass('disabled');
        }
    }

    $('#mailniaga_smtp_enable_backup, #mailniaga_smtp_backup_username, #mailniaga_smtp_backup_password, #mailniaga_smtp_backup_port').on('change input', updateSmtpTestButton);

    updateSmtpTestButton(); // Run on page load
});