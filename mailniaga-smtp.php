<?php
/**
 * Plugin Name: MailNiaga SMTP API
 * Plugin URI: https://mailniaga.com/
 * Description: A simple SMTP plugin for WordPress using MailNiaga API
 * Version: 1.3.0.beta-11072024
 * Author: Firdaus Azizi
 * Author URI: https://firz.my
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: mailniaga-smtp
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 8.0
 * Tested up to: 6.5
 */
// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';

// Define plugin constants
define('MAILNIAGA_SMTP_VERSION', '1.3.0');
define('MAILNIAGA_SMTP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MAILNIAGA_SMTP_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include necessary files
require_once MAILNIAGA_SMTP_PLUGIN_DIR . 'includes/class-mailniaga-smtp.php';
require_once MAILNIAGA_SMTP_PLUGIN_DIR . 'includes/class-mailniaga-command-pool.php';
require_once MAILNIAGA_SMTP_PLUGIN_DIR . 'includes/class-mailniaga-smtp-sender.php';
require_once MAILNIAGA_SMTP_PLUGIN_DIR . 'admin/class-mailniaga-smtp-admin.php';

// Initialize the plugin
function run_mailniaga_smtp() {
    $plugin = new MailNiaga_SMTP();
    $plugin->run();

    $plugin_admin = new MailNiaga_SMTP_Admin();
    $plugin_admin->init();
}
run_mailniaga_smtp();

// Hook into WordPress's PHPMailer
add_action('phpmailer_init', 'mailniaga_smtp_phpmailer_init');
function mailniaga_smtp_phpmailer_init($phpmailer) {
    $phpmailer->isSMTP();
    $phpmailer->Host = 'smtp.mailniaga.mx';
    $phpmailer->SMTPAuth = true;
    $phpmailer->Port = 587;
    $phpmailer->Username = 'api';
    $phpmailer->Password = get_option('mailniaga_smtp_api_key');
    $phpmailer->SMTPSecure = 'tls';
    $phpmailer->From = get_option('admin_email');
    $phpmailer->FromName = get_bloginfo('name');
}

register_activation_hook(__FILE__, 'mailniaga_smtp_create_db_table');

function mailniaga_smtp_create_db_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'mailniaga_email_queue';
    
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        to_email text NOT NULL,
        subject text NOT NULL,
        message longtext NOT NULL,
        headers text,
        attachments text,
        status varchar(20) NOT NULL DEFAULT 'queued',
        created_at datetime NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

add_action('wp_ajax_mailniaga_process_queue', 'mailniaga_ajax_process_queue');
add_action('wp_ajax_nopriv_mailniaga_process_queue', 'mailniaga_ajax_process_queue');

function mailniaga_ajax_process_queue() {
    if (!wp_verify_nonce($_POST['nonce'], 'mailniaga_process_queue')) {
        wp_die('Invalid nonce');
    }

    mailniaga_process_email_queue();
    wp_die('Queue processed');
}

// Override wp_mail function
if (!function_exists('wp_mail')) {
    function wp_mail($to, $subject, $message, $headers = '', $attachments = array()) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mailniaga_email_queue';

        $data = array(
            'to_email' => is_array($to) ? implode(',', $to) : $to,
            'subject' => $subject,
            'message' => $message,
            'headers' => is_array($headers) ? serialize($headers) : $headers,
            'attachments' => serialize($attachments),
            'created_at' => current_time('mysql')
        );

        $wpdb->insert($table_name, $data);

        // Trigger the cron job to process the queue
        wp_schedule_single_event(time(), 'mailniaga_process_email_queue');

        // Fallback: Trigger queue processing via AJAX
        wp_remote_post(admin_url('admin-ajax.php'), array(
            'blocking' => false,
            'body' => array(
                'action' => 'mailniaga_process_queue',
                'nonce' => wp_create_nonce('mailniaga_process_queue')
            )
        ));

        return true;
    }
}

// Add this to mailniaga-smtp.php

add_action('mailniaga_process_email_queue', 'mailniaga_process_email_queue');

function mailniaga_schedule_queue_processing() {
    if (!wp_next_scheduled('mailniaga_process_email_queue')) {
        wp_schedule_event(time(), 'every_minute', 'mailniaga_process_email_queue');
    }
}

function mailniaga_process_email_queue() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'mailniaga_email_queue';
    
    $emails = $wpdb->get_results("SELECT * FROM $table_name WHERE status = 'queued' LIMIT 50", ARRAY_A);
    
    if (empty($emails)) {
        return;
    }

    $formatted_emails = array_map(function($email) {
        return [
            'to' => explode(',', $email['to_email']),
            'subject' => $email['subject'],
            'message' => $email['message'],
            'headers' => maybe_unserialize($email['headers']),
            'attachments' => maybe_unserialize($email['attachments']),
            'from' => get_option('admin_email'),
        ];
    }, $emails);

    $sender = new MailNiaga_SMTP_Sender();
    $results = $sender->send_emails($formatted_emails);

    // Update email statuses in the database
    $successful_ids = array_slice(array_column($emails, 'id'), 0, $results['successful']);
    $failed_ids = array_slice(array_column($emails, 'id'), $results['successful'], $results['failed']);

    if (!empty($successful_ids)) {
        $wpdb->query("UPDATE $table_name SET status = 'sent' WHERE id IN (" . implode(',', $successful_ids) . ")");
    }

    if (!empty($failed_ids)) {
        $wpdb->query("UPDATE $table_name SET status = 'failed' WHERE id IN (" . implode(',', $failed_ids) . ")");
    }
}

function mailniaga_smtp_send_email($to, $subject, $message, $headers, $attachments) {
    $api_key = get_option('mailniaga_smtp_api_key');
    $enable_backup = get_option('mailniaga_smtp_enable_backup', '0');

    // Prepare email data
    $email = [
        'to' => $to,
        'subject' => $subject,
        'message' => $message,
        'headers' => $headers,
        'attachments' => $attachments,
        'from' => get_option('admin_email'),
    ];

    // Try API method first
    $sender = new MailNiaga_SMTP_Sender();
    $result = $sender->send_emails([$email]);

    if ($result['successful'] === 1) {
        return true;
    }

    // If API fails and backup is enabled, try SMTP
    if ($enable_backup === '1') {
        return mailniaga_smtp_send_email_smtp($to, $subject, $message, $headers, $attachments);
    }

    return false;
}

// function mailniaga_smtp_send_email_api($to, $subject, $message, $headers, $attachments) {
//     $api_key = get_option('mailniaga_smtp_api_key');
//     $api_url = 'https://api.mailniaga.mx/api/v0/messages';

//     $timing = [
//         'start' => microtime(true),
//         'client_created' => 0,
//         'request_prepared' => 0,
//         'request_sent' => 0,
//         'response_received' => 0,
//     ];

//     // Create a HandlerStack
//     $stack = HandlerStack::create();

//     // Add middleware to log request and response times
//     $stack->push(Middleware::mapRequest(function ($request) use (&$timing) {
//         $timing['request_prepared'] = microtime(true);
//         return $request;
//     }));
//     $stack->push(Middleware::mapResponse(function ($response) use (&$timing) {
//         $timing['response_received'] = microtime(true);
//         return $response;
//     }));

//     $client = new Client([
//         'timeout' => 30,
//         'connect_timeout' => 5,
//         'handler' => $stack,
//         'pool_size' => 25,
//     ]);
//     $timing['client_created'] = microtime(true);

//     // Prepare the email data
//     $email_data = [
//         'from' => '',
//         'to' => is_array($to) ? $to : [$to],
//         'subject' => $subject,
//         'content' => $message,
//         'as_html' => 1,
//     ];

//     // Parse headers
//     if (!is_array($headers)) {
//         $headers = explode("\n", str_replace("\r\n", "\n", $headers));
//     }

//     $cc = $bcc = array();
//     $from = get_option('admin_email');
//     $from_name = get_bloginfo('name');

//     foreach ($headers as $header) {
//         if (strpos($header, ':') === false) {
//             continue;
//         }
//         list($name, $content) = explode(':', trim($header), 2);
//         $name = trim($name);
//         $content = trim($content);

//         switch (strtolower($name)) {
//             case 'from':
//                 $from = $content;
//                 if (preg_match('/(.*)<(.+)>/', $from, $matches)) {
//                     $from_name = trim($matches[1]);
//                     $from = trim($matches[2]);
//                 }
//                 break;
//             case 'cc':
//                 $cc = array_merge($cc, explode(',', $content));
//                 break;
//             case 'bcc':
//                 $bcc = array_merge($bcc, explode(',', $content));
//                 break;
//         }
//     }

//     $email_data['from'] = $from_name ? "$from_name <$from>" : $from;
//     if (!empty($cc)) {
//         $email_data['cc'] = $cc;
//     }
//     if (!empty($bcc)) {
//         $email_data['bcc'] = $bcc;
//     }

//     $timing['data_prep_end'] = microtime(true);

//     try {
//         $timing['request_sent'] = microtime(true);
//         $response = $client->post($api_url, [
//             'json' => $email_data,
//             'headers' => [
//                 'Content-Type' => 'application/json',
//                 'X-Api-Key' => $api_key
//             ]
//         ]);

//         $response_code = $response->getStatusCode();
//         $response_body = json_decode($response->getBody(), true);

//         $debug_info = "GuzzleHttp Performance:\n";
//         $debug_info .= "Client Creation: " . round($timing['client_created'] - $timing['start'], 4) . " seconds\n";
//         $debug_info .= "Request Preparation: " . round($timing['request_prepared'] - $timing['client_created'], 4) . " seconds\n";
//         $debug_info .= "Time to First Byte: " . round($timing['response_received'] - $timing['request_sent'], 4) . " seconds\n";
//         $debug_info .= "Total Request Time: " . round($timing['response_received'] - $timing['request_sent'], 4) . " seconds\n";
//         $debug_info .= "\nResponse code: " . $response_code;
//         $debug_info .= "\nResponse body: " . print_r($response_body, true);

//         if ($response_code !== 200 || (isset($response_body['error']) && $response_body['error'])) {
//             mailniaga_smtp_log_error($response_body['message'] ?? 'Unknown error', $response_code, $debug_info);
//             return false;
//         }

//         // Log successful email
//         $to_addresses = is_array($to) ? implode(', ', $to) : $to;
//         mailniaga_smtp_log_success("Email sent via API to: $to_addresses, Subject: $subject\n" . $debug_info, round($timing['response_received'] - $timing['start'], 4));

//         return true;
//     } catch (RequestException $e) {
//         $error_message = $e->getMessage();
//         $response_code = $e->getCode();
//         mailniaga_smtp_log_error($error_message, $response_code, "Request failed. " . $debug_info);
        
//         // If API call failed, try backup SMTP
//         $backup_result = mailniaga_smtp_send_email_smtp($to, $subject, $message, $headers, $attachments);
//         return $backup_result;
//     }
// }

function mailniaga_smtp_send_email_smtp($to, $subject, $message, $headers, $attachments) {
    $smtp_host = 'smtp.mailniaga.mx';
    $smtp_port = get_option('mailniaga_smtp_backup_port', 2524);
    $smtp_username = get_option('mailniaga_smtp_backup_username');
    $smtp_password = get_option('mailniaga_smtp_backup_password');

    require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
    require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
    require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        // Start time measurement
        $start_time = microtime(true);

        $mail->isSMTP();
        $mail->Host       = $smtp_host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp_username;
        $mail->Password   = $smtp_password;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $smtp_port;

        // Determine the appropriate encryption based on the port
        if ($smtp_port == 465) {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($smtp_port == 587) {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            // For custom ports, let's default to TLS but allow it to be disabled
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        }

        $mail->setFrom(get_option('admin_email'), get_bloginfo('name'));
        $mail->addAddress($to);

        if (!empty($headers)) {
            foreach ($headers as $header) {
                $mail->addCustomHeader($header);
            }
        }

        if (!empty($attachments)) {
            foreach ($attachments as $attachment) {
                $mail->addAttachment($attachment);
            }
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $message;

        $mail->send();

        // Calculate processing time
        $processing_time = microtime(true) - $start_time;

        // Log successful email
        $to_addresses = is_array($to) ? implode(', ', $to) : $to;
        mailniaga_smtp_log_success("Email sent via STMP to: $to_addresses, Subject: $subject", $processing_time);

        return true;
    } catch (Exception $e) {
        // Log error with exception message
        mailniaga_smtp_log_error("Backup SMTP Email could not be sent. Mailer Error: {$mail->ErrorInfo}");

        return false;
    }
}

// Modify the existing mailniaga_smtp_log_error function in mailniaga-smtp.php

function mailniaga_smtp_log_error($error_message, $error_code = '', $debug_info = '', $email_id = null) {
    if (get_option('mailniaga_smtp_enable_error_log', '1') === '1') {
        $log_entry = current_time('Y-m-d H:i:s') . " - Error Code: $error_code - $error_message\n";
        // if (!empty($debug_info)) {
        //     $log_entry .= "Debug Info:\n$debug_info\n";
        // }
        if ($email_id) {
            $log_entry .= "Email ID: $email_id\n";
        }
        $log_entry .= "------------------------\n";
        
        $log_file = MAILNIAGA_SMTP_PLUGIN_DIR . 'logs/error.log';
        
        if (!file_exists(dirname($log_file))) {
            mkdir(dirname($log_file), 0755, true);
        }
        
        file_put_contents($log_file, $log_entry, FILE_APPEND);
    }
}

function mailniaga_smtp_log_success($message, $processing_time) {
    if (get_option('mailniaga_smtp_enable_email_log', '1') === '1') {
        $log_entry = current_time('Y-m-d H:i:s') . " - Success - $message\n";
        $log_entry .= "Processing Time: $processing_time seconds\n";
        $log_entry .= "------------------------\n";
        
        $log_file = MAILNIAGA_SMTP_PLUGIN_DIR . 'logs/email.log';
        
        if (!file_exists(dirname($log_file))) {
            mkdir(dirname($log_file), 0755, true);
        }
        
        file_put_contents($log_file, $log_entry, FILE_APPEND);
    } else {
        return;
    }
}

// add_action('admin_init', 'test_clear_logs');
// function test_clear_logs() {
//     if (isset($_GET['test_clear_logs'])) {
//         $admin = new MailNiaga_SMTP_Admin();
//         $admin->clear_log();
//         $admin->clear_email_log();
//         exit('Logs cleared');
//     }
// }

function mailniaga_smtp_get_email_stats() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'mailniaga_email_queue';

    $stats = array(
        'total_sent' => $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'sent'"),
        'total_failed' => $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'failed'"),
        'total_queued' => $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'queued'")
    );

    return $stats;
}

function mailniaga_smtp_display_email_stats() {
    $stats = mailniaga_smtp_get_email_stats();
    ?>
    <div class="email-stats">
        <h3>Email Statistics</h3>
        <p>Total Sent: <?php echo $stats['total_sent']; ?></p>
        <p>Total Failed: <?php echo $stats['total_failed']; ?></p>
        <p>Total Queued: <?php echo $stats['total_queued']; ?></p>
    </div>
    <?php
}

function mailniaga_smtp_reset_email_stats() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'mailniaga_email_queue';
    
    $wpdb->query("TRUNCATE TABLE $table_name");
    
    wp_redirect(add_query_arg('page', 'mailniaga-smtp', admin_url('options-general.php')));
    exit;
}

function mailniaga_smtp_refresh_email_queue() {
    wp_redirect(add_query_arg('page', 'mailniaga-smtp', admin_url('options-general.php')));
    exit;
}

function mailniaga_smtp_display_queue_status() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'mailniaga_email_queue';

    $queued_emails = $wpdb->get_results(
        "SELECT * FROM $table_name WHERE status = 'queued' ORDER BY created_at DESC LIMIT 20"
    );

    ?>
    <div class="queue-status">
        <h3>Email Queue Status</h3>
        <p>Total Emails in Queue: <?php echo count($queued_emails); ?></p>
        <table class="widefat">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>To</th>
                    <th>Subject</th>
                    <th>Created At</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($queued_emails as $email): ?>
                <tr>
                    <td><?php echo $email->id; ?></td>
                    <td><?php echo esc_html($email->to_email); ?></td>
                    <td><?php echo esc_html($email->subject); ?></td>
                    <td><?php echo $email->created_at; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}
