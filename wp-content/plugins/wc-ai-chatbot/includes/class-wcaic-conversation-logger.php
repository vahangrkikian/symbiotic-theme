<?php
defined( 'ABSPATH' ) || exit;

/**
 * Custom DB logging: wp_wcaic_conversation_log
 */
class WCAIC_Conversation_Logger {

    private static string $table = 'wcaic_conversation_log';

    public static function get_table(): string {
        global $wpdb;
        return $wpdb->prefix . self::$table;
    }

    public static function create_table(): void {
        global $wpdb;
        $table      = self::get_table();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id VARCHAR(64) NOT NULL DEFAULT '',
            user_message TEXT NOT NULL,
            ai_reply TEXT NOT NULL,
            provider VARCHAR(20) NOT NULL DEFAULT 'openai',
            model VARCHAR(50) NOT NULL DEFAULT '',
            messages LONGTEXT NOT NULL,
            flagged TINYINT(1) NOT NULL DEFAULT 0,
            prompt_tokens INT NOT NULL DEFAULT 0,
            completion_tokens INT NOT NULL DEFAULT 0,
            loop_iterations INT NOT NULL DEFAULT 0,
            message_count INT NOT NULL DEFAULT 0,
            ip_hash VARCHAR(64) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_activity DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        update_option( 'wcaic_conv_log_db_version', 2 );
    }

    public static function upsert( array $data ): void {
        global $wpdb;
        $table = self::get_table();

        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table} WHERE session_id = %s LIMIT 1",
            $data['session_id']
        ) );

        if ( $existing ) {
            $wpdb->update(
                $table,
                [
                    'user_message'      => $data['user_message'],
                    'ai_reply'          => $data['ai_reply'],
                    'messages'          => $data['messages'],
                    'flagged'           => max( (int) $data['flagged'], (int) $wpdb->get_var( $wpdb->prepare(
                        "SELECT flagged FROM {$table} WHERE id = %d",
                        $existing
                    ) ) ),
                    'prompt_tokens'     => $wpdb->get_var( $wpdb->prepare( "SELECT prompt_tokens FROM {$table} WHERE id = %d", $existing ) ) + (int) $data['prompt_tokens'],
                    'completion_tokens' => $wpdb->get_var( $wpdb->prepare( "SELECT completion_tokens FROM {$table} WHERE id = %d", $existing ) ) + (int) $data['completion_tokens'],
                    'loop_iterations'   => $wpdb->get_var( $wpdb->prepare( "SELECT loop_iterations FROM {$table} WHERE id = %d", $existing ) ) + (int) $data['loop_iterations'],
                    'message_count'     => (int) $data['message_count'],
                    'last_activity'     => current_time( 'mysql' ),
                ],
                [ 'id' => $existing ],
                [ '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s' ],
                [ '%d' ]
            );
        } else {
            $wpdb->insert(
                $table,
                [
                    'session_id'        => $data['session_id'],
                    'user_message'      => $data['user_message'],
                    'ai_reply'          => $data['ai_reply'],
                    'provider'          => $data['provider'],
                    'model'             => $data['model'],
                    'messages'          => $data['messages'],
                    'flagged'           => (int) $data['flagged'],
                    'prompt_tokens'     => (int) $data['prompt_tokens'],
                    'completion_tokens' => (int) $data['completion_tokens'],
                    'loop_iterations'   => (int) $data['loop_iterations'],
                    'message_count'     => (int) $data['message_count'],
                    'ip_hash'           => $data['ip_hash'],
                    'created_at'        => current_time( 'mysql' ),
                    'last_activity'     => current_time( 'mysql' ),
                ],
                [ '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s' ]
            );
        }
    }

    public static function prune_old( int $days = 90 ): void {
        global $wpdb;
        $table = self::get_table();
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$table} WHERE last_activity < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ) );
    }

    public static function get_recent( int $limit = 50, int $offset = 0 ): array {
        global $wpdb;
        $table = self::get_table();
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT id, session_id, user_message, ai_reply, provider, model, flagged, prompt_tokens, completion_tokens, loop_iterations, message_count, created_at, last_activity FROM {$table} ORDER BY last_activity DESC LIMIT %d OFFSET %d",
            $limit,
            $offset
        ), ARRAY_A );
    }

    public static function count_all(): int {
        global $wpdb;
        return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::get_table() );
    }
}
