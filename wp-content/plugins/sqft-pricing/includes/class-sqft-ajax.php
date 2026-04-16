<?php
/**
 * AJAX Handlers — admin formula testing and frontend price calculation.
 *
 * @package SqftPricing
 */

defined( 'ABSPATH' ) || exit;

class Sqft_Ajax {

	public static function init(): void {
		// Admin: evaluate formula in preview.
		add_action( 'wp_ajax_sqft_evaluate_formula', [ __CLASS__, 'evaluate_formula' ] );

		// Frontend: server-side price validation (optional AJAX call).
		add_action( 'wp_ajax_sqft_calculate_price', [ __CLASS__, 'calculate_price' ] );
		add_action( 'wp_ajax_nopriv_sqft_calculate_price', [ __CLASS__, 'calculate_price' ] );
	}

	/**
	 * Admin formula evaluation for the preview panel.
	 */
	public static function evaluate_formula(): void {
		check_ajax_referer( 'sqft_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'sqft-pricing' ) ], 403 );
		}

		$formula   = sanitize_textarea_field( wp_unslash( $_POST['formula'] ?? '' ) );
		$values    = json_decode( wp_unslash( $_POST['values'] ?? '{}' ), true );
		$min_price = floatval( $_POST['min_price'] ?? 0 );

		if ( empty( $formula ) ) {
			wp_send_json_error( [ 'message' => __( 'No formula provided.', 'sqft-pricing' ) ] );
		}

		if ( ! is_array( $values ) ) {
			$values = [];
		}

		// Build context.
		$context = [ 'min_price' => $min_price ];

		// Extract quantity if present.
		foreach ( $values as $slug => $data ) {
			if ( strtolower( $slug ) === 'quantity' ) {
				$context['quantity'] = floatval( $data['value'] ?? 1 );
				break;
			}
		}

		$result = Sqft_Formula_Engine::evaluate( $formula, $values, $context );

		wp_send_json_success( [
			'price'     => $result['price'],
			'breakdown' => $result['breakdown'],
		] );
	}

	/**
	 * Frontend server-side price calculation.
	 * Used for real-time price validation or when client JS is not trusted.
	 */
	public static function calculate_price(): void {
		check_ajax_referer( 'sqft_frontend_nonce', 'nonce' );

		$product_id = absint( $_POST['product_id'] ?? 0 );
		if ( ! $product_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid product ID.', 'sqft-pricing' ) ] );
		}

		$enabled = get_post_meta( $product_id, '_sqft_calculator_enabled', true );
		if ( $enabled !== '1' ) {
			wp_send_json_error( [ 'message' => __( 'Calculator not enabled for this product.', 'sqft-pricing' ) ] );
		}

		$selections = json_decode( wp_unslash( $_POST['selections'] ?? '{}' ), true );
		if ( ! is_array( $selections ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid selections.', 'sqft-pricing' ) ] );
		}

		$formula   = get_post_meta( $product_id, '_sqft_formula', true );
		$min_price = floatval( get_post_meta( $product_id, '_sqft_min_price', true ) );

		if ( empty( $formula ) ) {
			wp_send_json_error( [ 'message' => __( 'No pricing formula configured.', 'sqft-pricing' ) ] );
		}

		// Build context.
		$context = [ 'min_price' => $min_price ];
		$values  = [];

		foreach ( $selections as $slug => $data ) {
			$slug = sanitize_key( $slug );
			$values[ $slug ] = [
				'value'  => floatval( $data['value'] ?? 0 ),
				'base'   => floatval( $data['base'] ?? 0 ),
				'config' => isset( $data['config'] ) && is_array( $data['config'] )
					? array_map( 'sanitize_text_field', $data['config'] )
					: [],
			];

			if ( strtolower( $slug ) === 'quantity' ) {
				$context['quantity'] = floatval( $data['label'] ?? $data['value'] ?? 1 );
			}
		}

		$result = Sqft_Formula_Engine::evaluate( $formula, $values, $context );

		// Calculate per-unit price.
		$qty      = $context['quantity'] ?? 1;
		$per_unit = $qty > 1 ? round( $result['price'] / $qty, 2 ) : $result['price'];

		wp_send_json_success( [
			'price'    => $result['price'],
			'perUnit'  => $per_unit,
			'quantity' => $qty,
		] );
	}
}
