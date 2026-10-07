<?php
/**
 * Admin menu and assets.
 *
 * @package AideDemoDataImport
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Admin class.
 */
class Aide_Demo_Data_Import_Admin
{

	/**
	 * Instance.
	 *
	 * @var Aide_Demo_Data_Import_Admin|null
	 */
	private static $instance = null;

	/**
	 * Get singleton.
	 *
	 * @return Aide_Demo_Data_Import_Admin
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
		add_action('admin_menu', array($this, 'register_admin_menu'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
		add_filter('plugin_action_links_' . AIDEDEMODATAIMPORT_BASENAME, array($this, 'add_plugin_action_links'));
	}

	/**
	 * Register Tools submenu.
	 */
	public function register_admin_menu()
	{
		add_management_page(
			__('Demo Data Import', 'aidedemodataimport'),
			__('Demo Data Import', 'aidedemodataimport'),
			aidedemodataimport_required_capability(),
			'aidedemodataimport',
			array($this, 'render_page')
		);
	}

	/**
	 * Render import page.
	 */
	public function render_page()
	{
		if (!current_user_can(aidedemodataimport_required_capability())) {
			wp_die(esc_html__('You do not have permission to access this page.', 'aidedemodataimport'));
		}

		require_once AIDEDEMODATAIMPORT_PATH . 'admin/controllers/ImportController.php';
		$controller = new Aide_Demo_Data_Import_ImportController();
		$controller->render();
	}

	/**
	 * Enqueue assets on plugin screen only.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets($hook)
	{
		if ('tools_page_aidedemodataimport' !== $hook) {
			return;
		}

		wp_enqueue_style(
			'aidedemodataimport-admin',
			AIDEDEMODATAIMPORT_URL . 'assets/css/aidedemodataimport-admin.css',
			array(),
			AIDEDEMODATAIMPORT_VERSION
		);

		wp_enqueue_script(
			'aidedemodataimport-admin',
			AIDEDEMODATAIMPORT_URL . 'assets/js/aidedemodataimport-admin.js',
			array('jquery'),
			AIDEDEMODATAIMPORT_VERSION,
			true
		);

		$type_labels = array();
		foreach (Aide_Demo_Data_Import_Importer_Registry::get_importers() as $type => $importer) {
			$type_labels[ $type ] = $importer->get_label();
		}

		wp_localize_script(
			'aidedemodataimport-admin',
			'aidedemodataimportAdmin',
			array(
				'ajaxUrl'    => admin_url('admin-ajax.php'),
				'nonce'      => wp_create_nonce('aidedemodataimport_admin_nonce'),
				'batchSize'  => aidedemodataimport_batch_size(),
				'typeLabels' => $type_labels,
				'i18n'       => array(
					'confirmImport' => __(
						'Start importing this demo content? Existing demo items with the same ID will be skipped.',
						'aidedemodataimport'
					),
					/* translators: %d: number of records to import */
					'confirmImportCount' => __(
						'Import %d records?',
						'aidedemodataimport'
					),
					'confirmCleanup' => __(
						'Remove all demo content imported by this plugin for this type? This cannot be undone.',
						'aidedemodataimport'
					),
					'confirmTitle'   => __('Confirm import', 'aidedemodataimport'),
					'cleanupTitle'   => __('Confirm removal', 'aidedemodataimport'),
					'confirmOk'      => __('Import', 'aidedemodataimport'),
					'cleanupOk'      => __('Remove', 'aidedemodataimport'),
					'confirmCancel'  => __('Cancel', 'aidedemodataimport'),
					'importing' => __('Importing…', 'aidedemodataimport'),
					'cleaning'  => __('Removing…', 'aidedemodataimport'),
					'done'      => __('Done.', 'aidedemodataimport'),
					'error'     => __('Something went wrong.', 'aidedemodataimport'),
					'modeImport' => __('Importing', 'aidedemodataimport'),
					'modeCleanup' => __('Removing', 'aidedemodataimport'),
					'modeDone' => __('Completed', 'aidedemodataimport'),
					/* translators: %s: content type label */
					'progressImportSubtitle' => __('Importing %s demo records for this session.', 'aidedemodataimport'),
					/* translators: %s: content type label */
					'progressCleanupSubtitle' => __('Removing demo %s content tagged by this plugin.', 'aidedemodataimport'),
					/* translators: %s: content type label */
					'progressDoneSubtitle' => __('Finished processing %s.', 'aidedemodataimport'),
					/* translators: 1: current progress count, 2: total count */
					'progress' => __('Progress: %1$d / %2$d', 'aidedemodataimport'),
					/* translators: 1: current count, 2: total count */
					'progressCounts' => __('%1$d of %2$d records', 'aidedemodataimport'),
					/* translators: 1: imported count, 2: total available */
					'alreadyImported' => __('Already imported: %1$d of %2$d', 'aidedemodataimport'),
					/* translators: %d: remaining count */
					'remaining' => __('Remaining: %d', 'aidedemodataimport'),
					/* translators: %s: datetime string */
					'lastRun' => __('Last run: %s', 'aidedemodataimport'),
					/* translators: 1: imported count, 2: skipped count */
					'lastStats'      => __('imported %1$d, skipped %2$d', 'aidedemodataimport'),
					'never'          => __('Never', 'aidedemodataimport'),
					'badgeComplete'  => __('Complete', 'aidedemodataimport'),
					'badgePartial'   => __('Partial', 'aidedemodataimport'),
					'badgeNone'      => __('Not imported', 'aidedemodataimport'),
				),
			)
		);
	}

	/**
	 * Plugins list action links.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function add_plugin_action_links($links)
	{
		$import_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url(admin_url('tools.php?page=aidedemodataimport')),
			esc_html__('Import', 'aidedemodataimport')
		);
		array_unshift($links, $import_link);
		return $links;
	}
}
