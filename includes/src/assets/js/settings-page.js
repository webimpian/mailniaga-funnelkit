jQuery(document).ready(function($) {
    // Webhook generation
    $('#generate_webhook').on('click', function() {
        var $webhookField = $('#mailniaga_webhook');
        var $generateButton = $(this);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'generate_mailniaga_webhook',
                nonce: mailniaga_settings.nonce
            },
            success: function(response) {
                if (response.success) {
                    $webhookField.val(response.data.webhook);
                    $webhookField.attr('readonly', true);
                    $generateButton.hide();

                    // Add the callback URL and copy button below the webhook field
                    var $callbackUrlContainer = $('<p><strong>Callback URL:</strong> <code id="webhook_callback_url">' + response.data.callback_url + '</code> <button id="copy_webhook_url" class="button button-secondary">Copy URL</button></p>');
                    $callbackUrlContainer.insertAfter($generateButton);

                    // Initialize the copy button functionality
                    initCopyButton();

                    // Reload the page after a short delay
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    alert('Failed to generate webhook. Please try again.');
                }
            },
            error: function() {
                alert('An error occurred. Please try again.');
            }
        });
    });

    function initCopyButton() {
        $('#copy_webhook_url').on('click', function() {
            var webhookUrl = $('#webhook_callback_url').text();
            navigator.clipboard.writeText(webhookUrl).then(function() {
                alert('Webhook URL copied to clipboard!');
            }, function(err) {
                alert('Failed to copy. Please try again or copy manually.');
            });
        });
    }

    // Initialize copy button if it already exists on page load
    initCopyButton();

    $('#verify-api').on('click', function() {
        var $verifyButton = $(this);
        var $apiDetails = $('#api-details');
        var $apiVerificationResults = $('#api-verification-results');
        var $apiKeyField = $('input[name="mailniaga_wp_connector_settings[api_key]"]');

        var apiKey = $apiKeyField.val();

        $verifyButton.prop('disabled', true).text('Verifying...');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'verify_mailniaga_api',
                nonce: mailniaga_settings.verify_nonce,
                api_key: apiKey
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    var detailsHtml = '<p><strong>Organization:</strong> ' + data.organisation + '</p>' +
                        '<p><strong>Email:</strong> ' + data.email + '</p>' +
                        '<p><strong>Limit Quota:</strong> ' + data.limit_quota + '</p>' +
                        '<p><strong>Total Usage:</strong> ' + data.total_usage + '</p>' +
                        '<p><strong>Credit Balance:</strong> ' + data.credit_balance + '</p>';

                    $apiDetails.html(detailsHtml);
                    $apiVerificationResults.show();
                } else {
                    $apiKeyField.val(''); // Reset the API key field
                    alert('API verification failed: ' + response.data.message + '\nThe API key field has been reset.');
                    $apiDetails.html(''); // Clear any previous API details
                    $apiVerificationResults.hide();
                }
            },
            error: function() {
                $apiKeyField.val(''); // Reset the API key field
                alert('An error occurred during API verification. The API key field has been reset. Please try again.');
                $apiDetails.html(''); // Clear any previous API details
                $apiVerificationResults.hide();
            },
            complete: function() {
                $verifyButton.prop('disabled', false).text('Verify API');
            }
        });
    });

    // Function to verify API on page load
    function verifyApiOnLoad() {
        $('#verify-api').trigger('click');
    }

    // Verify API on page load
    verifyApiOnLoad();
});