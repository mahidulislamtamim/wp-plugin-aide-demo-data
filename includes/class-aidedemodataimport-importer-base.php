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
	 * Maximum importable records (static package + dynamic).
	 *
	 * @return int
	 */
	public function get_total()
	{
		$package = (int) $this->get_package_total();
		$max     = (int) aidedemodataimport_max_import_total();
		return max($package, $max);
	}

	/**
	 * Count of curated records in the static JSON package.
	 *
	 * @return int
	 */
	abstract public function get_package_total();

	/**
	 * Import one batch of pending (not-yet-imported) items.
	 *
	 * @param int $offset Offset into the pending list.
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
	 * How many records can still be imported (up to max total).
	 *
	 * @return int
	 */
	public function count_pending()
	{
		return max(0, (int) $this->get_total() - (int) $this->count_imported());
	}

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
		$package    = (int) $this->get_package_total();
		$imported   = (int) $this->count_imported();

		return array(
			'total'      => $total,
			'package'    => $package,
			'imported'   => $imported,
			'remaining'  => max(0, $total - $imported),
			'available'  => $this->is_available(),
			'last_run'   => isset($last['time']) ? $last['time'] : '',
			'last_stats' => isset($last['stats']) ? $last['stats'] : array(),
		);
	}

	/**
	 * Build a static or dynamically generated item for a 0-based index.
	 *
	 * @param int   $index        Zero-based index.
	 * @param array $static_items Static package rows.
	 * @param array $context      Extra generator context.
	 * @return array
	 */
	protected function build_item_at_index($index, $static_items, $context = array())
	{
		$index = max(0, (int) $index);
		if (isset($static_items[ $index ]) && is_array($static_items[ $index ])) {
			return $static_items[ $index ];
		}

		return Aide_Demo_Data_Import_Dynamic_Generator::make(
			$this->get_type(),
			$index + 1,
			$static_items,
			$context
		);
	}

	/**
	 * Collect the next pending items without materializing the full max list.
	 *
	 * @param int      $limit         How many pending items to return.
	 * @param callable $exists_cb     function(array $item): bool — true if already imported.
	 * @param array    $static_items  Static package.
	 * @param array    $context       Generator context.
	 * @return array
	 */
	protected function collect_next_pending_items($limit, $exists_cb, $static_items, $context = array())
	{
		$limit    = max(1, (int) $limit);
		$max      = (int) $this->get_total();
		$imported = (int) $this->count_imported();
		$start    = max(0, $imported - 10);
		$pending  = array();
		$scanned  = 0;
		$cap      = max($limit * 40, 200);

		for ($pass = 0; $pass < 2 && count($pending) < $limit; $pass++) {
			$from = (0 === $pass) ? $start : 0;
			for ($i = $from; $i < $max && count($pending) < $limit; $i++) {
				$item = $this->build_item_at_index($i, $static_items, $context);
				if (!$exists_cb($item)) {
					$pending[] = $item;
				}
				++$scanned;
				if ($scanned > $cap && empty($pending) && 0 === $pass) {
					break;
				}
				if ($scanned > $max + $limit) {
					break 2;
				}
			}
			if (!empty($pending) || 0 === $start) {
				break;
			}
			$scanned = 0;
		}

		return $pending;
	}

	/**
	 * Whether dependencies for this importer are satisfied.
	 *
	 * @return true|WP_Error
	 */
	public function validate_dependencies()
	{
		foreach ($this->get_dependencies() as $dep) {
			$dep_importer = Aide_Demo_Data_Import_Importer_Registry::get($dep);
			if (!$dep_importer) {
				continue;
			}

			if ((int) $dep_importer->count_imported() < 1 && empty(aidedemodataimport_get_id_map($dep))) {
				return new WP_Error(
					'missing_dependency',
					sprintf(
						/* translators: 1: current importer label, 2: dependency label */
						__('Import %2$s before importing %1$s.', 'aidedemodataimport'),
						$this->get_label(),
						$dep_importer->get_label()
					)
				);
			}
		}

		return true;
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
