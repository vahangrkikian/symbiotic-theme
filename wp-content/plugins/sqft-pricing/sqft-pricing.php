<?php
/**
 * Plugin Name: Sqft Pricing — Dynamic Print Product Calculator
 * Plugin URI:  https://sketchsigns.com
 * Description: Axiomprint-style product configurator for WooCommerce. Formula-based pricing with configurable options, quantity tiers, turnaround multipliers, and dynamic option dependencies.
 * Version:     2.0.0
 * Author:      SketchSigns
 * License:     GPL-2.0-or-later
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * WC requires at least: 8.0
 * WC tested up to: 9.0
 * Text Domain: sqft-pricing
 */

defined( 'ABSPATH' ) || exit;

// ─── Constants ───────────────────────────────────────────────────────────────

define( 'SQFT_VERSION',  '2.0.0' );
define( 'SQFT_FILE',     __FILE__ );
define( 'SQFT_PATH',     plugin_dir_path( __FILE__ ) );
define( 'SQFT_URL',      plugin_dir_url( __FILE__ ) );
define( 'SQFT_BASENAME', plugin_basename( __FILE__ ) );

// ─── HPOS Compatibility ──────────────────────────────────────────────────────

add_action( 'before_woocommerce_init', function (): void {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			SQFT_FILE,
			true
		);
	}
} );

// ─── Autoloader ──────────────────────────────────────────────────────────────

spl_autoload_register( function ( string $class_name ): void {
	if ( strpos( $class_name, 'Sqft_' ) !== 0 ) {
		return;
	}
	$file = SQFT_PATH . 'includes/class-' . str_replace( '_', '-', strtolower( $class_name ) ) . '.php';
	if ( file_exists( $file ) ) {
		require_once $file;
	}
} );

// ─── Legacy includes (kept for backward compat) ─────────────────────────────

require_once SQFT_PATH . 'price-calc.php';

// ─── Setup Example (admin page) ─────────────────────────────────────────────

if ( is_admin() ) {
	require_once SQFT_PATH . 'setup-example-product.php';
}

// ─── Activation / Deactivation ───────────────────────────────────────────────

register_activation_hook( __FILE__, function (): void {
	Sqft_Database::create_tables();
	update_option( 'sqft_pricing_version', SQFT_VERSION );
	flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, function (): void {
	global $wpdb;
	$wpdb->query(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_sqft_%' OR option_name LIKE '_transient_timeout_sqft_%'"
	);
	flush_rewrite_rules();
} );

// ─── WooCommerce Dependency Check ────────────────────────────────────────────

add_action( 'admin_init', function (): void {
	if ( ! class_exists( 'WooCommerce' ) ) {
		deactivate_plugins( SQFT_BASENAME );
		add_action( 'admin_notices', function (): void {
			echo '<div class="notice notice-error"><p>';
			esc_html_e( 'Sqft Pricing requires WooCommerce to be installed and active.', 'sqft-pricing' );
			echo '</p></div>';
		} );
	}
} );

// ─── Initialize Plugin ──────────────────────────────────────────────────────

add_action( 'plugins_loaded', function (): void {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	// Core classes.
	Sqft_Admin::init();
	Sqft_Frontend::init();
	Sqft_Cart::init();
	Sqft_Ajax::init();
} );

// ─── Plugin Action Links ─────────────────────────────────────────────────────

add_filter( 'plugin_action_links_' . SQFT_BASENAME, function ( array $links ): array {
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		admin_url( 'edit.php?post_type=product' ),
		__( 'Products', 'sqft-pricing' )
	);
	array_unshift( $links, $settings_link );
	return $links;
} );
