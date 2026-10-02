<?php

/**
 * Plugin Name:       Aide :: Demo Data Import
 * Plugin URI:        https://aide247.com/
 * Description:       Import curated demo posts, media, and WooCommerce products, customers, and orders from wp-admin.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Aide: Demo Data
 * Author URI:        https://aide247.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       aidedemodataimport
 * Domain Path:       /languages
 */

if (!defined('ABSPATH')) {
	exit;
}

define('AIDEDEMODATAIMPORT_VERSION', '1.0.0');
define('AIDEDEMODATAIMPORT_PATH', plugin_dir_path(__FILE__));
define('AIDEDEMODATAIMPORT_URL', plugin_dir_url(__FILE__));
define('AIDEDEMODATAIMPORT_BASENAME', plugin_basename(__FILE__));
define('AIDEDEMODATAIMPORT_DEMO_KEY', 'aidedemodataimport');

require_once AIDEDEMODATAIMPORT_PATH . 'includes/helpers/aidedemodataimport-helpers.php';
require_once AIDEDEMODATAIMPORT_PATH . 'includes/class-aidedemodataimport-installer.php';
require_once AIDEDEMODATAIMPORT_PATH . 'includes/class-aidedemodataimport-importer-base.php';
require_once AIDEDEMODATAIMPORT_PATH . 'includes/class-aidedemodataimport-importer-registry.php';
require_once AIDEDEMODATAIMPORT_PATH . 'includes/class-aidedemodataimport-ajax.php';
require_once AIDEDEMODATAIMPORT_PATH . 'includes/class-aidedemodataimport-core.php';

register_activation_hook(__FILE__, array('Aide_Demo_Data_Import_Installer', 'activate'));
register_deactivation_hook(__FILE__, array('Aide_Demo_Data_Import_Installer', 'deactivate'));

/**
 * Bootstrap plugin subsystems.
 *
 * Translations load automatically on WordPress.org for this plugin slug.
 */
function aidedemodataimport()
{
	Aide_Demo_Data_Import_Ajax::init();

	if (class_exists('Aide_Demo_Data_Import_Core')) {
		Aide_Demo_Data_Import_Core::instance();
	}
}
add_action('plugins_loaded', 'aidedemodataimport');
