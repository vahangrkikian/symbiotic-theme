<?php
defined( 'ABSPATH' ) || exit;

/**
 * 5 REST endpoints for the AI chatbot: /chat, /stream, /clear, /cart, /welcome
 * Namespace: wcaic/v1
 */
class WCAIC_Rest_API {

    private static ?self $instance = null;

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function register_routes(): void {
        register_rest_route( 'wcaic/v1', '/chat', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_chat' ],
            'permission_callback' => [ $this, 'check_rest_nonce' ],
            'args'                => [
                'message' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field',
                    'validate_callback' => fn( $v ) => strlen( $v ) >= 1 && strlen( $v ) <= 2000,
                ],
            ],
        ] );

        register_rest_route( 'wcaic/v1', '/stream', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_stream' ],
            'permission_callback' => [ $this, 'check_rest_nonce' ],
            'args'                => [
                'message' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field',
                    'validate_callback' => fn( $v ) => strlen( $v ) >= 1 && strlen( $v ) <= 2000,
                ],
            ],
        ] );

        register_rest_route( 'wcaic/v1', '/clear', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_clear' ],
            'permission_callback' => [ $this, 'check_rest_nonce' ],
        ] );

        register_rest_route( 'wcaic/v1', '/cart', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'handle_cart' ],
            'permission_callback' => [ $this, 'check_rest_nonce' ],
        ] );

        register_rest_route( 'wcaic/v1', '/welcome', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'handle_welcome' ],
            'permission_callback' => [ $this, 'check_rest_nonce' ],
        ] );
    }

    public function check_rest_nonce( WP_REST_Request $request ): bool|WP_Error {
        $nonce = $request->get_header( 'X-WP-Nonce' );
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new WP_Error( 'invalid_nonce', 'Invalid or missing nonce.', [ 'status' => 403 ] );
        }
        return true;
    }

    // -------------------------------------------------------------------------
    // POST /chat
    // -------------------------------------------------------------------------
    public function handle_chat( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $settings   = WCAIC_Plugin::get_instance()->get_settings();
        $message    = $request->get_param( 'message' );
        $session_id = $this->get_session_id( $request );

        // Rate limit
        $limit  = (int) ( $settings['ai_rate_limit'] ?? 10 );
        $rl     = WCAIC_Rate_Limiter::check( $session_id, 'ai', $limit );
        if ( ! $rl['allowed'] ) {
            return new WP_Error( 'rate_limit', 'Please wait a moment before sending another message.', [
                'status'      => 429,
                'retry_after' => $rl['retry_after'],
            ] );
        }

        // Security screen
        $screen = WCAIC_Security::screen_message( $message );
        if ( ! $screen['safe'] ) {
            WCAIC_Logger::warning( 'Flagged message: ' . $screen['reason'], [ 'session' => $session_id ] );
            return rest_ensure_response( [
                'success'     => true,
                'reply'       => $screen['reply'],
                'attachments' => [],
                'cart'        => $this->get_cart_summary(),
                'flagged'     => true,
            ] );
        }

        $message = $screen['cleaned'];

        // Load history
        $history   = WCAIC_Session_Manager::get_conversation( $session_id );
        $history[] = [ 'role' => 'user', 'content' => $message ];

        // AI
        $client   = WCAIC_AI_Client::create( $settings );
        $tools    = WCAIC_Tool_Definitions::get_tools( $settings['provider'] ?? 'openai' );
        $result   = $client->process_chat( $history, $tools, array_merge( $settings, [ 'session_id' => $session_id ] ) );

        // Update history
        $history[] = [ 'role' => 'assistant', 'content' => $result['text'] ];
        WCAIC_Session_Manager::save_conversation( $session_id, $result['history'] ?? $history );

        // Log
        if ( ! empty( $settings['conversation_logging'] ) ) {
            WCAIC_Conversation_Logger::upsert( [
                'session_id'         => $session_id,
                'user_message'       => $message,
                'ai_reply'           => $result['text'],
                'provider'           => $settings['provider'] ?? 'openai',
                'model'              => $settings[ ( $settings['provider'] ?? 'openai' ) . '_model' ] ?? '',
                'messages'           => wp_json_encode( $result['history'] ?? $history ),
                'prompt_tokens'      => $result['prompt_tokens'] ?? 0,
                'completion_tokens'  => $result['completion_tokens'] ?? 0,
                'loop_iterations'    => $result['loop_iterations'] ?? 0,
                'message_count'      => count( $result['history'] ?? $history ),
                'ip_hash'            => hash( 'sha256', $this->get_client_ip() ),
                'flagged'            => 0,
            ] );
        }

        return rest_ensure_response( [
            'success'     => true,
            'reply'       => $result['text'],
            'attachments' => $result['attachments'] ?? [],
            'cart'        => $this->get_cart_summary(),
        ] );
    }

    // -------------------------------------------------------------------------
    // POST /stream
    // -------------------------------------------------------------------------
    public function handle_stream( WP_REST_Request $request ): void {
        $settings   = WCAIC_Plugin::get_instance()->get_settings();
        $message    = $request->get_param( 'message' );
        $session_id = $this->get_session_id( $request );

        // Max stream duration: 120 seconds
        set_time_limit( 120 );

        // SSE headers
        header( 'Content-Type: text/event-stream' );
        header( 'Cache-Control: no-cache' );
        header( 'X-Accel-Buffering: no' );
        header( 'Connection: keep-alive' );

        // Disable output buffering
        while ( ob_get_level() > 0 ) {
            ob_end_flush();
        }

        $emit = static function ( string $event, array $data ): void {
            echo "event: {$event}\n";
            echo 'data: ' . wp_json_encode( $data ) . "\n\n";
            flush();
        };

        // Rate limit (same as /chat)
        $limit = (int) ( $settings['ai_rate_limit'] ?? 10 );
        $rl    = WCAIC_Rate_Limiter::check( $session_id, 'ai', $limit );
        if ( ! $rl['allowed'] ) {
            $emit( 'error', [ 'message' => 'Please wait a moment before sending another message.', 'retry_after' => $rl['retry_after'] ] );
            exit;
        }

        // Security screen
        $screen = WCAIC_Security::screen_message( $message );
        if ( ! $screen['safe'] ) {
            $emit( 'token',       [ 'text' => $screen['reply'] ] );
            $emit( 'attachments', [ 'attachments' => [] ] );
            $emit( 'done',        [ 'cart' => $this->get_cart_summary() ] );
            exit;
        }

        $message = $screen['cleaned'];

        // Load history
        $history   = WCAIC_Session_Manager::get_conversation( $session_id );
        $history[] = [ 'role' => 'user', 'content' => $message ];

        // Stream
        $client = WCAIC_AI_Client::create( $settings );
        $tools  = WCAIC_Tool_Definitions::get_tools( $settings['provider'] ?? 'openai' );
        $result = $client->process_chat_stream( $history, $tools, array_merge( $settings, [ 'session_id' => $session_id ] ) );

        // Save conversation history so the next message has context.
        if ( ! empty( $result['history'] ) ) {
            WCAIC_Session_Manager::save_conversation( $session_id, $result['history'] );
        }

        // Log
        if ( ! empty( $settings['conversation_logging'] ) ) {
            WCAIC_Conversation_Logger::upsert( [
                'session_id'         => $session_id,
                'user_message'       => $message,
                'ai_reply'           => $result['text'] ?? '',
                'provider'           => $settings['provider'] ?? 'openai',
                'model'              => $settings[ ( $settings['provider'] ?? 'openai' ) . '_model' ] ?? '',
                'messages'           => wp_json_encode( $result['history'] ?? $history ),
                'prompt_tokens'      => 0,
                'completion_tokens'  => 0,
                'loop_iterations'    => 0,
                'message_count'      => count( $result['history'] ?? $history ),
                'ip_hash'            => hash( 'sha256', $this->get_client_ip() ),
                'flagged'            => 0,
            ] );
        }

        exit;
    }

    // -------------------------------------------------------------------------
    // POST /clear
    // -------------------------------------------------------------------------
    public function handle_clear( WP_REST_Request $request ): WP_REST_Response {
        $session_id = $this->get_session_id( $request );
        WCAIC_Session_Manager::clear_conversation( $session_id );
        return rest_ensure_response( [ 'success' => true, 'message' => 'Conversation cleared.' ] );
    }

    // -------------------------------------------------------------------------
    // GET /cart
    // -------------------------------------------------------------------------
    public function handle_cart( WP_REST_Request $request ): WP_REST_Response {
        return rest_ensure_response( [
            'success' => true,
            'cart'    => $this->get_cart_summary(),
        ] );
    }

    // -------------------------------------------------------------------------
    // GET /welcome
    // -------------------------------------------------------------------------
    public function handle_welcome( WP_REST_Request $request ): WP_REST_Response {
        $categories = [];
        $terms      = get_terms( [
            'taxonomy'   => 'product_cat',
            'hide_empty' => true,
            'number'     => 10,
            'orderby'    => 'count',
            'order'      => 'DESC',
        ] );
        if ( ! is_wp_error( $terms ) ) {
            foreach ( $terms as $term ) {
                $categories[] = [
                    'name'  => $term->name,
                    'slug'  => $term->slug,
                    'count' => $term->count,
                ];
            }
        }

        $brands = [];
        $brand_terms = get_terms( [
            'taxonomy'   => 'product_tag',
            'hide_empty' => true,
            'number'     => 10,
            'orderby'    => 'count',
            'order'      => 'DESC',
        ] );
        if ( ! is_wp_error( $brand_terms ) ) {
            foreach ( $brand_terms as $term ) {
                $brands[] = [
                    'name'  => $term->name,
                    'slug'  => $term->slug,
                    'count' => $term->count,
                ];
            }
        }

        return rest_ensure_response( [
            'success'    => true,
            'categories' => $categories,
            'brands'     => $brands,
        ] );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------
    private function get_session_id( WP_REST_Request $request ): string {
        // Prefer WooCommerce session (unique per visitor)
        if ( isset( WC()->session ) && WC()->session->has_session() ) {
            return 'wc_' . WC()->session->get_session_id();
        }

        // Logged-in user: use user ID
        if ( is_user_logged_in() ) {
            return 'user_' . get_current_user_id();
        }

        // Fallback: IP + UA + daily rotating salt (prevents session fixation across days)
        $ip   = $this->get_client_ip();
        $ua   = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $salt = defined( 'NONCE_SALT' ) ? NONCE_SALT : 'wcaic-fallback-salt';
        $day  = gmdate( 'Y-m-d' );
        return 'anon_' . hash( 'sha256', $ip . $ua . $salt . $day );
    }

    private function get_client_ip(): string {
        foreach ( [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ] as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                $ip = trim( explode( ',', $_SERVER[ $key ] )[0] );
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }

    private function get_cart_summary(): array {
        if ( ! WC()->cart ) {
            return [ 'item_count' => 0, 'total' => '0.00' ];
        }
        return [
            'item_count' => WC()->cart->get_cart_contents_count(),
            'total'      => wc_format_decimal( WC()->cart->get_cart_contents_total(), 2 ),
        ];
    }
}
