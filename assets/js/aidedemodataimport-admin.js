(function ($) {
	'use strict';

	var cfg = window.aidedemodataimportAdmin || {};
	var $progress = $('#aidedemodataimport-progress');
	var $fill = $progress.find('.aidedemodataimport-progress__fill');
	var $text = $progress.find('.aidedemodataimport-progress__text');
	var $live = $('#aidedemodataimport-live-log');
	var busy = false;

	function logLine(msg) {
		$live.prepend($('<div/>').text(msg));
	}

	function setProgress(current, total) {
		var pct = total > 0 ? Math.min(100, Math.round((current / total) * 100)) : 100;
		$progress.prop('hidden', false);
		$fill.css('width', pct + '%');
		var tpl = (cfg.i18n && cfg.i18n.progress) || 'Progress: %1$d / %2$d';
		$text.text(tpl.replace('%1$d', current).replace('%2$d', total));
	}

	function setBusy(state) {
		busy = state;
		$('.aidedemodataimport-import, .aidedemodataimport-cleanup, .aidedemodataimport-qty, .aidedemodataimport-qty-all').prop(
			'disabled',
			state
		);
	}

	function ajax(action, data) {
		data = data || {};
		data.action = action;
		data.nonce = cfg.nonce;
		return $.post(cfg.ajaxUrl, data);
	}

	function getQtyInput(type) {
		return $('#aidedemodataimport-qty-' + type);
	}

	function readCount(type) {
		var $input = getQtyInput(type);
		var max = parseInt($input.attr('max'), 10) || 0;
		var val = parseInt($input.val(), 10);

		if (!val || val < 1) {
			val = 1;
		}
		if (max > 0 && val > max) {
			val = max;
		}

		$input.val(val);
		return val;
	}

	function applyStatus(type, status) {
		if (!status) {
			return;
		}

		var imported = parseInt(status.imported, 10) || 0;
		var total = parseInt(status.total, 10) || 0;
		var remaining =
			typeof status.remaining !== 'undefined'
				? parseInt(status.remaining, 10)
				: Math.max(0, total - imported);
		var lastRun = status.last_run || (cfg.i18n && cfg.i18n.never) || 'Never';
		var $card = $('.aidedemodataimport-card[data-type="' + type + '"]');
		var $row = $('#aidedemodataimport-history tr[data-type="' + type + '"]');
		var pct = total > 0 ? Math.min(100, Math.round((imported / total) * 100)) : 0;

		var importedTpl = (cfg.i18n && cfg.i18n.alreadyImported) || 'Already imported: %1$d of %2$d';
		var remainingTpl = (cfg.i18n && cfg.i18n.remaining) || 'Remaining: %d';
		var lastTpl = (cfg.i18n && cfg.i18n.lastRun) || 'Last run: %s';

		$card.find('.aidedemodataimport-card__imported-value').text(String(imported));
		$card.find('.aidedemodataimport-card__remaining-value').text(String(remaining));
		$card.find('.aidedemodataimport-card__progress-fill').css('width', pct + '%');
		$card.toggleClass('has-imports', imported > 0);

		$card.find('.aidedemodataimport-card__imported').text(
			importedTpl.replace('%1$d', String(imported)).replace('%2$d', String(total))
		);
		$card.find('.aidedemodataimport-card__remaining').text(remainingTpl.replace('%d', String(remaining)));

		var lastText = lastTpl.replace('%s', lastRun);
		if (status.last_stats && (status.last_stats.imported || status.last_stats.skipped)) {
			var statsTpl = (cfg.i18n && cfg.i18n.lastStats) || 'imported %1$d, skipped %2$d';
			lastText +=
				' · ' +
				statsTpl
					.replace('%1$d', String(status.last_stats.imported || 0))
					.replace('%2$d', String(status.last_stats.skipped || 0));
		}
		$card.find('.aidedemodataimport-card__last-run').text(lastText);

		// Badge state
		var $badge = $card.find('.aidedemodataimport-card__badge');
		if (!$card.hasClass('is-disabled') && $badge.length) {
			$badge.removeClass('is-info is-success is-warning');
			if (imported >= total && total > 0) {
				$badge.addClass('is-success').text((cfg.i18n && cfg.i18n.badgeComplete) || 'Complete');
			} else if (imported > 0) {
				$badge.addClass('is-info').text((cfg.i18n && cfg.i18n.badgePartial) || 'Partial');
			} else {
				$badge.text((cfg.i18n && cfg.i18n.badgeNone) || 'Not imported');
			}
		}

		$row.find('.aidedemodataimport-history__imported strong').text(String(imported));
		$row.find('.aidedemodataimport-history__total').text(String(total));
		$row.find('.aidedemodataimport-history__remaining').text(String(remaining));
		$row.find('.aidedemodataimport-history__last-run').text(lastRun);
	}

	function refreshStatus(type) {
		return ajax('aidedemodataimport_get_status', { type: type }).done(function (res) {
			if (res && res.success) {
				applyStatus(type, res.data);
			}
		});
	}

	function runImport(type) {
		if (busy) {
			return;
		}

		var count = readCount(type);
		var confirmTpl =
			(cfg.i18n && cfg.i18n.confirmImportCount) ||
			'Import %d records? Existing demo items with the same ID will be skipped.';
		var confirmMsg = confirmTpl.replace('%d', String(count));

		if (!window.confirm(confirmMsg)) {
			return;
		}

		setBusy(true);
		logLine((cfg.i18n && cfg.i18n.importing) || 'Importing…');
		setProgress(0, 0);

		ajax('aidedemodataimport_start_import', { type: type, count: count })
			.done(function (res) {
				if (!res || !res.success) {
					logLine((res && res.data && res.data.message) || (cfg.i18n && cfg.i18n.error) || 'Error');
					setBusy(false);
					return;
				}

				var total = res.data.total || 0;
				logLine(res.data.message || '');
				setProgress(0, total);

				if (total === 0) {
					logLine((cfg.i18n && cfg.i18n.done) || 'Done.');
					refreshStatus(type).always(function () {
						setBusy(false);
					});
					return;
				}

				runBatches(type, 0, total, count, 0, 0);
			})
			.fail(function () {
				logLine((cfg.i18n && cfg.i18n.error) || 'Error');
				setBusy(false);
			});
	}

	function runBatches(type, offset, total, count, importedTotal, skippedTotal) {
		var limit = cfg.batchSize || 10;

		ajax('aidedemodataimport_run_batch', {
			type: type,
			offset: offset,
			limit: limit,
			count: count
		})
			.done(function (res) {
				if (!res || !res.success) {
					logLine((res && res.data && res.data.message) || (cfg.i18n && cfg.i18n.error) || 'Error');
					setBusy(false);
					return;
				}

				var data = res.data;
				importedTotal += data.imported || 0;
				skippedTotal += data.skipped || 0;

				if (data.errors && data.errors.length) {
					data.errors.forEach(function (err) {
						logLine(err);
					});
				}

				var next = data.next || offset + limit;
				var current = Math.min(next, total);
				setProgress(current, total);

				if (data.done) {
					logLine(
						((cfg.i18n && cfg.i18n.done) || 'Done.') +
							' imported=' +
							importedTotal +
							' skipped=' +
							skippedTotal
					);

					// Persist full-session totals, then refresh live history counts.
					ajax('aidedemodataimport_run_batch', {
						type: type,
						offset: total,
						limit: 1,
						count: count,
						session_imported: importedTotal,
						session_skipped: skippedTotal
					}).always(function () {
						refreshStatus(type).always(function () {
							setBusy(false);
						});
					});
					return;
				}

				runBatches(type, next, total, count, importedTotal, skippedTotal);
			})
			.fail(function () {
				logLine((cfg.i18n && cfg.i18n.error) || 'Error');
				setBusy(false);
			});
	}

	function runCleanup(type) {
		if (busy) {
			return;
		}
		if (!window.confirm((cfg.i18n && cfg.i18n.confirmCleanup) || 'Remove?')) {
			return;
		}

		setBusy(true);
		logLine((cfg.i18n && cfg.i18n.cleaning) || 'Removing…');

		ajax('aidedemodataimport_cleanup', { type: type })
			.done(function (res) {
				if (!res || !res.success) {
					logLine((res && res.data && res.data.message) || (cfg.i18n && cfg.i18n.error) || 'Error');
				} else {
					logLine(res.data.message || ((cfg.i18n && cfg.i18n.done) || 'Done.'));
					if (res.data.status) {
						applyStatus(type, res.data.status);
					}
				}
				refreshStatus(type).always(function () {
					setBusy(false);
				});
			})
			.fail(function () {
				logLine((cfg.i18n && cfg.i18n.error) || 'Error');
				setBusy(false);
			});
	}

	$(document).on('click', '.aidedemodataimport-import', function () {
		runImport($(this).data('type'));
	});

	$(document).on('click', '.aidedemodataimport-cleanup', function () {
		runCleanup($(this).data('type'));
	});

	$(document).on('click', '.aidedemodataimport-qty-all', function (e) {
		e.preventDefault();
		var type = $(this).data('type');
		var max = parseInt($(this).data('max'), 10) || 0;
		if (max > 0) {
			getQtyInput(type).val(max);
		}
	});

	$(document).on('change blur', '.aidedemodataimport-qty', function () {
		var $input = $(this);
		var max = parseInt($input.attr('max'), 10) || 0;
		var val = parseInt($input.val(), 10);
		if (!val || val < 1) {
			val = 1;
		}
		if (max > 0 && val > max) {
			val = max;
		}
		$input.val(val);
	});
})(jQuery);
