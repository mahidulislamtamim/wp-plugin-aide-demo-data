<?php
/**
 * WooCommerce product importer.
 *
 * @package AideDemoDataImport
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Imports demo products.
 */
class Aide_Demo_Data_Import_Product_Importer extends Aide_Demo_Data_Import_Importer_Base
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
		return 'products';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label()
	{
		return __('Products', 'aidedemodataimport');
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_description()
	{
		return __('Import sample WooCommerce products with categories and images.', 'aidedemodataimport');
	}

	/**
	 * {@inheritdoc}
	 */
	public function requires_woocommerce()
	{
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_dependencies()
	{
		return array('media');
	}

	/**
	 * Load products.
	 *
	 * @return array
	 */
	private function get_items()
	{
		if (null !== $this->items) {
			return $this->items;
		}

		$data = aidedemodataimport_load_json('woocommerce/products.json');
		if (is_wp_error($data)) {
			$this->items = array();
			return $this->items;
		}

		$this->items = isset($data['products']) && is_array($data['products']) ? $data['products'] : array();
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
	 * Ensure product category terms.
	 *
	 * @param string[] $names Names.
	 * @return int[]
	 */
	private function ensure_categories($names)
	{
		$ids = array();
		foreach ((array) $names as $name) {
			$name = sanitize_text_field($name);
			if ('' === $name) {
				continue;
			}
			$term = term_exists($name, 'product_cat');
			if (!$term) {
				$term = wp_insert_term($name, 'product_cat');
			}
			if (is_wp_error($term)) {
				continue;
			}
			$ids[] = (int) (is_array($term) ? $term['term_id'] : $term);
		}
		return $ids;
	}

	/**
	 * Resolve a relative image path for a product (direct path or via media.json).
	 *
	 * @param array $item Product item.
	 * @return string Relative path under demo-data/, or empty.
	 */
	private function resolve_image_path($item)
	{
		if (!empty($item['image'])) {
			return (string) $item['image'];
		}

		if (empty($item['image_id'])) {
			return '';
		}

		$data = aidedemodataimport_load_json('media.json');
		if (is_wp_error($data) || empty($data['media']) || !is_array($data['media'])) {
			return '';
		}

		foreach ($data['media'] as $media_item) {
			if (empty($media_item['id']) || empty($media_item['file'])) {
				continue;
			}
			if ((string) $media_item['id'] === (string) $item['image_id']) {
				return (string) $media_item['file'];
			}
		}

		return '';
	}

	/**
	 * Resolve product image — uses media map when available, otherwise sideloads
	 * so product-only import still uploads a featured image.
	 *
	 * @param array $item Product item.
	 * @return int
	 */
	private function resolve_image($item)
	{
		$media_map = aidedemodataimport_get_id_map('media');
		if (!empty($item['image_id']) && isset($media_map[ $item['image_id'] ])) {
			return (int) $media_map[ $item['image_id'] ];
		}

		$path = $this->resolve_image_path($item);
		if ('' === $path) {
			return 0;
		}

		$demo_id = !empty($item['image_id'])
			? sanitize_text_field($item['image_id'])
			: ('media-product-' . (isset($item['id']) ? $item['id'] : md5($path)));

		$existing = aidedemodataimport_find_post_by_demo_id('attachment', $demo_id);
		if ($existing) {
			$media_map[ $demo_id ] = $existing;
			aidedemodataimport_set_id_map('media', $media_map);
			return $existing;
		}

		$attachment_id = Aide_Demo_Data_Import_Media_Importer::sideload_file(
			$path,
			isset($item['name']) ? $item['name'] : ''
		);
		if (is_wp_error($attachment_id)) {
			aidedemodataimport_log($attachment_id->get_error_message(), 'error', 'products');
			return 0;
		}

		aidedemodataimport_tag_post($attachment_id, $demo_id);
		$media_map[ $demo_id ] = $attachment_id;
		aidedemodataimport_set_id_map('media', $media_map);

		return (int) $attachment_id;
	}

	/**
	 * {@inheritdoc}
	 */
	public function import_batch($offset, $limit)
	{
		if (!aidedemodataimport_is_woocommerce_active()) {
			return array(
				'imported'  => 0,
				'skipped'   => 0,
				'errors'    => array(__('WooCommerce is not active.', 'aidedemodataimport')),
				'done'      => true,
				'processed' => 0,
			);
		}

		$items  = $this->get_items();
		$total  = count($items);
		$slice  = array_slice($items, $offset, $limit);
		$result = $this->empty_result(0, false);
		$id_map = aidedemodataimport_get_id_map('products');

		if (empty($slice)) {
			$result['done'] = true;
			return $result;
		}

		foreach ($slice as $item) {
			++$result['processed'];
			$demo_id = isset($item['id']) ? sanitize_text_field($item['id']) : '';
			if ('' === $demo_id) {
				$result['errors'][] = __('Product missing demo id.', 'aidedemodataimport');
				continue;
			}

			$existing = aidedemodataimport_find_post_by_demo_id('product', $demo_id);
			if ($existing) {
				$id_map[ $demo_id ] = $existing;
				++$result['skipped'];
				continue;
			}

			$product = new WC_Product_Simple();
			$product->set_name(isset($item['name']) ? sanitize_text_field($item['name']) : $demo_id);
			$product->set_status('publish');
			$product->set_catalog_visibility('visible');
			$product->set_description(isset($item['description']) ? wp_kses_post($item['description']) : '');
			$product->set_short_description(isset($item['short_description']) ? wp_kses_post($item['short_description']) : '');
			$product->set_regular_price(isset($item['regular_price']) ? (string) $item['regular_price'] : '9.99');
			if (!empty($item['sale_price'])) {
				$product->set_sale_price((string) $item['sale_price']);
			}
			if (!empty($item['sku'])) {
				$product->set_sku(sanitize_text_field($item['sku']));
			}
			$product->set_manage_stock(!empty($item['manage_stock']));
			if (isset($item['stock_quantity'])) {
				$product->set_stock_quantity((int) $item['stock_quantity']);
				$product->set_stock_status('instock');
			}

			$image_id = $this->resolve_image($item);
			if ($image_id) {
				$product->set_image_id($image_id);
			}

			$product_id = $product->save();
			if (!$product_id) {
				$result['errors'][] = __('Failed to save product.', 'aidedemodataimport');
				continue;
			}

			aidedemodataimport_tag_post($product_id, $demo_id);

			if (!empty($item['categories'])) {
				$cat_ids = $this->ensure_categories($item['categories']);
				if ($cat_ids) {
					wp_set_object_terms($product_id, $cat_ids, 'product_cat');
				}
			}

			$id_map[ $demo_id ] = $product_id;
			++$result['imported'];
			aidedemodataimport_log(
				sprintf(
					/* translators: %s: product name */
					__('Imported product: %s', 'aidedemodataimport'),
					$product->get_name()
				),
				'success',
				'products'
			);
		}

		aidedemodataimport_set_id_map('products', $id_map);
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
	 * Count imported demo products.
	 *
	 * @return int
	 */
	public function count_imported()
	{
		if (!aidedemodataimport_is_woocommerce_active()) {
			return 0;
		}

		$query = new WP_Query(
			array(
				'post_type'              => 'product',
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
				'post_type'      => 'product',
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
					/* translators: %d: product ID */
					__('Failed to delete product %d.', 'aidedemodataimport'),
					(int) $id
				);
			}
		}

		aidedemodataimport_clear_id_map('products');
		aidedemodataimport_log(
			sprintf(
				/* translators: %d: count */
				__('Removed %d demo products.', 'aidedemodataimport'),
				$deleted
			),
			'success',
			'products'
		);

		return array(
			'deleted' => $deleted,
			'errors'  => $errors,
		);
	}
}
