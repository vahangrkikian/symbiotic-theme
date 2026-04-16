<?php
/**
 * Sqft Pricing — Server-Side Pricing Engine
 *
 * Pure PHP formula engine. All price computation happens here.
 * Frontend JS never computes prices — this is the single source of truth.
 *
 * @package SqftPricing
 */

defined( 'ABSPATH' ) || exit;

/**
 * Unit-to-sqft conversion factors.
 *
 * Each factor converts (unit²) → sqft.
 * Example: 24in × 36in = 864 in² × 0.00694444 = 6.0 sqft
 */
function sqft_get_conversion_factors(): array {
	return [
		'inch' => 0.00694444,
		'cm'   => 0.00107639,
		'mm'   => 0.0000107639,
		'feet' => 1.0,
	];
}

/**
 * Calculate the final price for a print product based on dimensions and modifiers.
 *
 * @param float  $width      Width in the given unit.
 * @param float  $height     Height in the given unit.
 * @param string $unit       Unit of measurement: 'inch' | 'cm' | 'mm' | 'feet'.
 * @param float  $mat_rate   Material cost in $/sqft.
 * @param float  $print_rate Printing cost in $/sqft.
 * @param array  $modifiers  Array of modifier arrays, each with keys:
 *                           'type'  => 'multiplier' | 'adder_sqft' | 'flat_fee'
 *                           'value' => float
 *                           'label' => string (optional, for display)
 * @param float  $min_price  Minimum price floor.
 *
 * @return array {
 *     @type float  $sqft       Computed square footage.
 *     @type float  $base_price Price before modifiers.
 *     @type float  $final_price Final price after modifiers and floor.
 *     @type array  $breakdown  Step-by-step breakdown for admin preview.
 * }
 */
function sqft_calculate_price(
	float $width,
	float $height,
	string $unit,
	float $mat_rate,
	float $print_rate,
	array $modifiers = [],
	float $min_price = 0.0
): array {
	$factors = sqft_get_conversion_factors();
	$factor  = $factors[ $unit ] ?? $factors['inch'];

	// Step 1: Compute square footage.
	$area_units = $width * $height;
	$sqft       = $area_units * $factor;

	// Step 2: Base price from material + printing rates.
	$base_price = $sqft * ( $mat_rate + $print_rate );

	$breakdown = [
		'dimensions'  => sprintf( '%.2f × %.2f %s', $width, $height, $unit ),
		'area_units'  => round( $area_units, 4 ),
		'sqft'        => round( $sqft, 4 ),
		'rate_total'  => round( $mat_rate + $print_rate, 4 ),
		'base_price'  => round( $base_price, 2 ),
		'modifiers'   => [],
	];

	// Step 3: Apply modifiers in order — multipliers first, then adders, then flat fees.
	$price = $base_price;

	// Sort modifiers: multiplier → adder_sqft → flat_fee.
	$order_map = [ 'multiplier' => 0, 'adder_sqft' => 1, 'flat_fee' => 2 ];
	usort( $modifiers, function ( $a, $b ) use ( $order_map ) {
		$oa = $order_map[ $a['type'] ] ?? 3;
		$ob = $order_map[ $b['type'] ] ?? 3;
		return $oa - $ob;
	} );

	foreach ( $modifiers as $mod ) {
		$type  = $mod['type'] ?? '';
		$value = floatval( $mod['value'] ?? 0 );
		$label = $mod['label'] ?? $type;

		if ( $value == 0 && $type !== 'multiplier' ) {
			continue;
		}

		switch ( $type ) {
			case 'multiplier':
				if ( $value == 1.0 ) {
					break; // No effect.
				}
				$delta = $price * ( $value - 1.0 );
				$price *= $value;
				$breakdown['modifiers'][] = [
					'label'  => $label,
					'type'   => 'multiplier',
					'factor' => $value,
					'delta'  => round( $delta, 2 ),
				];
				break;

			case 'adder_sqft':
				$delta = $sqft * $value;
				$price += $delta;
				$breakdown['modifiers'][] = [
					'label'    => $label,
					'type'     => 'adder_sqft',
					'per_sqft' => $value,
					'delta'    => round( $delta, 2 ),
				];
				break;

			case 'flat_fee':
				$price += $value;
				$breakdown['modifiers'][] = [
					'label' => $label,
					'type'  => 'flat_fee',
					'delta' => round( $value, 2 ),
				];
				break;
		}
	}

	// Step 4: Apply minimum price floor.
	$floored = false;
	if ( $price < $min_price && $min_price > 0 ) {
		$price   = $min_price;
		$floored = true;
	}

	$final_price = round( $price, 2 );

	$breakdown['floored']     = $floored;
	$breakdown['min_price']   = $min_price;
	$breakdown['final_price'] = $final_price;

	return [
		'sqft'        => round( $sqft, 4 ),
		'base_price'  => round( $base_price, 2 ),
		'final_price' => $final_price,
		'breakdown'   => $breakdown,
	];
}

/**
 * Parse dimension string from a WooCommerce variation attribute.
 *
 * Handles formats: "24x36", "24 x 36", "24×36", "24X36"
 *
 * @param string $size_string The size attribute value.
 *
 * @return array|false Array with 'width' and 'height' floats, or false on failure.
 */
function sqft_parse_size_string( string $size_string ) {
	// Normalize: trim, lowercase, replace × with x.
	$s = strtolower( trim( $size_string ) );
	$s = str_replace( [ '×', 'х' ], 'x', $s ); // Replace multiplication sign and Cyrillic х.

	// Remove unit suffixes like "in", "inch", "inches", "cm", "mm", "ft", "feet".
	$s = preg_replace( '/\s*(inches|inch|in|cm|mm|feet|ft)\s*/i', '', $s );

	// Match: number x number.
	if ( preg_match( '/^([\d.]+)\s*x\s*([\d.]+)$/', $s, $m ) ) {
		$w = floatval( $m[1] );
		$h = floatval( $m[2] );
		if ( $w > 0 && $h > 0 ) {
			return [ 'width' => $w, 'height' => $h ];
		}
	}

	return false;
}

/**
 * Build the active modifiers array from product meta and selected options.
 *
 * @param int    $product_id  The product (or parent) ID.
 * @param string $color_profile 'single_layer' | 'double_layer'.
 * @param string $lamination    'none' | 'clear'.
 *
 * @return array Modifiers array ready for sqft_calculate_price().
 */
function sqft_build_modifiers( int $product_id, string $color_profile = 'single_layer', string $lamination = 'none' ): array {
	$modifiers = [];

	// Double layer multiplier.
	if ( $color_profile === 'double_layer' ) {
		$mult = floatval( get_post_meta( $product_id, '_opt_double_layer_mult', true ) );
		if ( $mult > 0 && $mult !== 1.0 ) {
			$modifiers[] = [
				'type'  => 'multiplier',
				'value' => $mult,
				'label' => __( 'Double Layer (Night)', 'sqft-pricing' ),
			];
		}
	}

	// Lamination adder.
	if ( $lamination === 'clear' ) {
		$adder = floatval( get_post_meta( $product_id, '_opt_lam_adder', true ) );
		if ( $adder > 0 ) {
			$modifiers[] = [
				'type'  => 'adder_sqft',
				'value' => $adder,
				'label' => __( 'Clear Lamination', 'sqft-pricing' ),
			];
		}
	}

	/**
	 * Filter the modifiers array before price calculation.
	 *
	 * Allows themes or other plugins to add custom modifiers (e.g. cutting fee, rush order).
	 *
	 * @param array  $modifiers     Current modifiers.
	 * @param int    $product_id    Product ID.
	 * @param string $color_profile Selected color profile.
	 * @param string $lamination    Selected lamination option.
	 */
	return apply_filters( 'sqft_pricing_modifiers', $modifiers, $product_id, $color_profile, $lamination );
}
