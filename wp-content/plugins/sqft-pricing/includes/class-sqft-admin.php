<?php
/**
 * Admin — meta box, option builder, AJAX save.
 *
 * @package SqftPricing
 */

defined( 'ABSPATH' ) || exit;

class Sqft_Admin {

	public static function init(): void {
		add_action( 'add_meta_boxes', [ __CLASS__, 'register_meta_box' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
		add_action( 'save_post_product', [ __CLASS__, 'save_meta' ] );
	}

	public static function register_meta_box(): void {
		add_meta_box(
			'sqft_pricing_options',
			__( 'Print Product Calculator', 'sqft-pricing' ),
			[ __CLASS__, 'render_meta_box' ],
			'product',
			'normal',
			'high'
		);
	}

	public static function enqueue_assets( string $hook ): void {
		global $post_type;
		if ( ( $hook !== 'post.php' && $hook !== 'post-new.php' ) || $post_type !== 'product' ) {
			return;
		}

		wp_enqueue_style( 'sqft-admin', SQFT_URL . 'assets/css/admin.css', [], SQFT_VERSION );
		wp_enqueue_script( 'sqft-admin', SQFT_URL . 'assets/js/admin-options.js', [ 'jquery', 'jquery-ui-sortable' ], SQFT_VERSION, true );
		wp_enqueue_media();

		wp_localize_script( 'sqft-admin', 'sqftAdmin', [
			'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
			'nonce'         => wp_create_nonce( 'sqft_admin_nonce' ),
			'variableTypes' => Sqft_Product_Options::get_variable_types(),
			'i18n'          => [
				'addVariable'    => __( 'Add Option Group', 'sqft-pricing' ),
				'addItem'        => __( 'Add Choice', 'sqft-pricing' ),
				'removeVariable' => __( 'Remove this option group?', 'sqft-pricing' ),
				'removeItem'     => __( 'Remove this choice?', 'sqft-pricing' ),
				'saving'         => __( 'Saving...', 'sqft-pricing' ),
				'saved'          => __( 'Saved!', 'sqft-pricing' ),
				'error'          => __( 'Error saving.', 'sqft-pricing' ),
				'previewCalc'    => __( 'Calculating...', 'sqft-pricing' ),
			],
		] );
	}

	public static function render_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'sqft_save_meta', 'sqft_meta_nonce' );

		$product_id = $post->ID;
		$config     = Sqft_Product_Options::get_product_config( $product_id );
		$variables  = $config['variables'];
		$formula    = $config['formula'];
		$min_price  = $config['min_price'];
		$enabled    = get_post_meta( $product_id, '_sqft_calculator_enabled', true );

		$var_types = Sqft_Product_Options::get_variable_types();

		include SQFT_PATH . 'templates/admin-product-options.php';
	}

	public static function save_meta( int $post_id ): void {
		if ( ! isset( $_POST['sqft_meta_nonce'] ) || ! wp_verify_nonce( $_POST['sqft_meta_nonce'], 'sqft_save_meta' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Save calculator enabled toggle.
		$enabled = isset( $_POST['_sqft_calculator_enabled'] ) ? '1' : '';
		update_post_meta( $post_id, '_sqft_calculator_enabled', $enabled );

		// Save formula.
		if ( isset( $_POST['_sqft_formula'] ) ) {
			update_post_meta( $post_id, '_sqft_formula', sanitize_textarea_field( wp_unslash( $_POST['_sqft_formula'] ) ) );
		}

		// Save min price.
		if ( isset( $_POST['_sqft_min_price'] ) ) {
			update_post_meta( $post_id, '_sqft_min_price', floatval( $_POST['_sqft_min_price'] ) );
		}

		// Parse and save variables from the repeater form.
		if ( isset( $_POST['sqft_var'] ) && is_array( $_POST['sqft_var'] ) ) {
			$variables_data = self::parse_posted_variables( $_POST['sqft_var'] );
			Sqft_Product_Options::save_product_config( $post_id, $variables_data );
		}
	}

	/**
	 * Parse the posted variable/item structure from the repeater form.
	 */
	private static function parse_posted_variables( array $posted ): array {
		$variables = [];

		foreach ( $posted as $v_idx => $v_data ) {
			if ( ! is_numeric( $v_idx ) ) {
				continue;
			}

			$variable = [
				'slug'     => sanitize_key( $v_data['slug'] ?? '' ),
				'label'    => sanitize_text_field( $v_data['label'] ?? '' ),
				'var_type' => sanitize_key( $v_data['var_type'] ?? 'list' ),
				'config'   => [],
				'items'    => [],
			];

			// Variable-level config.
			if ( ! empty( $v_data['config'] ) && is_array( $v_data['config'] ) ) {
				$variable['config'] = array_map( 'sanitize_text_field', $v_data['config'] );
			}

			// Items.
			if ( ! empty( $v_data['items'] ) && is_array( $v_data['items'] ) ) {
				foreach ( $v_data['items'] as $i_idx => $i_data ) {
					if ( ! is_numeric( $i_idx ) ) {
						continue;
					}

					$item = [
						'label'         => sanitize_text_field( $i_data['label'] ?? '' ),
						'value_numeric' => floatval( $i_data['value_numeric'] ?? 0 ),
						'base_cost'     => floatval( $i_data['base_cost'] ?? 0 ),
						'is_default'    => absint( $i_data['is_default'] ?? 0 ),
						'is_hidden'     => absint( $i_data['is_hidden'] ?? 0 ),
						'config'        => [],
						'filters'       => [],
					];

					// Item config (image_url, width, height, etc.).
					if ( ! empty( $i_data['config'] ) && is_array( $i_data['config'] ) ) {
						$item['config'] = array_map( 'sanitize_text_field', $i_data['config'] );
					}

					// Filters (dependencies).
					if ( ! empty( $i_data['filters'] ) && is_array( $i_data['filters'] ) ) {
						foreach ( $i_data['filters'] as $f_data ) {
							if ( ! empty( $f_data['depends_on_variable_slug'] ) && ! empty( $f_data['depends_on_item_label'] ) ) {
								$item['filters'][] = [
									'depends_on_variable_slug' => sanitize_key( $f_data['depends_on_variable_slug'] ),
									'depends_on_item_label'    => sanitize_text_field( $f_data['depends_on_item_label'] ),
								];
							}
						}
					}

					$variable['items'][] = $item;
				}
			}

			$variables[] = $variable;
		}

		return $variables;
	}
}
