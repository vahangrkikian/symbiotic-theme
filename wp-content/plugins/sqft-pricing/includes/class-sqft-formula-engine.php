<?php
/**
 * Formula Engine — evaluates pricing formulas with variable substitution.
 *
 * Supports the Axiomprint-style formula syntax:
 * - Variable references: VariableName, VariableName$base, Size$w, Size$h
 * - Math functions: floor(), round(), sqrt(), ceil()
 * - Arithmetic: +, -, *, /
 * - Sheet imposition: floor((SheetW * SheetH) / ((Size$w + bleed) * (Size$h + bleed)))
 * - Turnaround multiplier: * Turnaround (applied after subtotal)
 * - Turnaround base: + Turnaround$base (added after multiplier)
 *
 * Security: uses a safe tokenized evaluator, NOT eval().
 *
 * @package SqftPricing
 */

defined( 'ABSPATH' ) || exit;

class Sqft_Formula_Engine {

	/**
	 * Evaluate a pricing formula with given variable values.
	 *
	 * @param string $formula    The formula string.
	 * @param array  $values     Map of variable_slug => selected item data.
	 *                           Each entry: ['value' => float, 'base' => float, 'config' => array]
	 * @param array  $context    Additional context: 'quantity', 'versions_count', etc.
	 *
	 * @return array ['price' => float, 'breakdown' => array]
	 */
	public static function evaluate( string $formula, array $values, array $context = [] ): array {
		if ( empty( $formula ) ) {
			return [ 'price' => 0.0, 'breakdown' => [ 'error' => 'Empty formula' ] ];
		}

		$breakdown = [];

		// Build the substitution map.
		$subs = self::build_substitution_map( $values, $context );
		$breakdown['substitutions'] = $subs;

		// Replace variable tokens in formula.
		$expression = self::substitute_variables( $formula, $subs );
		$breakdown['expression'] = $expression;

		// Evaluate the safe math expression.
		$result = self::safe_eval( $expression );
		$breakdown['raw_result'] = $result;

		// Apply minimum price.
		$min_price = floatval( $context['min_price'] ?? 0 );
		if ( $min_price > 0 && $result < $min_price ) {
			$result = $min_price;
			$breakdown['floor_applied'] = true;
		}

		$final = round( $result, 2 );
		$breakdown['final_price'] = $final;

		return [
			'price'     => $final,
			'breakdown' => $breakdown,
		];
	}

	/**
	 * Build the variable substitution map.
	 *
	 * Registers each variable under both its original slug AND
	 * a PascalCase form to handle case mismatches between DB slugs and formula tokens.
	 * E.g. slug "print_mode" also registers as "Print_Mode".
	 */
	private static function build_substitution_map( array $values, array $context ): array {
		$subs = [];

		foreach ( $values as $slug => $data ) {
			$value = floatval( $data['value'] ?? 0 );
			$base  = floatval( $data['base'] ?? 0 );

			// Register under original slug.
			$subs[ $slug ]           = $value;
			$subs[ $slug . '$base' ] = $base;

			// Also register PascalCase form: "print_mode" → "Print_Mode".
			$pascal = preg_replace_callback( '/(^|_)([a-z])/', fn( $m ) => $m[1] . strtoupper( $m[2] ), $slug );
			if ( $pascal !== $slug ) {
				$subs[ $pascal ]           = $value;
				$subs[ $pascal . '$base' ] = $base;
			}

			// Also register UPPER variant of each segment for abbreviations:
			// "raised_spot_uv" → "Raised_Spot_UV" (2-3 letter segments get uppercased)
			$upper_parts = array_map( function ( $part ) {
				return strlen( $part ) <= 3 ? strtoupper( $part ) : ucfirst( $part );
			}, explode( '_', $slug ) );
			$upper_variant = implode( '_', $upper_parts );
			if ( $upper_variant !== $slug && $upper_variant !== $pascal ) {
				$subs[ $upper_variant ]           = $value;
				$subs[ $upper_variant . '$base' ] = $base;
			}

			// Size-specific: extract width, height, depth.
			if ( strtolower( $slug ) === 'size' ) {
				$config = $data['config'] ?? [];
				$w = floatval( $config['width'] ?? 0 );
				$h = floatval( $config['height'] ?? 0 );
				$d = floatval( $config['depth'] ?? 0 );
				$subs['Size$w'] = $w;
				$subs['Size$h'] = $h;
				$subs['Size$d'] = $d;
				$subs['size$w'] = $w;
				$subs['size$h'] = $h;
				$subs['size$d'] = $d;
			}
		}

		// Context: Quantity (use label-parsed number, not value_numeric).
		$qty = floatval( $context['quantity'] ?? 1 );
		$subs['Quantity']      = $qty;
		$subs['quantity']      = $qty;

		// Quantity base: try both cases.
		$qty_base = floatval( $values['Quantity']['base'] ?? $values['quantity']['base'] ?? 0 );
		$subs['Quantity$base'] = $qty_base;
		$subs['quantity$base'] = $qty_base;

		$subs['$versionsCount'] = intval( $context['versions_count'] ?? 1 );

		return $subs;
	}

	/**
	 * Substitute variable tokens in the formula string.
	 * Processes longer tokens first to prevent partial matches.
	 */
	private static function substitute_variables( string $formula, array $subs ): string {
		// Sort by key length descending to prevent partial matches.
		uksort( $subs, function ( $a, $b ) {
			return strlen( $b ) - strlen( $a );
		} );

		// Replace $versionsCount with actual numeric expression.
		$formula = str_replace( '$versionsCount', '*0 + ' . ( $subs['$versionsCount'] ?? 1 ), $formula );
		unset( $subs['$versionsCount'] );

		foreach ( $subs as $token => $value ) {
			$formula = str_replace( $token, (string) $value, $formula );
		}

		// Normalize function calls: remove spaces before parentheses.
		$formula = preg_replace( '/(floor|round|ceil|sqrt|abs)\s+\(/i', '$1(', $formula );

		return $formula;
	}

	/**
	 * Safely evaluate a math expression without eval().
	 *
	 * Supports: numbers, +, -, *, /, (), floor(), round(), sqrt(), ceil()
	 */
	public static function safe_eval( string $expression ): float {
		// Clean the expression.
		$expr = trim( $expression );

		// Replace math function names.
		$expr = str_ireplace(
			[ 'floor', 'round', 'sqrt', 'ceil', 'abs', 'min', 'max' ],
			[ 'FLOOR', 'ROUND', 'SQRT', 'CEIL', 'ABS', 'MIN', 'MAX' ],
			$expr
		);

		// Validate: only allow safe characters.
		$safe = preg_replace( '/[^0-9+\-*\/().,%\s]/', '', str_ireplace(
			[ 'FLOOR', 'ROUND', 'SQRT', 'CEIL', 'ABS', 'MIN', 'MAX' ],
			[ '', '', '', '', '', '', '' ],
			$expr
		) );

		// If cleaning removed significant characters, expression is invalid.
		if ( strlen( $safe ) < strlen( $expr ) * 0.5 ) {
			return 0.0;
		}

		// Tokenize and evaluate.
		try {
			$result = self::parse_expression( $expr, 0 );
			return is_finite( $result['value'] ) ? $result['value'] : 0.0;
		} catch ( \Throwable $e ) {
			return 0.0;
		}
	}

	/**
	 * Recursive descent parser for math expressions.
	 */
	private static function parse_expression( string $expr, int $pos ): array {
		$result = self::parse_term( $expr, $pos );
		$value  = $result['value'];
		$pos    = $result['pos'];

		while ( $pos < strlen( $expr ) ) {
			self::skip_whitespace( $expr, $pos );
			if ( $pos >= strlen( $expr ) ) break;

			$ch = $expr[ $pos ];
			if ( $ch === '+' ) {
				$pos++;
				$right = self::parse_term( $expr, $pos );
				$value += $right['value'];
				$pos = $right['pos'];
			} elseif ( $ch === '-' ) {
				$pos++;
				$right = self::parse_term( $expr, $pos );
				$value -= $right['value'];
				$pos = $right['pos'];
			} else {
				break;
			}
		}

		return [ 'value' => $value, 'pos' => $pos ];
	}

	private static function parse_term( string $expr, int $pos ): array {
		$result = self::parse_factor( $expr, $pos );
		$value  = $result['value'];
		$pos    = $result['pos'];

		while ( $pos < strlen( $expr ) ) {
			self::skip_whitespace( $expr, $pos );
			if ( $pos >= strlen( $expr ) ) break;

			$ch = $expr[ $pos ];
			if ( $ch === '*' ) {
				$pos++;
				$right = self::parse_factor( $expr, $pos );
				$value *= $right['value'];
				$pos = $right['pos'];
			} elseif ( $ch === '/' ) {
				$pos++;
				$right = self::parse_factor( $expr, $pos );
				$value = ( $right['value'] != 0 ) ? $value / $right['value'] : 0;
				$pos = $right['pos'];
			} else {
				break;
			}
		}

		return [ 'value' => $value, 'pos' => $pos ];
	}

	private static function parse_factor( string $expr, int $pos ): array {
		self::skip_whitespace( $expr, $pos );

		if ( $pos >= strlen( $expr ) ) {
			return [ 'value' => 0, 'pos' => $pos ];
		}

		// Check for math functions.
		foreach ( [ 'FLOOR', 'ROUND', 'SQRT', 'CEIL', 'ABS' ] as $func ) {
			if ( substr( $expr, $pos, strlen( $func ) ) === $func ) {
				$pos += strlen( $func );
				self::skip_whitespace( $expr, $pos );
				if ( $pos < strlen( $expr ) && $expr[ $pos ] === '(' ) {
					$pos++;
					$inner = self::parse_expression( $expr, $pos );
					$pos = $inner['pos'];
					self::skip_whitespace( $expr, $pos );
					if ( $pos < strlen( $expr ) && $expr[ $pos ] === ')' ) $pos++;

					$value = match ( $func ) {
						'FLOOR' => floor( $inner['value'] ),
						'ROUND' => round( $inner['value'] ),
						'SQRT'  => sqrt( abs( $inner['value'] ) ),
						'CEIL'  => ceil( $inner['value'] ),
						'ABS'   => abs( $inner['value'] ),
					};

					return [ 'value' => $value, 'pos' => $pos ];
				}
			}
		}

		// Unary minus.
		if ( $expr[ $pos ] === '-' ) {
			$pos++;
			$result = self::parse_factor( $expr, $pos );
			return [ 'value' => -$result['value'], 'pos' => $result['pos'] ];
		}

		// Unary plus.
		if ( $expr[ $pos ] === '+' ) {
			$pos++;
			return self::parse_factor( $expr, $pos );
		}

		// Parenthesized expression.
		if ( $expr[ $pos ] === '(' ) {
			$pos++;
			$result = self::parse_expression( $expr, $pos );
			$pos = $result['pos'];
			self::skip_whitespace( $expr, $pos );
			if ( $pos < strlen( $expr ) && $expr[ $pos ] === ')' ) $pos++;
			return [ 'value' => $result['value'], 'pos' => $pos ];
		}

		// Number.
		$start = $pos;
		while ( $pos < strlen( $expr ) && ( ctype_digit( $expr[ $pos ] ) || $expr[ $pos ] === '.' ) ) {
			$pos++;
		}

		if ( $pos > $start ) {
			return [ 'value' => floatval( substr( $expr, $start, $pos - $start ) ), 'pos' => $pos ];
		}

		// Unknown token — skip and return 0.
		$pos++;
		return [ 'value' => 0, 'pos' => $pos ];
	}

	private static function skip_whitespace( string $expr, int &$pos ): void {
		while ( $pos < strlen( $expr ) && ctype_space( $expr[ $pos ] ) ) {
			$pos++;
		}
	}

	/**
	 * Calculate price with quantity interpolation for custom quantities.
	 *
	 * When quantity falls between two tiers, linearly interpolate.
	 */
	public static function interpolate_quantity_price(
		string $formula,
		array $values,
		array $context,
		array $quantity_tiers
	): array {
		$qty = floatval( $context['quantity'] ?? 1 );

		// Sort tiers by quantity.
		usort( $quantity_tiers, fn( $a, $b ) => $a['qty'] - $b['qty'] );

		// Find surrounding tiers.
		$lower = null;
		$upper = null;

		foreach ( $quantity_tiers as $tier ) {
			if ( $tier['qty'] <= $qty ) {
				$lower = $tier;
			}
			if ( $tier['qty'] >= $qty && $upper === null ) {
				$upper = $tier;
			}
		}

		if ( ! $lower ) $lower = $quantity_tiers[0] ?? null;
		if ( ! $upper ) $upper = end( $quantity_tiers ) ?: null;

		if ( ! $lower || ! $upper || $lower['qty'] === $upper['qty'] ) {
			// Exact match or edge case — evaluate directly.
			$values['Quantity']['base'] = $lower['base'] ?? 0;
			$context['quantity']        = $qty;
			return self::evaluate( $formula, $values, $context );
		}

		// Calculate price at lower tier.
		$values_lower = $values;
		$values_lower['Quantity']['base'] = $lower['base'];
		$context_lower = $context;
		$context_lower['quantity'] = $lower['qty'];
		$result_lower = self::evaluate( $formula, $values_lower, $context_lower );

		// Calculate price at upper tier.
		$values_upper = $values;
		$values_upper['Quantity']['base'] = $upper['base'];
		$context_upper = $context;
		$context_upper['quantity'] = $upper['qty'];
		$result_upper = self::evaluate( $formula, $values_upper, $context_upper );

		// Linear interpolation.
		$range = $upper['qty'] - $lower['qty'];
		$ratio = ( $qty - $lower['qty'] ) / $range;
		$price = $result_lower['price'] + ( $result_upper['price'] - $result_lower['price'] ) * $ratio;

		return [
			'price'     => round( $price, 2 ),
			'breakdown' => [
				'interpolated'  => true,
				'lower_tier'    => $lower['qty'],
				'upper_tier'    => $upper['qty'],
				'lower_price'   => $result_lower['price'],
				'upper_price'   => $result_upper['price'],
				'ratio'         => round( $ratio, 4 ),
				'final_price'   => round( $price, 2 ),
			],
		];
	}
}
