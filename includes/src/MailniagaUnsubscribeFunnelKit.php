<?php

namespace Webimpian\MailniagaWPConnector;

class MailniagaUnsubscribeFunnelKit {
	private string $log_file;

	public function __construct() {
		$this->log_file = WP_CONTENT_DIR . '/mailniaga-unsubscribe.log';
	}

	public function register() {
		add_action('mailniaga_unsubscribe_cron', [$this, 'process_unsubscribes']);

		if (!wp_next_scheduled('mailniaga_unsubscribe_cron')) {
			wp_schedule_event(time(), 'bwf_every_minute', 'mailniaga_unsubscribe_cron');
		}
	}

	public function process_unsubscribes() {
		$this->log("Starting unsubscribe process");

		global $wpdb;
		$failed_deliveries_table = $wpdb->prefix . 'mailniaga_failed_deliveries';
		$unsubscribe_table = $wpdb->prefix . 'bwfan_message_unsubscribe';

		$unprocessed_emails = $wpdb->get_results(
			"SELECT id, to_email FROM {$failed_deliveries_table} WHERE unsubscribed = 0"
		);

		$this->log("Found " . count($unprocessed_emails) . " unprocessed emails");

		foreach ($unprocessed_emails as $email) {
			$this->log("Processing email: " . $email->to_email);

			$insert_result = $wpdb->insert(
				$unsubscribe_table,
				[
					'recipient' => $email->to_email,
					'mode' => 1,
					'c_date' => current_time('mysql'),
					'automation_id' => 0,
					'c_type' => 3,
					'sid' => 0
				],
				['%s', '%d', '%s', '%d', '%d', '%d']
			);

			if ($insert_result === false) {
				$this->log("Error inserting into unsubscribe table: " . $wpdb->last_error);
			} else {
				$this->log("Successfully inserted into unsubscribe table");
			}

			$update_result = $wpdb->update(
				$failed_deliveries_table,
				['unsubscribed' => 1],
				['id' => $email->id],
				['%d'],
				['%d']
			);

			if ($update_result === false) {
				$this->log("Error updating failed deliveries table: " . $wpdb->last_error);
			} else {
				$this->log("Successfully updated failed deliveries table");
			}
		}

		$this->log("Finished unsubscribe process");
	}

	private function log($message) {
		$timestamp = date('[Y-m-d H:i:s]');
		$log_message = $timestamp . ' ' . $message . PHP_EOL;
		error_log($log_message, 3, $this->log_file);
	}
}