<?php
/**
 * Helper functions for Aide Demo Data Import.
 *
 * @package AideDemoDataImport
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Absolute path to a file under demo-data/.
 *
 * @param string $relative Relative path inside demo-data/.
 * @return string
 */
function aidedemodataimport_get_demo_data_path($relative = '')
{
	$base = apply_filters(
		'aidedemodataimport_demo_data_path',
		AIDEDEMODATAIMPORT_PATH . 'demo-data/'
	);

	if ('' === $relative) {
		return $base;
	}

	return $base . ltrim($relative, '/\\');
}

/**
 * Required capability for admin actions.
 *
 * @return string
 */
function aidedemodataimport_required_capability()
{
	return apply_filters('aidedemodataimport_required_capability', 'manage_options');
}

/**
 * Whether WooCommerce is active.
 *
 * @return bool
 */
function aidedemodataimport_is_woocommerce_active()
{
	return class_exists('WooCommerce');
}

/**
 * Get import log entries.
 *
 * @return array
 */
function aidedemodataimport_get_import_log()
{
	$log = get_option('aidedemodataimport_import_log', array());
	return is_array($log) ? $log : array();
}

/**
 * Append an entry to the import log (capped at 100).
 *
 * @param string $message Message.
 * @param string $type    info|success|error|warning.
 * @param string $importer Importer type slug.
 */
function aidedemodataimport_log($message, $type = 'info', $importer = '')
{
	$log   = aidedemodataimport_get_import_log();
	$log[] = array(
		'time'     => current_time('mysql'),
		'type'     => sanitize_key($type),
		'importer' => sanitize_key($importer),
		'message'  => sanitize_text_field($message),
	);

	if (count($log) > 100) {
		$log = array_slice($log, -100);
	}

	update_option('aidedemodataimport_import_log', $log, false);
}

/**
 * Clear import log.
 */
function aidedemodataimport_clear_import_log()
{
	delete_option('aidedemodataimport_import_log');
}

/**
 * Get ID map for an importer type (demo_id => WP ID).
 *
 * @param string $type Importer type.
 * @return array
 */
function aidedemodataimport_get_id_map($type)
{
	$map = get_option('aidedemodataimport_id_map_' . sanitize_key($type), array());
	return is_array($map) ? $map : array();
}

/**
 * Save ID map for an importer type.
 *
 * @param string $type Importer type.
 * @param array  $map  Demo ID => WP ID.
 */
function aidedemodataimport_set_id_map($type, $map)
{
	update_option('aidedemodataimport_id_map_' . sanitize_key($type), $map, false);
}

/**
 * Clear ID map for an importer type.
 *
 * @param string $type Importer type.
 */
function aidedemodataimport_clear_id_map($type)
{
	delete_option('aidedemodataimport_id_map_' . sanitize_key($type));
}

/**
 * Default batch size for imports.
 *
 * @return int
 */
function aidedemodataimport_batch_size()
{
	$size = (int) apply_filters('aidedemodataimport_import_batch_size', 10);
	return max(1, min(50, $size));
}

/**
 * Read and decode a JSON demo data file.
 *
 * @param string $relative Relative path under demo-data/.
 * @return array|WP_Error
 */
function aidedemodataimport_load_json($relative)
{
	$path = aidedemodataimport_get_demo_data_path($relative);

	if (!file_exists($path) || !is_readable($path)) {
		return new WP_Error(
			'missing_demo_file',
			sprintf(
				/* translators: %s: file path */
				__('Demo data file not found: %s', 'aidedemodataimport'),
				$relative
			)
		);
	}

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local plugin file.
	$raw = file_get_contents($path);
	if (false === $raw) {
		return new WP_Error('read_failed', __('Could not read demo data file.', 'aidedemodataimport'));
	}

	$data = json_decode($raw, true);
	if (JSON_ERROR_NONE !== json_last_error() || !is_array($data)) {
		return new WP_Error('invalid_json', __('Demo data JSON is invalid.', 'aidedemodataimport'));
	}

	return $data;
}

/**
 * Find an existing entity by demo import meta.
 *
 * @param string $post_type Post type (or 'attachment').
 * @param string $demo_id   Stable demo ID.
 * @return int Post ID or 0.
 */
function aidedemodataimport_find_post_by_demo_id($post_type, $demo_id)
{
	$query = new WP_Query(
		array(
			'post_type'              => $post_type,
			'post_status'            => 'any',
			'posts_per_page'         => 1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Lookup by demo import meta.
			'meta_query'             => array(
				'relation' => 'AND',
				array(
					'key'   => '_demo_import_key',
					'value' => AIDEDEMODATAIMPORT_DEMO_KEY,
				),
				array(
					'key'   => '_demo_import_id',
					'value' => $demo_id,
				),
			),
		)
	);

	if (!empty($query->posts[0])) {
		return (int) $query->posts[0];
	}

	return 0;
}

/**
 * Find a user by demo import meta.
 *
 * @param string $demo_id Stable demo ID.
 * @return int User ID or 0.
 */
function aidedemodataimport_find_user_by_demo_id($demo_id)
{
	$users = get_users(
		array(
			'number'     => 1,
			'fields'     => 'ID',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Lookup by demo import meta.
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key'   => '_demo_import_key',
					'value' => AIDEDEMODATAIMPORT_DEMO_KEY,
				),
				array(
					'key'   => '_demo_import_id',
					'value' => $demo_id,
				),
			),
		)
	);

	return !empty($users[0]) ? (int) $users[0] : 0;
}

/**
 * Tag a post with demo import meta.
 *
 * @param int    $post_id Post ID.
 * @param string $demo_id Stable demo ID.
 */
function aidedemodataimport_tag_post($post_id, $demo_id)
{
	update_post_meta($post_id, '_demo_import_key', AIDEDEMODATAIMPORT_DEMO_KEY);
	update_post_meta($post_id, '_demo_import_id', sanitize_text_field($demo_id));
}

/**
 * Tag a user with demo import meta.
 *
 * @param int    $user_id User ID.
 * @param string $demo_id Stable demo ID.
 */
function aidedemodataimport_tag_user($user_id, $demo_id)
{
	update_user_meta($user_id, '_demo_import_key', AIDEDEMODATAIMPORT_DEMO_KEY);
	update_user_meta($user_id, '_demo_import_id', sanitize_text_field($demo_id));
}
