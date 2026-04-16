<?php
/**
 * Cart Integration — validates prices server-side and saves order meta.
 *
 * @package SqftPricing
 */

defined( 'ABSPATH' ) || exit;

class Sqft_Cart {

	public static function init(): void {
		add_filter( 'woocommerce_add_cart_item_data', [ __CLASS__, 'add_cart_item_data' ], 10, 2 );
		add_action( 'woocommerce_before_calculate_totals', [ __CLASS__, 'calculate_cart_totals' ], 10, 1 );
		add_filter( 'woocommerce_get_item_data', [ __CLASS__, 'display_cart_item_data' ], 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', [ __CLASS__, 'save_order_item_meta' ], 10, 4 );
	}

	/**
	 * Capture calculator selections when product is added to cart.
	 */
	public static function add_cart_item_data( array $cart_item_data, int $product_id ): array {
		$enabled = get_post_meta( $product_id, '_sqft_calculator_enabled', true );
		if ( $enabled !== '1' ) {
			return $cart_item_data;
		}

		// Get selections from the calculator form.
		if ( isset( $_POST['sqft_selections'] ) ) {
			$selections = json_decode( wp_unslash( $_POST['sqft_selections'] ), true );
			if ( is_array( $selections ) ) {
				// Sanitize all values.
				$clean = [];
				foreach ( $selections as $slug => $data ) {
					$clean[ sanitize_key( $slug ) ] = [
						'id'     => absint( $data['id'] ?? 0 ),
						'label'  => sanitize_text_field( $data['label'] ?? '' ),
						'value'  => floatval( $data['value'] ?? 0 ),
						'base'   => floatval( $data['base'] ?? 0 ),
						'config' => isset( $data['config'] ) && is_array( $data['config'] )
							? array_map( 'sanitize_text_field', $data['config'] )
							: [],
					];
				}
				$cart_item_data['sqft_selections'] = $clean;
			}
		}

		// Store the client-provided price for comparison (server recalculates).
		if ( isset( $_POST['sqft_calculated_price'] ) ) {
			$cart_item_data['sqft_client_price'] = floatval( $_POST['sqft_calculated_price'] );
		}

		// Make each configuration unique in the cart.
		$cart_item_data['sqft_unique_key'] = md5( wp_json_encode( $cart_item_data['sqft_selections'] ?? [] ) );

		return $cart_item_data;
	}

	/**
	 * Recalculate price server-side on every cart update.
	 * This is the security layer — client JS prices are never trusted.
	 */
	public static function calculate_cart_totals( $cart ): void {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		if ( did_action( 'woocommerce_before_calculate_totals' ) >= 2 ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			if ( empty( $cart_item['sqft_selections'] ) ) {
				continue;
			}

			$product    = $cart_item['data'];
			$product_id = $product->get_parent_id() ?: $product->get_id();
			$selections = $cart_item['sqft_selections'];

			// Get product formula.
			$formula   = get_post_meta( $product_id, '_sqft_formula', true );
			$min_price = floatval( get_post_meta( $product_id, '_sqft_min_price', true ) );

			if ( empty( $formula ) ) {
				continue;
			}

			// Build values map for the formula engine.
			$values  = [];
			$context = [ 'min_price' => $min_price ];

			foreach ( $selections as $slug => $data ) {
				$values[ $slug ] = [
					'value'  => floatval( $data['value'] ?? 0 ),
					'base'   => floatval( $data['base'] ?? 0 ),
					'config' => $data['config'] ?? [],
				];

				// Extract quantity from the quantity variable.
				if ( strtolower( $slug ) === 'quantity' ) {
					$context['quantity'] = floatval( $data['label'] ?? '' ) ?: floatval( $data['value'] ?? 1 );
				}
			}

			// Evaluate formula server-side.
			$result = Sqft_Formula_Engine::evaluate( $formula, $values, $context );

			if ( $result['price'] > 0 ) {
				$product->set_price( $result['price'] );
			}
		}
	}

	/**
	 * Display selected options in cart/checkout line items.
	 */
	public static function display_cart_item_data( array $item_data, array $cart_item ): array {
		if ( empty( $cart_item['sqft_selections'] ) ) {
			return $item_data;
		}

		$product_id = $cart_item['product_id'];
		$variables  = Sqft_Product_Options::get_variables( $product_id );

		// Create a slug-to-label map.
		$var_labels = [];
		foreach ( $variables as $var ) {
			$var_labels[ $var['slug'] ] = $var['label'];
		}

		foreach ( $cart_item['sqft_selections'] as $slug => $data ) {
			// Skip hidden or system variables.
			if ( empty( $data['label'] ) ) {
				continue;
			}

			$var_label = $var_labels[ $slug ] ?? ucfirst( str_replace( '_', ' ', $slug ) );

			$item_data[] = [
				'key'   => $var_label,
				'value' => $data['label'],
			];
		}

		return $item_data;
	}

	/**
	 * Save all selected options to the order item meta.
	 */
	public static function save_order_item_meta( $item, $cart_item_key, $values, $order ): void {
		if ( empty( $values['sqft_selections'] ) ) {
			return;
		}

		$product_id = $values['product_id'];
		$variables  = Sqft_Product_Options::get_variables( $product_id );

		$var_labels = [];
		foreach ( $variables as $var ) {
			$var_labels[ $var['slug'] ] = $var['label'];
		}

		foreach ( $values['sqft_selections'] as $slug => $data ) {
			if ( empty( $data['label'] ) ) {
				continue;
			}

			$var_label = $var_labels[ $slug ] ?? ucfirst( str_replace( '_', ' ', $slug ) );
			$item->add_meta_data( $var_label, $data['label'], true );
		}

		// Store the full selections JSON for reference.
		$item->add_meta_data( '_sqft_selections_raw', wp_json_encode( $values['sqft_selections'] ), true );
	}
}
