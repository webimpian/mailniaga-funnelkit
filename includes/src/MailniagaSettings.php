<?php

namespace Webimpian\MailniagaWPConnector;

class MailniagaSettings {
	private $settings;

	public function __construct() {
		$this->settings = get_option('mailniaga_wp_connector_settings', []);
	}

	public function register() {
		add_action('admin_menu', [$this, 'add_admin_menu']);
		add_action('admin_init', [$this, 'register_settings']);
	}

	public function add_admin_menu() {
		add_menu_page(
			__('Mailniaga WP Connector', 'mailniaga-wp-connector'),
			__('Mailniaga WP', 'mailniaga-wp-connector'),
			'manage_options',
			'mailniaga-wp-connector',
			[$this, 'render_admin_page'],
			'dashicons-email',
			100
		);
	}

	public function register_settings() {
		register_setting('mailniaga_wp_connector_settings', 'mailniaga_wp_connector_settings');

		add_settings_section(
			'mailniaga_wp_connector_main',
			__('Main Settings', 'mailniaga-wp-connector'),
			null,
			'mailniaga-wp-connector'
		);

		$this->add_settings_fields();
	}

	private function add_settings_fields() {
		$fields = [
			'api_key' => __('Mailniaga API Key', 'mailniaga-wp-connector'),
			'from_email' => __('Default From Email', 'mailniaga-wp-connector'),
			'from_name' => __('Default From Name', 'mailniaga-wp-connector'),
		];

		foreach ($fields as $key => $label) {
			add_settings_field(
				'mailniaga_' . $key,
				$label,
				[$this, $key . '_field_callback'],
				'mailniaga-wp-connector',
				'mailniaga_wp_connector_main'
			);
		}
	}

	public function api_key_field_callback() {
		$this->render_text_field('api_key');
	}

	public function from_email_field_callback() {
		$this->render_text_field('from_email', get_option('admin_email'));
	}

	public function from_name_field_callback() {
		$this->render_text_field('from_name', get_option('blogname'));
	}

	private function render_text_field($key, $default = '') {
		$value = $this->settings[$key] ?? $default;
		echo "<input type='text' name='mailniaga_wp_connector_settings[$key]' value='" . esc_attr($value) . "' class='regular-text'>";
	}

	public function render_admin_page() {
		?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
				<?php
				settings_fields('mailniaga_wp_connector_settings');
				do_settings_sections('mailniaga-wp-connector');
				submit_button('Save Settings');
				?>
            </form>
            <hr>
            <h2><?php _e('Test Email', 'mailniaga-wp-connector'); ?></h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="mailniaga_send_test_email">
				<?php wp_nonce_field('mailniaga_test_email', 'mailniaga_test_email_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="test_email"><?php _e('Recipient Email', 'mailniaga-wp-connector'); ?></label></th>
                        <td><input type="email" id="test_email" name="test_email" class="regular-text" value="<?php echo esc_attr(get_option('admin_email')); ?>" required></td>
                    </tr>
                </table>
				<?php submit_button('Send Test Email', 'secondary', 'send_test_email'); ?>
            </form>
        </div>
		<?php
	}

	public function get_settings() {
		return $this->settings;
	}
}