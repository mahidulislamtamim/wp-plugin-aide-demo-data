<?php
/**
 * Import admin view.
 *
 * @package AideDemoDataImport
 */

if (!defined('ABSPATH')) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$importers  = isset($view_data['importers']) ? $view_data['importers'] : array();
$woo_active = !empty($view_data['woo_active']);
$log        = isset($view_data['log']) ? $view_data['log'] : array();
?>
<div class="wrap aidedemodataimport-wrap">
	<h1><?php echo esc_html__('Aide :: Demo Data Import', 'aidedemodataimport'); ?></h1>
	<p class="description">
		<?php echo esc_html__('Choose a content type below to import curated demo data. Items are tagged so you can remove them later without affecting your own content.', 'aidedemodataimport'); ?>
	</p>

	<?php if (!$woo_active) : ?>
		<div class="notice notice-info inline">
			<p>
				<?php
				echo esc_html__(
					'WooCommerce is not active. Product, customer, and order imports are unavailable until WooCommerce is installed and activated.',
					'aidedemodataimport'
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<div class="aidedemodataimport-history-wrap">
		<h2><?php echo esc_html__('Import history', 'aidedemodataimport'); ?></h2>
		<table class="widefat striped aidedemodataimport-history" id="aidedemodataimport-history">
			<thead>
				<tr>
					<th scope="col"><?php echo esc_html__('Content type', 'aidedemodataimport'); ?></th>
					<th scope="col"><?php echo esc_html__('Already imported', 'aidedemodataimport'); ?></th>
					<th scope="col"><?php echo esc_html__('Available', 'aidedemodataimport'); ?></th>
					<th scope="col"><?php echo esc_html__('Remaining', 'aidedemodataimport'); ?></th>
					<th scope="col"><?php echo esc_html__('Last run', 'aidedemodataimport'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($importers as $importer) : ?>
					<?php
					$h_status    = $importer->get_status();
					$h_type      = $importer->get_type();
					$h_imported  = isset($h_status['imported']) ? (int) $h_status['imported'] : 0;
					$h_total     = isset($h_status['total']) ? (int) $h_status['total'] : 0;
					$h_remaining = isset($h_status['remaining']) ? (int) $h_status['remaining'] : max(0, $h_total - $h_imported);
					$h_last      = !empty($h_status['last_run']) ? $h_status['last_run'] : __('Never', 'aidedemodataimport');
					?>
					<tr data-type="<?php echo esc_attr($h_type); ?>">
						<td><?php echo esc_html($importer->get_label()); ?></td>
						<td class="aidedemodataimport-history__imported"><strong><?php echo esc_html((string) $h_imported); ?></strong></td>
						<td class="aidedemodataimport-history__total"><?php echo esc_html((string) $h_total); ?></td>
						<td class="aidedemodataimport-history__remaining"><?php echo esc_html((string) $h_remaining); ?></td>
						<td class="aidedemodataimport-history__last-run"><?php echo esc_html($h_last); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<div id="aidedemodataimport-progress" class="aidedemodataimport-progress" hidden>
		<div class="aidedemodataimport-progress__bar">
			<span class="aidedemodataimport-progress__fill" style="width:0%"></span>
		</div>
		<p class="aidedemodataimport-progress__text"></p>
	</div>

	<div class="aidedemodataimport-cards">
		<?php foreach ($importers as $importer) : ?>
			<?php
			$type           = $importer->get_type();
			$status         = $importer->get_status();
			$available      = $importer->is_available();
			$deps           = $importer->get_dependencies();
			$imported       = isset($status['imported']) ? (int) $status['imported'] : 0;
			$total          = isset($status['total']) ? (int) $status['total'] : 0;
			$remaining      = isset($status['remaining']) ? (int) $status['remaining'] : max(0, $total - $imported);
			$available_total = max(0, $total);
			$default_count  = $available_total > 0 ? min(10, $available_total) : 0;
			$progress_pct   = $total > 0 ? min(100, round(($imported / $total) * 100)) : 0;
			$card_class     = 'aidedemodataimport-card';
			if (!$available) {
				$card_class .= ' is-disabled';
			}
			if ($imported > 0) {
				$card_class .= ' has-imports';
			}
			?>
			<article class="<?php echo esc_attr($card_class); ?>" data-type="<?php echo esc_attr($type); ?>">
				<header class="aidedemodataimport-card__header">
					<div class="aidedemodataimport-card__heading">
						<h2 class="aidedemodataimport-card__title"><?php echo esc_html($importer->get_label()); ?></h2>
						<?php if (!$available) : ?>
							<span class="aidedemodataimport-card__badge is-warning"><?php echo esc_html__('WooCommerce required', 'aidedemodataimport'); ?></span>
						<?php elseif ($imported >= $total && $total > 0) : ?>
							<span class="aidedemodataimport-card__badge is-success"><?php echo esc_html__('Complete', 'aidedemodataimport'); ?></span>
						<?php elseif ($imported > 0) : ?>
							<span class="aidedemodataimport-card__badge is-info"><?php echo esc_html__('Partial', 'aidedemodataimport'); ?></span>
						<?php else : ?>
							<span class="aidedemodataimport-card__badge"><?php echo esc_html__('Not imported', 'aidedemodataimport'); ?></span>
						<?php endif; ?>
					</div>
					<p class="aidedemodataimport-card__desc"><?php echo esc_html($importer->get_description()); ?></p>
				</header>

				<div class="aidedemodataimport-card__stats" aria-label="<?php echo esc_attr__('Import status', 'aidedemodataimport'); ?>">
					<div class="aidedemodataimport-stat">
						<span class="aidedemodataimport-stat__value aidedemodataimport-card__imported-value"><?php echo esc_html((string) $imported); ?></span>
						<span class="aidedemodataimport-stat__label"><?php echo esc_html__('Imported', 'aidedemodataimport'); ?></span>
					</div>
					<div class="aidedemodataimport-stat">
						<span class="aidedemodataimport-stat__value aidedemodataimport-card__remaining-value"><?php echo esc_html((string) $remaining); ?></span>
						<span class="aidedemodataimport-stat__label"><?php echo esc_html__('Remaining', 'aidedemodataimport'); ?></span>
					</div>
					<div class="aidedemodataimport-stat">
						<span class="aidedemodataimport-stat__value"><?php echo esc_html((string) $total); ?></span>
						<span class="aidedemodataimport-stat__label"><?php echo esc_html__('In package', 'aidedemodataimport'); ?></span>
					</div>
				</div>

				<div class="aidedemodataimport-card__progress" title="<?php echo esc_attr((string) $progress_pct); ?>%">
					<span class="aidedemodataimport-card__progress-fill" style="width: <?php echo esc_attr((string) $progress_pct); ?>%;"></span>
				</div>

				<p class="aidedemodataimport-card__meta-line">
					<span class="aidedemodataimport-card__imported screen-reader-text">
						<?php
						printf(
							/* translators: 1: imported count, 2: total available */
							esc_html__('Already imported: %1$d of %2$d', 'aidedemodataimport'),
							absint($imported),
							absint($total)
						);
						?>
					</span>
					<span class="aidedemodataimport-card__remaining screen-reader-text">
						<?php
						printf(
							/* translators: %d: remaining count */
							esc_html__('Remaining: %d', 'aidedemodataimport'),
							absint($remaining)
						);
						?>
					</span>
					<span class="aidedemodataimport-card__last-run">
						<?php if (!empty($status['last_run'])) : ?>
							<?php
							printf(
								/* translators: %s: datetime */
								esc_html__('Last run: %s', 'aidedemodataimport'),
								esc_html($status['last_run'])
							);
							?>
							<?php if (!empty($status['last_stats']) && is_array($status['last_stats'])) : ?>
								<?php
								$last_imported = isset($status['last_stats']['imported']) ? (int) $status['last_stats']['imported'] : 0;
								$last_skipped  = isset($status['last_stats']['skipped']) ? (int) $status['last_stats']['skipped'] : 0;
								echo ' · ';
								printf(
									/* translators: 1: imported, 2: skipped */
									esc_html__('imported %1$d, skipped %2$d', 'aidedemodataimport'),
									absint($last_imported),
									absint($last_skipped)
								);
								?>
							<?php endif; ?>
						<?php else : ?>
							<?php echo esc_html__('Last run: never', 'aidedemodataimport'); ?>
						<?php endif; ?>
					</span>
					<?php if (!empty($deps)) : ?>
						<span class="aidedemodataimport-card__deps">
							·
							<?php
							printf(
								/* translators: %s: dependency list */
								esc_html__('Depends on: %s', 'aidedemodataimport'),
								esc_html(implode(', ', $deps))
							);
							?>
						</span>
					<?php endif; ?>
				</p>

				<?php if (!$available) : ?>
					<p class="aidedemodataimport-card__notice">
						<?php echo esc_html__('Activate WooCommerce to enable this importer.', 'aidedemodataimport'); ?>
					</p>
				<?php else : ?>
					<div class="aidedemodataimport-card__footer">
						<div class="aidedemodataimport-card__qty">
							<label for="aidedemodataimport-qty-<?php echo esc_attr($type); ?>">
								<?php echo esc_html__('Records to import', 'aidedemodataimport'); ?>
							</label>
							<div class="aidedemodataimport-card__qty-controls">
								<input
									type="number"
									class="aidedemodataimport-qty"
									id="aidedemodataimport-qty-<?php echo esc_attr($type); ?>"
									name="aidedemodataimport_qty_<?php echo esc_attr($type); ?>"
									min="1"
									max="<?php echo esc_attr((string) max(1, $available_total)); ?>"
									value="<?php echo esc_attr((string) max(1, $default_count)); ?>"
									<?php disabled(0 === $available_total); ?>
								/>
								<button type="button" class="button aidedemodataimport-qty-all" data-type="<?php echo esc_attr($type); ?>" data-max="<?php echo esc_attr((string) $available_total); ?>" <?php disabled(0 === $available_total); ?>>
									<?php echo esc_html__('All', 'aidedemodataimport'); ?>
								</button>
								<span class="aidedemodataimport-card__qty-hint">
									<?php
									printf(
										/* translators: %d: maximum available records */
										esc_html__('/ %d', 'aidedemodataimport'),
										absint($available_total)
									);
									?>
								</span>
							</div>
						</div>
						<div class="aidedemodataimport-card__actions">
							<button type="button" class="button button-primary aidedemodataimport-import" data-type="<?php echo esc_attr($type); ?>" <?php disabled(0 === $available_total); ?>>
								<?php echo esc_html__('Import', 'aidedemodataimport'); ?>
							</button>
							<button type="button" class="button aidedemodataimport-cleanup" data-type="<?php echo esc_attr($type); ?>">
								<?php echo esc_html__('Remove', 'aidedemodataimport'); ?>
							</button>
						</div>
					</div>
				<?php endif; ?>
			</article>
		<?php endforeach; ?>
	</div>

	<div class="aidedemodataimport-log-panel">
		<h2><?php echo esc_html__('Recent activity', 'aidedemodataimport'); ?></h2>
		<div id="aidedemodataimport-live-log" class="aidedemodataimport-live-log" aria-live="polite"></div>
		<?php if (!empty($log)) : ?>
			<ul class="aidedemodataimport-log-list">
				<?php foreach (array_slice($log, 0, 20) as $entry) : ?>
					<li class="aidedemodataimport-log-list__item is-<?php echo esc_attr(isset($entry['type']) ? $entry['type'] : 'info'); ?>">
						<code><?php echo esc_html(isset($entry['time']) ? $entry['time'] : ''); ?></code>
						<?php echo esc_html(isset($entry['message']) ? $entry['message'] : ''); ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p class="description"><?php echo esc_html__('No import activity yet.', 'aidedemodataimport'); ?></p>
		<?php endif; ?>
	</div>
</div>
<?php
// phpcs:enable
