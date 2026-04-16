<?php
/**
 * Symbiotic Theme — functions.php
 */
defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// WooCommerce dependency enforcement (3 enforcement points)
// ---------------------------------------------------------------------------
function symbiotic_check_woocommerce(): void {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function (): void {
            echo '<div class="notice notice-error is-dismissible"><p>';
            echo '<strong>' . esc_html__( 'Symbiotic Theme requires WooCommerce.', 'symbiotic-theme' ) . '</strong> ';
            echo esc_html__( 'Please ', 'symbiotic-theme' );
            echo '<a href="' . esc_url( admin_url( 'plugin-install.php?s=woocommerce&tab=search&type=term' ) ) . '">';
            echo esc_html__( 'install and activate WooCommerce', 'symbiotic-theme' );
            echo '</a>';
            echo esc_html__( ' to use this theme.', 'symbiotic-theme' );
            echo '</p></div>';
        } );

        if ( get_stylesheet() === 'symbiotic-theme' ) {
            switch_theme( WP_DEFAULT_THEME );
            wp_die(
                esc_html__( 'Symbiotic Theme requires WooCommerce to be installed and activated. Please install WooCommerce first, then re-activate Symbiotic Theme.', 'symbiotic-theme' ),
                esc_html__( 'Theme Activation Error', 'symbiotic-theme' ),
                [ 'back_link' => true ]
            );
        }
    }
}
add_action( 'after_setup_theme', 'symbiotic_check_woocommerce' );
add_action( 'switch_theme', function ( string $new_name ): void {
    if ( $new_name === 'Symbiotic Theme' ) {
        symbiotic_check_woocommerce();
    }
} );

// ---------------------------------------------------------------------------
// Theme setup
// ---------------------------------------------------------------------------
function symbiotic_theme_setup(): void {
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'woocommerce' );
    add_theme_support( 'wc-product-gallery-zoom' );
    add_theme_support( 'wc-product-gallery-lightbox' );
    add_theme_support( 'wc-product-gallery-slider' );
    load_theme_textdomain( 'symbiotic-theme', get_template_directory() . '/languages' );
}
add_action( 'after_setup_theme', 'symbiotic_theme_setup' );

// ---------------------------------------------------------------------------
// Include class files
// ---------------------------------------------------------------------------
require_once get_template_directory() . '/inc/class-symbiotic-wc-check.php';
require_once get_template_directory() . '/inc/class-symbiotic-assets.php';
require_once get_template_directory() . '/inc/class-symbiotic-rest.php';
require_once get_template_directory() . '/inc/class-symbiotic-admin.php';
require_once get_template_directory() . '/inc/class-symbiotic-product-importer.php';

Symbiotic_WC_Check::init();
Symbiotic_Assets::init();
Symbiotic_REST::init();
Symbiotic_Admin::init();
Symbiotic_Product_Importer::init();

// Enqueue page styles for non-React pages
add_action( 'wp_enqueue_scripts', function (): void {
	if ( ! is_front_page() && ! is_shop() && ! is_woocommerce() ) {
		wp_enqueue_style(
			'symbiotic-pages',
			get_template_directory_uri() . '/assets/page-styles.css',
			[],
			filemtime( get_template_directory() . '/assets/page-styles.css' )
		);
	}
} );

// Set up blog page
add_action( 'after_setup_theme', function (): void {
	add_theme_support( 'post-thumbnails' );
} , 20 );

// ---------------------------------------------------------------------------
// Suppress WooCommerce default frontend styles (React handles all UI)
// ---------------------------------------------------------------------------
function symbiotic_suppress_wc_styles(): void {
    add_filter( 'woocommerce_enqueue_styles', '__return_empty_array' );
}
add_action( 'wp_enqueue_scripts', 'symbiotic_suppress_wc_styles', 1 );
