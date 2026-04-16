<?php
defined( 'ABSPATH' ) || exit;

/**
 * Prompt injection detection and message screening. 30+ detection patterns.
 */
class WCAIC_Security {

    /** System override patterns */
    private static array $override_patterns = [
        '/ignore\s+(previous|all|prior)?\s*instructions?/i',
        '/new\s+instructions?\s*:/i',
        '/you\s+are\s+now\s+(a|an|the)?\s*/i',
        '/forget\s+(everything|all|what)/i',
        '/act\s+as\s+(a|an|the)?\s*/i',
        '/pretend\s+(to\s+be|you\s+are)/i',
        '/your\s+(new|actual|real|true)\s+(role|purpose|instructions?|task)/i',
        '/disregard\s+(previous|your|all)/i',
        '/override\s+(your|all|previous)/i',
        '/system\s+prompt\s*:/i',
        '/\[\s*system\s*\]/i',
        '/switch\s+(to|your)\s+(mode|persona|role)/i',
    ];

    /** Extraction patterns */
    private static array $extraction_patterns = [
        '/(what\s+is|show\s+me|tell\s+me|reveal|print|output|display|write\s+out|repeat)\s+your?\s+(system\s+prompt|instructions|context|rules|guidelines)/i',
        '/initial\s+(prompt|message|instructions?)/i',
        '/what\s+were\s+you\s+(told|instructed|programmed)/i',
        '/repeat\s+(after\s+me|your\s+instructions?|everything\s+above)/i',
    ];

    /** Jailbreak patterns */
    private static array $jailbreak_patterns = [
        '/(dan|jailbreak|developer|god|unrestricted|unlimited)\s+mode/i',
        '/(no\s+restrictions?|no\s+(safety|ethical)\s+guidelines?|bypass\s+(safety|restrictions?))/i',
        '/do\s+anything\s+now/i',
        '/without\s+(any\s+)?(restrictions?|limitations?|filters?)/i',
        '/enable\s+(developer|debug|admin|root)\s+mode/i',
    ];

    /** Code injection patterns */
    private static array $code_patterns = [
        '/<script/i',
        '/javascript\s*:/i',
        '/onerror\s*=/i',
        '/onload\s*=/i',
        '/eval\s*\(/i',
        '/document\.(cookie|location|write)/i',
        '/window\.(location|open)/i',
        '/<iframe/i',
        '/<img[^>]+onerror/i',
        '/on(click|mouseover|focus|blur|change|submit)\s*=/i',
    ];

    /** Structural anomalies patterns */
    private static array $structural_patterns = [
        '/\|\|\|/',
        '/-----{5,}/',
        '/====={5,}/',
        '/#####{5,}/',
    ];

    public static function screen_message( string $message ): array {
        $cleaned = sanitize_textarea_field( $message );

        // Max length
        if ( strlen( $cleaned ) > 2000 ) {
            return self::flag( $cleaned, 'Message exceeds maximum length of 2000 characters.', 'max_length' );
        }

        // Base64 payload detection
        if ( preg_match( '/[A-Za-z0-9+\/]{100,}={0,2}/', $cleaned ) ) {
            return self::flag( $cleaned, 'Invalid message format detected.', 'base64_payload' );
        }

        // Special character ratio (> 30%)
        $non_standard = preg_replace( '/[\w\s.,!?\'"()]/', '', $cleaned );
        $ratio = strlen( $cleaned ) > 0 ? ( strlen( $non_standard ) / strlen( $cleaned ) ) : 0;
        if ( $ratio > 0.30 ) {
            return self::flag( $cleaned, 'Message contains unusual characters.', 'char_ratio' );
        }

        // Check all pattern groups
        foreach ( self::$override_patterns as $pattern ) {
            if ( preg_match( $pattern, $cleaned ) ) {
                return self::flag( $cleaned, 'I\'m here to help with shopping only. How can I assist you today?', 'system_override' );
            }
        }

        foreach ( self::$extraction_patterns as $pattern ) {
            if ( preg_match( $pattern, $cleaned ) ) {
                return self::flag( $cleaned, 'I\'m here to help you find great products. What are you looking for?', 'extraction' );
            }
        }

        foreach ( self::$jailbreak_patterns as $pattern ) {
            if ( preg_match( $pattern, $cleaned ) ) {
                return self::flag( $cleaned, 'I\'m your shopping assistant. Let me know what products you\'re interested in!', 'jailbreak' );
            }
        }

        foreach ( self::$code_patterns as $pattern ) {
            if ( preg_match( $pattern, $cleaned ) ) {
                return self::flag( $cleaned, 'Invalid input detected. Please describe what you\'re looking for in plain text.', 'code_injection' );
            }
        }

        foreach ( self::$structural_patterns as $pattern ) {
            if ( preg_match( $pattern, $cleaned ) ) {
                return self::flag( $cleaned, 'Invalid message format. Please ask your question in plain text.', 'structural_anomaly' );
            }
        }

        return [
            'safe'    => true,
            'cleaned' => $cleaned,
            'reply'   => '',
            'reason'  => '',
        ];
    }

    private static function flag( string $cleaned, string $reply, string $reason ): array {
        return [
            'safe'    => false,
            'cleaned' => $cleaned,
            'reply'   => $reply,
            'reason'  => $reason,
        ];
    }
}
