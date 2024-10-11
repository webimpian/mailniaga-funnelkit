<?php
/**
 * Mailniaga WP Connector.
 *
 * @author  Web Impian
 * @license GPLv3
 *
 * @see    https://mailniaga.com
 */

/*
 * @wordpress-plugin
 * Plugin Name:         Mailniaga WP Connector
 * Plugin URI:          https://mailniaga.com
 * Version:             1.0.0
 * Description:         Streamline your WordPress email delivery with Mailniaga API integration. Boost email deliverability, track performance, and ensure reliable SMTP service for all your website's outgoing emails.
 * Author:              Web Impian
 * Author URI:          https://webimpian.com
 * Requires at least:   5.6
 * Tested up to:        6.6.1
 * Requires PHP:        7.4
 * License:             GPLv3
 * License URI:         https://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain:         mailniaga-wp-connector
 * Domain Path:         /languages
 */

namespace Webimpian\MailniagaWPConnector;

defined('ABSPATH') || exit;

define(
	'MAILNIAGA_WP_CONNECTOR',
	[
		'SLUG'     => 'mailniaga-wp-connector',
		'FILE'     => __FILE__,
		'HOOK'     => plugin_basename(__FILE__),
		'PATH'     => realpath(plugin_dir_path(__FILE__)),
		'URL'      => trailingslashit(plugin_dir_url(__FILE__)),
	]
);

// Check PHP version
if (version_compare(PHP_VERSION, '7.4', '<')) {
	add_action('admin_notices', function() {
		echo '<div class="error"><p>' . __('Mailniaga WP Connector requires PHP 7.4 or higher.', 'mailniaga-wp-connector') . '</p></div>';
	});
	return;
}

// Load dependencies and initialize the plugin
require __DIR__.'/includes/load.php';

add_action('plugins_loaded', function() {
	MailniagaConnector::get_instance()->register();
});

// Add action to display test email results
add_action('mailniaga_display_test_email_result', [MailniagaConnector::get_instance(), 'display_test_email_result']);

// Optional: Add a link to the plugin settings page in the plugins list
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
	$settings_link = '<a href="' . admin_url('admin.php?page=mailniaga-wp-connector') . '">' . __('Settings', 'mailniaga-wp-connector') . '</a>';
	array_unshift($links, $settings_link);
	return $links;
});