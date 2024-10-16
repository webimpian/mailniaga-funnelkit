<?php
/**
 * MailNiaga SMTP.
 *
 * @author  Web Impian
 * @license GPLv3
 *
 * @see    https://mailniaga.com
 */

/*
 * @wordpress-plugin
 * Plugin Name:         MailNiaga SMTP
 * Plugin URI:          https://mailniaga.com
 * Version:             1.0.0
 * Description:         Streamline your WordPress email delivery with Mail Niaga SMTP & API integration. Boost email deliverability, track performance, and ensure reliable SMTP service for all your website's outgoing emails.
 * Author:              Web Impian
 * Author URI:          https://webimpian.com
 * Requires at least:   5.6
 * Tested up to:        6.6.1
 * Requires PHP:        7.4
 * License:             GPLv3
 * License URI:         https://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain:         mailniaga-smtp
 * Domain Path:         /languages
 */

namespace Webimpian\MailniagaSmtp;

defined('ABSPATH') || exit;

define(
	'MAILNIAGA_SMTP',
	[
		'SLUG'     => 'mailniaga-smtp',
		'FILE'     => __FILE__,
		'HOOK'     => plugin_basename(__FILE__),
		'PATH'     => plugin_dir_path(__FILE__),
		'URL'      => plugin_dir_url(__FILE__),
		'VERSION'  => '1.0.0',
	]
);

require __DIR__ . '/includes/MailniagaSmtp.php';
require __DIR__ . '/includes/MailniagaSettings.php';

// Initialize the plugin
function init_plugin() {
	$mailniaga_smtp = new MailniagaSmtp();
	$mailniaga_smtp->register();
}
add_action('plugins_loaded', __NAMESPACE__ . '\init_plugin');

// Enqueue admin styles
function enqueue_admin_styles($hook) {
	if ('admin.php' !== $hook || !isset($_GET['page']) || 'mailniaga-smtp' !== $_GET['page']) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	wp_enqueue_style('mailniaga-smtp-admin-styles', MAILNIAGA_SMTP['URL'] . 'includes/admin-styles.css', array(), MAILNIAGA_SMTP['VERSION']);
}
add_action('admin_enqueue_scripts', __NAMESPACE__ . '\enqueue_admin_styles');

// Add settings link to plugin page
add_filter('plugin_action_links_' . MAILNIAGA_SMTP['HOOK'], function($links) {
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		esc_url(admin_url('admin.php?page=mailniaga-smtp')),
		esc_html__('Settings', 'mailniaga-smtp')
	);
	array_unshift($links, $settings_link);
	return $links;
});

// Add admin notices for test email results
add_action('admin_notices', function() {
	if (isset($_GET['page']) && 'mailniaga-smtp' === $_GET['page']) {
		// Sanitize and verify nonce
		$nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';

		if (wp_verify_nonce($nonce, 'mailniaga_smtp_test_email')) {
			if (isset($_GET['status'], $_GET['message'])) {
				$status = sanitize_key($_GET['status']);
				$message = wp_kses(wp_unslash($_GET['message']), array());
				$class = ('success' === $status) ? 'notice-success' : 'notice-error';
				?>
                <div class="notice <?php echo esc_attr($class); ?> is-dismissible">
                    <p><?php echo esc_html($message); ?></p>
                </div>
				<?php
			}
		}
	}
});

// Function to generate and verify nonce for test email
function generate_test_email_nonce() {
	return wp_create_nonce('mailniaga_smtp_test_email');
}

function verify_test_email_nonce($nonce) {
	return wp_verify_nonce($nonce, 'mailniaga_smtp_test_email');
}