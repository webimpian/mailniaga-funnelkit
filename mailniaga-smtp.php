<?php
/**
 * Mail Niaga SMTP.
 *
 * @author  Web Impian
 * @license GPLv3
 *
 * @see    https://mailniaga.com
 */

/*
 * @wordpress-plugin
 * Plugin Name:         Mail Niaga SMTP
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
		'PATH'     => realpath(plugin_dir_path(__FILE__)),
		'URL'      => trailingslashit(plugin_dir_url(__FILE__)),
		'VERSION'  => '1.0.0',
	]
);

require __DIR__.'/includes/MailniagaSmtp.php';
require __DIR__.'/includes/MailniagaSettings.php';
(new MailniagaSmtp())->register();

add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
	$settings_link = '<a href="' . admin_url('options-general.php?page=mailniaga-smtp') . '">' . __('Settings', 'mailniaga-smtp') . '</a>';
	array_unshift($links, $settings_link);
	return $links;
});

// Add admin notices for test email results
add_action('admin_notices', function() {
	if (isset($_GET['page']) && $_GET['page'] === 'mailniaga-smtp' && isset($_GET['status']) && isset($_GET['message'])) {
		$status = $_GET['status'];
		$message = urldecode($_GET['message']);
		$class = ($status === 'success') ? 'notice-success' : 'notice-error';
		?>
		<div class="notice <?php echo $class; ?> is-dismissible">
			<p><?php echo esc_html($message); ?></p>
		</div>
		<?php
	}
});