<?php
/**
 * Plugin installer — activation and deactivation.
 *
 * @package AideDemoDataImport
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Installer class.
 */
class Aide_Demo_Data_Import_Installer
{

	/**
	 * Activation callback.
	 */
	public static function activate()
	{
		self::add_default_options();
		update_option('aidedemodataimport_activated', time());
		update_option('aidedemodataimport_version', AIDEDEMODATAIMPORT_VERSION);

		if (ob_get_length()) {
			ob_end_clean();
		}
	}

	/**
	 * Deactivation callback.
	 */
	public static function deactivate()
	{
		delete_transient('aidedemodataimport_import_progress');
	}

	/**
	 * Default options.
	 */
	private static function add_default_options()
	{
		if (false === get_option('aidedemodataimport_settings', false)) {
			add_option(
				'aidedemodataimport_settings',
				array(
					'delete_demo_on_uninstall' => true,
				)
			);
		}

		if (false === get_option('aidedemodataimport_import_log', false)) {
			add_option('aidedemodataimport_import_log', array(), '', false);
		}
	}

	/**
	 * Whether WooCommerce is installed and active.
	 *
	 * @return bool
	 */
	public static function is_woocommerce_active()
	{
		return aidedemodataimport_is_woocommerce_active();
	}
}
