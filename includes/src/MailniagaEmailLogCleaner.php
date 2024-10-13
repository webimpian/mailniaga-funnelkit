<?php

namespace Webimpian\MailniagaWPConnector;

class MailniagaEmailLogCleaner {
	private const DAYS_TO_KEEP = 7;

	public function register() {
		// Register the cleanup action
		add_action('mailniaga_clean_email_logs', [$this, 'clean_old_email_logs']);

		// Schedule the event if it's not already scheduled
		if (!wp_next_scheduled('mailniaga_clean_email_logs')) {
			wp_schedule_event(time(), 'hourly', 'mailniaga_clean_email_logs');
		}
	}

	public function clean_old_email_logs() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'mailniaga_email_queue';
		$cutoff_date = date('Y-m-d H:i:s', strtotime('-' . self::DAYS_TO_KEEP . ' days'));

		// Log the start of the cleanup process
		error_log('MailniagaEmailLogCleaner: Starting cleanup process');

		$query = $wpdb->prepare(
			"DELETE FROM $table_name WHERE created_at < %s",
			$cutoff_date
		);

		$rows_affected = $wpdb->query($query);

		// Log the number of deleted records
		error_log("MailniagaEmailLogCleaner: Deleted $rows_affected old email log entries");

		// Log any potential errors
		if ($wpdb->last_error) {
			error_log("MailniagaEmailLogCleaner: Error during cleanup - " . $wpdb->last_error);
		}
	}

	public function unregister() {
		$timestamp = wp_next_scheduled('mailniaga_clean_email_logs');
		if ($timestamp) {
			wp_unschedule_event($timestamp, 'mailniaga_clean_email_logs');
			error_log('MailniagaEmailLogCleaner: Unscheduled cleanup event');
		}
	}
}