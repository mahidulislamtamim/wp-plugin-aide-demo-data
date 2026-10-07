<?php
/**
 * Generate demo records beyond the static JSON package.
 *
 * @package AideDemoDataImport
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Builds deterministic demo items for large import counts.
 */
class Aide_Demo_Data_Import_Dynamic_Generator
{

	/**
	 * How many CDN image variants cycle (product-01 … product-20, avatar-01 …).
	 *
	 * @var int
	 */
	const IMAGE_CYCLE = 20;

	/**
	 * Generate one item for an importer type.
	 *
	 * @param string $type          Importer type slug.
	 * @param int    $number        1-based record number.
	 * @param array  $static_items  Static package items (used as templates).
	 * @param array  $context       Extra context (e.g. product/customer demo IDs for orders).
	 * @return array
	 */
	public static function make($type, $number, $static_items = array(), $context = array())
	{
		$number = max(1, (int) $number);
		$pad    = sprintf('%03d', $number);
		$img    = sprintf('%02d', (($number - 1) % self::IMAGE_CYCLE) + 1);
		$tpl    = self::template($static_items, $number);

		switch ($type) {
			case 'media':
				return self::make_media($number, $pad, $img, $tpl);
			case 'posts':
				return self::make_post($number, $pad, $img, $tpl);
			case 'products':
				return self::make_product($number, $pad, $img, $tpl);
			case 'customers':
				return self::make_customer($number, $pad, $img, $tpl);
			case 'orders':
				return self::make_order($number, $pad, $tpl, $context);
			default:
				return array('id' => 'demo-' . $type . '-' . $pad);
		}
	}

	/**
	 * Pick a template row from the static package (cycles).
	 *
	 * @param array $static_items Static items.
	 * @param int   $number       1-based number.
	 * @return array
	 */
	private static function template($static_items, $number)
	{
		if (empty($static_items) || !is_array($static_items)) {
			return array();
		}
		$keys = array_values($static_items);
		$idx  = ($number - 1) % count($keys);
		return is_array($keys[ $idx ]) ? $keys[ $idx ] : array();
	}

	/**
	 * @param int    $number Number.
	 * @param string $pad    Padded id.
	 * @param string $img    Image cycle pad.
	 * @param array  $tpl    Template.
	 * @return array
	 */
	private static function make_media($number, $pad, $img, $tpl)
	{
		$title = isset($tpl['title']) ? (string) $tpl['title'] : 'Demo Media';
		$title = preg_replace('/\(\d+\)\s*$/', '', $title);
		$title = trim($title) . ' (' . $pad . ')';

		return array(
			'id'    => 'demo-media-' . $pad,
			'file'  => 'media/product-' . $img . '.jpg',
			'title' => $title,
		);
	}

	/**
	 * @param int    $number Number.
	 * @param string $pad    Padded id.
	 * @param string $img    Image cycle pad.
	 * @param array  $tpl    Template.
	 * @return array
	 */
	private static function make_post($number, $pad, $img, $tpl)
	{
		$media_pad = $pad;
		if ($number > 999) {
			$media_pad = sprintf('%03d', (($number - 1) % 1000) + 1);
		}

		return array(
			'id'                => 'demo-post-' . $pad,
			'post_title'        => sprintf(
				/* translators: %s: padded number */
				__('Demo Post %s', 'aidedemodataimport'),
				$pad
			),
			'post_content'      => sprintf(
				'<p>%s</p>',
				sprintf(
					/* translators: %s: padded number */
					__('This is sample blog post number %s imported by Aide :: Demo Data Import.', 'aidedemodataimport'),
					$pad
				)
			),
			'post_excerpt'      => sprintf(
				/* translators: %s: padded number */
				__('Sample excerpt for demo post %s.', 'aidedemodataimport'),
				$pad
			),
			'post_status'       => 'publish',
			'post_date'         => gmdate('Y-m-d H:i:s', strtotime('2024-01-01 09:00:00') + (($number - 1) * DAY_IN_SECONDS)),
			'categories'        => !empty($tpl['categories']) ? $tpl['categories'] : array('News', 'Demo'),
			'tags'              => array('aide', 'post-' . $pad),
			'featured_media_id' => 'demo-media-' . $media_pad,
			'featured_image'    => 'media/product-' . $img . '.jpg',
		);
	}

	/**
	 * @param int    $number Number.
	 * @param string $pad    Padded id.
	 * @param string $img    Image cycle pad.
	 * @param array  $tpl    Template.
	 * @return array
	 */
	private static function make_product($number, $pad, $img, $tpl)
	{
		$base_name = isset($tpl['name']) ? preg_replace('/\s+\d+$/', '', (string) $tpl['name']) : 'Demo Product';
		$price     = isset($tpl['regular_price']) ? (float) $tpl['regular_price'] : 19.99;
		$price     = round($price + (($number % 17) * 0.25), 2);

		$item = array(
			'id'                => 'demo-product-' . $pad,
			'name'              => trim($base_name) . ' ' . $pad,
			'description'       => sprintf(
				'<p>%s</p>',
				sprintf(
					/* translators: %s: product name */
					__('%s demo product for WooCommerce storefronts. Ideal sample catalog content.', 'aidedemodataimport'),
					trim($base_name)
				)
			),
			'short_description' => isset($tpl['short_description'])
				? (string) $tpl['short_description']
				: __('Sample demo product.', 'aidedemodataimport'),
			'regular_price'     => (string) $price,
			'sku'               => 'AIDE-SKU-' . $pad,
			'manage_stock'      => true,
			'stock_quantity'    => 10 + ($number % 90),
			'categories'        => !empty($tpl['categories']) ? $tpl['categories'] : array('Demo'),
			'image_id'          => 'demo-prod-img-' . $pad,
			'image'             => 'media/products/product-' . $img . '.jpg',
		);

		if (!empty($tpl['sale_price']) && 0 === $number % 5) {
			$item['sale_price'] = (string) round($price * 0.85, 2);
		}

		return $item;
	}

	/**
	 * @param int    $number Number.
	 * @param string $pad    Padded id.
	 * @param string $img    Image cycle pad.
	 * @param array  $tpl    Template.
	 * @return array
	 */
	private static function make_customer($number, $pad, $img, $tpl)
	{
		$first = isset($tpl['first_name']) ? (string) $tpl['first_name'] : 'Demo';
		$last  = isset($tpl['last_name']) ? (string) $tpl['last_name'] : 'Customer';
		$email = 'customer' . $pad . '@aidedemo.local';

		$billing = isset($tpl['billing']) && is_array($tpl['billing']) ? $tpl['billing'] : array();
		$billing['first_name'] = $first;
		$billing['last_name']  = $last;
		$billing['email']      = $email;
		$billing['address_1']  = isset($billing['address_1'])
			? preg_replace('/^\d+/', (string) $number, (string) $billing['address_1'])
			: $number . ' Demo Street';
		$billing['phone']      = '555' . str_pad((string) (1000000 + ($number % 8999999)), 7, '0', STR_PAD_LEFT);

		$shipping = isset($tpl['shipping']) && is_array($tpl['shipping']) ? $tpl['shipping'] : array();
		$shipping['first_name'] = $first;
		$shipping['last_name']  = $last;
		$shipping['address_1']  = isset($billing['address_1']) ? $billing['address_1'] : ($number . ' Demo Street');

		return array(
			'id'           => 'demo-customer-' . $pad,
			'username'     => 'aidedemo.customer' . $pad,
			'email'        => $email,
			'first_name'   => $first,
			'last_name'    => $last,
			'display_name' => $first . ' ' . $last,
			'avatar'       => 'avatars/avatar-' . $img . '.jpg',
			'billing'      => $billing,
			'shipping'     => $shipping,
		);
	}

	/**
	 * @param int    $number  Number.
	 * @param string $pad     Padded id.
	 * @param array  $tpl     Template.
	 * @param array  $context product_demo_ids, customer_demo_ids.
	 * @return array
	 */
	private static function make_order($number, $pad, $tpl, $context)
	{
		$product_ids  = !empty($context['product_demo_ids']) ? array_values((array) $context['product_demo_ids']) : array();
		$customer_ids = !empty($context['customer_demo_ids']) ? array_values((array) $context['customer_demo_ids']) : array();

		$customer_demo = !empty($customer_ids)
			? $customer_ids[ ($number - 1) % count($customer_ids) ]
			: sprintf('demo-customer-%03d', (($number - 1) % 100) + 1);

		$product_a = !empty($product_ids)
			? $product_ids[ ($number - 1) % count($product_ids) ]
			: sprintf('demo-product-%03d', (($number - 1) % 100) + 1);

		$product_b = !empty($product_ids)
			? $product_ids[ $number % count($product_ids) ]
			: sprintf('demo-product-%03d', ($number % 100) + 1);

		$statuses = array('completed', 'processing', 'on-hold', 'pending');
		$status   = isset($tpl['status']) ? (string) $tpl['status'] : $statuses[ ($number - 1) % count($statuses) ];

		$billing  = isset($tpl['billing']) && is_array($tpl['billing']) ? $tpl['billing'] : array();
		$shipping = isset($tpl['shipping']) && is_array($tpl['shipping']) ? $tpl['shipping'] : array();

		return array(
			'id'          => 'demo-order-' . $pad,
			'customer_id' => $customer_demo,
			'status'      => $status,
			'line_items'  => array(
				array(
					'product_id' => $product_a,
					'quantity'   => 1 + ($number % 3),
				),
				array(
					'product_id' => $product_b,
					'quantity'   => 1,
				),
			),
			'billing'     => $billing,
			'shipping'    => $shipping,
		);
	}
}
