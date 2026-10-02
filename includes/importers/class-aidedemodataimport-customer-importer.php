<?php
/**
 * WooCommerce customer importer.
 *
 * @package AideDemoDataImport
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Imports demo customers.
 */
class Aide_Demo_Data_Import_Customer_Importer extends Aide_Demo_Data_Import_Importer_Base
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
		return 'customers';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label()
	{
		return __('Customers', 'aidedemodataimport');
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_description()
	{
		return __('Import sample WooCommerce customers with billing, shipping, and profile avatars.', 'aidedemodataimport');
	}

	/**
	 * Sideload and attach a customer avatar.
	 *
	 * @param int   $user_id User ID.
	 * @param array $item    Customer item.
	 * @return int Attachment ID or 0.
	 */
	private function import_avatar($user_id, $item)
	{
		if (empty($item['avatar'])) {
			return 0;
		}

		$demo_id  = 'avatar-' . (isset($item['id']) ? sanitize_text_field($item['id']) : (string) $user_id);
		$existing = aidedemodataimport_find_post_by_demo_id('attachment', $demo_id);
		if ($existing) {
			$this->assign_avatar_meta($user_id, $existing);
			return $existing;
		}

		$title         = isset($item['display_name']) ? $item['display_name'] : ('Customer ' . $user_id);
		$attachment_id = Aide_Demo_Data_Import_Media_Importer::sideload_file($item['avatar'], $title);
		if (is_wp_error($attachment_id)) {
			aidedemodataimport_log($attachment_id->get_error_message(), 'error', 'customers');
			return 0;
		}

		aidedemodataimport_tag_post($attachment_id, $demo_id);
		$this->assign_avatar_meta($user_id, $attachment_id);

		return (int) $attachment_id;
	}

	/**
	 * Store avatar attachment on the user (works with get_avatar filter).
	 *
	 * @param int $user_id       User ID.
	 * @param int $attachment_id Attachment ID.
	 */
	private function assign_avatar_meta($user_id, $attachment_id)
	{
		update_user_meta($user_id, '_demo_avatar_id', (int) $attachment_id);
		// Common meta keys used by avatar plugins / themes.
		update_user_meta($user_id, 'wp_user_avatar', (int) $attachment_id);
	}

	/**
	 * {@inheritdoc}
	 */
	public function requires_woocommerce()
	{
		return true;
	}

	/**
	 * Load customers.
	 *
	 * @return array
	 */
	private function get_items()
	{
		if (null !== $this->items) {
			return $this->items;
		}

		$data = aidedemodataimport_load_json('woocommerce/customers.json');
		if (is_wp_error($data)) {
			$this->items = array();
			return $this->items;
		}

		$this->items = isset($data['customers']) && is_array($data['customers']) ? $data['customers'] : array();
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
		$id_map = aidedemodataimport_get_id_map('customers');

		if (empty($slice)) {
			$result['done'] = true;
			return $result;
		}

		foreach ($slice as $item) {
			++$result['processed'];
			$demo_id = isset($item['id']) ? sanitize_text_field($item['id']) : '';
			if ('' === $demo_id) {
				$result['errors'][] = __('Customer missing demo id.', 'aidedemodataimport');
				continue;
			}

			$existing = aidedemodataimport_find_user_by_demo_id($demo_id);
			if ($existing) {
				$id_map[ $demo_id ] = $existing;
				++$result['skipped'];
				continue;
			}

			$email    = isset($item['email']) ? sanitize_email($item['email']) : '';
			$username = isset($item['username']) ? sanitize_user($item['username'], true) : '';
			if ('' === $email || '' === $username) {
				$result['errors'][] = __('Customer missing email or username.', 'aidedemodataimport');
				continue;
			}

			if (email_exists($email) || username_exists($username)) {
				$result['errors'][] = sprintf(
					/* translators: %s: email */
					__('User already exists for %s; skipped.', 'aidedemodataimport'),
					$email
				);
				++$result['skipped'];
				continue;
			}

			$password = wp_generate_password(16, true);
			$user_id  = wp_insert_user(
				array(
					'user_login'   => $username,
					'user_email'   => $email,
					'user_pass'    => $password,
					'first_name'   => isset($item['first_name']) ? sanitize_text_field($item['first_name']) : '',
					'last_name'    => isset($item['last_name']) ? sanitize_text_field($item['last_name']) : '',
					'display_name' => isset($item['display_name']) ? sanitize_text_field($item['display_name']) : $username,
					'role'         => 'customer',
				)
			);

			if (is_wp_error($user_id)) {
				$result['errors'][] = $user_id->get_error_message();
				aidedemodataimport_log($user_id->get_error_message(), 'error', 'customers');
				continue;
			}

			aidedemodataimport_tag_user($user_id, $demo_id);

			$billing  = isset($item['billing']) && is_array($item['billing']) ? $item['billing'] : array();
			$shipping = isset($item['shipping']) && is_array($item['shipping']) ? $item['shipping'] : array();

			$address_keys = array(
				'first_name',
				'last_name',
				'company',
				'address_1',
				'address_2',
				'city',
				'state',
				'postcode',
				'country',
				'email',
				'phone',
			);

			foreach ($address_keys as $key) {
				if (isset($billing[ $key ])) {
					update_user_meta($user_id, 'billing_' . $key, sanitize_text_field($billing[ $key ]));
				}
				if (isset($shipping[ $key ]) && 'email' !== $key && 'phone' !== $key) {
					update_user_meta($user_id, 'shipping_' . $key, sanitize_text_field($shipping[ $key ]));
				}
			}

			$this->import_avatar($user_id, $item);

			$id_map[ $demo_id ] = $user_id;
			++$result['imported'];
			aidedemodataimport_log(
				sprintf(
					/* translators: %s: email */
					__('Imported customer: %s', 'aidedemodataimport'),
					$email
				),
				'success',
				'customers'
			);
		}

		aidedemodataimport_set_id_map('customers', $id_map);
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
	 * Count imported demo customers.
	 *
	 * @return int
	 */
	public function count_imported()
	{
		$query = new WP_User_Query(
			array(
				'number'      => 1,
				'count_total' => true,
				'fields'      => 'ID',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Lookup by demo import meta.
				'meta_key'    => '_demo_import_key',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Lookup by demo import meta.
				'meta_value'  => AIDEDEMODATAIMPORT_DEMO_KEY,
			)
		);

		return (int) $query->get_total();
	}

	/**
	 * {@inheritdoc}
	 */
	public function cleanup()
	{
		$users = get_users(
			array(
				'number'     => -1,
				'fields'     => array('ID'),
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Lookup by demo import meta.
				'meta_key'   => '_demo_import_key',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Lookup by demo import meta.
				'meta_value' => AIDEDEMODATAIMPORT_DEMO_KEY,
			)
		);

		$deleted = 0;
		$errors  = array();
		require_once ABSPATH . 'wp-admin/includes/user.php';

		foreach ($users as $user) {
			$id        = (int) $user->ID;
			$avatar_id = (int) get_user_meta($id, '_demo_avatar_id', true);
			if ($avatar_id) {
				wp_delete_attachment($avatar_id, true);
			}
			if (wp_delete_user($id)) {
				++$deleted;
			} else {
				$errors[] = sprintf(
					/* translators: %d: user ID */
					__('Failed to delete user %d.', 'aidedemodataimport'),
					$id
				);
			}
		}

		aidedemodataimport_clear_id_map('customers');
		aidedemodataimport_log(
			sprintf(
				/* translators: %d: count */
				__('Removed %d demo customers.', 'aidedemodataimport'),
				$deleted
			),
			'success',
			'customers'
		);

		return array(
			'deleted' => $deleted,
			'errors'  => $errors,
		);
	}
}
