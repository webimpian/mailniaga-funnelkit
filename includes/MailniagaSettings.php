<?php

namespace Webimpian\MailniagaSmtp;

class MailniagaSettings {
	private $options;
	private string $option_name = 'mailniaga_smtp_options';

	public function __construct() {
		$this->options = get_option($this->option_name, $this->get_default_options());
	}

	public function register() {
		add_action('admin_init', [$this, 'page_init']);
	}

	public function get_options() {
		return $this->options;
	}

	public function get_default_options(): array {
		return [
			'mailing_method' => 'smtp',
			'smtp_host' => 'smtp.mailniaga.mx',
			'smtp_port' => '2524',
			'smtp_username' => '',
			'smtp_password' => '',
			'smtp_encryption' => 'none',
			'from_email' => get_option('admin_email'),
			'from_name' => get_option('blogname'),
			'api_key' => '',
		];
	}

	public function page_init() {
		register_setting(
			'mailniaga_smtp_option_group',
			$this->option_name,
			[$this, 'sanitize']
		);

		add_settings_section(
			'mailniaga_general_section',
			esc_html__('General Settings', 'mailniaga-smtp'),
			[$this, 'general_section_info'],
			'mailniaga-smtp-admin'
		);

		add_settings_section(
			'mailniaga_smtp_section',
			esc_html__('SMTP Settings', 'mailniaga-smtp'),
			[$this, 'smtp_section_info'],
			'mailniaga-smtp-admin'
		);

		add_settings_section(
			'mailniaga_api_section',
			esc_html__('API Settings', 'mailniaga-smtp'),
			[$this, 'api_section_info'],
			'mailniaga-smtp-admin'
		);

		$this->add_settings_fields();
	}

	public function save_options($new_options) {
		$sanitized_options = $this->sanitize($new_options[$this->option_name]);
		$this->options = array_merge($this->options, $sanitized_options);
		update_option($this->option_name, $this->options);
	}

	private function add_settings_fields() {
		// General Settings
		add_settings_field(
			'mailing_method',
			esc_html__('Mailing Method', 'mailniaga-smtp'),
			[$this, 'mailing_method_callback'],
			'mailniaga-smtp-admin',
			'mailniaga_general_section'
		);

		// SMTP Settings
		$smtp_fields = [
			'smtp_host' => esc_html__('SMTP Host', 'mailniaga-smtp'),
			'smtp_port' => esc_html__('SMTP Port', 'mailniaga-smtp'),
			'smtp_username' => esc_html__('SMTP Username', 'mailniaga-smtp'),
			'smtp_password' => esc_html__('SMTP Password', 'mailniaga-smtp'),
			'smtp_encryption' => esc_html__('SMTP Encryption', 'mailniaga-smtp'),
		];

		foreach ($smtp_fields as $field => $title) {
			add_settings_field(
				$field,
				$title,
				[$this, $field . '_callback'],
				'mailniaga-smtp-admin',
				'mailniaga_smtp_section'
			);
		}

		// API Settings
		add_settings_field(
			'api_key',
			esc_html__('API Key', 'mailniaga-smtp'),
			[$this, 'api_key_callback'],
			'mailniaga-smtp-admin',
			'mailniaga_api_section'
		);

		// From Settings (added to General section)
		add_settings_field(
			'from_email',
			esc_html__('From Email', 'mailniaga-smtp'),
			[$this, 'from_email_callback'],
			'mailniaga-smtp-admin',
			'mailniaga_general_section'
		);

		add_settings_field(
			'from_name',
			esc_html__('From Name', 'mailniaga-smtp'),
			[$this, 'from_name_callback'],
			'mailniaga-smtp-admin',
			'mailniaga_general_section'
		);
	}

	public function sanitize($input): array {
		$sanitary_values = array();
		$sanitary_values['smtp_host'] = 'smtp.mailniaga.mx'; // Always set to default
		$fields = ['smtp_port', 'smtp_username', 'smtp_password', 'smtp_encryption', 'from_name', 'api_key'];
		foreach ($fields as $field) {
			if (isset($input[$field])) {
				$sanitary_values[$field] = sanitize_text_field($input[$field]);
			}
		}
		if (isset($input['from_email'])) {
			$sanitary_values['from_email'] = sanitize_email($input['from_email']);
		}
		$sanitary_values['mailing_method'] = isset($input['mailing_method']) && in_array($input['mailing_method'], ['smtp', 'api']) ? $input['mailing_method'] : 'smtp';

		return $sanitary_values;
	}

	public function general_section_info() {
		echo esc_html__('Choose between SMTP and API:', 'mailniaga-smtp');
	}

	public function smtp_section_info() {
		echo esc_html__('Enter your Mail Niaga SMTP settings below:', 'mailniaga-smtp');
	}

	public function api_section_info() {
		echo esc_html__('Enter your Mail Niaga API settings below:', 'mailniaga-smtp');
	}

	public function mailing_method_callback() {
		$method = $this->options['mailing_method'] ?? 'smtp';
		?>
        <label>
            <input type="radio" name="<?php echo esc_attr($this->option_name); ?>[mailing_method]" value="smtp" <?php checked($method, 'smtp'); ?>>
			<?php esc_html_e('SMTP', 'mailniaga-smtp'); ?>
        </label>
        <label>
            <input type="radio" name="<?php echo esc_attr($this->option_name); ?>[mailing_method]" value="api" <?php checked($method, 'api'); ?>>
			<?php esc_html_e('API', 'mailniaga-smtp'); ?>
        </label>
		<?php
	}

	public function smtp_host_callback() {
		echo '<input type="text" id="smtp_host" value="smtp.mailniaga.mx" disabled>';
		echo '<input type="hidden" name="' . esc_attr($this->option_name) . '[smtp_host]" value="smtp.mailniaga.mx">';
	}

	public function smtp_port_callback() {
		$this->text_field_callback('smtp_port', '2524');
	}

	public function smtp_username_callback() {
		$this->text_field_callback('smtp_username');
	}

	public function smtp_password_callback() {
		$this->text_field_callback('smtp_password', '', 'password');
	}

	public function smtp_encryption_callback() {
		$encryption = $this->options['smtp_encryption'] ?? 'none';
		?>
        <select name="<?php echo esc_attr($this->option_name); ?>[smtp_encryption]" id="smtp_encryption">
            <option value="none" <?php selected($encryption, 'none'); ?>><?php esc_html_e('None', 'mailniaga-smtp'); ?></option>
            <option value="ssl" <?php selected($encryption, 'ssl'); ?>><?php esc_html_e('SSL', 'mailniaga-smtp'); ?></option>
            <option value="tls" <?php selected($encryption, 'tls'); ?>><?php esc_html_e('TLS', 'mailniaga-smtp'); ?></option>
        </select>
		<?php
	}

	public function from_email_callback() {
		$default_email = get_option('admin_email');
		$this->text_field_callback('from_email', $default_email, 'email');
		echo '<p class="description">' . esc_html__('Default:', 'mailniaga-smtp') . ' ' . esc_html($default_email) . '</p>';
	}

	public function from_name_callback() {
		$default_name = get_option('blogname');
		$this->text_field_callback('from_name', $default_name);
		echo '<p class="description">' . esc_html__('Default:', 'mailniaga-smtp') . ' ' . esc_html($default_name) . '</p>';
	}

	public function api_key_callback() {
		$this->text_field_callback('api_key');
	}

	private function text_field_callback($field, $default = '', $type = 'text') {
		printf(
			'<input type="%s" name="%s[%s]" id="%s" value="%s" placeholder="%s">',
			esc_attr($type),
			esc_attr($this->option_name),
			esc_attr($field),
			esc_attr($field),
			isset($this->options[$field]) ? esc_attr($this->options[$field]) : '',
			esc_attr($default)
		);
	}
}