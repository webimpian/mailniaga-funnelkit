<?php

namespace Webimpian\MailniagaSmtp;

use WP_Error;

class MailniagaSmtp {
	private MailniagaSettings $settings;

	public function __construct() {
		$this->settings = new MailniagaSettings();
	}

	public function register() {
		$this->settings->register();
		add_action('admin_menu', [$this, 'add_admin_menu']);
		add_action('admin_post_mailniaga_smtp_test_email', [$this, 'send_test_email']);
		add_filter('wp_mail_from', [$this, 'set_from_email']);
		add_filter('wp_mail_from_name', [$this, 'set_from_name']);

		if ($this->is_smtp_enabled()) {
			add_action('phpmailer_init', [$this, 'configure_smtp']);
		} else {
			add_filter('pre_wp_mail', [$this, 'send_via_api'], 10, 2);
		}
	}

	private function is_smtp_enabled(): bool {
		$options = $this->settings->get_options();
		return $options['mailing_method'] === 'smtp';
	}

	public function add_admin_menu() {
		add_menu_page(
			'Mail Niaga SMTP',
			'Mail Niaga SMTP',
			'manage_options',
			'mailniaga-smtp',
			[$this, 'create_admin_page'],
			'dashicons-email-alt'
		);
	}

	public function create_admin_page() {
		?>
        <div class="wrap">
            <h1>Mail Niaga SMTP Settings</h1>
            <form method="post" action="options.php">
				<?php
				settings_fields('mailniaga_smtp_option_group');
				do_settings_sections('mailniaga-smtp-admin');
				submit_button();
				?>
            </form>
            <hr>
            <h2>Test Email</h2>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="mailniaga_smtp_test_email">
				<?php wp_nonce_field('mailniaga_smtp_test_email', 'mailniaga_smtp_test_email_nonce'); ?>
                <p>
                    <input type="email" name="test_email" placeholder="Enter recipient email" required>
					<?php submit_button('Send Test Email', 'secondary', 'submit', false); ?>
                </p>
            </form>
        </div>
		<?php
	}

	public function send_test_email() {
		if (
			!isset($_POST['mailniaga_smtp_test_email_nonce']) ||
			!wp_verify_nonce($_POST['mailniaga_smtp_test_email_nonce'], 'mailniaga_smtp_test_email')
		) {
			wp_die('Invalid nonce');
		}

		if (!current_user_can('manage_options')) {
			wp_die('Unauthorized access');
		}

		$to = sanitize_email($_POST['test_email']);
		$subject = 'Mail Niaga SMTP Test Email';
		$message = 'This is a test email sent from your WordPress site using Mail Niaga SMTP plugin.';
		$headers = array('Content-Type: text/html; charset=UTF-8');

		$result = wp_mail($to, $subject, $message, $headers);

		if ($result) {
			$status = 'success';
			$message = 'Test email sent successfully!';
		} else {
			$status = 'error';
			$message = 'Failed to send test email. Please check your settings.';
		}

		wp_safe_redirect(add_query_arg(
			array(
				'page' => 'mailniaga-smtp',
				'status' => $status,
				'message' => urlencode($message)
			),
			admin_url('options-general.php')
		));
		exit;
	}

	public function configure_smtp($phpmailer) {
		$options = $this->settings->get_options();

		$phpmailer->isSMTP();
		$phpmailer->Host = $options['smtp_host'];
		$phpmailer->SMTPAuth = true;
		$phpmailer->Port = $options['smtp_port'];
		$phpmailer->Username = $options['smtp_username'];
		$phpmailer->Password = $options['smtp_password'];

		if ($options['smtp_encryption'] != 'none') {
			$phpmailer->SMTPSecure = $options['smtp_encryption'];
		}
	}

	public function send_via_api($null, $atts) {
		$options = $this->settings->get_options();

		$to = is_array($atts['to']) ? implode(',', $atts['to']) : $atts['to'];
		$subject = $atts['subject'];
		$message = $atts['message'];

		$data = [
			'from' => $this->get_from(),
			'to' => [$to],
			'subject' => $subject,
			'as_html' => 1,
			'content' => $message
		];

		$response = wp_remote_post('https://api.mailniaga.mx/api/v0/messages', [
			'headers' => [
				'Content-Type' => 'application/json',
				'X-API-Key' => $options['api_key']
			],
			'body' => json_encode($data)
		]);

		if (is_wp_error($response)) {
			return new WP_Error('mailniaga_api_error', $response->get_error_message());
		}

		$body = wp_remote_retrieve_body($response);
		$result = json_decode($body, true);

		if (wp_remote_retrieve_response_code($response) !== 200) {
			return new WP_Error('mailniaga_api_error', $result['message'] ?? 'Unknown error');
		}

		return true;
	}

	private function get_from(): string {
		$options = $this->settings->get_options();
		$from_email = !empty($options['from_email']) ? $options['from_email'] : get_option('admin_email');
		$from_name = !empty($options['from_name']) ? $options['from_name'] : get_option('blogname');
		return "$from_name <$from_email>";
	}

	public function set_from_email($email) {
		$options = $this->settings->get_options();
		return !empty($options['from_email']) ? $options['from_email'] : get_option('admin_email');
	}

	public function set_from_name($name) {
		$options = $this->settings->get_options();
		return !empty($options['from_name']) ? $options['from_name'] : get_option('blogname');
	}
}