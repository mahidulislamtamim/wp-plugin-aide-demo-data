<?php
/**
 * Post importer.
 *
 * @package AideDemoDataImport
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Imports demo blog/news posts.
 */
class Aide_Demo_Data_Import_Post_Importer extends Aide_Demo_Data_Import_Importer_Base
{

	/**
	 * Cached posts.
	 *
	 * @var array|null
	 */
	private $items = null;

	/**
	 * {@inheritdoc}
	 */
	public function get_type()
	{
		return 'posts';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label()
	{
		return __('Blog / News Posts', 'aidedemodataimport');
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_description()
	{
		return __('Import sample posts with categories, tags, and featured images.', 'aidedemodataimport');
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_dependencies()
	{
		return array('media');
	}

	/**
	 * Load posts from JSON.
	 *
	 * @return array
	 */
	private function get_items()
	{
		if (null !== $this->items) {
			return $this->items;
		}

		$data = aidedemodataimport_load_json('posts.json');
		if (is_wp_error($data)) {
			$this->items = array();
			return $this->items;
		}

		$this->items = isset($data['posts']) && is_array($data['posts']) ? $data['posts'] : array();
		return $this->items;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_total()
	{
		return count($this->get_items());
	}

	/**
	 * Ensure terms exist and return IDs.
	 *
	 * @param string[] $names Term names.
	 * @param string   $taxonomy Taxonomy.
	 * @return int[]
	 */
	private function ensure_terms($names, $taxonomy)
	{
		$ids = array();
		foreach ((array) $names as $name) {
			$name = sanitize_text_field($name);
			if ('' === $name) {
				continue;
			}
			$term = term_exists($name, $taxonomy);
			if (!$term) {
				$term = wp_insert_term($name, $taxonomy);
			}
			if (is_wp_error($term)) {
				continue;
			}
			$ids[] = (int) (is_array($term) ? $term['term_id'] : $term);
		}
		return $ids;
	}

	/**
	 * Resolve featured image from media map or file path.
	 *
	 * @param array $item Post item.
	 * @return int Attachment ID or 0.
	 */
	private function resolve_featured_image($item)
	{
		$media_map = aidedemodataimport_get_id_map('media');

		if (!empty($item['featured_media_id']) && isset($media_map[ $item['featured_media_id'] ])) {
			return (int) $media_map[ $item['featured_media_id'] ];
		}

		if (!empty($item['featured_image'])) {
			$demo_id = 'media-from-' . (isset($item['id']) ? $item['id'] : md5($item['featured_image']));
			$existing = aidedemodataimport_find_post_by_demo_id('attachment', $demo_id);
			if ($existing) {
				return $existing;
			}
			$attachment_id = Aide_Demo_Data_Import_Media_Importer::sideload_file(
				$item['featured_image'],
				isset($item['post_title']) ? $item['post_title'] : ''
			);
			if (!is_wp_error($attachment_id)) {
				aidedemodataimport_tag_post($attachment_id, $demo_id);
				$media_map[ $demo_id ] = $attachment_id;
				aidedemodataimport_set_id_map('media', $media_map);
				return (int) $attachment_id;
			}
		}

		return 0;
	}

	/**
	 * {@inheritdoc}
	 */
	public function import_batch($offset, $limit)
	{
		$items  = $this->get_items();
		$total  = count($items);
		$slice  = array_slice($items, $offset, $limit);
		$result = $this->empty_result(0, false);
		$id_map = aidedemodataimport_get_id_map('posts');
		$author = get_current_user_id();

		if (empty($slice)) {
			$result['done'] = true;
			return $result;
		}

		foreach ($slice as $item) {
			++$result['processed'];
			$demo_id = isset($item['id']) ? sanitize_text_field($item['id']) : '';
			if ('' === $demo_id) {
				$result['errors'][] = __('Post missing demo id.', 'aidedemodataimport');
				continue;
			}

			$existing = aidedemodataimport_find_post_by_demo_id('post', $demo_id);
			if ($existing) {
				$id_map[ $demo_id ] = $existing;
				++$result['skipped'];
				continue;
			}

			$postarr = array(
				'post_title'   => isset($item['post_title']) ? sanitize_text_field($item['post_title']) : $demo_id,
				'post_content' => isset($item['post_content']) ? wp_kses_post($item['post_content']) : '',
				'post_excerpt' => isset($item['post_excerpt']) ? sanitize_textarea_field($item['post_excerpt']) : '',
				'post_status'  => isset($item['post_status']) ? sanitize_key($item['post_status']) : 'publish',
				'post_type'    => 'post',
				'post_author'  => $author,
			);

			if (!empty($item['post_date'])) {
				$postarr['post_date']     = sanitize_text_field($item['post_date']);
				$postarr['post_date_gmt'] = get_gmt_from_date($postarr['post_date']);
			}

			$post_id = wp_insert_post($postarr, true);
			if (is_wp_error($post_id)) {
				$result['errors'][] = $post_id->get_error_message();
				aidedemodataimport_log($post_id->get_error_message(), 'error', 'posts');
				continue;
			}

			aidedemodataimport_tag_post($post_id, $demo_id);

			if (!empty($item['categories'])) {
				$cat_ids = $this->ensure_terms($item['categories'], 'category');
				if ($cat_ids) {
					wp_set_post_terms($post_id, $cat_ids, 'category');
				}
			}

			if (!empty($item['tags'])) {
				$tag_ids = $this->ensure_terms($item['tags'], 'post_tag');
				if ($tag_ids) {
					wp_set_post_terms($post_id, $tag_ids, 'post_tag');
				}
			}

			$thumb = $this->resolve_featured_image($item);
			if ($thumb) {
				set_post_thumbnail($post_id, $thumb);
			}

			if (!empty($item['meta']) && is_array($item['meta'])) {
				foreach ($item['meta'] as $key => $value) {
					update_post_meta($post_id, sanitize_key($key), sanitize_text_field((string) $value));
				}
			}

			$id_map[ $demo_id ] = $post_id;
			++$result['imported'];
			aidedemodataimport_log(
				sprintf(
					/* translators: %s: post title */
					__('Imported post: %s', 'aidedemodataimport'),
					$postarr['post_title']
				),
				'success',
				'posts'
			);
		}

		aidedemodataimport_set_id_map('posts', $id_map);

		$next           = $offset + $limit;
		$result['done'] = $next >= $total;

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
	 * Count imported demo posts.
	 *
	 * @return int
	 */
	public function count_imported()
	{
		$query = new WP_Query(
			array(
				'post_type'              => 'post',
				'post_status'            => 'any',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Lookup by demo import meta.
				'meta_key'               => '_demo_import_key',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Lookup by demo import meta.
				'meta_value'             => AIDEDEMODATAIMPORT_DEMO_KEY,
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
				'post_type'      => 'post',
				'post_status'    => 'any',
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
			$result = wp_delete_post((int) $id, true);
			if ($result) {
				++$deleted;
			} else {
				$errors[] = sprintf(
					/* translators: %d: post ID */
					__('Failed to delete post %d.', 'aidedemodataimport'),
					(int) $id
				);
			}
		}

		aidedemodataimport_clear_id_map('posts');
		aidedemodataimport_log(
			sprintf(
				/* translators: %d: count */
				__('Removed %d demo posts.', 'aidedemodataimport'),
				$deleted
			),
			'success',
			'posts'
		);

		return array(
			'deleted' => $deleted,
			'errors'  => $errors,
		);
	}
}
