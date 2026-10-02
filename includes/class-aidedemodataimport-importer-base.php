<?php
/**
 * Abstract importer base class.
 *
 * @package AideDemoDataImport
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Base importer.
 */
abstract class Aide_Demo_Data_Import_Importer_Base
{

	/**
	 * Importer type slug.
	 *
	 * @return string
	 */
	abstract public function get_type();

	/**
	 * Display label.
	 *
	 * @return string
	 */
	abstract public function get_label();

	/**
	 * Short description.
	 *
	 * @return string
	 */
	abstract public function get_description();

	/**
	 * Dependency type slugs that must be imported first.
	 *
	 * @return string[]
	 */
	public function get_dependencies()
	{
		return array();
	}

	/**
	 * Whether this importer requires WooCommerce.
	 *
	 * @return bool
	 */
	public function requires_woocommerce()
	{
		return false;
	}

	/**
	 * Whether the importer is available in the current environment.
	 *
	 * @return bool
	 */
	public function is_available()
	{
		if ($this->requires_woocommerce() && !aidedemodataimport_is_woocommerce_active()) {
			return false;
		}
		return true;
	}

	/**
	 * Total items in demo data.
	 *
	 * @return int
	 */
	abstract public function get_total();

	/**
	 * Import one batch.
	 *
	 * @param int $offset Offset.
	 * @param int $limit  Batch size.
	 * @return array{imported:int,skipped:int,errors:array,done:bool,processed:int}
	 */
	abstract public function import_batch($offset, $limit);

	/**
	 * Remove demo content for this type.
	 *
	 * @return array{deleted:int,errors:array}
	 */
	abstract public function cleanup();

	/**
	 * How many demo records of this type already exist in the site.
	 *
	 * @return int
	 */
	abstract public function count_imported();

	/**
	 * Status summary for the admin UI.
	 *
	 * @return array
	 */
	public function get_status()
	{
		$option_key = 'aidedemodataimport_last_' . $this->get_type();
		$last       = get_option($option_key, array());
		$total      = (int) $this->get_total();
		$imported   = (int) $this->count_imported();

		return array(
			'total'      => $total,
			'imported'   => $imported,
			'remaining'  => max(0, $total - $imported),
			'available'  => $this->is_available(),
			'last_run'   => isset($last['time']) ? $last['time'] : '',
			'last_stats' => isset($last['stats']) ? $last['stats'] : array(),
		);
	}

	/**
	 * Record last import run.
	 *
	 * @param array $stats Stats array.
	 */
	protected function record_last_run($stats)
	{
		update_option(
			'aidedemodataimport_last_' . $this->get_type(),
			array(
				'time'  => current_time('mysql'),
				'stats' => $stats,
			),
			false
		);
	}

	/**
	 * Empty batch result helper.
	 *
	 * @param int  $processed Items looked at in this batch.
	 * @param bool $done      Whether finished.
	 * @return array
	 */
	protected function empty_result($processed = 0, $done = true)
	{
		return array(
			'imported'  => 0,
			'skipped'   => 0,
			'errors'    => array(),
			'done'      => $done,
			'processed' => $processed,
		);
	}
}
