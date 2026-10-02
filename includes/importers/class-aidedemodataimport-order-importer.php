<?php
/**
 * WooCommerce order importer.
 *
 * @package AideDemoDataImport
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Imports demo orders.
 */
class Aide_Demo_Data_Import_Order_Importer extends Aide_Demo_Data_Import_Importer_Base
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
		return 'orders';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label()
	{
		return __('Orders', 'aidedemodataimport');
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_description()
	{
		return __('Import sample WooCommerce orders linked to demo products and customers.', 'aidedemodataimport');
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
		return array('products', 'customers');
	}

	/**
	 * Load orders.
	 *
	 * @return array
	 */
	private function get_items()
	{
		if (null !== $this->items) {
			return $this->items;
		}

		$data = aidedemodataimport_load_json('woocommerce/orders.json');
		if (is_wp_error($data)) {
			$this->items = array();
			return $this->items;
		}

		$this->items = isset($data['orders']) && is_array($data['orders']) ? $data['orders'] : array();
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
	 * Find existing order by demo meta.
	 *
	 * @param string $demo_id Demo ID.
	 * @return int
	 */
	private function find_order($demo_id)
	{
		$orders = wc_get_orders(
			array(
				'limit'      => 1,
				'return'     => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Lookup by demo import meta.
				'meta_key'   => '_demo_import_id',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Lookup by demo import meta.
				'meta_value' => $demo_id,
			)
		);

		if (!empty($orders[0])) {
			$order_id = (int) $orders[0];
			$key      = get_post_meta($order_id, '_demo_import_key', true);
			if (AIDEDEMODATAIMPORT_DEMO_KEY === $key) {
				return $order_id;
			}
			// HPOS may store meta differently — also check order meta API.
			$order = wc_get_order($order_id);
			if ($order && AIDEDEMODATAIMPORT_DEMO_KEY === $order->get_meta('_demo_import_key')) {
				return $order_id;
			}
		}

		return 0;
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

		$items        = $this->get_items();
		$total        = count($items);
		$slice        = array_slice($items, $offset, $limit);
		$result       = $this->empty_result(0, false);
		$id_map       = aidedemodataimport_get_id_map('orders');
		$product_map  = aidedemodataimport_get_id_map('products');
		$customer_map = aidedemodataimport_get_id_map('customers');

		if (empty($slice)) {
			$result['done'] = true;
			return $result;
		}

		foreach ($slice as $item) {
			++$result['processed'];
			$demo_id = isset($item['id']) ? sanitize_text_field($item['id']) : '';
			if ('' === $demo_id) {
				$result['errors'][] = __('Order missing demo id.', 'aidedemodataimport');
				continue;
			}

			$existing = $this->find_order($demo_id);
			if ($existing) {
				$id_map[ $demo_id ] = $existing;
				++$result['skipped'];
				continue;
			}

			$customer_demo = isset($item['customer_id']) ? $item['customer_id'] : '';
			$customer_id   = isset($customer_map[ $customer_demo ]) ? (int) $customer_map[ $customer_demo ] : 0;

			$order = wc_create_order(
				array(
					'customer_id' => $customer_id,
					'status'      => isset($item['status']) ? sanitize_key($item['status']) : 'completed',
				)
			);

			if (is_wp_error($order)) {
				$result['errors'][] = $order->get_error_message();
				continue;
			}

			$line_items = isset($item['line_items']) && is_array($item['line_items']) ? $item['line_items'] : array();
			foreach ($line_items as $line) {
				$product_demo = isset($line['product_id']) ? $line['product_id'] : '';
				$qty          = isset($line['quantity']) ? max(1, (int) $line['quantity']) : 1;
				if (!isset($product_map[ $product_demo ])) {
					$result['errors'][] = sprintf(
						/* translators: %s: product demo id */
						__('Product %s not found for order; import products first.', 'aidedemodataimport'),
						$product_demo
					);
					continue;
				}
				$product = wc_get_product((int) $product_map[ $product_demo ]);
				if ($product) {
					$order->add_product($product, $qty);
				}
			}

			if (!empty($item['billing']) && is_array($item['billing'])) {
				$order->set_address(
					array_map('sanitize_text_field', $item['billing']),
					'billing'
				);
			}
			if (!empty($item['shipping']) && is_array($item['shipping'])) {
				$order->set_address(
					array_map('sanitize_text_field', $item['shipping']),
					'shipping'
				);
			}

			$order->calculate_totals();
			$order->update_meta_data('_demo_import_key', AIDEDEMODATAIMPORT_DEMO_KEY);
			$order->update_meta_data('_demo_import_id', $demo_id);
			$order->save();

			$id_map[ $demo_id ] = $order->get_id();
			++$result['imported'];
			aidedemodataimport_log(
				sprintf(
					/* translators: %d: order ID */
					__('Imported order #%d', 'aidedemodataimport'),
					$order->get_id()
				),
				'success',
				'orders'
			);
		}

		aidedemodataimport_set_id_map('orders', $id_map);
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
	 * Count imported demo orders.
	 *
	 * @return int
	 */
	public function count_imported()
	{
		if (!aidedemodataimport_is_woocommerce_active() || !function_exists('wc_get_orders')) {
			return 0;
		}

		$orders = wc_get_orders(
			array(
				'limit'      => 1,
				'return'     => 'ids',
				'paginate'   => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Lookup by demo import meta.
				'meta_key'   => '_demo_import_key',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Lookup by demo import meta.
				'meta_value' => AIDEDEMODATAIMPORT_DEMO_KEY,
			)
		);

		if (is_object($orders) && isset($orders->total)) {
			return (int) $orders->total;
		}

		// Fallback without pagination support.
		$all = wc_get_orders(
			array(
				'limit'      => -1,
				'return'     => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Lookup by demo import meta.
				'meta_key'   => '_demo_import_key',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Lookup by demo import meta.
				'meta_value' => AIDEDEMODATAIMPORT_DEMO_KEY,
			)
		);

		return is_array($all) ? count($all) : 0;
	}

	/**
	 * {@inheritdoc}
	 */
	public function cleanup()
	{
		if (!aidedemodataimport_is_woocommerce_active()) {
			return array(
				'deleted' => 0,
				'errors'  => array(),
			);
		}

		$orders = wc_get_orders(
			array(
				'limit'      => -1,
				'return'     => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Lookup by demo import meta.
				'meta_key'   => '_demo_import_key',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Lookup by demo import meta.
				'meta_value' => AIDEDEMODATAIMPORT_DEMO_KEY,
			)
		);

		$deleted = 0;
		$errors  = array();

		foreach ($orders as $order_id) {
			$order = wc_get_order($order_id);
			if (!$order) {
				continue;
			}
			$result = $order->delete(true);
			if ($result) {
				++$deleted;
			} else {
				$errors[] = sprintf(
					/* translators: %d: order ID */
					__('Failed to delete order %d.', 'aidedemodataimport'),
					(int) $order_id
				);
			}
		}

		aidedemodataimport_clear_id_map('orders');
		aidedemodataimport_log(
			sprintf(
				/* translators: %d: count */
				__('Removed %d demo orders.', 'aidedemodataimport'),
				$deleted
			),
			'success',
			'orders'
		);

		return array(
			'deleted' => $deleted,
			'errors'  => $errors,
		);
	}
}
