<?php
/**
 * Frontend — renders the product calculator on single product pages.
 *
 * @package SqftPricing
 */

defined( 'ABSPATH' ) || exit;

class Sqft_Frontend {

	public static function init(): void {
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
		add_action( 'woocommerce_before_add_to_cart_button', [ __CLASS__, 'render_calculator' ], 15 );
		add_filter( 'woocommerce_product_get_price', [ __CLASS__, 'filter_simple_product_price' ], 10, 2 );
	}

	public static function enqueue_assets(): void {
		if ( ! is_product() ) {
			return;
		}

		global $post;
		$product_id = $post->ID ?? 0;
		$enabled    = get_post_meta( $product_id, '_sqft_calculator_enabled', true );

		if ( $enabled !== '1' ) {
			return;
		}

		wp_enqueue_style(
			'sqft-frontend',
			SQFT_URL . 'assets/css/frontend.css',
			[],
			SQFT_VERSION
		);

		wp_enqueue_script(
			'sqft-frontend',
			SQFT_URL . 'assets/js/frontend-calc.js',
			[ 'jquery' ],
			SQFT_VERSION,
			true
		);

		// Get full product config.
		$config = Sqft_Product_Options::get_product_config( $product_id );

		wp_localize_script( 'sqft-frontend', 'sqftCalc', [
			'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
			'nonce'          => wp_create_nonce( 'sqft_frontend_nonce' ),
			'productId'      => $product_id,
			'formula'        => $config['formula'],
			'minPrice'       => $config['min_price'],
			'variables'      => self::prepare_variables_for_js( $config['variables'] ),
			'currencySymbol' => html_entity_decode( get_woocommerce_currency_symbol() ),
			'currencyPos'    => get_option( 'woocommerce_currency_pos', 'left' ),
			'i18n'           => [
				'addToCart'   => __( 'Add to Cart', 'sqft-pricing' ),
				'orderNow'   => __( 'Order Now', 'sqft-pricing' ),
				'estimatedAt' => __( 'Estimated ready', 'sqft-pricing' ),
				'perUnit'    => __( 'per unit', 'sqft-pricing' ),
				'total'      => __( 'Total', 'sqft-pricing' ),
				'calculating' => __( 'Calculating...', 'sqft-pricing' ),
			],
		] );
	}

	/**
	 * Prepare variables array for frontend JS consumption.
	 * Resolves DB-level IDs to slugs/labels so the frontend can filter by either.
	 */
	private static function prepare_variables_for_js( array $variables ): array {
		// Build lookup maps: variable ID → slug, item ID → label.
		$var_id_to_slug = [];
		$item_id_to_data = [];
		foreach ( $variables as $var ) {
			$var_id_to_slug[ (int) $var['id'] ] = $var['slug'];
			foreach ( $var['items'] as $item ) {
				$item_id_to_data[ (int) $item['id'] ] = [
					'label'    => $item['label'],
					'var_slug' => $var['slug'],
				];
			}
		}

		$output = [];

		foreach ( $variables as $var ) {
			$var_config = $var['config'] ?? [];
			$is_hidden  = ! empty( $var_config['hidden'] );

			$js_var = [
				'id'        => (int) $var['id'],
				'slug'      => $var['slug'],
				'label'     => $var['label'],
				'type'      => $var['var_type'],
				'config'    => $var_config,
				'isHidden'  => $is_hidden,
				'items'     => [],
			];

			foreach ( $var['items'] as $item ) {
				$js_item = [
					'id'        => (int) $item['id'],
					'label'     => $item['label'],
					'value'     => floatval( $item['value_numeric'] ),
					'base'      => floatval( $item['base_cost'] ),
					'isDefault' => (bool) $item['is_default'],
					'isHidden'  => (bool) $item['is_hidden'],
					'config'    => $item['config'],
					'filters'   => [],
				];

				foreach ( $item['filters'] as $filter ) {
					$dep_var_id  = (int) ( $filter['depends_on_variable_id'] ?? 0 );
					$dep_item_id = (int) ( $filter['depends_on_item_id'] ?? 0 );

					// Resolve IDs to slugs/labels.
					$dep_var_slug  = $filter['depends_on_variable_slug'] ?? ( $var_id_to_slug[ $dep_var_id ] ?? '' );
					$dep_item_label = $filter['depends_on_item_label'] ?? ( $item_id_to_data[ $dep_item_id ]['label'] ?? '' );

					$js_item['filters'][] = [
						'variableSlug' => $dep_var_slug,
						'itemId'       => $dep_item_id,
						'itemLabel'    => $dep_item_label,
					];
				}

				$js_var['items'][] = $js_item;
			}

			$output[] = $js_var;
		}

		return $output;
	}

	/**
	 * Render the calculator form on the product page.
	 */
	public static function render_calculator(): void {
		global $post;
		$product_id = $post->ID ?? 0;
		$enabled    = get_post_meta( $product_id, '_sqft_calculator_enabled', true );

		if ( $enabled !== '1' ) {
			return;
		}

		$config = Sqft_Product_Options::get_product_config( $product_id );
		if ( empty( $config['variables'] ) ) {
			return;
		}

		echo '<div id="sqft-calculator" class="sqft-calculator" data-product-id="' . esc_attr( $product_id ) . '">';
		echo '<div class="sqft-calc-options" id="sqft-calc-options"></div>';
		echo '<div class="sqft-calc-price-panel" id="sqft-calc-price-panel">';
		echo '<div class="sqft-price-display" id="sqft-price-display"></div>';
		echo '</div>';

		// Hidden inputs for cart submission.
		echo '<input type="hidden" name="sqft_selections" id="sqft-selections-input" value="">';
		echo '<input type="hidden" name="sqft_calculated_price" id="sqft-calculated-price-input" value="">';
		echo '</div>';
	}

	/**
	 * For simple products with calculator, return the calculated price.
	 */
	public static function filter_simple_product_price( $price, $product ): string {
		$product_id = $product->get_id();
		$enabled    = get_post_meta( $product_id, '_sqft_calculator_enabled', true );

		if ( $enabled !== '1' ) {
			return $price;
		}

		// Return empty to let the calculator handle display.
		return $price;
	}
}
