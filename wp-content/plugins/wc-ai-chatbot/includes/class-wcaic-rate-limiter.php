<?php
defined( 'ABSPATH' ) || exit;

/**
 * Sliding window rate limiter using WordPress transients.
 */
class WCAIC_Rate_Limiter {

    /**
     * Check and record a request within the sliding window.
     *
     * @param string $session_id Session identifier.
     * @param string $type       Limit type: 'ai' or 'tool'.
     * @param int    $limit      Max requests per window.
     * @param int    $window     Window size in seconds (default 60).
     * @return array{allowed: bool, current: int, retry_after: int}
     */
    public static function check( string $session_id, string $type, int $limit, int $window = 60 ): array {
        $key        = 'wcaic_rl_' . md5( $session_id . '_' . $type );
        $timestamps = get_transient( $key );
        $now        = time();

        if ( ! is_array( $timestamps ) ) {
            $timestamps = [];
        }

        // Remove timestamps outside window
        $timestamps = array_values(
            array_filter( $timestamps, static fn( int $t ): bool => ( $now - $t ) < $window )
        );

        if ( count( $timestamps ) >= $limit ) {
            $retry_after = $window - ( $now - min( $timestamps ) );
            return [
                'allowed'     => false,
                'current'     => count( $timestamps ),
                'retry_after' => max( 1, (int) $retry_after ),
            ];
        }

        $timestamps[] = $now;
        set_transient( $key, $timestamps, $window * 2 );

        return [
            'allowed'     => true,
            'current'     => count( $timestamps ),
            'retry_after' => 0,
        ];
    }

    /**
     * Clear all rate limit transients for a session.
     */
    public static function clear( string $session_id ): void {
        foreach ( [ 'ai', 'tool' ] as $type ) {
            delete_transient( 'wcaic_rl_' . md5( $session_id . '_' . $type ) );
        }
    }
}
