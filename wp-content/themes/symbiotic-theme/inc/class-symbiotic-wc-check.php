<?php
defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce dependency enforcement class.
 */
class Symbiotic_WC_Check {

    public static function init(): void {
        add_action( 'after_setup_theme', [ __CLASS__, 'check' ], 5 );
        add_action( 'switch_theme',      [ __CLASS__, 'on_activation' ], 10, 2 );
    }

    public static function check(): void {
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', [ __CLASS__, 'show_admin_notice' ] );
            if ( get_stylesheet() === 'symbiotic-theme' ) {
                self::deactivate_theme();
            }
        }
    }

    public static function show_admin_notice(): void {
        echo '<div class="notice notice-error is-dismissible"><p>';
        echo '<strong>' . esc_html__( 'Symbiotic Theme', 'symbiotic-theme' ) . '</strong> ';
        echo esc_html__( 'requires WooCommerce to be installed and activated.', 'symbiotic-theme' );
        echo ' <a href="' . esc_url( admin_url( 'plugin-install.php?s=woocommerce&tab=search&type=term' ) ) . '">';
        echo esc_html__( 'Install WooCommerce', 'symbiotic-theme' );
        echo '</a>';
        echo '</p></div>';
    }

    public static function deactivate_theme(): void {
        switch_theme( WP_DEFAULT_THEME );
        wp_die(
            esc_html__( 'Symbiotic Theme requires WooCommerce to be installed and activated. Please install WooCommerce first, then re-activate Symbiotic Theme.', 'symbiotic-theme' ),
            esc_html__( 'Theme Activation Error', 'symbiotic-theme' ),
            [ 'back_link' => true ]
        );
    }

    public static function on_activation( string $old_theme, WP_Theme $new_theme ): void {
        if ( $new_theme->get_stylesheet() === 'symbiotic-theme' ) {
            self::check();
        }
    }
}
