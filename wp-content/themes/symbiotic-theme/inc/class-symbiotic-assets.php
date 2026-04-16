<?php
defined( 'ABSPATH' ) || exit;

/**
 * Enqueues the Vite-built React bundle and localizes WordPress data.
 */
class Symbiotic_Assets {

    public static function init(): void {
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
    }

    public static function enqueue(): void {
        $dist_url  = get_template_directory_uri() . '/dist/';
        $dist_path = get_template_directory() . '/dist/';

        // Main CSS
        if ( file_exists( $dist_path . 'main.css' ) ) {
            wp_enqueue_style(
                'symbiotic-main',
                $dist_url . 'main.css',
                [],
                self::file_version( $dist_path . 'main.css' )
            );
        }

        // Main JS
        if ( file_exists( $dist_path . 'main.js' ) ) {
            wp_enqueue_script(
                'symbiotic-main',
                $dist_url . 'main.js',
                [],
                self::file_version( $dist_path . 'main.js' ),
                true
            );

            self::localize( 'symbiotic-main' );
        }
    }

    private static function localize( string $handle ): void {
        $settings     = (array) get_option( 'wcaic_settings', [] );
        $theme_opts   = Symbiotic_Admin::get_options();
        $currency_sym = get_woocommerce_currency_symbol();
        $currency     = get_woocommerce_currency();

        // Theme options override plugin primary color if set differently
        $primary_color = $theme_opts['color_primary'] ?? $settings['primary_color'] ?? '#6366f1';

        wp_localize_script( $handle, 'symbioticData', [
            'restUrl'        => esc_url_raw( rest_url() ),
            'nonce'          => wp_create_nonce( 'wp_rest' ),
            'storeName'      => get_bloginfo( 'name' ),
            'themeUrl'       => get_template_directory_uri(),
            'isStreaming'    => ! empty( $settings['streaming_enabled'] ),
            'primaryColor'   => $primary_color,
            'greeting'       => ! empty( $theme_opts['welcome_override'] )
                                    ? $theme_opts['welcome_override']
                                    : ( $settings['greeting'] ?? 'Hi! How can I help you today?' ),
            'wcRestUrl'      => esc_url_raw( rest_url( 'wc/v3/' ) ),
            'currentUserId'  => get_current_user_id(),
            'isLoggedIn'     => is_user_logged_in(),
            'currency'       => $currency,
            'currencySymbol' => html_entity_decode( $currency_sym ),
            'locale'         => get_locale(),
            'langCode'       => substr( get_locale(), 0, 2 ),
            'isRtl'          => class_exists( 'WCAIC_Language' ) && WCAIC_Language::is_rtl(),
            'checkoutUrl'    => wc_get_checkout_url(),
            'cartUrl'        => wc_get_cart_url(),
            'shopUrl'        => get_permalink( wc_get_page_id( 'shop' ) ),
            // Full theme options for CSS variable injection in React
            'themeOptions'   => [
                'colorBg'           => $theme_opts['color_bg'],
                'colorSurface'      => $theme_opts['color_surface'],
                'colorSurface2'     => $theme_opts['color_surface_2'],
                'colorText'         => $theme_opts['color_text'],
                'colorTextMuted'    => $theme_opts['color_text_muted'],
                'colorPrimary'      => $theme_opts['color_primary'],
                'colorBotBubble'    => $theme_opts['color_bot_bubble'],
                'leftMaxWidth'      => $theme_opts['left_max_width'],
                'rightWidth'        => $theme_opts['right_width'],
                'rightSidebarWidth' => $theme_opts['right_sidebar_width'],
                'borderRadius'      => $theme_opts['border_radius'],
                'baseFontSize'      => $theme_opts['base_font_size'],
                'botName'           => $theme_opts['bot_name'],
                'botAvatarUrl'      => $theme_opts['bot_avatar_url'],
                'placeholderText'   => $theme_opts['placeholder_text'],
                'inputHint'         => $theme_opts['input_hint'],
                'fontFamily'        => $theme_opts['font_family'],
                // Layout toggles
                'layoutFullwidth'   => ! empty( $theme_opts['layout_fullwidth'] ) && $theme_opts['layout_fullwidth'] !== '0',
                'showProductPanel'  => empty( $theme_opts['show_product_panel'] ) || $theme_opts['show_product_panel'] !== '0',
                'showRightSidebar'  => ! empty( $theme_opts['show_right_sidebar'] ) && $theme_opts['show_right_sidebar'] !== '0',
                'welcomeOverride'   => $theme_opts['welcome_override'] ?? '',
                // Theme mode
                'themeMode'         => $theme_opts['theme_mode'] ?? 'dark',
                // Homepage content
                'heroTitle'         => $theme_opts['hero_title'] ?? '',
                'heroSubtitle'      => $theme_opts['hero_subtitle'] ?? '',
                'heroImageUrl'      => $theme_opts['hero_image_url'] ?? '',
                'promoTitle'        => $theme_opts['promo_title'] ?? '',
                'promoText'         => $theme_opts['promo_text'] ?? '',
                'promoImageUrl'     => $theme_opts['promo_image_url'] ?? '',
                'promoCtaText'      => $theme_opts['promo_cta_text'] ?? '',
                'promoCtaQuery'     => $theme_opts['promo_cta_query'] ?? '',
            ],
            // Detect if we're on a product page — pass product ID to auto-open it.
            'initialProductId' => is_singular( 'product' ) ? get_the_ID() : 0,
            // Pass wcaicData for chat widget integration
            'wcaicData'      => [
                'restUrl'     => esc_url_raw( rest_url() ),
                'nonce'       => wp_create_nonce( 'wp_rest' ),
                'storeName'   => get_bloginfo( 'name' ),
                'isStreaming' => ! empty( $settings['streaming_enabled'] ),
                'settings'    => [
                    'position'     => $settings['widget_position'] ?? 'bottom-right',
                    'primaryColor' => $primary_color,
                    'greeting'     => $settings['greeting'] ?? 'Hi! How can I help?',
                ],
            ],
        ] );

        // Inject custom CSS from theme admin
        if ( ! empty( $theme_opts['custom_css'] ) && file_exists( get_template_directory() . '/dist/main.css' ) ) {
            wp_add_inline_style( 'symbiotic-main', $theme_opts['custom_css'] );
        }
    }

    private static function file_version( string $path ): string {
        return file_exists( $path ) ? (string) filemtime( $path ) : '1.0.0';
    }
}
