<?php

namespace Webimpian\MailniagaWPConnector;

class MailniagaFailedDeliveriesLog {
	private int $per_page = 10;

	public function register() {
		add_action('admin_menu', [$this, 'add_submenu_page']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
	}

	public function add_submenu_page() {
		$hook = add_submenu_page(
			'mailniaga-smtp',
			__('Failed Deliveries', 'mailniaga-smtp'),
			__('Failed Deliveries', 'mailniaga-smtp'),
			'manage_options',
			'mailniaga-smtp-failed-deliveries',
			[$this, 'render_failed_deliveries_page']
		);
		add_action("load-$hook", [$this, 'screen_option']);
	}

	public function screen_option() {
		// Add screen options if needed
	}

	public function enqueue_scripts($hook) {
		if (strpos($hook, 'mailniaga-smtp-failed-deliveries') === false) {
			return;
		}

		wp_enqueue_style(
			'mailniaga-email-log',
			MAILNIAGA_WP_CONNECTOR['URL'] . 'includes/src/assets/css/email-log.css',
			[],
			MAILNIAGA_WP_CONNECTOR['VERSION']
		);
	}

	public function render_failed_deliveries_page() {
		$page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
		$total_items = $this->get_total_failed_deliveries();
		$failed_deliveries = $this->get_failed_deliveries($page);

		?>
        <div class="wrap">
            <h1><?php echo esc_html(__('Failed Deliveries', 'mailniaga-smtp')); ?></h1>

            <table class="wp-list-table widefat fixed striped mailniaga-email-log-table">
                <thead>
                <tr>
                    <th><?php _e('ID', 'mailniaga-smtp'); ?></th>
                    <th><?php _e('Domain', 'mailniaga-smtp'); ?></th>
                    <th><?php _e('To Email', 'mailniaga-smtp'); ?></th>
                    <th><?php _e('From Email', 'mailniaga-smtp'); ?></th>
                    <th><?php _e('MX', 'mailniaga-smtp'); ?></th>
                    <th><?php _e('Response', 'mailniaga-smtp'); ?></th>
                    <th><?php _e('Created At', 'mailniaga-smtp'); ?></th>
                    <th><?php _e('Unsubscribed', 'mailniaga-smtp'); ?></th>
                </tr>
                </thead>
                <tbody>
				<?php if (empty($failed_deliveries)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center;">
							<?php _e('No failed deliveries found.', 'mailniaga-smtp'); ?>
                        </td>
                    </tr>
				<?php else: ?>
					<?php foreach ($failed_deliveries as $delivery): ?>
                        <tr>
                            <td><?php echo esc_html($delivery->id); ?></td>
                            <td><?php echo esc_html($delivery->domain); ?></td>
                            <td><?php echo esc_html($delivery->to_email); ?></td>
                            <td><?php echo esc_html($delivery->from_email); ?></td>
                            <td><?php echo esc_html($delivery->mx); ?></td>
                            <td><?php echo esc_html($delivery->delivery_response); ?></td>
                            <td><?php echo esc_html($delivery->created_at); ?></td>
                            <td><?php echo $delivery->unsubscribed ? __('Yes', 'mailniaga-smtp') : __('No', 'mailniaga-smtp'); ?></td>
                        </tr>
					<?php endforeach; ?>
				<?php endif; ?>
                </tbody>
            </table>
			<?php if (!empty($failed_deliveries)): ?>
				<?php $this->pagination($page, $total_items); ?>
			<?php endif; ?>
        </div>
		<?php
	}

	private function get_total_failed_deliveries() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'mailniaga_failed_deliveries';
		return $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
	}

	private function get_failed_deliveries($page) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'mailniaga_failed_deliveries';
		$offset = ($page - 1) * $this->per_page;

		$query = "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT %d OFFSET %d";
		return $wpdb->get_results($wpdb->prepare($query, $this->per_page, $offset));
	}

	private function pagination($page, $total_items) {
		$total_pages = ceil($total_items / $this->per_page);

		echo '<div class="tablenav"><div class="tablenav-pages">';
		echo '<span class="displaying-num">' . sprintf(_n('%s item', '%s items', $total_items, 'mailniaga-smtp'), number_format_i18n($total_items)) . '</span>';

		echo '<span class="pagination-links">';

		if ($page > 1) {
			echo '<a class="first-page button" href="' . esc_url(add_query_arg('paged', 1)) . '">«</a>';
			echo '<a class="prev-page button" href="' . esc_url(add_query_arg('paged', $page - 1)) . '">‹</a>';
		} else {
			echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">«</span>';
			echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">‹</span>';
		}

		echo '<span class="paging-input">';
		echo '<label for="current-page-selector" class="screen-reader-text">' . __('Current Page', 'mailniaga-smtp') . '</label>';
		echo '<input class="current-page" id="current-page-selector" type="text" name="paged" value="' . esc_attr($page) . '" size="1" aria-describedby="table-paging">';
		echo '<span class="tablenav-paging-text"> ' . __('of', 'mailniaga-smtp') . ' <span class="total-pages">' . number_format_i18n($total_pages) . '</span></span>';
		echo '</span>';

		if ($page < $total_pages) {
			echo '<a class="next-page button" href="' . esc_url(add_query_arg('paged', $page + 1)) . '">›</a>';
			echo '<a class="last-page button" href="' . esc_url(add_query_arg('paged', $total_pages)) . '">»</a>';
		} else {
			echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">›</span>';
			echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">»</span>';
		}

		echo '</span></div></div>';
	}
}