jQuery(document).ready(function($) {

    // Initialize date pickers
    $('.date-picker').datepicker({
        dateFormat: 'yy-mm-dd',
        changeMonth: true,
        changeYear: true
    });

    $('.view-details').on('click', function(e) {
        e.preventDefault();
        var emailId = $(this).data('id');
        $.ajax({
            url: mailniagaEmailLog.ajaxurl,
            type: 'POST',
            data: {
                action: 'mailniaga_get_email_details',
                email_id: emailId,
                nonce: mailniagaEmailLog.nonce
            },
            success: function(response) {
                var contentWrapper = $('<div>').addClass('email-details-wrapper');

                // Parse the response data
                var parsedData = $.parseHTML(response.data);

                // Extract email details
                var details = $(parsedData).filter(function() {
                    return this.nodeType === 1 && this.tagName !== 'PRE';
                });

                // Extract message content
                var messageContent = $(parsedData).filter('pre').text();

                // Append email details
                contentWrapper.append(details);

                // Create iframe for message content
                var iframe = $('<iframe>').addClass('email-content-iframe');
                contentWrapper.append(iframe);

                $('#email-details-content').html(contentWrapper);

                $('#email-details-modal').dialog({
                    title: mailniagaEmailLog.i18n.emailDetails,
                    dialogClass: 'wp-dialog email-details-dialog',
                    autoOpen: false,
                    draggable: false,
                    width: 800,
                    height: '70%',
                    modal: true,
                    resizable: false,
                    closeOnEscape: true,
                    position: {
                        my: "center",
                        at: "center",
                        of: window
                    },
                    open: function () {
                        $('.ui-widget-overlay').bind('click', function () {
                            $('#email-details-modal').dialog('close');
                        });

                        $(this).parent().css({
                            "width": "800px",
                            "height": "50vh",
                            "overflow": "hidden"
                        });

                        // Set iframe content after dialog is open
                        var iframeContent = iframe[0].contentWindow.document;
                        iframeContent.open();
                        iframeContent.write(messageContent);
                        iframeContent.close();

                        // Adjust iframe height
                        iframe.on('load', function() {
                            var availableHeight = $(this).parent().height() - $(this).parent().find('.email-details').outerHeight(true);
                            $(this).height(availableHeight * 1.5); // Increase height by 50%
                        });
                    },
                    create: function () {
                        $('.ui-dialog-titlebar-close').addClass('ui-button');
                    },
                });
                $('#email-details-modal').dialog('open');
            }
        });
    });
});