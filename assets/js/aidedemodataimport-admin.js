(function ($) {
	'use strict';

	var cfg = window.aidedemodataimportAdmin || {};
	var $progress = $('#aidedemodataimport-progress');
	var $fill = $progress.find('.aidedemodataimport-progress__fill');
	var $text = $progress.find('.aidedemodataimport-progress__text');
	var $live = $('#aidedemodataimport-live-log');
	var busy = false;
	var $modal = null;
	var modalResolver = null;

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

	function ensureModal() {
		if ($modal && $modal.length) {
			return $modal;
		}

		$modal = $(
			'<div class="aidedemodataimport-modal" id="aidedemodataimport-modal" hidden aria-hidden="true">' +
				'<div class="aidedemodataimport-modal__backdrop" data-modal-dismiss="1"></div>' +
				'<div class="aidedemodataimport-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="aidedemodataimport-modal-title" aria-describedby="aidedemodataimport-modal-message">' +
					'<div class="aidedemodataimport-modal__icon" aria-hidden="true">!</div>' +
					'<h2 class="aidedemodataimport-modal__title" id="aidedemodataimport-modal-title"></h2>' +
					'<p class="aidedemodataimport-modal__message" id="aidedemodataimport-modal-message"></p>' +
					'<div class="aidedemodataimport-modal__actions">' +
						'<button type="button" class="button aidedemodataimport-modal__cancel"></button>' +
						'<button type="button" class="button button-primary aidedemodataimport-modal__ok"></button>' +
					'</div>' +
				'</div>' +
			'</div>'
		);

		$('body').append($modal);

		$modal.on('click', '[data-modal-dismiss], .aidedemodataimport-modal__cancel', function (e) {
			e.preventDefault();
			closeConfirm(false);
		});

		$modal.on('click', '.aidedemodataimport-modal__ok', function (e) {
			e.preventDefault();
			closeConfirm(true);
		});

		$(document).on('keydown.aidedemodataimportModal', function (e) {
			if (!$modal.hasClass('is-open')) {
				return;
			}
			if (e.key === 'Escape') {
				e.preventDefault();
				closeConfirm(false);
			} else if (e.key === 'Enter') {
				e.preventDefault();
				closeConfirm(true);
			}
		});

		return $modal;
	}

	function closeConfirm(confirmed) {
		if (!$modal || !$modal.hasClass('is-open')) {
			return;
		}

		$modal.removeClass('is-open').attr({ hidden: true, 'aria-hidden': 'true' });
		$('body').removeClass('aidedemodataimport-modal-open');

		var resolve = modalResolver;
		modalResolver = null;
		if (typeof resolve === 'function') {
			resolve(!!confirmed);
		}
	}

	/**
	 * SweetAlert-style confirm without third-party libraries.
	 *
	 * @param {Object} options
	 * @return {Promise<boolean>}
	 */
	function confirmDialog(options) {
		options = options || {};
		ensureModal();

		var variant = options.variant === 'danger' ? 'danger' : 'confirm';
		var $icon = $modal.find('.aidedemodataimport-modal__icon');
		var $ok = $modal.find('.aidedemodataimport-modal__ok');

		$modal.find('.aidedemodataimport-modal__title').text(options.title || '');
		$modal.find('.aidedemodataimport-modal__message').text(options.message || '');
		$modal.find('.aidedemodataimport-modal__cancel').text(
			options.cancelText || (cfg.i18n && cfg.i18n.confirmCancel) || 'Cancel'
		);
		$ok.text(options.okText || (cfg.i18n && cfg.i18n.confirmOk) || 'OK')
			.toggleClass('is-danger', variant === 'danger')
			.toggleClass('button-primary', variant !== 'danger');

		$icon
			.removeClass('is-confirm is-danger')
			.addClass(variant === 'danger' ? 'is-danger' : 'is-confirm')
			.text(variant === 'danger' ? '×' : '?');

		return new Promise(function (resolve) {
			modalResolver = resolve;
			$modal.removeAttr('hidden').attr('aria-hidden', 'false').addClass('is-open');
			$('body').addClass('aidedemodataimport-modal-open');
			window.setTimeout(function () {
				$ok.trigger('focus');
			}, 20);
		});
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

		var $qty = getQtyInput(type);
		var $all = $card.find('.aidedemodataimport-qty-all');
		var qtyMax = Math.max(0, remaining);
		if ($qty.length) {
			$qty.attr('max', String(Math.max(1, qtyMax)));
			if (qtyMax < 1) {
				$qty.prop('disabled', true).val(1);
			} else {
				$qty.prop('disabled', false);
				var currentVal = parseInt($qty.val(), 10) || 1;
				if (currentVal > qtyMax) {
					$qty.val(qtyMax);
				}
			}
		}
		if ($all.length) {
			$all.attr('data-max', String(qtyMax)).prop('disabled', qtyMax < 1);
		}
		$card.find('.aidedemodataimport-card__qty-hint').text('/ ' + qtyMax + ' remaining');
		$card.find('.aidedemodataimport-import').prop('disabled', qtyMax < 1 || busy);
	}

	function refreshStatus(type) {
		return ajax('aidedemodataimport_get_status', { type: type }).done(function (res) {
			if (res && res.success) {
				applyStatus(type, res.data);
			}
		});
	}

	function startImport(type, count) {
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

				// Use server-capped total so batches stay aligned with pending remaining.
				runBatches(type, 0, total, total, 0, 0);
			})
			.fail(function () {
				logLine((cfg.i18n && cfg.i18n.error) || 'Error');
				setBusy(false);
			});
	}

	function runImport(type) {
		if (busy) {
			return;
		}

		var count = readCount(type);
		var confirmTpl =
			(cfg.i18n && cfg.i18n.confirmImportCount) ||
			'Import %d records?';
		var confirmMsg = confirmTpl.replace('%d', String(count));

		confirmDialog({
			title: (cfg.i18n && cfg.i18n.confirmTitle) || 'Confirm import',
			message: confirmMsg,
			okText: (cfg.i18n && cfg.i18n.confirmOk) || 'Import',
			cancelText: (cfg.i18n && cfg.i18n.confirmCancel) || 'Cancel',
			variant: 'confirm'
		}).then(function (ok) {
			if (ok) {
				startImport(type, count);
			}
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

	function startCleanup(type) {
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

	function runCleanup(type) {
		if (busy) {
			return;
		}

		confirmDialog({
			title: (cfg.i18n && cfg.i18n.cleanupTitle) || 'Confirm removal',
			message: (cfg.i18n && cfg.i18n.confirmCleanup) || 'Remove?',
			okText: (cfg.i18n && cfg.i18n.cleanupOk) || 'Remove',
			cancelText: (cfg.i18n && cfg.i18n.confirmCancel) || 'Cancel',
			variant: 'danger'
		}).then(function (ok) {
			if (ok) {
				startCleanup(type);
			}
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
