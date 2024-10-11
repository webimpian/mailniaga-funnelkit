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
			'timeout'  => 10.0,
		]);
	}

	public function register() {
		add_filter('pre_wp_mail', [$this, 'send_mail'], 10, 2);
	}

	public function send_mail($null, $atts): bool {
		if (empty($this->settings['api_key'])) {
			error_log('Mailniaga API Error: API key is not set');
			return false;
		}

		$to = is_array($atts['to']) ? $atts['to'] : [$atts['to']];
		$headers = $this->parse_headers($atts['headers']);

		// Use the provided From email and name if available, otherwise use default settings
		$from_email = $this->get_from_email($headers);
		$from_name = $this->get_from_name($headers);
		$from = $from_name ? sprintf('%s <%s>', $from_name, $from_email) : $from_email;

		$data = [
			'from' => $from,
			'to' => $to,
			'reply_to' => $headers['reply-to'] ?? '',
			'subject' => $atts['subject'],
			'as_html' => 1,
			'content' => $atts['message'],
		];

		if (!empty($headers['content-type']) && strpos($headers['content-type'], 'text/plain') !== false) {
			$data['content_plain'] = $atts['message'];
			unset($data['content']);
		}

		try {
			$response = $this->client->request('POST', 'messages', [
				'headers' => [
					'Content-Type' => 'application/json',
					'X-API-Key' => $this->settings['api_key'],
				],
				'json' => $data,
			]);

			$result = json_decode($response->getBody(), true);
			error_log('Mailniaga API Response: ' . json_encode($result));

			if ($result['error'] || $result['status_code'] !== 200) {
				throw new \Exception('Failed to send email: ' . ($result['message'] ?? 'Unknown error'));
			}

			error_log('Mailniaga API Success: Email sent successfully to ' . $result['data']['total_recipient'] . ' recipient(s)');
			return true;
		} catch (GuzzleException $e) {
			error_log('Mailniaga API Error: ' . $e->getMessage());
			error_log('Request data: ' . json_encode($data));
			return false;
		} catch (\Exception $e) {
			error_log('Mailniaga Send Error: ' . $e->getMessage());
			error_log('Request data: ' . json_encode($data));
			return false;
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
		$subject = __('Mailniaga WP Connector Test Email', 'mailniaga-wp-connector');
		$message = __('This is a test email sent from the Mailniaga WP Connector plugin.', 'mailniaga-wp-connector');

		$start_time = microtime(true);
		$result = wp_mail($to, $subject, $message);
		$end_time = microtime(true);
		$time_taken = round($end_time - $start_time, 3);

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
}