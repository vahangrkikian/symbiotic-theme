<?php
/**
 * CLI script to create DB tables and the example Business Cards product.
 *
 * Usage: php wp-content/plugins/sqft-pricing/run-setup.php
 * Run from the WordPress root directory.
 */

$_SERVER['HTTP_HOST']   = 'symbiotic-theme';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SERVER_NAME'] = 'symbiotic-theme';

require_once __DIR__ . '/../../../wp-load.php';

// Disable WC action scheduler shutdown hooks that cause CLI hangs.
remove_all_actions( 'shutdown' );

// Ensure setup file is loaded (may not be in CLI since is_admin() is false).
require_once __DIR__ . '/setup-example-product.php';

echo "WordPress loaded. Prefix: " . $GLOBALS['wpdb']->prefix . "\n";

// Step 1: Create tables.
echo "\n=== Creating DB Tables ===\n";
Sqft_Database::create_tables();

global $wpdb;
$tables = $wpdb->get_col( $wpdb->prepare(
	"SHOW TABLES LIKE %s",
	$wpdb->prefix . 'sqft%'
) );

if ( empty( $tables ) ) {
	// dbDelta may have failed, try direct SQL.
	echo "dbDelta did not create tables. Trying direct SQL...\n";

	$charset = $wpdb->get_charset_collate();

	$wpdb->query( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sqft_variables (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		product_id bigint(20) unsigned NOT NULL,
		slug varchar(100) NOT NULL DEFAULT '',
		label varchar(255) NOT NULL DEFAULT '',
		var_type varchar(50) NOT NULL DEFAULT 'list',
		sort_order int(11) NOT NULL DEFAULT 0,
		config longtext,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY product_id (product_id),
		KEY slug (slug)
	) {$charset}" );

	if ( $wpdb->last_error ) {
		echo "  Error: " . $wpdb->last_error . "\n";
	}

	$wpdb->query( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sqft_variable_items (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		variable_id bigint(20) unsigned NOT NULL,
		label varchar(255) NOT NULL DEFAULT '',
		value_numeric decimal(12,6) NOT NULL DEFAULT 0.000000,
		base_cost decimal(12,6) NOT NULL DEFAULT 0.000000,
		is_default tinyint(1) NOT NULL DEFAULT 0,
		is_hidden tinyint(1) NOT NULL DEFAULT 0,
		sort_order int(11) NOT NULL DEFAULT 0,
		config longtext,
		PRIMARY KEY (id),
		KEY variable_id (variable_id)
	) {$charset}" );

	if ( $wpdb->last_error ) {
		echo "  Error: " . $wpdb->last_error . "\n";
	}

	$wpdb->query( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sqft_item_filters (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		item_id bigint(20) unsigned NOT NULL,
		depends_on_variable_id bigint(20) unsigned NOT NULL,
		depends_on_item_id bigint(20) unsigned NOT NULL,
		PRIMARY KEY (id),
		KEY item_id (item_id),
		KEY depends_on_variable_id (depends_on_variable_id)
	) {$charset}" );

	if ( $wpdb->last_error ) {
		echo "  Error: " . $wpdb->last_error . "\n";
	}
}

// Verify tables.
$tables = $wpdb->get_col( "SHOW TABLES LIKE '{$wpdb->prefix}sqft%'" );
echo "Tables found: " . count( $tables ) . "\n";
foreach ( $tables as $t ) {
	echo "  - {$t}\n";
}

if ( count( $tables ) < 3 ) {
	echo "\nFAILED: Not all tables were created.\n";
	exit( 1 );
}

// Step 2: Create the example product.
echo "\n=== Creating Example Product ===\n";

// Check if product already exists.
$existing = get_posts( [
	'post_type'  => 'product',
	'title'      => 'Classic Business Cards',
	'post_status' => 'any',
	'numberposts' => 1,
] );

if ( ! empty( $existing ) ) {
	echo "Product already exists (ID: {$existing[0]->ID}). Cleaning config and reusing...\n";
	Sqft_Product_Options::delete_product_config( $existing[0]->ID );
	// Don't recreate — let the setup function create a new one.
}

$product_id = sqft_create_business_cards_product();

if ( is_wp_error( $product_id ) ) {
	echo "ERROR: " . $product_id->get_error_message() . "\n";
	exit( 1 );
}

echo "Product created! ID: {$product_id}\n";
echo "Edit: /wp-admin/post.php?post={$product_id}&action=edit\n";
echo "View: " . get_permalink( $product_id ) . "\n";

// Verify data.
$vars = Sqft_Product_Options::get_variables( $product_id );
echo "\nVariables created: " . count( $vars ) . "\n";
foreach ( $vars as $v ) {
	$items = count( $v['items'] );
	$filters = 0;
	foreach ( $v['items'] as $item ) {
		$filters += count( $item['filters'] );
	}
	echo "  [{$v['sort_order']}] {$v['label']} ({$v['slug']}) — {$items} items, {$filters} filters\n";
}

$formula = get_post_meta( $product_id, '_sqft_formula', true );
echo "\nFormula: " . substr( $formula, 0, 80 ) . "...\n";

echo "\n=== DONE ===\n";
