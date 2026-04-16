/**
 * Sqft Pricing — Admin Options Builder
 *
 * Handles the repeatable variable/item builder in the product edit meta box.
 */

(function ($) {
	'use strict';

	if (typeof sqftAdmin === 'undefined') return;

	var variableIndex = 0;
	var itemIndexes = {};

	$(document).ready(function () {
		initIndexes();
		initTabs();
		initSortable();
		initEvents();
		initAutoSlug();
	});

	/**
	 * Count existing variables/items to set starting indexes.
	 */
	function initIndexes() {
		$('.sqft-variable-row').each(function () {
			var idx = parseInt($(this).data('index'), 10);
			if (idx >= variableIndex) variableIndex = idx + 1;

			var varIdx = idx;
			itemIndexes[varIdx] = 0;
			$(this).find('.sqft-item-row').each(function () {
				var iIdx = parseInt($(this).data('item-index'), 10);
				if (iIdx >= itemIndexes[varIdx]) itemIndexes[varIdx] = iIdx + 1;
			});
		});
	}

	/**
	 * Tab switching.
	 */
	function initTabs() {
		$('.sqft-tab').on('click', function () {
			var tab = $(this).data('tab');
			$('.sqft-tab').removeClass('active');
			$(this).addClass('active');
			$('.sqft-tab-content').removeClass('active');
			$('.sqft-tab-content[data-tab="' + tab + '"]').addClass('active');

			if (tab === 'preview') {
				buildPreviewSelectors();
			}
		});
	}

	/**
	 * Make variables and items sortable.
	 */
	function initSortable() {
		$('#sqft-variables-list').sortable({
			handle: '.sqft-drag-handle',
			placeholder: 'sqft-sort-placeholder',
			tolerance: 'pointer',
			update: reindexVariables
		});

		$('.sqft-items-list').sortable({
			handle: '.sqft-item-drag',
			items: '.sqft-item-row',
			placeholder: 'sqft-sort-placeholder',
			tolerance: 'pointer'
		});
	}

	/**
	 * Bind all events.
	 */
	function initEvents() {
		// Add variable.
		$('#sqft-add-variable').on('click', addVariable);

		// Add item (delegated).
		$(document).on('click', '.sqft-add-item', function () {
			var varIdx = parseInt($(this).data('var-index'), 10);
			addItem(varIdx, $(this).closest('.sqft-variable-row'));
		});

		// Remove variable.
		$(document).on('click', '.sqft-remove-variable', function () {
			if (confirm(sqftAdmin.i18n.removeVariable)) {
				$(this).closest('.sqft-variable-row').slideUp(200, function () { $(this).remove(); });
			}
		});

		// Remove item.
		$(document).on('click', '.sqft-remove-item', function () {
			$(this).closest('.sqft-item-row').slideUp(150, function () { $(this).remove(); });
		});

		// Variable type change — show/hide config sections.
		$(document).on('change', '.sqft-var-type', function () {
			var type = $(this).val();
			var $row = $(this).closest('.sqft-variable-row');
			$row.find('.sqft-config-row').hide();
			$row.find('.sqft-config-' + type.replace('_tiers', '')).show();
		});

		// Toggle filters.
		$(document).on('click', '.sqft-toggle-filters', function () {
			$(this).closest('.sqft-variable-row').find('.sqft-item-filters').toggle();
		});

		// Add filter.
		$(document).on('click', '.sqft-add-filter', function () {
			var $filters = $(this).closest('.sqft-item-filters');
			var $row = $(this).closest('.sqft-item-row');
			var varIdx = $row.closest('.sqft-variable-row').data('index');
			var itemIdx = $row.data('item-index');
			var fIdx = $filters.find('.sqft-filter-row').length;
			var prefix = 'sqft_var[' + varIdx + '][items][' + itemIdx + '][filters][' + fIdx + ']';

			var html = '<div class="sqft-filter-row">' +
				'<span>Show when</span>' +
				'<input type="text" name="' + prefix + '[depends_on_variable_slug]" placeholder="variable slug" class="small-text">' +
				'<span>=</span>' +
				'<input type="text" name="' + prefix + '[depends_on_item_label]" placeholder="item label" class="small-text">' +
				'<button type="button" class="sqft-remove-filter button-link">&times;</button>' +
				'</div>';
			$(this).before(html);
		});

		// Remove filter.
		$(document).on('click', '.sqft-remove-filter', function () {
			$(this).closest('.sqft-filter-row').remove();
		});

		// Default radio — sync hidden input.
		$(document).on('change', 'input[name^="sqft_var"][name$="[default_item]"]', function () {
			var $varRow = $(this).closest('.sqft-variable-row');
			$varRow.find('.sqft-item-default-hidden').val('0');
			$(this).closest('.sqft-item-row').find('.sqft-item-default-hidden').val('1');
		});

		// Image upload.
		$(document).on('click', '.sqft-upload-image', function (e) {
			e.preventDefault();
			var $btn = $(this);
			var $wrap = $btn.closest('.sqft-item-image-wrap');
			var $input = $wrap.find('.sqft-item-image-input');

			var frame = wp.media({ title: 'Select Image', multiple: false, library: { type: 'image' } });
			frame.on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();
				$input.val(attachment.url);
				$wrap.find('.sqft-item-image-thumb').remove();
				$btn.before('<img src="' + attachment.url + '" class="sqft-item-image-thumb" width="30" height="30">');
			});
			frame.open();
		});

		// Test formula.
		$('#sqft-test-formula').on('click', testFormula);
	}

	/**
	 * Auto-generate slug from label.
	 */
	function initAutoSlug() {
		$(document).on('blur', '.sqft-var-label', function () {
			var $slug = $(this).closest('.sqft-variable-header-fields').find('.sqft-var-slug');
			if (!$slug.val()) {
				$slug.val($(this).val().toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, ''));
			}
		});
	}

	/**
	 * Add a new variable group.
	 */
	function addVariable() {
		var idx = variableIndex++;
		itemIndexes[idx] = 0;

		var types = sqftAdmin.variableTypes;
		var typeOptions = '';
		for (var key in types) {
			typeOptions += '<option value="' + key + '">' + types[key] + '</option>';
		}

		var html = '<div class="sqft-variable-row" data-index="' + idx + '">' +
			'<div class="sqft-variable-header">' +
			'<span class="sqft-drag-handle dashicons dashicons-move"></span>' +
			'<div class="sqft-variable-header-fields">' +
			'<input type="text" name="sqft_var[' + idx + '][label]" placeholder="Option Name" class="sqft-var-label">' +
			'<input type="text" name="sqft_var[' + idx + '][slug]" placeholder="slug" class="sqft-var-slug">' +
			'<select name="sqft_var[' + idx + '][var_type]" class="sqft-var-type">' + typeOptions + '</select>' +
			'</div>' +
			'<button type="button" class="sqft-remove-variable">&times;</button>' +
			'</div>' +
			'<div class="sqft-variable-config">' +
			'<div class="sqft-config-row sqft-config-size" style="display:none;">' +
			'<input type="number" name="sqft_var[' + idx + '][config][min_width]" placeholder="Min W" step="0.01" class="small-text">' +
			'<input type="number" name="sqft_var[' + idx + '][config][max_width]" placeholder="Max W" step="0.01" class="small-text">' +
			'<input type="number" name="sqft_var[' + idx + '][config][min_height]" placeholder="Min H" step="0.01" class="small-text">' +
			'<input type="number" name="sqft_var[' + idx + '][config][max_height]" placeholder="Max H" step="0.01" class="small-text">' +
			'<select name="sqft_var[' + idx + '][config][metric]" class="small-text">' +
			'<option value="inch">Inches</option><option value="cm">CM</option><option value="mm">MM</option>' +
			'</select>' +
			'</div>' +
			'<div class="sqft-config-row sqft-config-turnaround" style="display:none;">' +
			'<p class="description">Turnaround: Value = multiplier (1.0, 1.2), Base Cost = flat fee.</p>' +
			'</div>' +
			'<div class="sqft-config-row sqft-config-quantity" style="display:none;">' +
			'<p class="description">Quantity: Label = qty, Base Cost = per-unit rate at this tier.</p>' +
			'</div>' +
			'</div>' +
			'<div class="sqft-items-list" data-var-index="' + idx + '">' +
			'<div class="sqft-items-header">' +
			'<span class="col-label">Choice Label</span>' +
			'<span class="col-value">Value</span>' +
			'<span class="col-base">Base Cost</span>' +
			'<span class="col-default">Default</span>' +
			'<span class="col-hidden">Hidden</span>' +
			'<span class="col-image">Image</span>' +
			'<span class="col-actions"></span>' +
			'</div></div>' +
			'<button type="button" class="button sqft-add-item" data-var-index="' + idx + '">+ Add Choice</button>' +
			'<div class="sqft-filters-toggle"><button type="button" class="button-link sqft-toggle-filters">Show/Hide Dependency Filters</button></div>' +
			'</div>';

		$('#sqft-variables-list').append(html);

		// Make the new items list sortable.
		$('.sqft-items-list[data-var-index="' + idx + '"]').sortable({
			handle: '.sqft-item-drag',
			items: '.sqft-item-row',
			placeholder: 'sqft-sort-placeholder',
			tolerance: 'pointer'
		});
	}

	/**
	 * Add a new item to a variable.
	 */
	function addItem(varIdx, $varRow) {
		if (!itemIndexes[varIdx]) itemIndexes[varIdx] = 0;
		var iIdx = itemIndexes[varIdx]++;
		var prefix = 'sqft_var[' + varIdx + '][items][' + iIdx + ']';

		var html = '<div class="sqft-item-row" data-item-index="' + iIdx + '">' +
			'<span class="sqft-item-drag dashicons dashicons-move"></span>' +
			'<input type="text" name="' + prefix + '[label]" placeholder="Label" class="sqft-item-label">' +
			'<input type="number" name="' + prefix + '[value_numeric]" step="0.000001" class="sqft-item-value" placeholder="0" value="0">' +
			'<input type="number" name="' + prefix + '[base_cost]" step="0.01" class="sqft-item-base" placeholder="0" value="0">' +
			'<label class="sqft-item-default-wrap"><input type="radio" name="sqft_var[' + varIdx + '][default_item]" value="' + iIdx + '"></label>' +
			'<label class="sqft-item-hidden-wrap"><input type="checkbox" name="' + prefix + '[is_hidden]" value="1"></label>' +
			'<div class="sqft-item-image-wrap">' +
			'<input type="hidden" name="' + prefix + '[config][image_url]" class="sqft-item-image-input">' +
			'<button type="button" class="button-link sqft-upload-image" title="Upload"><span class="dashicons dashicons-format-image"></span></button>' +
			'</div>' +
			'<input type="hidden" name="' + prefix + '[config][width]" class="sqft-item-width">' +
			'<input type="hidden" name="' + prefix + '[config][height]" class="sqft-item-height">' +
			'<input type="hidden" name="' + prefix + '[config][day_count]" class="sqft-item-daycount">' +
			'<input type="hidden" name="' + prefix + '[is_default]" value="0" class="sqft-item-default-hidden">' +
			'<button type="button" class="sqft-remove-item">&times;</button>' +
			'<div class="sqft-item-filters" style="display:none;">' +
			'<button type="button" class="button-link sqft-add-filter">+ Add Filter</button>' +
			'</div></div>';

		$varRow.find('.sqft-items-list').append(html);
	}

	/**
	 * Reindex variables after sort.
	 */
	function reindexVariables() {
		// Sort order is determined by DOM position; form names carry the index.
		// Since WP processes by the posted array keys, order is implicit.
	}

	/**
	 * Build preview selectors from current form data.
	 */
	function buildPreviewSelectors() {
		var $container = $('#sqft-preview-selectors');
		$container.empty();

		$('.sqft-variable-row').each(function () {
			var label = $(this).find('.sqft-var-label').val();
			var slug = $(this).find('.sqft-var-slug').val();
			if (!label || !slug) return;

			var html = '<div class="sqft-preview-var">';
			html += '<label><strong>' + escHtml(label) + '</strong> (' + escHtml(slug) + ')</label>';
			html += '<select class="sqft-preview-select" data-slug="' + escAttr(slug) + '">';

			$(this).find('.sqft-item-row').each(function () {
				var itemLabel = $(this).find('.sqft-item-label').val();
				var itemValue = $(this).find('.sqft-item-value').val();
				var itemBase = $(this).find('.sqft-item-base').val();
				var isDefault = $(this).find('.sqft-item-default-hidden').val() === '1';
				var width = $(this).find('.sqft-item-width').val() || '';
				var height = $(this).find('.sqft-item-height').val() || '';

				html += '<option value="' + escAttr(itemValue) + '" data-base="' + escAttr(itemBase) + '"';
				html += ' data-width="' + escAttr(width) + '" data-height="' + escAttr(height) + '"';
				if (isDefault) html += ' selected';
				html += '>' + escHtml(itemLabel) + ' (val=' + itemValue + ', base=' + itemBase + ')</option>';
			});

			html += '</select></div>';
			$container.append(html);
		});
	}

	/**
	 * Test formula with current preview selections.
	 */
	function testFormula() {
		var formula = $('#sqft-formula').val();
		if (!formula) {
			$('#sqft-preview-output').html('<span style="color:#d63638;">No formula set.</span>');
			return;
		}

		var values = {};
		$('.sqft-preview-select').each(function () {
			var slug = $(this).data('slug');
			var $opt = $(this).find(':selected');

			values[slug] = {
				value: parseFloat($opt.val()) || 0,
				base: parseFloat($opt.data('base')) || 0,
				config: {
					width: parseFloat($opt.data('width')) || 0,
					height: parseFloat($opt.data('height')) || 0
				}
			};
		});

		$('#sqft-test-formula').prop('disabled', true).text(sqftAdmin.i18n.previewCalc);

		$.post(sqftAdmin.ajaxUrl, {
			action: 'sqft_evaluate_formula',
			nonce: sqftAdmin.nonce,
			formula: formula,
			values: JSON.stringify(values),
			min_price: $('#sqft-min-price').val() || 0
		}, function (response) {
			$('#sqft-test-formula').prop('disabled', false).text('Calculate Price');

			if (response.success) {
				var d = response.data;
				var html = '<div class="preview-price">$' + parseFloat(d.price).toFixed(2) + '</div>';
				if (d.breakdown) {
					html += '<div class="preview-breakdown">';
					html += 'Expression: ' + escHtml(d.breakdown.expression || '') + '<br>';
					html += 'Raw result: ' + (d.breakdown.raw_result || 0);
					if (d.breakdown.floor_applied) html += ' (min price applied)';
					html += '</div>';
				}
				$('#sqft-preview-output').html(html);
			} else {
				$('#sqft-preview-output').html('<span style="color:#d63638;">' + (response.data.message || 'Error') + '</span>');
			}
		}).fail(function () {
			$('#sqft-test-formula').prop('disabled', false).text('Calculate Price');
		});
	}

	function escHtml(s) {
		var d = document.createElement('div');
		d.appendChild(document.createTextNode(s));
		return d.innerHTML;
	}

	function escAttr(s) {
		return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/'/g,'&#39;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
	}

})(jQuery);
