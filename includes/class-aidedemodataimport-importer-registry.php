<?php
/**
 * Importer registry.
 *
 * @package AideDemoDataImport
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Registry of importers.
 */
class Aide_Demo_Data_Import_Importer_Registry
{

	/**
	 * Cached importers.
	 *
	 * @var Aide_Demo_Data_Import_Importer_Base[]|null
	 */
	private static $importers = null;

	/**
	 * Get all registered importers keyed by type.
	 *
	 * @return Aide_Demo_Data_Import_Importer_Base[]
	 */
	public static function get_importers()
	{
		if (null !== self::$importers) {
			return self::$importers;
		}

		$importers = array();

		if (class_exists('Aide_Demo_Data_Import_Media_Importer')) {
			$importers['media'] = new Aide_Demo_Data_Import_Media_Importer();
		}
		if (class_exists('Aide_Demo_Data_Import_Post_Importer')) {
			$importers['posts'] = new Aide_Demo_Data_Import_Post_Importer();
		}
		if (class_exists('Aide_Demo_Data_Import_Product_Importer')) {
			$importers['products'] = new Aide_Demo_Data_Import_Product_Importer();
		}
		if (class_exists('Aide_Demo_Data_Import_Customer_Importer')) {
			$importers['customers'] = new Aide_Demo_Data_Import_Customer_Importer();
		}
		if (class_exists('Aide_Demo_Data_Import_Order_Importer')) {
			$importers['orders'] = new Aide_Demo_Data_Import_Order_Importer();
		}

		/**
		 * Filter registered importers.
		 *
		 * @param Aide_Demo_Data_Import_Importer_Base[] $importers Importers keyed by type.
		 */
		$importers = apply_filters('aidedemodataimport_importers', $importers);

		self::$importers = is_array($importers) ? $importers : array();
		return self::$importers;
	}

	/**
	 * Get one importer by type.
	 *
	 * @param string $type Type slug.
	 * @return Aide_Demo_Data_Import_Importer_Base|null
	 */
	public static function get($type)
	{
		$importers = self::get_importers();
		$type      = sanitize_key($type);
		return isset($importers[ $type ]) ? $importers[ $type ] : null;
	}
}
