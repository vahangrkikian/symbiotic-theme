<?php
/**
 * Product Options CRUD — manages variables, items, and filters.
 *
 * @package SqftPricing
 */

defined( 'ABSPATH' ) || exit;

class Sqft_Product_Options {

	/**
	 * Get all variables for a product, ordered by sort_order.
	 */
	public static function get_variables( int $product_id ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'sqft_variables';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE product_id = %d ORDER BY sort_order ASC, id ASC",
				$product_id
			),
			ARRAY_A
		);

		foreach ( $rows as &$row ) {
			$row['config'] = json_decode( $row['config'] ?? '{}', true ) ?: [];
			$row['items']  = self::get_items( (int) $row['id'] );
		}

		return $rows ?: [];
	}

	/**
	 * Get all items for a variable.
	 */
	public static function get_items( int $variable_id ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'sqft_variable_items';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE variable_id = %d ORDER BY sort_order ASC, id ASC",
				$variable_id
			),
			ARRAY_A
		);

		foreach ( $rows as &$row ) {
			$row['config']  = json_decode( $row['config'] ?? '{}', true ) ?: [];
			$row['filters'] = self::get_item_filters( (int) $row['id'] );
		}

		return $rows ?: [];
	}

	/**
	 * Get filters for an item.
	 */
	public static function get_item_filters( int $item_id ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'sqft_item_filters';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE item_id = %d",
				$item_id
			),
			ARRAY_A
		) ?: [];
	}

	/**
	 * Save complete product configuration (variables + items + filters).
	 * Replaces all existing data for this product.
	 */
	public static function save_product_config( int $product_id, array $variables_data ): bool {
		global $wpdb;

		// Delete existing data for this product.
		self::delete_product_config( $product_id );

		foreach ( $variables_data as $var_order => $var ) {
			$var_id = self::insert_variable( $product_id, $var, $var_order );
			if ( ! $var_id ) {
				continue;
			}

			$items = $var['items'] ?? [];
			foreach ( $items as $item_order => $item ) {
				$item_id = self::insert_item( $var_id, $item, $item_order );
				if ( ! $item_id ) {
					continue;
				}

				$filters = $item['filters'] ?? [];
				foreach ( $filters as $filter ) {
					self::insert_filter( $item_id, $filter );
				}
			}
		}

		// Store the pricing formula.
		$formula = $variables_data['_formula'] ?? '';
		if ( $formula ) {
			update_post_meta( $product_id, '_sqft_formula', sanitize_textarea_field( $formula ) );
		}

		// Clear caches.
		wp_cache_delete( "sqft_product_{$product_id}", 'sqft_pricing' );

		return true;
	}

	/**
	 * Insert a variable.
	 */
	private static function insert_variable( int $product_id, array $var, int $order ): int {
		global $wpdb;

		$config = $var['config'] ?? [];

		$wpdb->insert(
			$wpdb->prefix . 'sqft_variables',
			[
				'product_id' => $product_id,
				'slug'       => sanitize_key( $var['slug'] ?? '' ),
				'label'      => sanitize_text_field( $var['label'] ?? '' ),
				'var_type'   => sanitize_key( $var['var_type'] ?? 'list' ),
				'sort_order' => $order,
				'config'     => wp_json_encode( $config ),
			],
			[ '%d', '%s', '%s', '%s', '%d', '%s' ]
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Insert an item.
	 */
	private static function insert_item( int $variable_id, array $item, int $order ): int {
		global $wpdb;

		$config = $item['config'] ?? [];

		$wpdb->insert(
			$wpdb->prefix . 'sqft_variable_items',
			[
				'variable_id'   => $variable_id,
				'label'         => sanitize_text_field( $item['label'] ?? '' ),
				'value_numeric' => floatval( $item['value_numeric'] ?? 0 ),
				'base_cost'     => floatval( $item['base_cost'] ?? 0 ),
				'is_default'    => absint( $item['is_default'] ?? 0 ),
				'is_hidden'     => absint( $item['is_hidden'] ?? 0 ),
				'sort_order'    => $order,
				'config'        => wp_json_encode( $config ),
			],
			[ '%d', '%s', '%f', '%f', '%d', '%d', '%d', '%s' ]
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Insert a filter dependency.
	 */
	private static function insert_filter( int $item_id, array $filter ): int {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'sqft_item_filters',
			[
				'item_id'                 => $item_id,
				'depends_on_variable_id'  => absint( $filter['depends_on_variable_id'] ?? 0 ),
				'depends_on_item_id'      => absint( $filter['depends_on_item_id'] ?? 0 ),
			],
			[ '%d', '%d', '%d' ]
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Delete all configuration for a product.
	 */
	public static function delete_product_config( int $product_id ): void {
		global $wpdb;

		$var_table    = $wpdb->prefix . 'sqft_variables';
		$item_table   = $wpdb->prefix . 'sqft_variable_items';
		$filter_table = $wpdb->prefix . 'sqft_item_filters';

		// Get variable IDs.
		$var_ids = $wpdb->get_col(
			$wpdb->prepare( "SELECT id FROM {$var_table} WHERE product_id = %d", $product_id )
		);

		if ( ! empty( $var_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $var_ids ), '%d' ) );

			// Get item IDs.
			$item_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT id FROM {$item_table} WHERE variable_id IN ({$placeholders})",
					...$var_ids
				)
			);

			// Delete filters.
			if ( ! empty( $item_ids ) ) {
				$item_placeholders = implode( ',', array_fill( 0, count( $item_ids ), '%d' ) );
				$wpdb->query(
					$wpdb->prepare(
						"DELETE FROM {$filter_table} WHERE item_id IN ({$item_placeholders})",
						...$item_ids
					)
				);
			}

			// Delete items.
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$item_table} WHERE variable_id IN ({$placeholders})",
					...$var_ids
				)
			);

			// Delete variables.
			$wpdb->delete( $var_table, [ 'product_id' => $product_id ], [ '%d' ] );
		}
	}

	/**
	 * Get complete product config as a structured array for frontend/API use.
	 */
	public static function get_product_config( int $product_id ): array {
		$cached = wp_cache_get( "sqft_product_{$product_id}", 'sqft_pricing' );
		if ( false !== $cached ) {
			return $cached;
		}

		$variables = self::get_variables( $product_id );
		$formula   = get_post_meta( $product_id, '_sqft_formula', true ) ?: '';
		$min_price = floatval( get_post_meta( $product_id, '_sqft_min_price', true ) );

		$config = [
			'product_id' => $product_id,
			'formula'    => $formula,
			'min_price'  => $min_price,
			'variables'  => $variables,
		];

		wp_cache_set( "sqft_product_{$product_id}", $config, 'sqft_pricing', 3600 );

		return $config;
	}

	/**
	 * Check if a product has sqft pricing configured.
	 */
	public static function has_config( int $product_id ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'sqft_variables';

		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE product_id = %d",
				$product_id
			)
		);
	}

	/**
	 * Get variable types available for configuration.
	 */
	public static function get_variable_types(): array {
		return [
			'list'           => __( 'Dropdown List', 'sqft-pricing' ),
			'radio'          => __( 'Radio Buttons', 'sqft-pricing' ),
			'card'           => __( 'Card Selection', 'sqft-pricing' ),
			'pill'           => __( 'Pill Buttons', 'sqft-pricing' ),
			'quantity_tiers' => __( 'Quantity Tiers', 'sqft-pricing' ),
			'turnaround'     => __( 'Turnaround Options', 'sqft-pricing' ),
			'size'           => __( 'Size Selector', 'sqft-pricing' ),
			'material_card'  => __( 'Material Card (with image)', 'sqft-pricing' ),
		];
	}
}
