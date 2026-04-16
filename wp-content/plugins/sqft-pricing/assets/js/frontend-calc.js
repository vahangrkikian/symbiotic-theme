/**
 * Sqft Pricing — Frontend Product Calculator
 *
 * Renders option groups dynamically and calculates prices using the formula engine.
 * Price is computed client-side for display, then validated server-side at cart time.
 */

(function ($) {
	'use strict';

	if (typeof sqftCalc === 'undefined') return;

	var config = sqftCalc;
	var variables = config.variables || [];
	var selections = {}; // slug -> selected item object
	var calculatedPrice = 0;

	$(document).ready(function () {
		if (!$('#sqft-calculator').length || !variables.length) return;

		initSelections();
		renderOptions();
		calculatePrice();
	});

	/**
	 * Set initial selections to default items.
	 * For filtered variables, pick the first visible default.
	 */
	function initSelections() {
		// First pass: set defaults without filter consideration.
		variables.forEach(function (v) {
			var defaultItem = v.items.find(function (it) { return it.isDefault && !it.isHidden; });
			if (!defaultItem) defaultItem = v.items.find(function (it) { return !it.isHidden; });
			if (defaultItem) {
				selections[v.slug] = defaultItem;
			}
		});

		// Second pass: re-validate selections against filters.
		autoSelectFiltered();
	}

	/**
	 * Auto-select first visible item for variables whose current selection
	 * is no longer visible due to filter changes. Handles hidden (auto) variables.
	 */
	function autoSelectFiltered() {
		var changed = false;
		variables.forEach(function (v) {
			var visibleItems = getVisibleItems(v);
			var current = selections[v.slug];

			// If current selection is not in visible items, auto-select first visible.
			if (current && visibleItems.length) {
				var stillVisible = visibleItems.some(function (it) { return it.id === current.id; });
				if (!stillVisible) {
					// Prefer default among visible, else first visible.
					var newDefault = visibleItems.find(function (it) { return it.isDefault; });
					selections[v.slug] = newDefault || visibleItems[0];
					changed = true;
				}
			} else if (!current && visibleItems.length) {
				selections[v.slug] = visibleItems[0];
				changed = true;
			}
		});

		// If any selection changed, run again (cascading filters).
		if (changed) autoSelectFiltered();
	}

	/**
	 * Render all option groups into the calculator.
	 */
	function renderOptions() {
		// Auto-select filtered items before rendering.
		autoSelectFiltered();

		var $container = $('#sqft-calc-options');
		$container.empty();

		variables.forEach(function (v) {
			// Filter visible items based on dependencies.
			var visibleItems = getVisibleItems(v);

			// Hidden variables (like Print_Color) — don't render but keep in selections.
			if (v.isHidden) return;

			// If no visible items, skip rendering.
			if (!visibleItems.length) return;

			var $group = $('<div class="sqft-opt-group" data-slug="' + esc(v.slug) + '">');
			$group.append('<label class="sqft-opt-label">' + esc(v.label) + '</label>');

			var $options;

			switch (v.type) {
				case 'card':
				case 'material_card':
					$options = renderCardOptions(v, visibleItems);
					break;

				case 'pill':
					$options = renderPillOptions(v, visibleItems);
					break;

				case 'turnaround':
					$options = renderTurnaroundOptions(v, visibleItems);
					break;

				case 'quantity_tiers':
					$options = renderQuantityOptions(v, visibleItems);
					break;

				case 'size':
					$options = renderSizeOptions(v, visibleItems);
					break;

				case 'radio':
					$options = renderRadioOptions(v, visibleItems);
					break;

				case 'list':
				default:
					$options = renderDropdownOptions(v, visibleItems);
					break;
			}

			$group.append($options);
			$container.append($group);
		});
	}

	/**
	 * Get visible items for a variable, applying dependency filters.
	 *
	 * Filter logic (matches Axiomprint):
	 * - Filters are grouped by variableSlug
	 * - Within a group: OR logic (at least one filter must match)
	 * - Across groups: AND logic (all groups must pass)
	 *
	 * Example: if an item has filters [Paper=14PT_C2S, Paper=16PT_C2S, Shape=Rectangle]
	 * → It's visible when (Paper is 14PT_C2S OR 16PT_C2S) AND (Shape is Rectangle)
	 */
	function getVisibleItems(variable) {
		// Skip hidden variables entirely.
		if (variable.isHidden) return [];

		return variable.items.filter(function (item) {
			if (item.isHidden) return false;

			// If no filters, always visible.
			if (!item.filters || !item.filters.length) return true;

			// Group filters by variableSlug.
			var groups = {};
			item.filters.forEach(function (f) {
				var slug = f.variableSlug;
				if (!slug) return;
				if (!groups[slug]) groups[slug] = [];
				groups[slug].push(f);
			});

			// AND across groups: every group must have at least one match.
			for (var slug in groups) {
				var selected = selections[slug];
				if (!selected) return false;

				// OR within group: at least one filter must match.
				var groupMatch = groups[slug].some(function (f) {
					// Match by item ID (most reliable).
					if (f.itemId && selected.id) {
						return selected.id === f.itemId;
					}
					// Fallback: match by label.
					if (f.itemLabel && selected.label) {
						return selected.label === f.itemLabel;
					}
					return false;
				});

				if (!groupMatch) return false;
			}

			return true;
		});
	}

	// ─── Renderers ──────────────────────────────────────────────────────────

	function renderDropdownOptions(v, items) {
		var $wrap = $('<div class="sqft-opt-dropdown">');
		var $select = $('<select class="sqft-select" data-slug="' + esc(v.slug) + '">');

		items.forEach(function (item) {
			var selected = (selections[v.slug] && selections[v.slug].id === item.id) ? ' selected' : '';
			$select.append('<option value="' + item.id + '"' + selected + '>' + esc(item.label) + '</option>');
		});

		$select.on('change', function () {
			var itemId = parseInt($(this).val(), 10);
			var item = items.find(function (it) { return it.id === itemId; });
			if (item) onSelect(v.slug, item);
		});

		$wrap.append($select);
		return $wrap;
	}

	function renderRadioOptions(v, items) {
		var $wrap = $('<div class="sqft-opt-radios">');

		items.forEach(function (item) {
			var active = (selections[v.slug] && selections[v.slug].id === item.id) ? ' active' : '';
			var $label = $('<label class="sqft-radio-option' + active + '">');
			var $input = $('<input type="radio" name="sqft_opt_' + esc(v.slug) + '" value="' + item.id + '"' +
				(active ? ' checked' : '') + '>');

			$label.append($input);
			$label.append('<span class="sqft-radio-text">' + esc(item.label) + '</span>');

			if (item.config && item.config.image_url) {
				$label.prepend('<img src="' + esc(item.config.image_url) + '" class="sqft-radio-img" alt="">');
			}

			$input.on('change', function () {
				$wrap.find('.sqft-radio-option').removeClass('active');
				$label.addClass('active');
				onSelect(v.slug, item);
			});

			$wrap.append($label);
		});

		return $wrap;
	}

	function renderCardOptions(v, items) {
		var $wrap = $('<div class="sqft-opt-cards">');

		items.forEach(function (item) {
			var active = (selections[v.slug] && selections[v.slug].id === item.id) ? ' active' : '';
			var $card = $('<div class="sqft-card' + active + '" data-item-id="' + item.id + '">');

			if (item.config && item.config.image_url) {
				$card.append('<div class="sqft-card-img"><img src="' + esc(item.config.image_url) + '" alt=""></div>');
			}

			$card.append('<div class="sqft-card-label">' + esc(item.label) + '</div>');

			if (item.base > 0) {
				$card.append('<div class="sqft-card-price">+' + formatPrice(item.base) + '</div>');
			}

			$card.on('click', function () {
				$wrap.find('.sqft-card').removeClass('active');
				$card.addClass('active');
				onSelect(v.slug, item);
			});

			$wrap.append($card);
		});

		return $wrap;
	}

	function renderPillOptions(v, items) {
		var $wrap = $('<div class="sqft-opt-pills">');

		items.forEach(function (item) {
			var active = (selections[v.slug] && selections[v.slug].id === item.id) ? ' active' : '';
			var $pill = $('<button type="button" class="sqft-pill' + active + '">');
			$pill.text(item.label);

			$pill.on('click', function (e) {
				e.preventDefault();
				$wrap.find('.sqft-pill').removeClass('active');
				$pill.addClass('active');
				onSelect(v.slug, item);
			});

			$wrap.append($pill);
		});

		return $wrap;
	}

	function renderTurnaroundOptions(v, items) {
		var $wrap = $('<div class="sqft-opt-turnaround">');

		items.forEach(function (item) {
			var active = (selections[v.slug] && selections[v.slug].id === item.id) ? ' active' : '';
			var dayCount = item.config ? (item.config.day_count || '') : '';
			var dayLabel = dayCount ? dayCount + (dayCount === '1' ? ' day' : ' days') : '';

			var $btn = $('<button type="button" class="sqft-turnaround-btn' + active + '">');
			$btn.append('<span class="sqft-ta-label">' + esc(item.label) + '</span>');
			if (dayLabel) {
				$btn.append('<span class="sqft-ta-days">' + esc(dayLabel) + '</span>');
			}
			$btn.append('<span class="sqft-ta-price" data-item-id="' + item.id + '"></span>');

			$btn.on('click', function (e) {
				e.preventDefault();
				$wrap.find('.sqft-turnaround-btn').removeClass('active');
				$btn.addClass('active');
				onSelect(v.slug, item);
			});

			$wrap.append($btn);
		});

		return $wrap;
	}

	function renderQuantityOptions(v, items) {
		var $wrap = $('<div class="sqft-opt-quantity">');
		var $select = $('<select class="sqft-select sqft-qty-select" data-slug="' + esc(v.slug) + '">');

		items.forEach(function (item) {
			var selected = (selections[v.slug] && selections[v.slug].id === item.id) ? ' selected' : '';
			$select.append('<option value="' + item.id + '"' + selected + '>' +
				esc(item.label) + '</option>');
		});

		$select.on('change', function () {
			var itemId = parseInt($(this).val(), 10);
			var item = items.find(function (it) { return it.id === itemId; });
			if (item) onSelect(v.slug, item);
		});

		$wrap.append($select);
		return $wrap;
	}

	function renderSizeOptions(v, items) {
		var $wrap = $('<div class="sqft-opt-size">');

		// Dropdown for preset sizes.
		var $select = $('<select class="sqft-select sqft-size-select" data-slug="' + esc(v.slug) + '">');

		items.forEach(function (item) {
			var selected = (selections[v.slug] && selections[v.slug].id === item.id) ? ' selected' : '';
			$select.append('<option value="' + item.id + '"' + selected + ' data-width="' +
				(item.config.width || '') + '" data-height="' + (item.config.height || '') + '">' +
				esc(item.label) + '</option>');
		});

		$select.on('change', function () {
			var itemId = parseInt($(this).val(), 10);
			var item = items.find(function (it) { return it.id === itemId; });
			if (item) onSelect(v.slug, item);
		});

		$wrap.append($select);

		// Custom size inputs (if configured).
		if (v.config && v.config.min_width) {
			var $custom = $('<div class="sqft-custom-size" style="display:none;">');
			$custom.append('<input type="number" class="sqft-custom-w" placeholder="Width" step="0.01" ' +
				'min="' + (v.config.min_width || 1) + '" max="' + (v.config.max_width || 100) + '">');
			$custom.append('<span> × </span>');
			$custom.append('<input type="number" class="sqft-custom-h" placeholder="Height" step="0.01" ' +
				'min="' + (v.config.min_height || 1) + '" max="' + (v.config.max_height || 100) + '">');
			$custom.append('<span class="sqft-size-metric">' + esc(v.config.metric || 'inch') + '</span>');
			$wrap.append($custom);
		}

		return $wrap;
	}

	// ─── Selection Handler ──────────────────────────────────────────────────

	function onSelect(slug, item) {
		selections[slug] = item;

		// Re-render to update filtered options.
		renderOptions();

		// Recalculate price.
		calculatePrice();

		// Update hidden form inputs.
		updateFormInputs();
	}

	// ─── Price Calculation ──────────────────────────────────────────────────

	function calculatePrice() {
		var formula = config.formula;
		if (!formula) {
			$('#sqft-price-display').html('<span class="sqft-no-price">Configure pricing formula in admin.</span>');
			return;
		}

		// Build substitution map.
		var subs = {};
		for (var slug in selections) {
			var item = selections[slug];
			subs[slug] = item.value;
			subs[slug + '$base'] = item.base;

			// Size-specific.
			if (slug.toLowerCase() === 'size' && item.config) {
				subs['Size$w'] = parseFloat(item.config.width) || item.value;
				subs['Size$h'] = parseFloat(item.config.height) || 0;
			}
		}

		// Quantity from the quantity variable.
		var qtyItem = selections['quantity'] || selections['Quantity'];
		if (qtyItem) {
			subs['Quantity'] = parseFloat(qtyItem.label) || qtyItem.value;
			subs['Quantity$base'] = qtyItem.base;
		}

		// Versions count (default 1).
		subs['$versionsCount'] = 1;

		// Substitute into formula.
		var expr = formula;

		// Replace $versionsCount.
		expr = expr.replace(/\$versionsCount/g, '*0 + ' + subs['$versionsCount']);

		// Sort keys by length (longest first) to prevent partial matches.
		var keys = Object.keys(subs).sort(function (a, b) { return b.length - a.length; });
		keys.forEach(function (key) {
			if (key === '$versionsCount') return;
			// Escape $ for regex.
			var escaped = key.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&');
			expr = expr.replace(new RegExp(escaped, 'g'), String(subs[key]));
		});

		// Evaluate.
		var price;
		try {
			price = safeEval(expr);
		} catch (e) {
			price = 0;
		}

		// Apply minimum.
		var minPrice = parseFloat(config.minPrice) || 0;
		if (minPrice > 0 && price < minPrice) {
			price = minPrice;
		}

		price = Math.round(price * 100) / 100;
		calculatedPrice = price;

		// Per-unit price.
		var qty = subs['Quantity'] || 1;
		var perUnit = qty > 1 ? Math.round((price / qty) * 100) / 100 : price;

		// Update display.
		var $display = $('#sqft-price-display');
		var html = '<div class="sqft-price-total">';
		html += '<span class="sqft-price-label">' + config.i18n.total + ':</span>';
		html += '<span class="sqft-price-amount">' + formatPrice(price) + '</span>';
		html += '</div>';

		if (qty > 1) {
			html += '<div class="sqft-price-per-unit">';
			html += formatPrice(perUnit) + ' ' + config.i18n.perUnit;
			html += '</div>';
		}

		// Turnaround info.
		var taItem = selections['turnaround'] || selections['Turnaround'];
		if (taItem && taItem.config && taItem.config.day_count) {
			var days = parseInt(taItem.config.day_count, 10);
			var readyDate = new Date();
			readyDate.setDate(readyDate.getDate() + days);
			var dateStr = readyDate.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
			html += '<div class="sqft-price-eta">' + config.i18n.estimatedAt + ': ' + dateStr + '</div>';
		}

		$display.html(html);

		// Update turnaround button prices.
		updateTurnaroundPrices(subs);

		// Update hidden input.
		$('#sqft-calculated-price-input').val(price);
	}

	/**
	 * Calculate and display price on each turnaround button.
	 */
	function updateTurnaroundPrices(baseSubs) {
		var taVar = variables.find(function (v) {
			return v.slug === 'turnaround' || v.slug === 'Turnaround';
		});
		if (!taVar) return;

		taVar.items.forEach(function (item) {
			if (item.isHidden) return;

			// Temporarily substitute this turnaround's values.
			var tempSubs = $.extend({}, baseSubs);
			var taSlug = taVar.slug;
			tempSubs[taSlug] = item.value;
			tempSubs[taSlug + '$base'] = item.base;

			var expr = config.formula;
			expr = expr.replace(/\$versionsCount/g, '*0 + 1');

			var keys = Object.keys(tempSubs).sort(function (a, b) { return b.length - a.length; });
			keys.forEach(function (key) {
				var escaped = key.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&');
				expr = expr.replace(new RegExp(escaped, 'g'), String(tempSubs[key]));
			});

			var price;
			try { price = safeEval(expr); } catch (e) { price = 0; }

			var minPrice = parseFloat(config.minPrice) || 0;
			if (minPrice > 0 && price < minPrice) price = minPrice;
			price = Math.round(price * 100) / 100;

			$('.sqft-ta-price[data-item-id="' + item.id + '"]').text(formatPrice(price));
		});
	}

	/**
	 * Update hidden form inputs for cart submission.
	 */
	function updateFormInputs() {
		var data = {};
		for (var slug in selections) {
			data[slug] = {
				id: selections[slug].id,
				label: selections[slug].label,
				value: selections[slug].value,
				base: selections[slug].base,
				config: selections[slug].config || {}
			};
		}
		$('#sqft-selections-input').val(JSON.stringify(data));
		$('#sqft-calculated-price-input').val(calculatedPrice);
	}

	// ─── Safe Math Evaluator ────────────────────────────────────────────────

	function safeEval(expr) {
		expr = expr.trim();

		// Replace math functions.
		expr = expr.replace(/floor/gi, 'Math.floor');
		expr = expr.replace(/round/gi, 'Math.round');
		expr = expr.replace(/sqrt/gi, 'Math.sqrt');
		expr = expr.replace(/ceil/gi, 'Math.ceil');
		expr = expr.replace(/abs/gi, 'Math.abs');

		// Security: only allow safe characters.
		if (/[^0-9+\-*\/().,%\sMathfloorundceilsqrtab]/i.test(
			expr.replace(/Math\.(floor|round|sqrt|ceil|abs)/g, '')
		)) {
			return 0;
		}

		// Use Function constructor (safer than eval, no access to scope).
		try {
			var fn = new Function('return (' + expr + ');');
			var result = fn();
			return isFinite(result) ? result : 0;
		} catch (e) {
			return 0;
		}
	}

	// ─── Helpers ────────────────────────────────────────────────────────────

	function formatPrice(amount) {
		var sym = config.currencySymbol;
		var pos = config.currencyPos || 'left';
		var formatted = parseFloat(amount).toFixed(2);

		switch (pos) {
			case 'left': return sym + formatted;
			case 'right': return formatted + sym;
			case 'left_space': return sym + ' ' + formatted;
			case 'right_space': return formatted + ' ' + sym;
			default: return sym + formatted;
		}
	}

	function esc(s) {
		var d = document.createElement('div');
		d.appendChild(document.createTextNode(String(s)));
		return d.innerHTML;
	}

})(jQuery);
