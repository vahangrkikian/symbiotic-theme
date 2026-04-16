<?php
/**
 * Setup Example Product: Classic Business Cards
 *
 * Replicates the axiomprint.com/product/classic-business-cards-160 product
 * with all 9 variables, 40+ items, dependency filters, and the exact pricing formula.
 *
 * Usage: Visit /wp-admin/ then navigate to Tools > Sqft Setup Example
 * Or run: wp eval-file wp-content/plugins/sqft-pricing/setup-example-product.php
 *
 * @package SqftPricing
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the admin page for one-click setup.
 */
add_action( 'admin_menu', function (): void {
	add_management_page(
		__( 'Sqft Pricing — Setup Example', 'sqft-pricing' ),
		__( 'Sqft Setup Example', 'sqft-pricing' ),
		'manage_woocommerce',
		'sqft-setup-example',
		'sqft_setup_example_page'
	);
} );

function sqft_setup_example_page(): void {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Access denied.' );
	}

	echo '<div class="wrap"><h1>Sqft Pricing — Setup Example Product</h1>';

	if ( isset( $_POST['sqft_run_setup'] ) && check_admin_referer( 'sqft_setup_example' ) ) {
		$result = sqft_create_business_cards_product();
		if ( is_wp_error( $result ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
		} else {
			echo '<div class="notice notice-success"><p>';
			printf(
				__( 'Product created! <a href="%s">Edit product</a> | <a href="%s">View product</a>', 'sqft-pricing' ),
				get_edit_post_link( $result ),
				get_permalink( $result )
			);
			echo '</p></div>';
		}
	}

	echo '<form method="post">';
	wp_nonce_field( 'sqft_setup_example' );
	echo '<p>' . esc_html__( 'This will create a "Classic Business Cards" product with the full Axiomprint-style configurator — 9 option groups, 40+ choices, dependency filters, quantity tiers, turnaround options, and the sheet imposition pricing formula.', 'sqft-pricing' ) . '</p>';
	echo '<p><strong>' . esc_html__( 'What gets created:', 'sqft-pricing' ) . '</strong></p>';
	echo '<ul style="list-style:disc;margin-left:20px;">';
	echo '<li>WooCommerce Simple product: "Classic Business Cards"</li>';
	echo '<li>9 option groups: Shape, Size, Paper Stock, Printed Sides, Print Color, Finishing, Round Corners, Quantity, Turnaround</li>';
	echo '<li>Dependency filters (e.g., Finishing options filtered by Paper Stock)</li>';
	echo '<li>12 quantity tiers (50 to 20,000)</li>';
	echo '<li>5 turnaround options with multipliers</li>';
	echo '<li>Sheet imposition formula: floor((12×18) / ((w+0.25)×(h+0.25)))</li>';
	echo '</ul>';
	submit_button( __( 'Create Example Product', 'sqft-pricing' ), 'primary', 'sqft_run_setup' );
	echo '</form></div>';
}

/**
 * Create the full Classic Business Cards product.
 *
 * @return int|WP_Error Product ID on success, WP_Error on failure.
 */
function sqft_create_business_cards_product() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return new WP_Error( 'no_wc', 'WooCommerce is not active.' );
	}

	// Ensure DB tables exist (skip if already there).
	global $wpdb;
	$exists = $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}sqft_variables'" );
	if ( ! $exists ) {
		Sqft_Database::create_tables();
	}

	// ─── Create WooCommerce Product ──────────────────────────────────────

	$product_id = wp_insert_post( [
		'post_title'   => 'Classic Business Cards',
		'post_content' => '<p>Premium classic business cards printed on high-quality card stock. Choose from multiple paper stocks, finishes, sizes, and shapes. Available in quantities from 50 to 20,000 with turnaround options from Express to standard.</p>',
		'post_excerpt' => 'Custom printed business cards with formula-based pricing.',
		'post_status'  => 'publish',
		'post_type'    => 'product',
	] );

	if ( is_wp_error( $product_id ) || ! $product_id ) {
		return new WP_Error( 'product_fail', 'Failed to create product.' );
	}

	// Set WooCommerce product type and meta.
	wp_set_object_terms( $product_id, 'simple', 'product_type' );
	update_post_meta( $product_id, '_regular_price', '0' );
	update_post_meta( $product_id, '_price', '0' );
	update_post_meta( $product_id, '_stock_status', 'instock' );
	update_post_meta( $product_id, '_visibility', 'visible' );
	update_post_meta( $product_id, '_manage_stock', 'no' );

	// ─── Enable Calculator ───────────────────────────────────────────────

	update_post_meta( $product_id, '_sqft_calculator_enabled', '1' );
	update_post_meta( $product_id, '_sqft_min_price', 0 );

	// ─── Set Pricing Formula ─────────────────────────────────────────────

	$formula = '(Quantity / floor((12*18) / ((Size$w + 0.25) * (Size$h + 0.25))) * (Paper_Stock + Printed_Sides + Print_Color + Finishing + Quantity$base) + Shape$base + Paper_Stock$base + Size$base + Finishing$base + Print_Color$base + Printed_Sides$base + (Round_Corners * Quantity / 1000 + Round_Corners$base)) * Turnaround + Turnaround$base';

	update_post_meta( $product_id, '_sqft_formula', $formula );

	// ─── Create Variables & Items ────────────────────────────────────────

	// Helper functions.
	$_vt = $wpdb->prefix . 'sqft_variables';
	$_it = $wpdb->prefix . 'sqft_variable_items';
	$_ft = $wpdb->prefix . 'sqft_item_filters';

	$insert_var = function ( $d ) use ( $wpdb, $_vt, $product_id ) {
		$wpdb->insert( $_vt, [
			'product_id' => $product_id, 'slug' => $d['slug'], 'label' => $d['label'],
			'var_type' => $d['var_type'], 'sort_order' => $d['sort_order'],
			'config' => wp_json_encode( $d['config'] ?? [] ),
		] );
		return (int) $wpdb->insert_id;
	};

	$insert_item = function ( $var_id, $d, $order ) use ( $wpdb, $_it ) {
		$wpdb->insert( $_it, [
			'variable_id' => $var_id, 'label' => $d['label'],
			'value_numeric' => $d['value'], 'base_cost' => $d['base'],
			'is_default' => $d['is_default'] ?? 0, 'is_hidden' => $d['is_hidden'] ?? 0,
			'sort_order' => $order, 'config' => wp_json_encode( $d['config'] ?? [] ),
		] );
		return (int) $wpdb->insert_id;
	};

	$insert_filter = function ( $item_id, $dep_var_id, $dep_item_id ) use ( $wpdb, $_ft ) {
		$wpdb->insert( $_ft, [
			'item_id' => $item_id, 'depends_on_variable_id' => $dep_var_id,
			'depends_on_item_id' => $dep_item_id,
		] );
	};

	// ═══════════════════════════════════════════════════════════════════════
	// VARIABLE 1: Shape
	// ═══════════════════════════════════════════════════════════════════════

	$shape_var_id = $insert_var( [
		'slug'       => 'Shape',
		'label'      => 'Shape',
		'var_type'   => 'card',
		'sort_order' => 0,
	] );

	$shape_rectangle_id = $insert_item( $shape_var_id, [
		'label' => 'Rectangle', 'value' => 1, 'base' => 0, 'is_default' => 1,
	], 0 );

	$shape_square_id = $insert_item( $shape_var_id, [
		'label' => 'Square', 'value' => 1, 'base' => 5,
	], 1 );

	$shape_circle_id = $insert_item( $shape_var_id, [
		'label' => 'Circle', 'value' => 1, 'base' => 15,
	], 2 );

	// ═══════════════════════════════════════════════════════════════════════
	// VARIABLE 2: Size
	// ═══════════════════════════════════════════════════════════════════════

	$size_var_id = $insert_var( [
		'slug'       => 'Size',
		'label'      => 'Size',
		'var_type'   => 'size',
		'sort_order' => 1,
		'config'     => [
			'min_width'  => '1.5',
			'max_width'  => '4',
			'min_height' => '1.5',
			'max_height' => '4',
			'metric'     => 'inch',
		],
	] );

	$size_35x2_id = $insert_item( $size_var_id, [
		'label' => '3.5 x 2', 'value' => 7, 'base' => 5, 'is_default' => 1,
		'config' => [ 'width' => '3.5', 'height' => '2' ],
	], 0 );
	$insert_filter( $size_35x2_id, $shape_var_id, $shape_rectangle_id );

	$size_2x2_id = $insert_item( $size_var_id, [
		'label' => '2 x 2', 'value' => 4, 'base' => 10,
		'config' => [ 'width' => '2', 'height' => '2' ],
	], 1 );
	$insert_filter( $size_2x2_id, $shape_var_id, $shape_square_id );
	$insert_filter( $size_2x2_id, $shape_var_id, $shape_circle_id );

	$size_335x216_id = $insert_item( $size_var_id, [
		'label' => '3.35 x 2.16', 'value' => 7.236, 'base' => 15,
		'config' => [ 'width' => '3.35', 'height' => '2.16' ],
	], 2 );
	$insert_filter( $size_335x216_id, $shape_var_id, $shape_rectangle_id );

	$size_custom_id = $insert_item( $size_var_id, [
		'label' => 'Custom Size', 'value' => 0, 'base' => 35,
		'config' => [ 'width' => '3.5', 'height' => '2', 'is_custom' => '1' ],
	], 3 );
	// Custom size available for all shapes.
	$insert_filter( $size_custom_id, $shape_var_id, $shape_rectangle_id );
	$insert_filter( $size_custom_id, $shape_var_id, $shape_square_id );
	$insert_filter( $size_custom_id, $shape_var_id, $shape_circle_id );

	// ═══════════════════════════════════════════════════════════════════════
	// VARIABLE 3: Paper Stock
	// ═══════════════════════════════════════════════════════════════════════

	$paper_var_id = $insert_var( [
		'slug'       => 'Paper_Stock',
		'label'      => 'Paper Stock',
		'var_type'   => 'material_card',
		'sort_order' => 2,
	] );

	$paper_14pt_c2s_id = $insert_item( $paper_var_id, [
		'label' => '14PT Coated Both Sides', 'value' => 0.258, 'base' => 5, 'is_default' => 1,
		'config' => [ 'mass' => '3.832', 'thick' => '1.431' ],
	], 0 );

	$paper_14pt_c1s_id = $insert_item( $paper_var_id, [
		'label' => '14PT Coated One Side', 'value' => 0.22, 'base' => 5,
		'config' => [ 'mass' => '3.832', 'thick' => '1.431' ],
	], 1 );

	$paper_16pt_c2s_id = $insert_item( $paper_var_id, [
		'label' => '16PT Coated Both Sides', 'value' => 0.305, 'base' => 8,
		'config' => [ 'mass' => '4.194', 'thick' => '1.608' ],
	], 2 );

	$paper_100_uncoated_id = $insert_item( $paper_var_id, [
		'label' => '100# Uncoated Cover', 'value' => 0.25, 'base' => 12,
		'config' => [ 'mass' => '3.668', 'thick' => '1.411' ],
	], 3 );

	$paper_130_uncoated_id = $insert_item( $paper_var_id, [
		'label' => '130# Uncoated Cover', 'value' => 0.48, 'base' => 15, 'is_hidden' => 1,
		'config' => [ 'mass' => '4.663', 'thick' => '1.785' ],
	], 4 );

	$paper_cc_solar_id = $insert_item( $paper_var_id, [
		'label' => '100# Classic Crest Solar White', 'value' => 0.32, 'base' => 12,
		'config' => [ 'mass' => '3.51', 'thick' => '1.1455' ],
	], 5 );

	$paper_cc_natural_id = $insert_item( $paper_var_id, [
		'label' => '100# Classic Crest Natural White', 'value' => 0.32, 'base' => 12,
		'config' => [ 'mass' => '3.51', 'thick' => '1.1455' ],
	], 6 );

	$paper_cc_linen_solar_id = $insert_item( $paper_var_id, [
		'label' => '100# Classic Crest Linen Solar White', 'value' => 0.38, 'base' => 15,
		'config' => [ 'mass' => '3.51', 'thick' => '1.1455' ],
	], 7 );

	$paper_cc_linen_natural_id = $insert_item( $paper_var_id, [
		'label' => '100# Classic Crest Linen Natural White', 'value' => 0.38, 'base' => 15,
		'config' => [ 'mass' => '3.51', 'thick' => '1.1455' ],
	], 8 );

	// ═══════════════════════════════════════════════════════════════════════
	// VARIABLE 4: Printed Sides
	// ═══════════════════════════════════════════════════════════════════════

	$sides_var_id = $insert_var( [
		'slug'       => 'Printed_Sides',
		'label'      => 'Printed Sides',
		'var_type'   => 'radio',
		'sort_order' => 3,
	] );

	$sides_both_id = $insert_item( $sides_var_id, [
		'label' => 'Front and Back', 'value' => 0.24, 'base' => 8, 'is_default' => 1,
	], 0 );

	$sides_front_id = $insert_item( $sides_var_id, [
		'label' => 'Front Only', 'value' => 0.18, 'base' => 3,
	], 1 );

	// ═══════════════════════════════════════════════════════════════════════
	// VARIABLE 5: Print Color (hidden, auto-selected)
	// ═══════════════════════════════════════════════════════════════════════

	$color_var_id = $insert_var( [
		'slug'       => 'Print_Color',
		'label'      => 'Print Color',
		'var_type'   => 'list',
		'sort_order' => 4,
		'config'     => [ 'hidden' => '1' ],
	] );

	$color_40_id = $insert_item( $color_var_id, [
		'label' => 'Full Color (4/0)', 'value' => 0, 'base' => 0,
	], 0 );
	$insert_filter( $color_40_id, $sides_var_id, $sides_front_id );

	$color_44_id = $insert_item( $color_var_id, [
		'label' => 'Full Color (4/4)', 'value' => 0, 'base' => 0, 'is_default' => 1,
	], 1 );
	$insert_filter( $color_44_id, $sides_var_id, $sides_both_id );

	// ═══════════════════════════════════════════════════════════════════════
	// VARIABLE 6: Finishing
	// ═══════════════════════════════════════════════════════════════════════

	$finishing_var_id = $insert_var( [
		'slug'       => 'Finishing',
		'label'      => 'Finishing',
		'var_type'   => 'list',
		'sort_order' => 5,
	] );

	// Glossy, Front Only → only when Paper = 14PT C1S
	$finish_glossy_front_id = $insert_item( $finishing_var_id, [
		'label' => 'Glossy, Front Only', 'value' => 0, 'base' => 0, 'is_default' => 1,
	], 0 );
	$insert_filter( $finish_glossy_front_id, $paper_var_id, $paper_14pt_c1s_id );

	// Glossy, 2 Sides → when Paper = 14PT C2S or 16PT C2S
	$finish_glossy_both_id = $insert_item( $finishing_var_id, [
		'label' => 'Glossy, 2 Sides', 'value' => 0, 'base' => 0,
	], 1 );
	$insert_filter( $finish_glossy_both_id, $paper_var_id, $paper_14pt_c2s_id );
	$insert_filter( $finish_glossy_both_id, $paper_var_id, $paper_16pt_c2s_id );

	// Dull Matte, 2 Sides → when Paper = 14PT C2S or 16PT C2S
	$finish_matte_id = $insert_item( $finishing_var_id, [
		'label' => 'Dull Matte, 2 Sides', 'value' => 0.12, 'base' => 15,
	], 2 );
	$insert_filter( $finish_matte_id, $paper_var_id, $paper_14pt_c2s_id );
	$insert_filter( $finish_matte_id, $paper_var_id, $paper_16pt_c2s_id );

	// UV High-Gloss, Front Only → when Paper = 14PT C1S
	$finish_uv_front_id = $insert_item( $finishing_var_id, [
		'label' => 'UV High-Gloss, Front Only', 'value' => 0.08, 'base' => 5,
	], 3 );
	$insert_filter( $finish_uv_front_id, $paper_var_id, $paper_14pt_c1s_id );

	// UV High-Gloss, 2 Sides → when Paper = 14PT C2S or 16PT C2S
	$finish_uv_both_id = $insert_item( $finishing_var_id, [
		'label' => 'UV High-Gloss, 2 Sides', 'value' => 0.10, 'base' => 7,
	], 4 );
	$insert_filter( $finish_uv_both_id, $paper_var_id, $paper_14pt_c2s_id );
	$insert_filter( $finish_uv_both_id, $paper_var_id, $paper_16pt_c2s_id );

	// Uncoated, 2 Sides → when Paper = any uncoated/Classic Crest
	$finish_uncoated_id = $insert_item( $finishing_var_id, [
		'label' => 'Uncoated, 2 Sides', 'value' => 0, 'base' => 0,
	], 5 );
	$insert_filter( $finish_uncoated_id, $paper_var_id, $paper_100_uncoated_id );
	$insert_filter( $finish_uncoated_id, $paper_var_id, $paper_130_uncoated_id );
	$insert_filter( $finish_uncoated_id, $paper_var_id, $paper_cc_solar_id );
	$insert_filter( $finish_uncoated_id, $paper_var_id, $paper_cc_natural_id );
	$insert_filter( $finish_uncoated_id, $paper_var_id, $paper_cc_linen_solar_id );
	$insert_filter( $finish_uncoated_id, $paper_var_id, $paper_cc_linen_natural_id );

	// ═══════════════════════════════════════════════════════════════════════
	// VARIABLE 7: Round Corners
	// ═══════════════════════════════════════════════════════════════════════

	$corners_var_id = $insert_var( [
		'slug'       => 'Round_Corners',
		'label'      => 'Round Corners',
		'var_type'   => 'card',
		'sort_order' => 6,
	] );

	$insert_item( $corners_var_id, [
		'label' => 'No', 'value' => 0, 'base' => 0, 'is_default' => 1,
		'config' => [ 'radius' => '0', 'corners' => '0' ],
	], 0 );

	$insert_item( $corners_var_id, [
		'label' => '1/8" Round', 'value' => 12, 'base' => 8,
		'config' => [ 'radius' => '0.125', 'corners' => '4' ],
	], 1 );

	$insert_item( $corners_var_id, [
		'label' => '1/4" Round', 'value' => 12, 'base' => 8,
		'config' => [ 'radius' => '0.25', 'corners' => '4' ],
	], 2 );

	$insert_item( $corners_var_id, [
		'label' => '1/8" Round, 2 Corners', 'value' => 12, 'base' => 8, 'is_hidden' => 1,
		'config' => [ 'radius' => '0.125', 'corners' => '2' ],
	], 3 );

	$insert_item( $corners_var_id, [
		'label' => '1/4" Round, 2 Corners', 'value' => 12, 'base' => 8, 'is_hidden' => 1,
		'config' => [ 'radius' => '0.25', 'corners' => '2' ],
	], 4 );

	// ═══════════════════════════════════════════════════════════════════════
	// VARIABLE 8: Quantity
	// ═══════════════════════════════════════════════════════════════════════

	$qty_var_id = $insert_var( [
		'slug'       => 'Quantity',
		'label'      => 'Quantity',
		'var_type'   => 'quantity_tiers',
		'sort_order' => 7,
	] );

	$qty_tiers = [
		[ 'label' => '50',    'value' => 50,    'base' => 0.85, 'is_default' => 1 ],
		[ 'label' => '100',   'value' => 100,   'base' => 0.80 ],
		[ 'label' => '150',   'value' => 150,   'base' => 0.75 ],
		[ 'label' => '200',   'value' => 200,   'base' => 0.70 ],
		[ 'label' => '250',   'value' => 250,   'base' => 0.60 ],
		[ 'label' => '500',   'value' => 500,   'base' => 0.55 ],
		[ 'label' => '1000',  'value' => 1000,  'base' => 0.45 ],
		[ 'label' => '2500',  'value' => 2500,  'base' => 0.35 ],
		[ 'label' => '5000',  'value' => 5000,  'base' => 0.25 ],
		[ 'label' => '10000', 'value' => 10000, 'base' => 0.20 ],
		[ 'label' => '15000', 'value' => 15000, 'base' => 0.18 ],
		[ 'label' => '20000', 'value' => 20000, 'base' => 0.16 ],
	];

	$qty_item_ids = [];
	foreach ( $qty_tiers as $order => $tier ) {
		$qty_item_ids[ $tier['label'] ] = $insert_item( $qty_var_id, $tier, $order );
	}

	// ═══════════════════════════════════════════════════════════════════════
	// VARIABLE 9: Turnaround
	// ═══════════════════════════════════════════════════════════════════════

	$turnaround_var_id = $insert_var( [
		'slug'       => 'Turnaround',
		'label'      => 'Turnaround',
		'var_type'   => 'turnaround',
		'sort_order' => 8,
	] );

	// For quantities 2500+ → 4-5 Business Days (default), 3 Business Days
	$ta_45day_id = $insert_item( $turnaround_var_id, [
		'label' => '4-5 Business Days', 'value' => 1.0, 'base' => 5, 'is_default' => 1,
		'config' => [ 'day_count' => '5' ],
	], 0 );
	// Filter: show only for qty 2500, 5000, 10000, 15000, 20000
	$insert_filter( $ta_45day_id, $qty_var_id, $qty_item_ids['2500'] );
	$insert_filter( $ta_45day_id, $qty_var_id, $qty_item_ids['5000'] );
	$insert_filter( $ta_45day_id, $qty_var_id, $qty_item_ids['10000'] );
	$insert_filter( $ta_45day_id, $qty_var_id, $qty_item_ids['15000'] );
	$insert_filter( $ta_45day_id, $qty_var_id, $qty_item_ids['20000'] );

	$ta_3day_large_id = $insert_item( $turnaround_var_id, [
		'label' => '3 Business Days', 'value' => 1.2, 'base' => 18,
		'config' => [ 'day_count' => '3' ],
	], 1 );
	$insert_filter( $ta_3day_large_id, $qty_var_id, $qty_item_ids['2500'] );
	$insert_filter( $ta_3day_large_id, $qty_var_id, $qty_item_ids['5000'] );
	$insert_filter( $ta_3day_large_id, $qty_var_id, $qty_item_ids['10000'] );
	$insert_filter( $ta_3day_large_id, $qty_var_id, $qty_item_ids['15000'] );
	$insert_filter( $ta_3day_large_id, $qty_var_id, $qty_item_ids['20000'] );

	// For quantities 50-1000 → 3 Business Days (default), Next Day, Express
	$ta_3day_small_id = $insert_item( $turnaround_var_id, [
		'label' => '3 Business Days', 'value' => 1.0, 'base' => 5,
		'config' => [ 'day_count' => '3' ],
	], 2 );
	$insert_filter( $ta_3day_small_id, $qty_var_id, $qty_item_ids['50'] );
	$insert_filter( $ta_3day_small_id, $qty_var_id, $qty_item_ids['100'] );
	$insert_filter( $ta_3day_small_id, $qty_var_id, $qty_item_ids['150'] );
	$insert_filter( $ta_3day_small_id, $qty_var_id, $qty_item_ids['200'] );
	$insert_filter( $ta_3day_small_id, $qty_var_id, $qty_item_ids['250'] );
	$insert_filter( $ta_3day_small_id, $qty_var_id, $qty_item_ids['500'] );
	$insert_filter( $ta_3day_small_id, $qty_var_id, $qty_item_ids['1000'] );

	$ta_nextday_id = $insert_item( $turnaround_var_id, [
		'label' => 'Next Day', 'value' => 1.2, 'base' => 18,
		'config' => [ 'day_count' => '1' ],
	], 3 );
	$insert_filter( $ta_nextday_id, $qty_var_id, $qty_item_ids['50'] );
	$insert_filter( $ta_nextday_id, $qty_var_id, $qty_item_ids['100'] );
	$insert_filter( $ta_nextday_id, $qty_var_id, $qty_item_ids['150'] );
	$insert_filter( $ta_nextday_id, $qty_var_id, $qty_item_ids['200'] );
	$insert_filter( $ta_nextday_id, $qty_var_id, $qty_item_ids['250'] );
	$insert_filter( $ta_nextday_id, $qty_var_id, $qty_item_ids['500'] );
	$insert_filter( $ta_nextday_id, $qty_var_id, $qty_item_ids['1000'] );

	$ta_express_id = $insert_item( $turnaround_var_id, [
		'label' => 'Express', 'value' => 1.4, 'base' => 32,
		'config' => [ 'day_count' => '0' ],
	], 4 );
	$insert_filter( $ta_express_id, $qty_var_id, $qty_item_ids['50'] );
	$insert_filter( $ta_express_id, $qty_var_id, $qty_item_ids['100'] );
	$insert_filter( $ta_express_id, $qty_var_id, $qty_item_ids['150'] );
	$insert_filter( $ta_express_id, $qty_var_id, $qty_item_ids['200'] );
	$insert_filter( $ta_express_id, $qty_var_id, $qty_item_ids['250'] );
	$insert_filter( $ta_express_id, $qty_var_id, $qty_item_ids['500'] );

	return $product_id;
}
