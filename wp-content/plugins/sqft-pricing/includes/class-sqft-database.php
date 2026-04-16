<?php
/**
 * Database table creation and management.
 *
 * @package SqftPricing
 */

defined( 'ABSPATH' ) || exit;

class Sqft_Database {

	/**
	 * Create custom tables for product options storage.
	 *
	 * Note: dbDelta requires exact formatting:
	 * - No IF NOT EXISTS
	 * - Two spaces after PRIMARY KEY
	 * - KEY not INDEX
	 * - Each field on its own line
	 */
	public static function create_tables(): void {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();

		// Product variables (option groups).
		$sql_variables = "CREATE TABLE {$wpdb->prefix}sqft_variables (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			product_id bigint(20) unsigned NOT NULL,
			slug varchar(100) NOT NULL,
			label varchar(255) NOT NULL,
			var_type varchar(50) NOT NULL DEFAULT 'list',
			sort_order int(11) NOT NULL DEFAULT 0,
			config longtext,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY product_id (product_id),
			KEY slug (slug)
		) {$charset};";

		// Variable items (option choices).
		$sql_items = "CREATE TABLE {$wpdb->prefix}sqft_variable_items (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			variable_id bigint(20) unsigned NOT NULL,
			label varchar(255) NOT NULL,
			value_numeric decimal(12,6) NOT NULL DEFAULT 0.000000,
			base_cost decimal(12,6) NOT NULL DEFAULT 0.000000,
			is_default tinyint(1) NOT NULL DEFAULT 0,
			is_hidden tinyint(1) NOT NULL DEFAULT 0,
			sort_order int(11) NOT NULL DEFAULT 0,
			config longtext,
			PRIMARY KEY  (id),
			KEY variable_id (variable_id)
		) {$charset};";

		// Item filters (dependencies between items).
		$sql_filters = "CREATE TABLE {$wpdb->prefix}sqft_item_filters (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			item_id bigint(20) unsigned NOT NULL,
			depends_on_variable_id bigint(20) unsigned NOT NULL,
			depends_on_item_id bigint(20) unsigned NOT NULL,
			PRIMARY KEY  (id),
			KEY item_id (item_id),
			KEY depends_on_variable_id (depends_on_variable_id)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_variables );
		dbDelta( $sql_items );
		dbDelta( $sql_filters );
	}
}
