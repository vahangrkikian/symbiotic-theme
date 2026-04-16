<?php
/**
 * Admin template — Product Options Builder.
 *
 * @var int    $product_id
 * @var array  $variables
 * @var string $formula
 * @var float  $min_price
 * @var string $enabled
 * @var array  $var_types
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="sqft-admin-wrap">

	<!-- Enable Toggle -->
	<div class="sqft-toggle-row">
		<label>
			<input type="checkbox" name="_sqft_calculator_enabled" value="1" <?php checked( $enabled, '1' ); ?>>
			<strong><?php esc_html_e( 'Enable Print Calculator for this product', 'sqft-pricing' ); ?></strong>
		</label>
		<p class="description"><?php esc_html_e( 'When enabled, the calculator replaces the standard WooCommerce price display on the product page.', 'sqft-pricing' ); ?></p>
	</div>

	<!-- Tab Navigation -->
	<div class="sqft-tabs">
		<button type="button" class="sqft-tab active" data-tab="options"><?php esc_html_e( 'Options', 'sqft-pricing' ); ?></button>
		<button type="button" class="sqft-tab" data-tab="formula"><?php esc_html_e( 'Formula & Pricing', 'sqft-pricing' ); ?></button>
		<button type="button" class="sqft-tab" data-tab="preview"><?php esc_html_e( 'Preview', 'sqft-pricing' ); ?></button>
	</div>

	<!-- Tab: Options Builder -->
	<div class="sqft-tab-content active" data-tab="options">
		<div id="sqft-variables-list">
			<?php if ( ! empty( $variables ) ) : ?>
				<?php foreach ( $variables as $v_idx => $var ) : ?>
					<?php sqft_render_variable_row( $v_idx, $var, $var_types ); ?>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>

		<button type="button" class="button sqft-add-variable" id="sqft-add-variable">
			+ <?php esc_html_e( 'Add Option Group', 'sqft-pricing' ); ?>
		</button>

		<p class="description" style="margin-top: 12px;">
			<?php esc_html_e( 'Each option group represents a configurable attribute (e.g., Shape, Size, Paper Stock, Quantity, Turnaround). Drag to reorder.', 'sqft-pricing' ); ?>
		</p>
	</div>

	<!-- Tab: Formula & Pricing -->
	<div class="sqft-tab-content" data-tab="formula">
		<div class="sqft-section">
			<h4><?php esc_html_e( 'Pricing Formula', 'sqft-pricing' ); ?></h4>
			<p class="description">
				<?php esc_html_e( 'Use variable slugs as tokens. Each variable contributes two values: VariableName (the item\'s value_numeric) and VariableName$base (the item\'s base_cost). For Size: Size$w and Size$h are available.', 'sqft-pricing' ); ?>
			</p>
			<textarea name="_sqft_formula" id="sqft-formula" rows="5" class="large-text code"><?php echo esc_textarea( $formula ); ?></textarea>

			<div class="sqft-formula-help">
				<h5><?php esc_html_e( 'Formula Reference', 'sqft-pricing' ); ?></h5>
				<table class="sqft-ref-table">
					<tr><td><code>Shape$base</code></td><td><?php esc_html_e( 'Shape flat fee', 'sqft-pricing' ); ?></td></tr>
					<tr><td><code>Size$w</code>, <code>Size$h</code></td><td><?php esc_html_e( 'Selected size width/height', 'sqft-pricing' ); ?></td></tr>
					<tr><td><code>Size$base</code></td><td><?php esc_html_e( 'Size flat fee', 'sqft-pricing' ); ?></td></tr>
					<tr><td><code>Paper_Stock</code></td><td><?php esc_html_e( 'Paper stock per-unit value', 'sqft-pricing' ); ?></td></tr>
					<tr><td><code>Paper_Stock$base</code></td><td><?php esc_html_e( 'Paper stock flat fee', 'sqft-pricing' ); ?></td></tr>
					<tr><td><code>Printed_Sides</code></td><td><?php esc_html_e( 'Printed sides per-unit value', 'sqft-pricing' ); ?></td></tr>
					<tr><td><code>Finishing</code></td><td><?php esc_html_e( 'Finishing per-unit value', 'sqft-pricing' ); ?></td></tr>
					<tr><td><code>Round_Corners</code></td><td><?php esc_html_e( 'Round corners value (per 1000)', 'sqft-pricing' ); ?></td></tr>
					<tr><td><code>Quantity</code></td><td><?php esc_html_e( 'Selected quantity', 'sqft-pricing' ); ?></td></tr>
					<tr><td><code>Quantity$base</code></td><td><?php esc_html_e( 'Per-unit base cost at selected tier', 'sqft-pricing' ); ?></td></tr>
					<tr><td><code>Turnaround</code></td><td><?php esc_html_e( 'Turnaround multiplier (e.g. 1.0, 1.2)', 'sqft-pricing' ); ?></td></tr>
					<tr><td><code>Turnaround$base</code></td><td><?php esc_html_e( 'Turnaround flat fee', 'sqft-pricing' ); ?></td></tr>
					<tr><td><code>floor()</code>, <code>round()</code>, <code>sqrt()</code></td><td><?php esc_html_e( 'Math functions', 'sqft-pricing' ); ?></td></tr>
				</table>

				<h5><?php esc_html_e( 'Example: Business Cards (Axiomprint-style)', 'sqft-pricing' ); ?></h5>
				<pre class="sqft-formula-example">(Quantity / floor((12*18) / ((Size$w + 0.25) * (Size$h + 0.25))) * (Paper_Stock + Printed_Sides + Print_Color + Finishing + Quantity$base) + Shape$base + Paper_Stock$base + Size$base + Finishing$base + Print_Color$base + Printed_Sides$base + (Round_Corners * Quantity / 1000 + Round_Corners$base)) * Turnaround + Turnaround$base</pre>
			</div>
		</div>

		<div class="sqft-section">
			<h4><?php esc_html_e( 'Price Settings', 'sqft-pricing' ); ?></h4>
			<div class="sqft-field-row">
				<div class="sqft-field" style="max-width: 200px;">
					<label for="sqft-min-price"><?php esc_html_e( 'Minimum Price ($)', 'sqft-pricing' ); ?></label>
					<input type="number" id="sqft-min-price" name="_sqft_min_price"
					       value="<?php echo esc_attr( $min_price ); ?>"
					       step="0.01" min="0" placeholder="0.00">
				</div>
			</div>
		</div>
	</div>

	<!-- Tab: Preview -->
	<div class="sqft-tab-content" data-tab="preview">
		<div class="sqft-section">
			<h4><?php esc_html_e( 'Price Calculator Preview', 'sqft-pricing' ); ?></h4>
			<p class="description"><?php esc_html_e( 'Test the formula with different option selections. Save the product first to update the preview.', 'sqft-pricing' ); ?></p>

			<div id="sqft-admin-preview">
				<div id="sqft-preview-selectors"></div>
				<div class="sqft-preview-result">
					<button type="button" class="button button-primary" id="sqft-test-formula">
						<?php esc_html_e( 'Calculate Price', 'sqft-pricing' ); ?>
					</button>
					<div id="sqft-preview-output"></div>
				</div>
			</div>
		</div>
	</div>

</div>

<?php
/**
 * Render a single variable row in the options builder.
 * This is a static method called from the template.
 */
function sqft_render_variable_row( int $idx, array $var, array $var_types ): void {
	$slug     = $var['slug'] ?? '';
	$label    = $var['label'] ?? '';
	$var_type = $var['var_type'] ?? 'list';
	$items    = $var['items'] ?? [];
	$config   = $var['config'] ?? [];
	?>
	<div class="sqft-variable-row" data-index="<?php echo esc_attr( $idx ); ?>">
		<div class="sqft-variable-header">
			<span class="sqft-drag-handle dashicons dashicons-move"></span>
			<div class="sqft-variable-header-fields">
				<input type="text" name="sqft_var[<?php echo $idx; ?>][label]" value="<?php echo esc_attr( $label ); ?>"
				       placeholder="<?php esc_attr_e( 'Option Name (e.g. Paper Stock)', 'sqft-pricing' ); ?>" class="sqft-var-label">
				<input type="text" name="sqft_var[<?php echo $idx; ?>][slug]" value="<?php echo esc_attr( $slug ); ?>"
				       placeholder="<?php esc_attr_e( 'slug (e.g. paper_stock)', 'sqft-pricing' ); ?>" class="sqft-var-slug">
				<select name="sqft_var[<?php echo $idx; ?>][var_type]" class="sqft-var-type">
					<?php foreach ( $var_types as $type_key => $type_label ) : ?>
						<option value="<?php echo esc_attr( $type_key ); ?>" <?php selected( $var_type, $type_key ); ?>>
							<?php echo esc_html( $type_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<button type="button" class="sqft-remove-variable" title="<?php esc_attr_e( 'Remove', 'sqft-pricing' ); ?>">&times;</button>
		</div>

		<!-- Variable-level config (for size type: min/max, metric, etc.) -->
		<div class="sqft-variable-config">
			<div class="sqft-config-row sqft-config-size" style="<?php echo $var_type === 'size' ? '' : 'display:none;'; ?>">
				<input type="number" name="sqft_var[<?php echo $idx; ?>][config][min_width]"
				       value="<?php echo esc_attr( $config['min_width'] ?? '' ); ?>" placeholder="Min W" step="0.01" class="small-text">
				<input type="number" name="sqft_var[<?php echo $idx; ?>][config][max_width]"
				       value="<?php echo esc_attr( $config['max_width'] ?? '' ); ?>" placeholder="Max W" step="0.01" class="small-text">
				<input type="number" name="sqft_var[<?php echo $idx; ?>][config][min_height]"
				       value="<?php echo esc_attr( $config['min_height'] ?? '' ); ?>" placeholder="Min H" step="0.01" class="small-text">
				<input type="number" name="sqft_var[<?php echo $idx; ?>][config][max_height]"
				       value="<?php echo esc_attr( $config['max_height'] ?? '' ); ?>" placeholder="Max H" step="0.01" class="small-text">
				<select name="sqft_var[<?php echo $idx; ?>][config][metric]" class="small-text">
					<option value="inch" <?php selected( $config['metric'] ?? 'inch', 'inch' ); ?>>Inches</option>
					<option value="cm" <?php selected( $config['metric'] ?? '', 'cm' ); ?>>CM</option>
					<option value="mm" <?php selected( $config['metric'] ?? '', 'mm' ); ?>>MM</option>
				</select>
			</div>

			<div class="sqft-config-row sqft-config-turnaround" style="<?php echo $var_type === 'turnaround' ? '' : 'display:none;'; ?>">
				<p class="description"><?php esc_html_e( 'Turnaround items: "Value" = multiplier (e.g., 1.0, 1.2), "Base Cost" = flat fee.', 'sqft-pricing' ); ?></p>
			</div>

			<div class="sqft-config-row sqft-config-quantity" style="<?php echo $var_type === 'quantity_tiers' ? '' : 'display:none;'; ?>">
				<p class="description"><?php esc_html_e( 'Quantity items: "Label" = quantity (e.g., 100), "Base Cost" = per-unit rate at this tier.', 'sqft-pricing' ); ?></p>
			</div>
		</div>

		<!-- Items list -->
		<div class="sqft-items-list" data-var-index="<?php echo $idx; ?>">
			<div class="sqft-items-header">
				<span class="col-label"><?php esc_html_e( 'Choice Label', 'sqft-pricing' ); ?></span>
				<span class="col-value"><?php esc_html_e( 'Value', 'sqft-pricing' ); ?></span>
				<span class="col-base"><?php esc_html_e( 'Base Cost', 'sqft-pricing' ); ?></span>
				<span class="col-default"><?php esc_html_e( 'Default', 'sqft-pricing' ); ?></span>
				<span class="col-hidden"><?php esc_html_e( 'Hidden', 'sqft-pricing' ); ?></span>
				<span class="col-image"><?php esc_html_e( 'Image', 'sqft-pricing' ); ?></span>
				<span class="col-actions"></span>
			</div>

			<?php foreach ( $items as $i_idx => $item ) : ?>
				<?php sqft_render_item_row( $idx, $i_idx, $item ); ?>
			<?php endforeach; ?>
		</div>

		<button type="button" class="button sqft-add-item" data-var-index="<?php echo $idx; ?>">
			+ <?php esc_html_e( 'Add Choice', 'sqft-pricing' ); ?>
		</button>

		<!-- Filters info -->
		<div class="sqft-filters-toggle">
			<button type="button" class="button-link sqft-toggle-filters"><?php esc_html_e( 'Show/Hide Dependency Filters', 'sqft-pricing' ); ?></button>
		</div>
	</div>
	<?php
}

// Alias for template use.
function sqft_admin_render_variable_row( int $idx, array $var, array $var_types ): void {
	sqft_render_variable_row( $idx, $var, $var_types );
}

/**
 * Render a single item row.
 */
function sqft_render_item_row( int $var_idx, int $item_idx, array $item ): void {
	$prefix = "sqft_var[{$var_idx}][items][{$item_idx}]";
	$config = $item['config'] ?? [];
	?>
	<div class="sqft-item-row" data-item-index="<?php echo esc_attr( $item_idx ); ?>">
		<span class="sqft-item-drag dashicons dashicons-move"></span>

		<input type="text" name="<?php echo $prefix; ?>[label]"
		       value="<?php echo esc_attr( $item['label'] ?? '' ); ?>"
		       placeholder="<?php esc_attr_e( 'Label', 'sqft-pricing' ); ?>" class="sqft-item-label">

		<input type="number" name="<?php echo $prefix; ?>[value_numeric]"
		       value="<?php echo esc_attr( $item['value_numeric'] ?? 0 ); ?>"
		       step="0.000001" class="sqft-item-value" placeholder="0">

		<input type="number" name="<?php echo $prefix; ?>[base_cost]"
		       value="<?php echo esc_attr( $item['base_cost'] ?? 0 ); ?>"
		       step="0.01" class="sqft-item-base" placeholder="0">

		<label class="sqft-item-default-wrap">
			<input type="radio" name="sqft_var[<?php echo $var_idx; ?>][default_item]"
			       value="<?php echo $item_idx; ?>" <?php checked( $item['is_default'] ?? 0, 1 ); ?>>
		</label>

		<label class="sqft-item-hidden-wrap">
			<input type="checkbox" name="<?php echo $prefix; ?>[is_hidden]"
			       value="1" <?php checked( $item['is_hidden'] ?? 0, 1 ); ?>>
		</label>

		<div class="sqft-item-image-wrap">
			<input type="hidden" name="<?php echo $prefix; ?>[config][image_url]"
			       value="<?php echo esc_attr( $config['image_url'] ?? '' ); ?>" class="sqft-item-image-input">
			<?php if ( ! empty( $config['image_url'] ) ) : ?>
				<img src="<?php echo esc_url( $config['image_url'] ); ?>" class="sqft-item-image-thumb" width="30" height="30">
			<?php endif; ?>
			<button type="button" class="button-link sqft-upload-image" title="<?php esc_attr_e( 'Upload', 'sqft-pricing' ); ?>">
				<span class="dashicons dashicons-format-image"></span>
			</button>
		</div>

		<!-- Size config (width/height for size-type items) -->
		<input type="hidden" name="<?php echo $prefix; ?>[config][width]"
		       value="<?php echo esc_attr( $config['width'] ?? '' ); ?>" class="sqft-item-width">
		<input type="hidden" name="<?php echo $prefix; ?>[config][height]"
		       value="<?php echo esc_attr( $config['height'] ?? '' ); ?>" class="sqft-item-height">

		<!-- Turnaround config (day count) -->
		<input type="hidden" name="<?php echo $prefix; ?>[config][day_count]"
		       value="<?php echo esc_attr( $config['day_count'] ?? '' ); ?>" class="sqft-item-daycount">

		<!-- Default flag (set by JS from radio) -->
		<input type="hidden" name="<?php echo $prefix; ?>[is_default]"
		       value="<?php echo esc_attr( $item['is_default'] ?? 0 ); ?>" class="sqft-item-default-hidden">

		<button type="button" class="sqft-remove-item" title="<?php esc_attr_e( 'Remove', 'sqft-pricing' ); ?>">&times;</button>

		<!-- Filter rows (dependencies) -->
		<div class="sqft-item-filters" style="display:none;">
			<?php
			$filters = $item['filters'] ?? [];
			foreach ( $filters as $f_idx => $filter ) :
				$f_prefix = "{$prefix}[filters][{$f_idx}]";
			?>
				<div class="sqft-filter-row">
					<span><?php esc_html_e( 'Show when', 'sqft-pricing' ); ?></span>
					<input type="text" name="<?php echo $f_prefix; ?>[depends_on_variable_slug]"
					       value="<?php echo esc_attr( $filter['depends_on_variable_slug'] ?? $filter['depends_on_variable_id'] ?? '' ); ?>"
					       placeholder="<?php esc_attr_e( 'variable slug', 'sqft-pricing' ); ?>" class="small-text">
					<span>=</span>
					<input type="text" name="<?php echo $f_prefix; ?>[depends_on_item_label]"
					       value="<?php echo esc_attr( $filter['depends_on_item_label'] ?? $filter['depends_on_item_id'] ?? '' ); ?>"
					       placeholder="<?php esc_attr_e( 'item label', 'sqft-pricing' ); ?>" class="small-text">
					<button type="button" class="sqft-remove-filter button-link">&times;</button>
				</div>
			<?php endforeach; ?>
			<button type="button" class="button-link sqft-add-filter">+ <?php esc_html_e( 'Add Filter', 'sqft-pricing' ); ?></button>
		</div>
	</div>
	<?php
}
