<?php
/**
 * Uninstall Aide :: Demo Data Import.
 *
 * Fired when the plugin is deleted from WordPress admin.
 *
 * @package AideDemoDataImport
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

/**
 * Delete demo-tagged posts and attachments.
 */
function aidedemodataimport_uninstall_delete_demo_posts() {
	$post_types = array('post', 'product', 'attachment', 'shop_order');

	foreach ($post_types as $post_type) {
		$query = new WP_Query(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Demo cleanup by known meta key.
				'meta_key'       => '_demo_import_key',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Demo cleanup by known meta value.
				'meta_value'     => 'aidedemodataimport',
			)
		);

		foreach ($query->posts as $post_id) {
			if ('attachment' === $post_type) {
				wp_delete_attachment((int) $post_id, true);
			} else {
				wp_delete_post((int) $post_id, true);
			}
		}
	}
}

/**
 * Delete demo-tagged users.
 */
function aidedemodataimport_uninstall_delete_demo_users() {
	require_once ABSPATH . 'wp-admin/includes/user.php';

	$users = get_users(
		array(
			'number'     => -1,
			'fields'     => array('ID'),
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Demo cleanup by known meta key.
			'meta_key'   => '_demo_import_key',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Demo cleanup by known meta value.
			'meta_value' => 'aidedemodataimport',
		)
	);

	foreach ($users as $user) {
		wp_delete_user((int) $user->ID);
	}
}

/**
 * Delete WooCommerce orders via WC API when available.
 */
function aidedemodataimport_uninstall_delete_demo_orders() {
	if (!function_exists('wc_get_orders')) {
		return;
	}

	$orders = wc_get_orders(
		array(
			'limit'      => -1,
			'return'     => 'ids',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Demo cleanup by known meta key.
			'meta_key'   => '_demo_import_key',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Demo cleanup by known meta value.
			'meta_value' => 'aidedemodataimport',
		)
	);

	foreach ($orders as $order_id) {
		$order = wc_get_order($order_id);
		if ($order) {
			$order->delete(true);
		}
	}
}

/**
 * Delete plugin options and transients.
 */
function aidedemodataimport_uninstall_delete_options() {
	global $wpdb;

	$like = $wpdb->esc_like('aidedemodataimport_') . '%';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like));

	$like_t = $wpdb->esc_like('_transient_aidedemodataimport_') . '%';
	$like_o = $wpdb->esc_like('_transient_timeout_aidedemodataimport_') . '%';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like_t));
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like_o));
}

$aidedemodataimport_settings    = get_option('aidedemodataimport_settings', array());
$aidedemodataimport_delete_demo = !isset($aidedemodataimport_settings['delete_demo_on_uninstall']) || !empty($aidedemodataimport_settings['delete_demo_on_uninstall']);

try {
	if ($aidedemodataimport_delete_demo) {
		aidedemodataimport_uninstall_delete_demo_orders();
		aidedemodataimport_uninstall_delete_demo_posts();
		aidedemodataimport_uninstall_delete_demo_users();
	}
	aidedemodataimport_uninstall_delete_options();
	wp_cache_flush();
} catch (Exception $e) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Best-effort uninstall.
}
