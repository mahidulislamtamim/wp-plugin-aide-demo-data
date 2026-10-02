<?php
/**
 * Import admin controller.
 *
 * @package AideDemoDataImport
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * ImportController.
 */
class Aide_Demo_Data_Import_ImportController
{

	/**
	 * Render the import view.
	 */
	public function render()
	{
		$importers = Aide_Demo_Data_Import_Importer_Registry::get_importers();
		$woo_active = aidedemodataimport_is_woocommerce_active();

		$view_data = array(
			'importers'  => $importers,
			'woo_active' => $woo_active,
			'log'        => array_reverse(aidedemodataimport_get_import_log()),
		);

		include AIDEDEMODATAIMPORT_PATH . 'admin/views/import/index.php';
	}
}
