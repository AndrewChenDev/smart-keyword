(function ($) {
	'use strict';

	function switchTab(slug) {
		$('.skai-settings .nav-tab').each(function () {
			var $a = $(this);
			$a.toggleClass('nav-tab-active', $a.data('tab') === slug);
		});
		$('.skai-tab-pane').each(function () {
			var $p = $(this);
			$p.toggle($p.data('tab') === slug);
		});
		$('.skai-submit-wrap').toggle(slug !== 'usage');

		if (window.history && window.history.replaceState) {
			var url = new URL(window.location.href);
			url.searchParams.set('tab', slug);
			window.history.replaceState({}, '', url.toString());
		}
	}

	function selectIsCustom($select) {
		return $select.val() === '__custom__';
	}

	function showHideCustomInput($select) {
		var provider = $select.data('provider');
		var $input = $('.skai-model-custom[data-provider="' + provider + '"]');
		if (selectIsCustom($select)) {
			$input.show().trigger('focus');
		} else {
			$input.hide();
		}
	}

	function repopulateSelect($select, models, pricing, preferredValue) {
		var current = preferredValue || $select.val() || '';
		var provider = $select.data('provider');
		pricing = pricing || {};
		$select.empty();
		var matched = false;
		models.forEach(function (m) {
			var label = m;
			var $opt = $('<option>').attr('value', m);
			if (pricing[m]) {
				label = m + ' ' + pricing[m].label;
				$opt.attr('data-price-input',  pricing[m].input);
				$opt.attr('data-price-output', pricing[m].output);
			}
			$opt.text(label);
			if (m === current) {
				$opt.attr('selected', 'selected');
				matched = true;
			}
			$select.append($opt);
		});
		var $custom = $('<option>').attr('value', '__custom__').text('Custom…');
		if (!matched && current && current !== '') {
			$custom.attr('selected', 'selected');
			$('.skai-model-custom[data-provider="' + provider + '"]').val(current);
		}
		$select.append($custom);
		showHideCustomInput($select);
		updatePriceReadout($select);
	}

	function updatePriceReadout($select) {
		var provider = $select.data('provider');
		var $readout = $('.skai-model-price[data-provider="' + provider + '"]');
		if (!$readout.length) return;
		var $opt = $select.find('option:selected');
		var input  = $opt.attr('data-price-input');
		var output = $opt.attr('data-price-output');
		if (input && output && $opt.val() !== '__custom__') {
			$readout.text(SKAI_SETTINGS.i18n.price_known.replace('%s', formatPriceLabel(input, output)));
		} else {
			$readout.text(SKAI_SETTINGS.i18n.price_unknown);
		}
	}

	function formatUsdPerM(v) {
		v = parseFloat(v);
		if (!isFinite(v) || v <= 0) return '$0';
		var s = v.toFixed(4).replace(/0+$/, '').replace(/\.$/, '');
		return '$' + s;
	}

	function formatPriceLabel(input, output) {
		return formatUsdPerM(input) + '/M in | ' + formatUsdPerM(output) + '/M out';
	}

	function fetchModels(provider) {
		var $btn    = $('.skai-refresh-models[data-provider="' + provider + '"]');
		var $status = $('.skai-model-status[data-provider="' + provider + '"]');
		var $select = $('.skai-model-select[data-provider="' + provider + '"]');

		$btn.prop('disabled', true);
		$status.text(SKAI_SETTINGS.i18n.loading);

		$.post(SKAI_SETTINGS.ajaxurl, {
			action: 'skai_fetch_models',
			nonce: SKAI_SETTINGS.nonce,
			provider: provider
		}).done(function (res) {
			$btn.prop('disabled', false);
			if (res && res.success && res.data && Array.isArray(res.data.models)) {
				repopulateSelect($select, res.data.models, res.data.pricing || {}, $select.val());
				$status.text(SKAI_SETTINGS.i18n.loaded.replace('%d', res.data.models.length));
			} else if (res && res.data && res.data.code === 'no_key') {
				$status.text(SKAI_SETTINGS.i18n.no_key);
			} else {
				$status.text((res && res.data && res.data.message) || SKAI_SETTINGS.i18n.error);
			}
		}).fail(function () {
			$btn.prop('disabled', false);
			$status.text(SKAI_SETTINGS.i18n.error);
		});
	}

	$(function () {
		$('.skai-settings .nav-tab').on('click', function (e) {
			e.preventDefault();
			switchTab($(this).data('tab'));
		});

		$('.skai-model-select').each(function () {
			showHideCustomInput($(this));
		}).on('change', function () {
			showHideCustomInput($(this));
			updatePriceReadout($(this));
		});

		$('.skai-refresh-models').on('click', function () {
			fetchModels($(this).data('provider'));
		});

		// On form submit, if a select is on "__custom__", copy the typed value into the select's value
		// so WordPress saves the custom model id into {provider}_model.
		$('form').on('submit', function () {
			$('.skai-model-select').each(function () {
				var $select = $(this);
				if (selectIsCustom($select)) {
					var provider = $select.data('provider');
					var custom = ($('.skai-model-custom[data-provider="' + provider + '"]').val() || '').trim();
					if (custom !== '') {
						// Insert as option so the select submits this value.
						$select.append($('<option>').attr('value', custom).text(custom).attr('selected', 'selected'));
					}
				}
			});
		});

		$('.skai-reset-month').on('click', function (e) {
			if (!window.confirm(SKAI_SETTINGS.i18n.confirm_month)) {
				e.preventDefault();
			}
		});

		$('.skai-reset-all').on('click', function (e) {
			if (!window.confirm(SKAI_SETTINGS.i18n.confirm_all)) {
				e.preventDefault();
			}
		});

		$(document).on('click', '.skai-toggle', function () {
			var $btn = $(this);
			var expanded = $btn.attr('aria-expanded') === 'true';
			var nextExpanded = !expanded;
			$btn.attr('aria-expanded', nextExpanded ? 'true' : 'false');
			$btn.text((nextExpanded ? '▼ ' : '▶ ') + $btn.data('label'));
			$btn.closest('tr').next('.skai-model-rows').attr('hidden', nextExpanded ? null : 'hidden');
		});
	});
})(jQuery);
