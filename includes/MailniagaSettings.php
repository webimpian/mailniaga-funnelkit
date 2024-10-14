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
			'General Settings',
			[$this, 'general_section_info'],
			'mailniaga-smtp-admin'
		);

		add_settings_section(
			'mailniaga_smtp_section',
			'SMTP Settings',
			[$this, 'smtp_section_info'],
			'mailniaga-smtp-admin'
		);

		add_settings_section(
			'mailniaga_api_section',
			'API Settings',
			[$this, 'api_section_info'],
			'mailniaga-smtp-admin'
		);

		$this->add_settings_fields();
	}

	private function add_settings_fields() {
		// General Settings
		add_settings_field(
			'mailing_method',
			'Mailing Method',
			[$this, 'mailing_method_callback'],
			'mailniaga-smtp-admin',
			'mailniaga_general_section'
		);

		// SMTP Settings
		$smtp_fields = [
			'smtp_host' => 'SMTP Host',
			'smtp_port' => 'SMTP Port',
			'smtp_username' => 'SMTP Username',
			'smtp_password' => 'SMTP Password',
			'smtp_encryption' => 'SMTP Encryption',
			'from_email' => 'From Email',
			'from_name' => 'From Name'
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
			'API Key',
			[$this, 'api_key_callback'],
			'mailniaga-smtp-admin',
			'mailniaga_api_section'
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
		echo 'Choose between SMTP and API:';
	}

	public function smtp_section_info() {
		echo 'Enter your Mail Niaga SMTP settings below:';
	}

	public function api_section_info() {
		echo 'Enter your Mail Niaga API settings below:';
	}

	public function mailing_method_callback() {
		$method = $this->options['mailing_method'] ?? 'smtp';
		?>
		<label>
			<input type="radio" name="<?php echo $this->option_name; ?>[mailing_method]" value="smtp" <?php checked($method, 'smtp'); ?>>
			SMTP
		</label>
		<label>
			<input type="radio" name="<?php echo $this->option_name; ?>[mailing_method]" value="api" <?php checked($method, 'api'); ?>>
			API
		</label>
		<?php
	}

	public function smtp_host_callback() {
		echo '<input type="text" id="smtp_host" value="smtp.mailniaga.mx" disabled>';
		echo '<input type="hidden" name="' . $this->option_name . '[smtp_host]" value="smtp.mailniaga.mx">';
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
		<select name="<?php echo $this->option_name; ?>[smtp_encryption]" id="smtp_encryption">
			<option value="none" <?php selected($encryption, 'none'); ?>>None</option>
			<option value="ssl" <?php selected($encryption, 'ssl'); ?>>SSL</option>
			<option value="tls" <?php selected($encryption, 'tls'); ?>>TLS</option>
		</select>
		<?php
	}

	public function from_email_callback() {
		$default_email = get_option('admin_email');
		$this->text_field_callback('from_email', $default_email, 'email');
		echo '<p class="description">Default: ' . esc_html($default_email) . '</p>';
	}

	public function from_name_callback() {
		$default_name = get_option('blogname');
		$this->text_field_callback('from_name', $default_name);
		echo '<p class="description">Default: ' . esc_html($default_name) . '</p>';
	}

	public function api_key_callback() {
		$this->text_field_callback('api_key');
	}

	private function text_field_callback($field, $default = '', $type = 'text') {
		printf(
			'<input type="%s" name="%s[%s]" id="%s" value="%s" placeholder="%s">',
			$type,
			$this->option_name,
			$field,
			$field,
			isset($this->options[$field]) ? esc_attr($this->options[$field]) : '',
			esc_attr($default)
		);
	}
}