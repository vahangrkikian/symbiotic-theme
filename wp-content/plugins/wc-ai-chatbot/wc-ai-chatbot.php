<?php
/**
 * Plugin Name: WC AI Chatbot
 * Plugin URI:  https://yourdomain.com
 * Description: AI-powered WooCommerce shopping assistant with tool-calling, SSE streaming, and semantic search.
 * Version:     1.2.0
 * Author:      Your Name
 * License:     GPL-2.0-or-later
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * WC requires at least: 8.0
 * WC tested up to: 9.0
 */

defined( 'ABSPATH' ) || exit;

// Constants
define( 'WCAIC_VERSION',  '1.2.0' );
define( 'WCAIC_FILE',     __FILE__ );
define( 'WCAIC_PATH',     plugin_dir_path( __FILE__ ) );
define( 'WCAIC_URL',      plugin_dir_url( __FILE__ ) );
define( 'WCAIC_BASENAME', plugin_basename( __FILE__ ) );

// PSR-4-style autoloader: WCAIC_* → includes/class-wcaic-*.php
spl_autoload_register( function ( string $class_name ): void {
    if ( strpos( $class_name, 'WCAIC_' ) !== 0 ) {
        return;
    }
    $file = WCAIC_PATH . 'includes/class-' . str_replace( '_', '-', strtolower( $class_name ) ) . '.php';
    if ( file_exists( $file ) ) {
        require_once $file;
    }
} );

// Activation hook
register_activation_hook( __FILE__, function (): void {
    WCAIC_Conversation_Logger::create_table();

    $defaults = [
        'provider'              => 'openai',
        'openai_model'          => 'gpt-4o-mini',
        'anthropic_model'       => 'claude-sonnet-4-6',
        'widget_enabled'        => '1',
        'widget_position'       => 'bottom-right',
        'primary_color'         => '#2563eb',
        'greeting'              => 'Hi! I\'m your AI shopping assistant. How can I help you today?',
        'streaming_enabled'     => '1',
        'system_prompt'         => '',
        'ai_rate_limit'         => '10',
        'tool_rate_limit'       => '30',
        'max_iterations'        => '5',
        'conversation_logging'  => '1',
        'max_history'           => '20',
        'embedding_enabled'     => '0',
    ];

    if ( ! get_option( 'wcaic_settings' ) ) {
        update_option( 'wcaic_settings', $defaults, false );
    }

    update_option( 'wcaic_version', WCAIC_VERSION, false );

    // Schedule daily cleanup
    if ( ! wp_next_scheduled( 'wcaic_daily_cleanup' ) ) {
        wp_schedule_event( time(), 'daily', 'wcaic_daily_cleanup' );
    }
} );

// Deactivation hook
register_deactivation_hook( __FILE__, function (): void {
    // Clear all rate-limit transients
    global $wpdb;
    $wpdb->query(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wcaic_rl_%' OR option_name LIKE '_transient_timeout_wcaic_rl_%'"
    );
    wp_clear_scheduled_hook( 'wcaic_daily_cleanup' );
} );

// Bootstrap
add_action( 'plugins_loaded', function (): void {
    WCAIC_Plugin::get_instance();
} );
