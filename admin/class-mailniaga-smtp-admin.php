<?php
// admin/class-mailniaga-smtp-admin.php

class MailNiaga_SMTP_Admin {
    public function init() {
        add_action('admin_menu', array($this, 'add_plugin_page'));
        add_action('admin_init', array($this, 'page_init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_post_clear_mailniaga_smtp_log', array($this, 'clear_log'));
        add_action('admin_post_clear_mailniaga_smtp_email_log', array($this, 'clear_email_log'));
        add_action('admin_post_send_test_email', array($this, 'send_test_email'));
        add_action('wp_ajax_verify_mailniaga_api_key', array($this, 'ajax_verify_api_key'));
        add_action('admin_post_refresh_mailniaga_smtp_log', array($this, 'refresh_log'));
        add_action('admin_post_download_error_log', array($this, 'download_error_log'));
        add_action('admin_post_download_email_log', array($this, 'download_email_log'));
        add_action('admin_notices', array($this, 'display_admin_notices'));
        add_action('admin_post_reset_email_stats', array($this, 'reset_email_stats'));
        add_action('admin_post_refresh_email_queue', array($this, 'refresh_email_queue'));
        add_action('admin_post_test_mailniaga_smtp_backup_connection', array($this, 'test_backup_connection'));
    }

    public function enqueue_admin_scripts($hook) {
        if ('settings_page_mailniaga-smtp' !== $hook) {
            return;
        }

        wp_enqueue_script('mailniaga-smtp-admin', plugin_dir_url(__FILE__) . 'js/admin.js', array('jquery'), '1.0.0', true);
        wp_localize_script('mailniaga-smtp-admin', 'mailniaga_smtp_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mailniaga_smtp_verify_nonce')
        ));
    }

    public function add_plugin_page() {
        add_options_page(
            'MailNiaga SMTP Settings',
            'MailNiaga SMTP',
            'manage_options',
            'mailniaga-smtp',
            array($this, 'create_admin_page')
        );
    }

    public function create_admin_page() {
        ?>
        <div class="wrap">
            <form method="post" action="options.php">
            <?php
                settings_fields('mailniaga_smtp_option_group');
                do_settings_sections('mailniaga-smtp-admin');
                submit_button();
            ?>
            </form>

            <?php mailniaga_smtp_display_email_stats(); ?>
        
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="reset_email_stats">
                <?php wp_nonce_field('reset_email_stats_nonce', 'mailniaga_smtp_reset_nonce'); ?>
                <?php submit_button('Reset Email Stats', 'secondary', 'reset_stats', false); ?>
            </form>
            
            <?php mailniaga_smtp_display_queue_status(); ?>
            
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="refresh_email_queue">
                <?php wp_nonce_field('refresh_email_queue_nonce', 'mailniaga_smtp_refresh_nonce'); ?>
                <div style="margin-top: 20px; margin-bottom: 20px;">
                    <?php submit_button('Refresh Email Queue', 'secondary', 'refresh_queue', false); ?>
                </div>
            </form>

            <h2>Send Test Email</h2>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="send_test_email">
                <?php wp_nonce_field('send_test_email_nonce', 'mailniaga_smtp_test_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="test_email_from">From Email</label></th>
                        <td>
                            <input type="email" id="test_email_from" name="test_email_from" class="regular-text" required value="<?php echo esc_attr(get_option('admin_email')); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="test_email">To Email</label></th>
                        <td>
                            <input type="email" id="test_email" name="test_email" class="regular-text" required>
                        </td>
                    </tr>
                </table>
                <div style="display: flex; align-items: center;">
                    <div style="margin-right: 10px;">
                        <?php submit_button('Test Send via API', 'primary', 'send_test_email_api', false); ?>
                    </div>
                    <div>
                        <?php 
                        $smtp_enabled = get_option('mailniaga_smtp_enable_backup', '0') === '1';
                        $smtp_username = get_option('mailniaga_smtp_backup_username', '');
                        $smtp_password = get_option('mailniaga_smtp_backup_password', '');
                        $smtp_port = get_option('mailniaga_smtp_backup_port', '');
                        
                        $smtp_configured = $smtp_enabled && $smtp_username && $smtp_password && $smtp_port;
                        
                        submit_button('Test Send via SMTP', $smtp_configured ? 'secondary' : 'secondary disabled', 'send_test_email_smtp', false, $smtp_configured ? [] : ['disabled' => 'disabled']);
                        ?>
                    </div>
                </div>
            </form>
            
            <?php
            if (isset($_GET['test_email_sent'])) {
                $result = get_transient('mailniaga_smtp_test_email_result');
                if ($result !== false) {
                    echo '<div class="notice notice-' . ($result['success'] ? 'success' : 'error') . ' is-dismissible">';
                    echo '<p>' . esc_html($result['message']) . '</p>';
                    echo '</div>';
                    delete_transient('mailniaga_smtp_test_email_result');
                }
            }
            ?>

            <h2>Email Log</h2>
            <div style="background: #fff; padding: 10px;">
                <?php $this->display_email_log(); ?>
            </div>
            <div style="margin-top: 20px;">
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display: inline-block; margin-right: 10px;">
                    <input type="hidden" name="action" value="clear_mailniaga_smtp_email_log">
                    <?php wp_nonce_field('clear_mailniaga_smtp_email_log_nonce', 'mailniaga_smtp_email_nonce'); ?>
                    <?php submit_button('Clear Email Log', 'delete', 'clear_email_log', false); ?>
                </form>
    
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display: inline-block;">
                    <input type="hidden" name="action" value="refresh_mailniaga_smtp_log">
                    <?php wp_nonce_field('refresh_mailniaga_smtp_log_nonce', 'mailniaga_smtp_refresh_nonce'); ?>
                    <input type="submit" name="refresh_email_log" value="Refresh Email Log" class="button button-secondary">
                </form>
                <div style="margin-bottom: 20px;"></div>
            </div>


            <h2>Error Log</h2>
            <div style="background: #fff; padding: 10px;">
                <?php $this->display_log(); ?>
            </div>
            <div style="margin-top: 20px;">
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display: inline-block; margin-right: 10px;">
                    <input type="hidden" name="action" value="clear_mailniaga_smtp_log">
                    <?php wp_nonce_field('clear_mailniaga_smtp_log_nonce', 'mailniaga_smtp_nonce'); ?>
                    <?php submit_button('Clear Log', 'delete', 'clear_log', false); ?>
                </form>
                
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display: inline-block;">
                    <input type="hidden" name="action" value="refresh_mailniaga_smtp_log">
                    <?php wp_nonce_field('refresh_mailniaga_smtp_log_nonce', 'mailniaga_smtp_refresh_nonce'); ?>
                    <input type="submit" name="refresh_error_log" value="Refresh Log" class="button button-secondary">
                </form>
            </div>

        </div>
        <?php
    }

    public function page_init() {
        register_setting(
            'mailniaga_smtp_option_group',
            'mailniaga_smtp_api_key',
            array($this, 'sanitize')
        );

        add_settings_section(
            'mailniaga_smtp_setting_section',
            'MailNiaga SMTP Settings',
            array($this, 'section_info'),
            'mailniaga-smtp-admin'
        );

        add_settings_field(
            'mailniaga_smtp_api_key',
            'API Key',
            array($this, 'api_key_callback'),
            'mailniaga-smtp-admin',
            'mailniaga_smtp_setting_section'
        );

        register_setting('mailniaga_smtp_option_group', 'mailniaga_smtp_enable_email_log', array($this, 'sanitize_checkbox'));
        register_setting('mailniaga_smtp_option_group', 'mailniaga_smtp_enable_error_log', array($this, 'sanitize_checkbox'));

        add_settings_field(
            'mailniaga_smtp_enable_email_log',
            'Enable Email Log',
            array($this, 'enable_email_log_callback'),
            'mailniaga-smtp-admin',
            'mailniaga_smtp_setting_section'
        );

        add_settings_field(
            'mailniaga_smtp_enable_error_log',
            'Enable Error Log',
            array($this, 'enable_error_log_callback'),
            'mailniaga-smtp-admin',
            'mailniaga_smtp_setting_section'
        );

        register_setting(
            'mailniaga_smtp_option_group',
            'mailniaga_smtp_batch_size',
            array($this, 'sanitize_batch_size')
        );
    
        add_settings_field(
            'mailniaga_smtp_batch_size',
            'Email Batch Size',
            array($this, 'batch_size_callback'),
            'mailniaga-smtp-admin',
            'mailniaga_smtp_setting_section'
        );

        register_setting(
            'mailniaga_smtp_option_group',
            'mailniaga_smtp_connection_pool_size',
            array($this, 'sanitize_pool_size')
        );

        add_settings_field(
            'mailniaga_smtp_connection_pool_size',
            'API Connection Pool Size',
            array($this, 'connection_pool_size_callback'),
            'mailniaga-smtp-admin',
            'mailniaga_smtp_setting_section'
        );

        register_setting('mailniaga_smtp_option_group', 'mailniaga_smtp_enable_backup', array($this, 'sanitize_checkbox'));
        register_setting('mailniaga_smtp_option_group', 'mailniaga_smtp_backup_username', array($this, 'sanitize'));
        register_setting('mailniaga_smtp_option_group', 'mailniaga_smtp_backup_password', array($this, 'sanitize'));

        add_settings_field(
            'mailniaga_smtp_enable_backup',
            'Enable Backup SMTP',
            array($this, 'enable_backup_callback'),
            'mailniaga-smtp-admin',
            'mailniaga_smtp_setting_section'
        );

        add_settings_field(
            'mailniaga_smtp_backup_username',
            'Backup SMTP Username',
            array($this, 'backup_username_callback'),
            'mailniaga-smtp-admin',
            'mailniaga_smtp_setting_section'
        );

        add_settings_field(
            'mailniaga_smtp_backup_password',
            'Backup SMTP Password',
            array($this, 'backup_password_callback'),
            'mailniaga-smtp-admin',
            'mailniaga_smtp_setting_section'
        );

        register_setting(
            'mailniaga_smtp_option_group',
            'mailniaga_smtp_backup_port',
            array($this, 'sanitize_port')
        );
    
        add_settings_field(
            'mailniaga_smtp_backup_port',
            'Backup SMTP Port',
            array($this, 'backup_port_callback'),
            'mailniaga-smtp-admin',
            'mailniaga_smtp_setting_section'
        );

    }

    public function sanitize($input) {
        if(isset($input) && !empty($input)) {
            return sanitize_text_field($input);
        }
        return '';
    }

    public function section_info() {
        print 'Enter your MailNiaga SMTP settings below:';
    }

    public function api_key_callback() {
        $api_key = get_option('mailniaga_smtp_api_key');
        $masked_key = $this->mask_api_key($api_key);
        ?>
        <input type="text" id="mailniaga_smtp_api_key" name="mailniaga_smtp_api_key" value="<?php echo esc_attr($api_key); ?>" style="width: 300px;" />
        <input type="text" id="mailniaga_smtp_api_key_masked" value="<?php echo esc_attr($masked_key); ?>" style="width: 300px; display: none;" readonly />
        <button type="button" id="toggle_api_key" class="button"><?php _e('Show API Key', 'mailniaga-smtp'); ?></button>
        <button type="button" id="verify_api_key" class="button"><?php _e('Verify API Key', 'mailniaga-smtp'); ?></button>
        <div id="api_key_verification_result"></div>
        <?php
    }

    public function ajax_verify_api_key() {
        check_ajax_referer('mailniaga_smtp_verify_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $api_key = sanitize_text_field($_POST['api_key']);
        $result = $this->verify_api_key($api_key);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success($result);
        }
    }

    private function verify_api_key($api_key) {
        $response = wp_remote_get('https://api.mailniaga.mx/api/v0/user', array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-api-key' => $api_key
            )
        ));

        if (is_wp_error($response)) {
            return new WP_Error('api_error', 'Failed to connect to API: ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data) || !is_array($data)) {
            return new WP_Error('api_error', 'Invalid response from API');
        }

        if (isset($data['error']) && $data['error']) {
            return new WP_Error('api_error', $data['message'] ?? 'Unknown error occurred');
        }

        return $data['data'] ?? array();
    }

    public function connection_pool_size_callback() {
        $pool_size = get_option('mailniaga_smtp_connection_pool_size', 25);
        echo "<input type='number' id='mailniaga_smtp_connection_pool_size' name='mailniaga_smtp_connection_pool_size' value='" . esc_attr($pool_size) . "' min='1' max='100' />";
        echo "<p class='description'>Set the maximum number of concurrent connections for the API. Default is 25.</p>";
    }

    public function sanitize_pool_size($input) {
        $value = intval($input);
        return ($value > 0 && $value <= 100) ? $value : 25; // Default to 25 if invalid
    }

    public function download_error_log() {
        $this->download_log('error.log', 'mailniaga-smtp-error-log.txt');
    }
    
    public function download_email_log() {
        $this->download_log('email.log', 'mailniaga-smtp-email-log.txt');
    }

    private function download_log($log_file, $download_filename) {
        $file_path = MAILNIAGA_SMTP_PLUGIN_DIR . 'logs/' . $log_file;
    
        if (file_exists($file_path) && current_user_can('manage_options')) {
            header('Content-Type: text/plain');
            header('Content-Disposition: attachment; filename="' . $download_filename . '"');
            header('Content-Length: ' . filesize($file_path));
            readfile($file_path);
            exit;
        } else {
            wp_die('You do not have permission to download this file or the file does not exist.');
        }
    }

    public function display_log() {
        if (get_option('mailniaga_smtp_enable_error_log', '1') !== '1') {
            echo '<p>Error logging is currently disabled.</p>';
            return;
        }
    
        $log_file = MAILNIAGA_SMTP_PLUGIN_DIR . 'logs/error.log';
        $max_lines = 100; // Limit to the last 100 lines
    
        if (file_exists($log_file)) {
            $log_content = file_get_contents($log_file);
            if (empty($log_content)) {
                echo '<p>The log is empty.</p>';
            } else {
                echo '<div style="height: 250px; overflow-y: scroll; background: #f9f9f9; padding: 10px; border: 1px solid #ddd;">'; // Scrollable container
                $lines = file($log_file);
                $lines = array_slice($lines, max(0, count($lines) - $max_lines)); // Get the last $max_lines lines
                foreach ($lines as $line) {
                    echo htmlspecialchars($line) . "<br>";
                }
                echo '</div>'; // Close scrollable container
                
                // Add download button
                echo '<p><a href="' . esc_url(admin_url('admin-post.php?action=download_error_log')) . '" class="button">Download Full Error Log</a></p>';
            }
        }
    }
    
    public function display_email_log() {
        if (get_option('mailniaga_smtp_enable_email_log', '1') !== '1') {
            echo '<p>Email logging is currently disabled.</p>';
            return;
        }
    
        $log_file = MAILNIAGA_SMTP_PLUGIN_DIR . 'logs/email.log';
        $max_lines = 100; // Limit to the last 100 lines
    
        if (file_exists($log_file)) {
            $log_content = file_get_contents($log_file);
            if (empty($log_content)) {
                echo '<p>The log is empty.</p>';
            } else {
                echo '<div style="height: 250px; overflow-y: scroll; background: #f9f9f9; padding: 10px; border: 1px solid #ddd;">'; // Scrollable container
                $lines = file($log_file);
                $lines = array_slice($lines, max(0, count($lines) - $max_lines)); // Get the last $max_lines lines
                foreach ($lines as $line) {
                    echo htmlspecialchars($line) . "<br>";
                }
                echo '</div>'; // Close scrollable container
                
                // Add download button
                echo '<p><a href="' . esc_url(admin_url('admin-post.php?action=download_email_log')) . '" class="button">Download Full Email Log</a></p>';
            }
        }
    }

    public function clear_log() {
        //error_log('MailNiaga SMTP: Clear error log initiated 1');

        if (!current_user_can('manage_options')) {
            error_log('MailNiaga SMTP: Unauthorized user attempted to clear error log');
            wp_die('Unauthorized user');
        }
    
        //error_log('MailNiaga SMTP: Clear error log initiated 2');
    
        check_admin_referer('clear_mailniaga_smtp_log_nonce', 'mailniaga_smtp_nonce');
        
        //error_log('MailNiaga SMTP: Clear error log initiated 3');

        if (get_option('mailniaga_smtp_enable_error_log', '1') === '1') {
            $log_file = MAILNIAGA_SMTP_PLUGIN_DIR . 'logs/error.log';
            error_log('MailNiaga SMTP: Attempting to clear log file: ' . $log_file);
            if (file_exists($log_file)) {
                $result = file_put_contents($log_file, '');
                if ($result !== false) {
                    //error_log('MailNiaga SMTP: Error log cleared successfully');
                    add_settings_error('mailniaga_smtp_messages', 'mailniaga_smtp_message', 'Error log cleared successfully', 'updated');
                } else {
                    //error_log('MailNiaga SMTP: Failed to clear error log');
                    add_settings_error('mailniaga_smtp_messages', 'mailniaga_smtp_message', 'Failed to clear error log', 'error');
                }
            } else {
                error_log('MailNiaga SMTP: Error log file does not exist');
            }
        } else {
            error_log('MailNiaga SMTP: Error logging is disabled');
        }
    
        //error_log('MailNiaga SMTP: Setting transient for admin notices');
        set_transient('mailniaga_smtp_admin_notices', get_settings_errors(), 30);
    
        //error_log('MailNiaga SMTP: Redirecting to settings page');
        wp_redirect(add_query_arg('page', 'mailniaga-smtp', admin_url('options-general.php')));
        exit;
    }
    
    public function clear_email_log() {
        //error_log('MailNiaga SMTP: Clear email log initiated 1');
        if (!current_user_can('manage_options')) {
            error_log('MailNiaga SMTP: Unauthorized user attempted to clear email log');
            wp_die('Unauthorized user');
        }
    
        //error_log('MailNiaga SMTP: Clear email log initiated 2');
    
        check_admin_referer('clear_mailniaga_smtp_email_log_nonce', 'mailniaga_smtp_email_nonce');
        
        //error_log('MailNiaga SMTP: Clear email log initiated 3');
    
        if (get_option('mailniaga_smtp_enable_email_log', '1') === '1') {
            $log_file = MAILNIAGA_SMTP_PLUGIN_DIR . 'logs/email.log';
            //error_log('MailNiaga SMTP: Attempting to clear email log file: ' . $log_file);
            if (file_exists($log_file)) {
                $result = file_put_contents($log_file, '');
                if ($result !== false) {
                    //error_log('MailNiaga SMTP: Email log cleared successfully');
                    add_settings_error('mailniaga_smtp_messages', 'mailniaga_smtp_message', 'Email log cleared successfully', 'updated');
                } else {
                    error_log('MailNiaga SMTP: Failed to clear email log');
                    add_settings_error('mailniaga_smtp_messages', 'mailniaga_smtp_message', 'Failed to clear email log', 'error');
                }
            } else {
                error_log('MailNiaga SMTP: Email log file does not exist');
            }
        } else {
            error_log('MailNiaga SMTP: Email logging is disabled');
        }
    
        //error_log('MailNiaga SMTP: Setting transient for admin notices');
        set_transient('mailniaga_smtp_admin_notices', get_settings_errors(), 30);
    
        //error_log('MailNiaga SMTP: Redirecting to settings page');
        wp_redirect(add_query_arg('page', 'mailniaga-smtp', admin_url('options-general.php')));
        exit;
    }

    public function display_admin_notices() {
        $notices = get_transient('mailniaga_smtp_admin_notices');
        if ($notices !== false) {
            foreach ($notices as $notice) {
                echo '<div class="notice notice-' . $notice['type'] . ' is-dismissible"><p>' . $notice['message'] . '</p></div>';
            }
            delete_transient('mailniaga_smtp_admin_notices');
        }
    }

    public function send_test_email() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized user');
        }
    
        check_admin_referer('send_test_email_nonce', 'mailniaga_smtp_test_nonce');
    
        $to = sanitize_email($_POST['test_email']);
        $from = sanitize_email($_POST['test_email_from']);
        $subject = 'MailNiaga SMTP Test Email';
        $message = 'This is a test email sent from the MailNiaga SMTP plugin for WordPress.';
        $headers = array('From: ' . $from);
    
        if (isset($_POST['send_test_email_api'])) {
            $sender = new MailNiaga_SMTP_Sender();
            $email = [
                'to' => $to,
                'from' => $from,
                'subject' => $subject,
                'message' => $message,
                'headers' => $headers,
                'attachments' => array()
            ];
            $result = $sender->send_emails([$email]);
            $method = 'API';
            $success = ($result['successful'] === 1);
        } elseif (isset($_POST['send_test_email_smtp'])) {
            $result = mailniaga_smtp_send_email_smtp($to, $subject, $message, $headers, array());
            $method = 'SMTP';
            $success = $result;
        } else {
            wp_die('Invalid test email method');
        }
    
        if ($success) {
            $response = array(
                'success' => true,
                'message' => "Test email sent successfully via {$method} from {$from} to {$to}!"
            );
        } else {
            $response = array(
                'success' => false,
                'message' => "Failed to send test email via {$method}. Please check the error log for more details."
            );
        }
    
        set_transient('mailniaga_smtp_test_email_result', $response, 60);
    
        wp_redirect(add_query_arg(array('page' => 'mailniaga-smtp', 'test_email_sent' => '1'), admin_url('options-general.php')));
        exit;
    }

    private function mask_api_key($key) {
        if (strlen($key) <= 8) {
            return str_repeat('*', strlen($key));
        }
        return substr($key, 0, 4) . str_repeat('*', strlen($key) - 8) . substr($key, -4);
    }

    public function refresh_log() {
        //error_log('MailNiaga SMTP: refresh log initiated 1');
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized user');
        }
    
        check_admin_referer('refresh_mailniaga_smtp_log_nonce', 'mailniaga_smtp_refresh_nonce');
    
        wp_redirect(add_query_arg('page', 'mailniaga-smtp', admin_url('options-general.php')));
        exit;
    }

    public function reset_email_stats() {
        check_admin_referer('reset_email_stats_nonce', 'mailniaga_smtp_reset_nonce');
        if (current_user_can('manage_options')) {
            mailniaga_smtp_reset_email_stats();
        }
    }
    
    public function refresh_email_queue() {
        check_admin_referer('refresh_email_queue_nonce', 'mailniaga_smtp_refresh_nonce');
        if (current_user_can('manage_options')) {
            mailniaga_smtp_refresh_email_queue();
        }
    }

    public function enable_email_log_callback() {
        $enabled = get_option('mailniaga_smtp_enable_email_log', '1');
        echo '<input type="checkbox" id="mailniaga_smtp_enable_email_log" name="mailniaga_smtp_enable_email_log" value="1"' . checked(1, $enabled, false) . '/>';
        echo '<label for="mailniaga_smtp_enable_email_log"> Enable email logging</label>';
    }
    
    public function enable_error_log_callback() {
        $enabled = get_option('mailniaga_smtp_enable_error_log', '1');
        echo '<input type="checkbox" id="mailniaga_smtp_enable_error_log" name="mailniaga_smtp_enable_error_log" value="1"' . checked(1, $enabled, false) . '/>';
        echo '<label for="mailniaga_smtp_enable_error_log"> Enable error logging</label>';
    }
    
    public function sanitize_checkbox($input) {
        return isset($input) ? '1' : '0';
    }
    
    public function sanitize_batch_size($input) {
        $value = intval($input);
        return ($value > 0) ? $value : 10; // Default to 10 if invalid
    }
    
    public function batch_size_callback() {
        $batch_size = get_option('mailniaga_smtp_batch_size', 10);
        echo "<input type='number' id='mailniaga_smtp_batch_size' name='mailniaga_smtp_batch_size' value='" . esc_attr($batch_size) . "' min='1' max='100' />";
        echo "<p class='description'>Define the number of emails to process per batch. This affects how many emails are sent in each processing cycle. A higher number may improve performance but could also increase server load.</p>";
    }

    public function enable_backup_callback() {
        $enabled = get_option('mailniaga_smtp_enable_backup', '0');
        echo '<input type="checkbox" id="mailniaga_smtp_enable_backup" name="mailniaga_smtp_enable_backup" value="1"' . checked(1, $enabled, false) . '/>';
        echo '<label for="mailniaga_smtp_enable_backup"> Enable backup SMTP connection</label>';
        echo '<p class="description">When enabled, this will use SMTP as a backup if the API method fails. Make sure to configure the SMTP settings below.</p>';
    }
    
    public function backup_username_callback() {
        $username = get_option('mailniaga_smtp_backup_username', '');
        echo "<input type='text' id='mailniaga_smtp_backup_username' name='mailniaga_smtp_backup_username' value='" . esc_attr($username) . "' />";
    }
    
    public function backup_password_callback() {
        $password = get_option('mailniaga_smtp_backup_password', '');
        $masked_password = $this->mask_api_key($password);
        echo "<input type='password' id='mailniaga_smtp_backup_password' name='mailniaga_smtp_backup_password' value='" . esc_attr($password) . "' />";
        echo "<input type='text' id='mailniaga_smtp_backup_password_masked' value='" . esc_attr($masked_password) . "' style='display: none;' readonly />";
        echo "<button type='button' id='toggle_backup_password' class='button'>" . __('Show Password', 'mailniaga-smtp') . "</button>";
    }

    public function sanitize_port($input) {
        $port = intval($input);
        return ($port >= 1 && $port <= 65535) ? $port : 2524; // Default to 2524 if invalid
    }
    
    public function backup_port_callback() {
        $port = get_option('mailniaga_smtp_backup_port', 2524);
        echo "<input type='number' id='mailniaga_smtp_backup_port' name='mailniaga_smtp_backup_port' value='" . esc_attr($port) . "' min='1' max='65535' />";
        echo "<p class='description'>Enter the SMTP port number. Common ports are 25, 465, or 587. Default is 2524.</p>";
    }

}