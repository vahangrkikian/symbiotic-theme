<?php
defined( 'ABSPATH' ) || exit;

/**
 * Singleton bootstrap. Registered on plugins_loaded.
 */
class WCAIC_Plugin {

    private static ?self $instance = null;
    private array $settings = [];

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->settings = (array) get_option( 'wcaic_settings', [] );

        add_action( 'admin_menu',                  [ $this, 'add_settings_page' ] );
        add_action( 'admin_init',                  [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts',       [ $this, 'enqueue_admin_assets' ] );
        add_action( 'rest_api_init',               [ $this, 'register_routes' ] );
        add_action( 'woocommerce_loaded',          [ $this, 'maybe_init_wc_session_for_rest' ] );
        add_action( 'wp_enqueue_scripts',          [ $this, 'enqueue_frontend_assets' ] );
        add_action( 'wp_footer',                   [ $this, 'render_chat_widget' ] );
        add_action( 'before_woocommerce_init',     [ $this, 'declare_hpos_compat' ] );
        add_action( 'wcaic_daily_cleanup',         [ $this, 'daily_cleanup' ] );
        add_filter( 'wp_headers',                  [ $this, 'add_security_headers' ] );

        // "Talk with AI" buttons on shop loop and single product
        add_action( 'woocommerce_after_shop_loop_item',    [ $this, 'render_ask_ai_button' ] );
        add_action( 'woocommerce_single_product_summary',  [ $this, 'render_ask_ai_button' ], 35 );
    }

    public function get_settings(): array {
        return $this->settings;
    }

    public function add_settings_page(): void {
        WCAIC_Admin::get_instance()->add_menu_pages();
    }

    public function register_settings(): void {
        WCAIC_Admin::get_instance()->register_settings();
    }

    public function enqueue_admin_assets( string $hook ): void {
        WCAIC_Admin::get_instance()->enqueue_assets( $hook );
    }

    public function register_routes(): void {
        WCAIC_Rest_API::get_instance()->register_routes();
    }

    public function maybe_init_wc_session_for_rest(): void {
        if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
            return;
        }
        $route = $GLOBALS['wp']->query_vars['rest_route'] ?? '';
        if ( strpos( $route, 'wcaic/v1' ) === false ) {
            return;
        }
        if ( WC()->session && ! WC()->session->has_session() ) {
            WC()->session->set_customer_session_cookie( true );
        }
        if ( WC()->session ) {
            WC()->session->init();
        }
        if ( WC()->cart ) {
            WC()->cart->get_cart();
        }
    }

    public function enqueue_frontend_assets(): void {
        if ( ! $this->is_widget_enabled() ) {
            return;
        }
        if ( ! $this->is_woocommerce_page() ) {
            return;
        }

        wp_enqueue_style(
            'wcaic-widget',
            WCAIC_URL . 'assets/css/chatbot-widget.css',
            [],
            WCAIC_VERSION
        );

        wp_enqueue_script(
            'wcaic-widget',
            WCAIC_URL . 'assets/js/chatbot-widget.js',
            [],
            WCAIC_VERSION,
            true
        );

        $s = $this->settings;
        wp_localize_script( 'wcaic-widget', 'wcaicData', [
            'restUrl'     => esc_url_raw( rest_url() ),
            'nonce'       => wp_create_nonce( 'wp_rest' ),
            'storeName'   => get_bloginfo( 'name' ),
            'isStreaming' => ! empty( $s['streaming_enabled'] ),
            'settings'    => [
                'position'      => $s['widget_position'] ?? 'bottom-right',
                'primaryColor'  => $s['primary_color'] ?? '#2563eb',
                'greeting'      => $s['greeting'] ?? 'Hi! How can I help?',
                'widgetEnabled' => ! empty( $s['widget_enabled'] ),
            ],
        ] );
    }

    public function render_chat_widget(): void {
        if ( ! $this->is_widget_enabled() || ! $this->is_woocommerce_page() ) {
            return;
        }
        $template = WCAIC_PATH . 'templates/chat-widget.php';
        if ( file_exists( $template ) ) {
            include $template;
        }
    }

    public function render_ask_ai_button(): void {
        if ( ! $this->is_widget_enabled() ) {
            return;
        }
        echo '<button class="wcaic-ask-ai button" type="button">' . esc_html__( 'Talk with AI', 'wc-ai-chatbot' ) . '</button>';
    }

    public function declare_hpos_compat(): void {
        if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'custom_order_tables',
                WCAIC_FILE,
                true
            );
        }
    }

    public function daily_cleanup(): void {
        $days = (int) ( $this->settings['log_retention_days'] ?? 90 );
        WCAIC_Conversation_Logger::prune_old( $days );

        // Purge expired product search transients
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wcaic_products_%' OR option_name LIKE '_transient_timeout_wcaic_products_%'"
        );
    }

    private function is_widget_enabled(): bool {
        return ! empty( $this->settings['widget_enabled'] );
    }

    public function add_security_headers( array $headers ): array {
        $headers['X-Content-Type-Options'] = 'nosniff';
        $headers['X-Frame-Options']        = 'SAMEORIGIN';
        $headers['Referrer-Policy']        = 'strict-origin-when-cross-origin';
        return $headers;
    }

    private function is_woocommerce_page(): bool {
        return is_woocommerce() || is_cart() || is_checkout() || is_account_page();
    }
}
