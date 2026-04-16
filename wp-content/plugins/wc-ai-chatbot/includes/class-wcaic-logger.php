<?php
defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce logger integration wrapper.
 */
class WCAIC_Logger {

    private static ?WC_Logger_Interface $logger = null;
    private const SOURCE = 'wc-ai-chatbot';

    public static function log( string $message, string $level = 'info', array $context = [] ): void {
        if ( ! function_exists( 'wc_get_logger' ) ) {
            return;
        }
        if ( null === self::$logger ) {
            self::$logger = wc_get_logger();
        }
        $context['source'] = self::SOURCE;
        self::$logger->log( $level, $message, $context );
    }

    public static function debug( string $message, array $context = [] ): void {
        self::log( $message, 'debug', $context );
    }

    public static function info( string $message, array $context = [] ): void {
        self::log( $message, 'info', $context );
    }

    public static function error( string $message, array $context = [] ): void {
        self::log( $message, 'error', $context );
    }

    public static function warning( string $message, array $context = [] ): void {
        self::log( $message, 'warning', $context );
    }
}
