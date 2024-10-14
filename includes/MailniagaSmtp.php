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
		// Check user capabilities
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'mailniaga-smtp'));
		}

		// Handle form submission
		if (isset($_POST['submit']) && check_admin_referer('mailniaga_smtp_settings', 'mailniaga_smtp_nonce')) {
			// Process and save settings
			$this->settings->save_options($_POST);
			add_settings_error('mailniaga_smtp_messages', 'mailniaga_smtp_message', __('Settings Saved', 'mailniaga-smtp'), 'updated');
		}

		$active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';
		?>
        <div class="wrap mailniaga-smtp-wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
			<?php settings_errors('mailniaga_smtp_messages'); ?>
            <h2 class="nav-tab-wrapper">
                <a href="<?php echo esc_url(add_query_arg(['page' => 'mailniaga-smtp', 'tab' => 'general'], admin_url('admin.php'))); ?>" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>"><?php echo esc_html__('General', 'mailniaga-smtp'); ?></a>
                <a href="<?php echo esc_url(add_query_arg(['page' => 'mailniaga-smtp', 'tab' => 'smtp'], admin_url('admin.php'))); ?>" class="nav-tab <?php echo $active_tab == 'smtp' ? 'nav-tab-active' : ''; ?>"><?php echo esc_html__('SMTP', 'mailniaga-smtp'); ?></a>
                <a href="<?php echo esc_url(add_query_arg(['page' => 'mailniaga-smtp', 'tab' => 'api'], admin_url('admin.php'))); ?>" class="nav-tab <?php echo $active_tab == 'api' ? 'nav-tab-active' : ''; ?>"><?php echo esc_html__('API', 'mailniaga-smtp'); ?></a>
            </h2>
            <form method="post" action="">
				<?php
				// Output security fields
				settings_fields('mailniaga_smtp_option_group');
				// Output nonce field
				wp_nonce_field('mailniaga_smtp_settings', 'mailniaga_smtp_nonce');
				?>
                <div class="tab-content">
                    <div id="general-settings" class="tab-pane" style="display: <?php echo $active_tab == 'general' ? 'block' : 'none'; ?>">
                        <h2><?php echo esc_html__('General Settings', 'mailniaga-smtp'); ?></h2>
                        <table class="form-table">
							<?php do_settings_fields('mailniaga-smtp-admin', 'mailniaga_general_section'); ?>
                        </table>
                    </div>
                    <div id="smtp-settings" class="tab-pane" style="display: <?php echo $active_tab == 'smtp' ? 'block' : 'none'; ?>">
                        <h2><?php echo esc_html__('SMTP Settings', 'mailniaga-smtp'); ?></h2>
                        <table class="form-table">
							<?php do_settings_fields('mailniaga-smtp-admin', 'mailniaga_smtp_section'); ?>
                        </table>
                    </div>
                    <div id="api-settings" class="tab-pane" style="display: <?php echo $active_tab == 'api' ? 'block' : 'none'; ?>">
                        <h2><?php echo esc_html__('API Settings', 'mailniaga-smtp'); ?></h2>
                        <table class="form-table">
							<?php do_settings_fields('mailniaga-smtp-admin', 'mailniaga_api_section'); ?>
                        </table>
                    </div>
                </div>
				<?php submit_button(); ?>
            </form>
            <hr>
            <h2><?php echo esc_html__('Test Email', 'mailniaga-smtp'); ?></h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="mailniaga_smtp_test_email">
				<?php wp_nonce_field('mailniaga_smtp_test_email', 'mailniaga_smtp_test_email_nonce'); ?>
                <p>
                    <input type="email" name="test_email" placeholder="<?php echo esc_attr__('Enter recipient email', 'mailniaga-smtp'); ?>" required>
					<?php submit_button(__('Send Test Email', 'mailniaga-smtp'), 'secondary', 'submit_test_email', false); ?>
                </p>
            </form>
        </div>
		<?php
	}

	public function send_test_email() {
		if (
			!isset($_POST['mailniaga_smtp_test_email_nonce']) ||
			!wp_verify_nonce(sanitize_key(wp_unslash($_POST['mailniaga_smtp_test_email_nonce'])), 'mailniaga_smtp_test_email')
		) {
			wp_die(esc_html__('Invalid nonce', 'mailniaga-smtp'));
		}

		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('Unauthorized access', 'mailniaga-smtp'));
		}

		$to = isset($_POST['test_email']) ? sanitize_email(wp_unslash($_POST['test_email'])) : '';
		if (empty($to)) {
			wp_die(esc_html__('Invalid email address', 'mailniaga-smtp'));
		}

		$subject = esc_html__('Mail Niaga SMTP Test Email', 'mailniaga-smtp');
		$message = esc_html__('This is a test email sent from your WordPress site using Mail Niaga SMTP plugin.', 'mailniaga-smtp');
		$headers = array('Content-Type: text/html; charset=UTF-8');

		$result = wp_mail($to, $subject, $message, $headers);

		if ($result) {
			$status = 'success';
			$message = esc_html__('Test email sent successfully!', 'mailniaga-smtp');
		} else {
			$status = 'error';
			$message = esc_html__('Failed to send test email. Please check your settings.', 'mailniaga-smtp');
		}

		wp_safe_redirect(add_query_arg(
			array(
				'page' => 'mailniaga-smtp',
				'status' => $status,
				'message' => urlencode($message)
			),
			admin_url('admin.php')
		));
		exit;
	}

	public function configure_smtp($phpmailer) {
		$options = $this->settings->get_options();

		$phpmailer->isSMTP();
		$phpmailer->Host = !empty($options['smtp_host']) ? $options['smtp_host'] : 'smtp.mailniaga.mx';
		$phpmailer->SMTPAuth = true;
		$phpmailer->Port = !empty($options['smtp_port']) ? $options['smtp_port'] : '2524';
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
			'body' => wp_json_encode($data)
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
		return sprintf('%s <%s>', $from_name, $from_email);
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