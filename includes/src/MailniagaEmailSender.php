<?php

namespace Webimpian\MailniagaWPConnector;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class MailniagaEmailSender {
	private $settings;
	private $client;

	public function __construct($settings) {
		$this->settings = $settings;
		$this->client = new Client([
			'base_uri' => 'https://api.mailniaga.mx/api/v0/',
			'timeout'  => 99999999999999999, // Reduced timeout for individual requests
		]);
	}

	public function register() {
		add_filter('pre_wp_mail', [$this, 'queue_mail'], 10, 2);
		add_action('init', [$this, 'schedule_queue_processing']);
		add_action('process_mailniaga_email_queue', [$this, 'process_email_queue']);
	}

	public function queue_mail($null, $atts): bool {
		global $wpdb;

		$to = is_array($atts['to']) ? implode(',', $atts['to']) : $atts['to'];
		$headers = $this->parse_headers($atts['headers']);

		$from_email = $this->get_from_email($headers);
		$from_name = $this->get_from_name($headers);

		$table_name = $wpdb->prefix . 'mailniaga_email_queue';
		$result = $wpdb->insert(
			$table_name,
			[
				'to_email' => $to,
				'from_email' => $from_email,
				'from_name' => $from_name,
				'subject' => $atts['subject'],
				'message' => $atts['message'],
				'headers' => serialize($headers),
				'attachments' => serialize($atts['attachments'] ?? []),
				'status' => 'queued',
				'created_at' => current_time('mysql'),
			]
		);

		if ($result === false) {
			$this->log("Failed to queue email to: $to, subject: {$atts['subject']}. DB Error: " . $wpdb->last_error);
			return false;
		}

		$this->log("Email queued successfully. To: $to, Subject: {$atts['subject']}");
		return true;
	}

	public function schedule_queue_processing() {
		if (!as_next_scheduled_action('process_mailniaga_email_queue')) {
			as_schedule_recurring_action(time(), 1, 'process_mailniaga_email_queue');
			//$this->log("Email queue processing scheduled");
		}
	}

	public function process_email_queue() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'mailniaga_email_queue';

		$emails = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE status = 'queued' ORDER BY created_at ASC LIMIT %d",
				100
			)
		);

		//$this->log("Processing " . count($emails) . " emails from queue");

		foreach ($emails as $email) {
			$this->send_queued_email($email);
		}
	}

	private function send_queued_email($email) {
		$to = explode(',', $email->to_email);
		$headers = unserialize($email->headers);

		$data = [
			'from' => sprintf('%s <%s>', $email->from_name, $email->from_email),
			'to' => $to,
			'reply_to' => $headers['reply-to'] ?? '',
			'subject' => $email->subject,
			'as_html' => 1,
			'content' => $email->message,
		];

		if (!empty($headers['content-type']) && strpos($headers['content-type'], 'text/plain') !== false) {
			$data['content_plain'] = $email->message;
			unset($data['content']);
		}

		try {
			//$this->log("Sending email ID: {$email->id} to: {$email->to_email}");

			$response = $this->client->request('POST', 'messages', [
				'headers' => [
					'Content-Type' => 'application/json',
					'X-API-Key' => $this->settings['api_key'],
				],
				'json' => $data,
			]);

			$result = json_decode($response->getBody(), true);

			if ($result['error'] || $result['status_code'] !== 200) {
				throw new \Exception('Failed to send email: ' . ($result['message'] ?? 'Unknown error'));
			}

			$this->update_email_status($email->id, 'sent');
			//$this->log("Email ID: {$email->id} sent successfully");
		} catch (GuzzleException $e) {
			$this->log("Mailniaga API Error for email ID: {$email->id}: " . $e->getMessage(), 'error');
			$this->update_email_status($email->id, 'failed', $e->getMessage());
		} catch (\Exception $e) {
			$this->log("Mailniaga Send Error for email ID: {$email->id}: " . $e->getMessage(), 'error');
			$this->update_email_status($email->id, 'failed', $e->getMessage());
		}
	}

	private function update_email_status($email_id, $status, $error_message = null) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'mailniaga_email_queue';

		$result = $wpdb->update(
			$table_name,
			[
				'status' => $status,
				'error_message' => $error_message,
				'updated_at' => current_time('mysql'),
			],
			['id' => $email_id]
		);

		if ($result === false) {
			$this->log("Failed to update status for email ID: $email_id. DB Error: " . $wpdb->last_error, 'error');
		} else {
			$this->log("Updated status for email ID: $email_id to: $status");
		}
	}

	private function get_from_email($headers): string {
		if (!empty($headers['from'])) {
			$from = $this->extract_email($headers['from']);
			if ($from) {
				return $from;
			}
		}
		return $this->settings['from_email'] ?? get_option('admin_email');
	}

	private function get_from_name($headers): string {
		if (!empty($headers['from'])) {
			$name = $this->extract_name($headers['from']);
			if ($name) {
				return $name;
			}
		}
		return $this->settings['from_name'] ?? get_option('blogname');
	}

	private function extract_email($from): ?string {
		if (preg_match('/<(.+)>/', $from, $matches)) {
			return $matches[1];
		}
		if (filter_var($from, FILTER_VALIDATE_EMAIL)) {
			return $from;
		}
		return null;
	}

	private function extract_name($from): ?string {
		if (preg_match('/^(.+?)\s*</', $from, $matches)) {
			return trim($matches[1], ' "');
		}
		return null;
	}

	private function parse_headers($headers): array {
		$parsed = [];
		if (is_string($headers)) {
			$headers = explode("\n", $headers);
		}
		foreach ($headers as $header) {
			if (is_string($header)) {
				$parts = explode(':', $header, 2);
				if (count($parts) == 2) {
					$parsed[strtolower(trim($parts[0]))] = trim($parts[1]);
				}
			} elseif (is_array($header) && count($header) == 2) {
				$parsed[strtolower(trim($header[0]))] = trim($header[1]);
			}
		}
		return $parsed;
	}

	public function send_test_email($to): array {
		$subject = __('Mailniaga WP Connector Test Email', 'mailniaga-smtp');
		$message = __('This is a test email sent from the Mailniaga WP Connector plugin.', 'mailniaga-smtp');

		$start_time = microtime(true);
		$result = wp_mail($to, $subject, $message);
		$end_time = microtime(true);
		$time_taken = round($end_time - $start_time, 3);

		if ($result) {
			$this->log("Test email sent successfully to: $to");
		} else {
			$this->log("Failed to send test email to: $to", 'error');
		}

		return [
			'success' => $result,
			'time_taken' => $time_taken,
			'error_message' => $result ? null : $this->get_last_error(),
		];
	}

	private function get_last_error() {
		if (function_exists('error_get_last')) {
			$error = error_get_last();
			if ($error !== null) {
				return $error['message'];
			}
		}
		return 'Unknown error';
	}

	private function log($message, $level = 'info') {
		$log_message = "[Mailniaga WP Connector] [$level] $message";
		error_log($log_message);
	}
}