<?php
defined( 'ABSPATH' ) || exit;

/**
 * Conversation history: WC Session (primary) + transients (fallback).
 */
class WCAIC_Session_Manager {

    private const MAX_MESSAGES = 40;
    private const TRANSIENT_TTL = 7200; // 2 hours

    public static function get_conversation( string $session_id ): array {
        // Try WC session first
        if ( self::wc_session_active() ) {
            $data = WC()->session->get( 'wcaic_conversation_' . $session_id );
            if ( is_array( $data ) ) {
                return $data;
            }
        }

        // Fallback: transient
        $data = get_transient( self::transient_key( $session_id ) );
        return is_array( $data ) ? $data : [];
    }

    public static function save_conversation( string $session_id, array $messages ): void {
        // Trim to max
        if ( count( $messages ) > self::MAX_MESSAGES ) {
            $messages = array_slice( $messages, -self::MAX_MESSAGES );
        }

        if ( self::wc_session_active() ) {
            WC()->session->set( 'wcaic_conversation_' . $session_id, $messages );
        }

        set_transient( self::transient_key( $session_id ), $messages, self::TRANSIENT_TTL );
    }

    public static function clear_conversation( string $session_id ): void {
        if ( self::wc_session_active() ) {
            WC()->session->__unset( 'wcaic_conversation_' . $session_id );
        }
        delete_transient( self::transient_key( $session_id ) );
    }

    private static function wc_session_active(): bool {
        return isset( WC()->session ) && WC()->session instanceof WC_Session_Handler;
    }

    private static function transient_key( string $session_id ): string {
        // Transient key must be ≤ 172 chars
        return 'wcaic_conv_' . substr( md5( $session_id ), 0, 20 );
    }
}
