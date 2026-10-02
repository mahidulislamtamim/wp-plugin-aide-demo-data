<?php
/**
 * Core plugin bootstrap.
 *
 * @package AideDemoDataImport
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Core singleton.
 */
class Aide_Demo_Data_Import_Core
{

	/**
	 * Instance.
	 *
	 * @var Aide_Demo_Data_Import_Core|null
	 */
	private static $instance = null;

	/**
	 * Whether initialized.
	 *
	 * @var bool
	 */
	private static $initialized = false;

	/**
	 * Get singleton.
	 *
	 * @return Aide_Demo_Data_Import_Core
	 */
	public static function instance()
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct()
	{
		if (!self::$initialized) {
			$this->init();
			self::$initialized = true;
		}
	}

	/**
	 * Init.
	 */
	private function init()
	{
		$this->load_importers();
		add_filter('get_avatar_url', array($this, 'filter_demo_avatar_url'), 10, 3);

		if (is_admin()) {
			require_once AIDEDEMODATAIMPORT_PATH . 'admin/class-aidedemodataimport-admin.php';
			Aide_Demo_Data_Import_Admin::instance();
		}
	}

	/**
	 * Serve imported demo avatar URLs for users that have one.
	 *
	 * @param string $url         Avatar URL.
	 * @param mixed  $id_or_email User ID, email, or object.
	 * @param array  $args        Avatar args.
	 * @return string
	 */
	public function filter_demo_avatar_url($url, $id_or_email, $args)
	{
		$user_id = 0;
		if (is_numeric($id_or_email)) {
			$user_id = (int) $id_or_email;
		} elseif (is_object($id_or_email) && !empty($id_or_email->user_id)) {
			$user_id = (int) $id_or_email->user_id;
		} elseif (is_object($id_or_email) && !empty($id_or_email->ID)) {
			$user_id = (int) $id_or_email->ID;
		} elseif (is_string($id_or_email) && is_email($id_or_email)) {
			$user = get_user_by('email', $id_or_email);
			if ($user) {
				$user_id = (int) $user->ID;
			}
		}

		if (!$user_id) {
			return $url;
		}

		$avatar_id = (int) get_user_meta($user_id, '_demo_avatar_id', true);
		if (!$avatar_id) {
			return $url;
		}

		$size = !empty($args['size']) ? (int) $args['size'] : 96;
		$src  = wp_get_attachment_image_url($avatar_id, array($size, $size));
		return $src ? $src : $url;
	}

	/**
	 * Load importer class files.
	 */
	private function load_importers()
	{
		$files = array(
			'includes/importers/class-aidedemodataimport-media-importer.php',
			'includes/importers/class-aidedemodataimport-post-importer.php',
			'includes/importers/class-aidedemodataimport-product-importer.php',
			'includes/importers/class-aidedemodataimport-customer-importer.php',
			'includes/importers/class-aidedemodataimport-order-importer.php',
		);

		foreach ($files as $file) {
			$path = AIDEDEMODATAIMPORT_PATH . $file;
			if (file_exists($path)) {
				require_once $path;
			}
		}
	}
}
