<?php
/**
 * Media importer.
 *
 * @package AideDemoDataImport
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Imports bundled images listed in media.json.
 */
class Aide_Demo_Data_Import_Media_Importer extends Aide_Demo_Data_Import_Importer_Base
{

	/**
	 * Cached items.
	 *
	 * @var array|null
	 */
	private $items = null;

	/**
	 * {@inheritdoc}
	 */
	public function get_type()
	{
		return 'media';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label()
	{
		return __('Media', 'aidedemodataimport');
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_description()
	{
		return __('Import sample images from the Aide CDN (used by demo posts).', 'aidedemodataimport');
	}

	/**
	 * Load media items from JSON.
	 *
	 * @return array
	 */
	private function get_items()
	{
		if (null !== $this->items) {
			return $this->items;
		}

		$data = aidedemodataimport_load_json('media.json');
		if (is_wp_error($data)) {
			$this->items = array();
			return $this->items;
		}

		$this->items = isset($data['media']) && is_array($data['media']) ? $data['media'] : array();
		return $this->items;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_package_total()
	{
		return count($this->get_items());
	}

	/**
	 * Sideload a demo image (remote CDN first, local demo-data fallback).
	 *
	 * @param string $relative Relative path under demo images / demo-data, or absolute URL.
	 * @param string $title    Attachment title.
	 * @return int|WP_Error Attachment ID.
	 */
	public static function sideload_file($relative, $title = '')
	{
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp      = '';
		$filename = '';

		$url = aidedemodataimport_demo_image_url($relative);
		if (!is_wp_error($url)) {
			$downloaded = download_url($url, 30);
			if (!is_wp_error($downloaded)) {
				$tmp      = $downloaded;
				$filename = basename(wp_parse_url($url, PHP_URL_PATH));
			}
		}

		// Local fallback when CDN is unreachable or path is local-only.
		if ('' === $tmp) {
			$absolute = aidedemodataimport_resolve_safe_demo_path($relative);
			if (is_wp_error($absolute)) {
				if (is_wp_error($url)) {
					return $url;
				}
				return new WP_Error(
					'download_failed',
					sprintf(
						/* translators: %s: relative image path */
						__('Could not download demo image: %s', 'aidedemodataimport'),
						$relative
					)
				);
			}

			$tmp = wp_tempnam(basename($absolute));
			if (!$tmp) {
				return new WP_Error('tmp_failed', __('Could not create temporary file.', 'aidedemodataimport'));
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy -- Local copy for sideload.
			if (!copy($absolute, $tmp)) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				@unlink($tmp);
				return new WP_Error('copy_failed', __('Could not copy media file.', 'aidedemodataimport'));
			}
			$filename = basename($absolute);
		}

		if ('' === $filename) {
			$filename = 'demo-image.jpg';
		}

		$file_array = array(
			'name'     => sanitize_file_name($filename),
			'tmp_name' => $tmp,
		);

		$attachment_id = media_handle_sideload($file_array, 0, $title);
		if (is_wp_error($attachment_id)) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			@unlink($tmp);
			return $attachment_id;
		}

		return (int) $attachment_id;
	}

	/**
	 * Pending (not yet imported) media items.
	 *
	 * @param int $limit Max items to collect.
	 * @return array
	 */
	private function get_pending_items($limit = 10)
	{
		return $this->collect_next_pending_items(
			$limit,
			static function ($item) {
				$demo_id = isset($item['id']) ? sanitize_text_field($item['id']) : '';
				return ('' === $demo_id) || (bool) aidedemodataimport_find_post_by_demo_id('attachment', $demo_id);
			},
			$this->get_items()
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function import_batch($offset, $limit)
	{
		unset($offset);
		$slice  = $this->get_pending_items($limit);
		$result = $this->empty_result(0, false);
		$id_map = aidedemodataimport_get_id_map('media');

		if (empty($slice)) {
			$result['done'] = true;
			$this->record_last_run(
				array(
					'imported' => 0,
					'skipped'  => 0,
				)
			);
			return $result;
		}

		foreach ($slice as $item) {
			++$result['processed'];
			$demo_id = isset($item['id']) ? sanitize_text_field($item['id']) : '';
			$file    = isset($item['file']) ? $item['file'] : '';
			$title   = isset($item['title']) ? sanitize_text_field($item['title']) : $demo_id;

			if ('' === $demo_id || '' === $file) {
				$result['errors'][] = __('Invalid media item skipped.', 'aidedemodataimport');
				continue;
			}

			$existing = aidedemodataimport_find_post_by_demo_id('attachment', $demo_id);
			if ($existing) {
				$id_map[ $demo_id ] = $existing;
				++$result['skipped'];
				continue;
			}

			$attachment_id = self::sideload_file($file, $title);
			if (is_wp_error($attachment_id)) {
				$result['errors'][] = $attachment_id->get_error_message();
				aidedemodataimport_log($attachment_id->get_error_message(), 'error', 'media');
				continue;
			}

			aidedemodataimport_tag_post($attachment_id, $demo_id);
			$id_map[ $demo_id ] = $attachment_id;
			++$result['imported'];
			aidedemodataimport_log(
				sprintf(
					/* translators: %s: media title */
					__('Imported media: %s', 'aidedemodataimport'),
					$title
				),
				'success',
				'media'
			);
		}

		aidedemodataimport_set_id_map('media', $id_map);
		$result['done'] = count($slice) < $limit || $this->count_pending() < 1;

		if ($result['done']) {
			$this->record_last_run(
				array(
					'imported' => $result['imported'],
					'skipped'  => $result['skipped'],
				)
			);
		}

		return $result;
	}

	/**
	 * Count demo attachments created by the media importer.
	 *
	 * @return int
	 */
	public function count_imported()
	{
		$query = new WP_Query(
			array(
				'post_type'              => 'attachment',
				'post_status'            => 'inherit',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => false,
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
						'key'     => '_demo_import_id',
						'value'   => 'demo-media-',
						'compare' => 'LIKE',
					),
				),
			)
		);

		return (int) $query->found_posts;
	}

	/**
	 * {@inheritdoc}
	 */
	public function cleanup()
	{
		$query = new WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Lookup by demo import meta.
				'meta_key'       => '_demo_import_key',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Lookup by demo import meta.
				'meta_value'     => AIDEDEMODATAIMPORT_DEMO_KEY,
			)
		);

		$deleted = 0;
		$errors  = array();

		foreach ($query->posts as $id) {
			$result = wp_delete_attachment((int) $id, true);
			if ($result) {
				++$deleted;
			} else {
				$errors[] = sprintf(
					/* translators: %d: attachment ID */
					__('Failed to delete attachment %d.', 'aidedemodataimport'),
					(int) $id
				);
			}
		}

		aidedemodataimport_clear_id_map('media');
		aidedemodataimport_log(
			sprintf(
				/* translators: %d: count */
				__('Removed %d demo media items.', 'aidedemodataimport'),
				$deleted
			),
			'success',
			'media'
		);

		return array(
			'deleted' => $deleted,
			'errors'  => $errors,
		);
	}
}
