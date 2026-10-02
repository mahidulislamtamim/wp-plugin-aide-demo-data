<?php
/**
 * Admin AJAX router.
 *
 * @package AideDemoDataImport
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * AJAX handlers.
 */
class Aide_Demo_Data_Import_Ajax
{

	/**
	 * Register handlers.
	 */
	public static function init()
	{
		foreach (self::actions() as $action) {
			add_action('wp_ajax_' . $action, array(__CLASS__, 'route'));
		}
	}

	/**
	 * Action names.
	 *
	 * @return string[]
	 */
	private static function actions()
	{
		return array(
			'aidedemodataimport_start_import',
			'aidedemodataimport_run_batch',
			'aidedemodataimport_get_status',
			'aidedemodataimport_cleanup',
		);
	}

	/**
	 * Route AJAX requests.
	 */
	public static function route()
	{
		$action = isset($_POST['action']) ? sanitize_text_field(wp_unslash($_POST['action'])) : '';

		$nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
		if (!wp_verify_nonce($nonce, 'aidedemodataimport_admin_nonce')) {
			wp_send_json_error(array('message' => __('Security check failed.', 'aidedemodataimport')));
		}

		if (!current_user_can(aidedemodataimport_required_capability())) {
			wp_send_json_error(array('message' => __('Unauthorized.', 'aidedemodataimport')));
		}

		$type = isset($_POST['type']) ? sanitize_key(wp_unslash($_POST['type'])) : '';
		$importer = Aide_Demo_Data_Import_Importer_Registry::get($type);

		if (!$importer) {
			wp_send_json_error(array('message' => __('Unknown importer type.', 'aidedemodataimport')));
		}

		switch ($action) {
			case 'aidedemodataimport_start_import':
				self::start_import($importer);
				break;
			case 'aidedemodataimport_run_batch':
				self::run_batch($importer);
				break;
			case 'aidedemodataimport_get_status':
				wp_send_json_success($importer->get_status());
				break;
			case 'aidedemodataimport_cleanup':
				self::run_cleanup($importer);
				break;
			default:
				wp_send_json_error(array('message' => __('Unknown action.', 'aidedemodataimport')));
		}
	}

	/**
	 * Resolve how many records the user requested for this session.
	 *
	 * @param Aide_Demo_Data_Import_Importer_Base $importer Importer.
	 * @return int
	 */
	private static function resolve_import_count($importer)
	{
		$pending = max(0, (int) $importer->count_pending());
		if ($pending < 1) {
			return 0;
		}

		// Nonce verified in route() before this method runs.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$count = isset($_POST['count']) ? absint(wp_unslash($_POST['count'])) : $pending;
		if ($count < 1) {
			$count = $pending;
		}

		return min($count, $pending);
	}

	/**
	 * Session target count from the client (not re-capped to shrinking pending).
	 *
	 * @return int
	 */
	private static function session_target_count()
	{
		// Nonce verified in route() before this method runs.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		return isset($_POST['count']) ? absint(wp_unslash($_POST['count'])) : 0;
	}

	/**
	 * Start import session.
	 *
	 * @param Aide_Demo_Data_Import_Importer_Base $importer Importer.
	 */
	private static function start_import($importer)
	{
		if (!$importer->is_available()) {
			wp_send_json_error(
				array(
					'message' => __('This importer is not available. Activate WooCommerce if required.', 'aidedemodataimport'),
				)
			);
		}

		$deps = $importer->validate_dependencies();
		if (is_wp_error($deps)) {
			wp_send_json_error(
				array(
					'message' => $deps->get_error_message(),
				)
			);
		}

		$type    = $importer->get_type();
		$total   = self::resolve_import_count($importer);
		$pending = (int) $importer->count_pending();

		/**
		 * Fires before an import starts.
		 *
		 * @param string $type Importer type.
		 */
		do_action('aidedemodataimport_before_import', $type);

		wp_send_json_success(
			array(
				'type'      => $type,
				'total'     => $total,
				'available' => (int) $importer->get_total(),
				'pending'   => $pending,
				'message'   => sprintf(
					/* translators: 1: importer label, 2: selected count, 3: pending count */
					__('Starting import of %1$s (%2$d of %3$d remaining items).', 'aidedemodataimport'),
					$importer->get_label(),
					$total,
					$pending
				),
			)
		);
	}

	/**
	 * Run one batch.
	 *
	 * @param Aide_Demo_Data_Import_Importer_Base $importer Importer.
	 */
	private static function run_batch($importer)
	{
		if (!$importer->is_available()) {
			wp_send_json_error(array('message' => __('Importer not available.', 'aidedemodataimport')));
		}

		$deps = $importer->validate_dependencies();
		if (is_wp_error($deps)) {
			wp_send_json_error(array('message' => $deps->get_error_message()));
		}

		// Nonce verified in route() before this method runs.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$offset = isset($_POST['offset']) ? absint(wp_unslash($_POST['offset'])) : 0;
		$max    = self::session_target_count();
		if ($max < 1) {
			$max = self::resolve_import_count($importer);
		}
		$pending = max(0, (int) $importer->count_pending());
		$limit   = isset($_POST['limit']) ? absint(wp_unslash($_POST['limit'])) : aidedemodataimport_batch_size();
		$limit   = max(1, min(50, $limit));

		if ($max < 1 || $offset >= $max || $pending < 1) {
			if (isset($_POST['session_imported']) || isset($_POST['session_skipped'])) {
				update_option(
					'aidedemodataimport_last_' . $importer->get_type(),
					array(
						'time'  => current_time('mysql'),
						'stats' => array(
							'imported' => isset($_POST['session_imported']) ? absint(wp_unslash($_POST['session_imported'])) : 0,
							'skipped'  => isset($_POST['session_skipped']) ? absint(wp_unslash($_POST['session_skipped'])) : 0,
						),
					),
					false
				);
			}
			// phpcs:enable WordPress.Security.NonceVerification.Missing

			wp_send_json_success(
				array(
					'type'      => $importer->get_type(),
					'offset'    => $offset,
					'limit'     => 0,
					'next'      => $offset,
					'imported'  => 0,
					'skipped'   => 0,
					'errors'    => array(),
					'done'      => true,
					'processed' => 0,
					'total'     => $max,
					'status'    => $importer->get_status(),
				)
			);
		}

		// Always pull the next pending items (offset 0). Session $offset tracks progress toward $max.
		$limit  = min($limit, $max - $offset, $pending);
		$result = $importer->import_batch(0, $limit);
		$next   = $offset + $limit;
		$done   = ($next >= $max) || ((int) $importer->count_pending() < 1) || !empty($result['done']);

		if ($done) {
			$importer_done_stats = array(
				'imported' => isset($result['imported']) ? (int) $result['imported'] : 0,
				'skipped'  => isset($result['skipped']) ? (int) $result['skipped'] : 0,
			);
			// Prefer cumulative session stats when provided by the client.
			// phpcs:disable WordPress.Security.NonceVerification.Missing
			if (isset($_POST['session_imported'])) {
				$importer_done_stats['imported'] = absint(wp_unslash($_POST['session_imported']));
			}
			if (isset($_POST['session_skipped'])) {
				$importer_done_stats['skipped'] = absint(wp_unslash($_POST['session_skipped']));
			}
			// phpcs:enable WordPress.Security.NonceVerification.Missing
			update_option(
				'aidedemodataimport_last_' . $importer->get_type(),
				array(
					'time'  => current_time('mysql'),
					'stats' => $importer_done_stats,
				),
				false
			);

			/**
			 * Fires after an import finishes.
			 *
			 * @param string $type    Importer type.
			 * @param array  $results Last batch results.
			 */
			do_action('aidedemodataimport_after_import', $importer->get_type(), $result);
		}

		// phpcs:enable WordPress.Security.NonceVerification.Missing

		wp_send_json_success(
			array(
				'type'      => $importer->get_type(),
				'offset'    => $offset,
				'limit'     => $limit,
				'next'      => $next,
				'imported'  => isset($result['imported']) ? (int) $result['imported'] : 0,
				'skipped'   => isset($result['skipped']) ? (int) $result['skipped'] : 0,
				'errors'    => isset($result['errors']) ? $result['errors'] : array(),
				'done'      => $done,
				'processed' => isset($result['processed']) ? (int) $result['processed'] : 0,
				'total'     => $max,
				'status'    => $importer->get_status(),
			)
		);
	}

	/**
	 * Run cleanup.
	 *
	 * @param Aide_Demo_Data_Import_Importer_Base $importer Importer.
	 */
	private static function run_cleanup($importer)
	{
		$result = $importer->cleanup();
		wp_send_json_success(
			array(
				'type'    => $importer->get_type(),
				'deleted' => isset($result['deleted']) ? (int) $result['deleted'] : 0,
				'errors'  => isset($result['errors']) ? $result['errors'] : array(),
				'message' => sprintf(
					/* translators: 1: count, 2: label */
					__('Removed %1$d items from %2$s.', 'aidedemodataimport'),
					isset($result['deleted']) ? (int) $result['deleted'] : 0,
					$importer->get_label()
				),
				'status'  => $importer->get_status(),
			)
		);
	}
}
