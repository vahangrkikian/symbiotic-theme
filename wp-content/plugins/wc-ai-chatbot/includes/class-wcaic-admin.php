<?php
defined( 'ABSPATH' ) || exit;

/**
 * Admin settings page, API key encryption, menu registration.
 */
class WCAIC_Admin {

    private static ?self $instance = null;

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function add_menu_pages(): void {
        add_submenu_page(
            'woocommerce',
            __( 'AI Chatbot', 'wc-ai-chatbot' ),
            __( 'AI Chatbot', 'wc-ai-chatbot' ),
            'manage_woocommerce',
            'wcaic-settings',
            [ $this, 'render_settings_page' ]
        );

        add_submenu_page(
            'woocommerce',
            __( 'AI Chatbot — Conversation Log', 'wc-ai-chatbot' ),
            __( 'AI Chat Logs', 'wc-ai-chatbot' ),
            'manage_woocommerce',
            'wcaic-conv-log',
            [ $this, 'render_conv_log_page' ]
        );

        add_submenu_page(
            'woocommerce',
            __( 'AI Chatbot — Embeddings', 'wc-ai-chatbot' ),
            __( 'AI Embeddings', 'wc-ai-chatbot' ),
            'manage_woocommerce',
            'wcaic-embeddings',
            [ $this, 'render_embeddings_page' ]
        );
    }

    public function register_settings(): void {
        register_setting( 'wcaic_settings_group', 'wcaic_settings', [
            'sanitize_callback' => [ $this, 'sanitize_settings' ],
        ] );

        // ---- Section: AI Provider ----
        add_settings_section( 'wcaic_provider', __( 'AI Provider', 'wc-ai-chatbot' ), null, 'wcaic-settings' );

        add_settings_field( 'provider', __( 'Provider', 'wc-ai-chatbot' ), [ $this, 'field_provider' ], 'wcaic-settings', 'wcaic_provider' );
        add_settings_field( 'openai_api_key', __( 'OpenAI API Key', 'wc-ai-chatbot' ), [ $this, 'field_openai_key' ], 'wcaic-settings', 'wcaic_provider' );
        add_settings_field( 'openai_model', __( 'OpenAI Model', 'wc-ai-chatbot' ), [ $this, 'field_openai_model' ], 'wcaic-settings', 'wcaic_provider' );
        add_settings_field( 'anthropic_api_key', __( 'Anthropic API Key', 'wc-ai-chatbot' ), [ $this, 'field_anthropic_key' ], 'wcaic-settings', 'wcaic_provider' );
        add_settings_field( 'anthropic_model', __( 'Anthropic Model', 'wc-ai-chatbot' ), [ $this, 'field_anthropic_model' ], 'wcaic-settings', 'wcaic_provider' );

        // ---- Section: Chat Widget ----
        add_settings_section( 'wcaic_widget', __( 'Chat Widget', 'wc-ai-chatbot' ), null, 'wcaic-settings' );

        add_settings_field( 'widget_enabled', __( 'Enable Widget', 'wc-ai-chatbot' ), [ $this, 'field_widget_enabled' ], 'wcaic-settings', 'wcaic_widget' );
        add_settings_field( 'widget_position', __( 'Widget Position', 'wc-ai-chatbot' ), [ $this, 'field_widget_position' ], 'wcaic-settings', 'wcaic_widget' );
        add_settings_field( 'primary_color', __( 'Primary Color', 'wc-ai-chatbot' ), [ $this, 'field_primary_color' ], 'wcaic-settings', 'wcaic_widget' );
        add_settings_field( 'greeting', __( 'Greeting Message', 'wc-ai-chatbot' ), [ $this, 'field_greeting' ], 'wcaic-settings', 'wcaic_widget' );
        add_settings_field( 'streaming_enabled', __( 'Enable Streaming', 'wc-ai-chatbot' ), [ $this, 'field_streaming' ], 'wcaic-settings', 'wcaic_widget' );

        // ---- Section: Advanced ----
        add_settings_section( 'wcaic_advanced', __( 'Advanced', 'wc-ai-chatbot' ), null, 'wcaic-settings' );

        add_settings_field( 'system_prompt', __( 'Custom System Prompt', 'wc-ai-chatbot' ), [ $this, 'field_system_prompt' ], 'wcaic-settings', 'wcaic_advanced' );
        add_settings_field( 'ai_rate_limit', __( 'Max AI Requests/min', 'wc-ai-chatbot' ), [ $this, 'field_ai_rate_limit' ], 'wcaic-settings', 'wcaic_advanced' );
        add_settings_field( 'tool_rate_limit', __( 'Max Tool Calls/min', 'wc-ai-chatbot' ), [ $this, 'field_tool_rate_limit' ], 'wcaic-settings', 'wcaic_advanced' );
        add_settings_field( 'max_iterations', __( 'Max Loop Iterations', 'wc-ai-chatbot' ), [ $this, 'field_max_iterations' ], 'wcaic-settings', 'wcaic_advanced' );
        add_settings_field( 'conversation_logging', __( 'Conversation Logging', 'wc-ai-chatbot' ), [ $this, 'field_conv_logging' ], 'wcaic-settings', 'wcaic_advanced' );
        add_settings_field( 'max_history', __( 'Max History Length', 'wc-ai-chatbot' ), [ $this, 'field_max_history' ], 'wcaic-settings', 'wcaic_advanced' );
    }

    public function sanitize_settings( array $input ): array {
        $clean = [];
        $clean['provider']             = in_array( $input['provider'] ?? '', [ 'openai', 'anthropic' ] ) ? $input['provider'] : 'openai';
        $clean['openai_model']         = sanitize_text_field( $input['openai_model'] ?? 'gpt-4o-mini' );
        $clean['anthropic_model']      = sanitize_text_field( $input['anthropic_model'] ?? 'claude-sonnet-4-6' );
        $clean['widget_enabled']       = ! empty( $input['widget_enabled'] ) ? '1' : '0';
        $clean['widget_position']      = in_array( $input['widget_position'] ?? '', [ 'bottom-right', 'bottom-left' ] ) ? $input['widget_position'] : 'bottom-right';
        $clean['primary_color']        = sanitize_hex_color( $input['primary_color'] ?? '#2563eb' ) ?: '#2563eb';
        $clean['greeting']             = sanitize_text_field( $input['greeting'] ?? 'Hi! How can I help?' );
        $clean['streaming_enabled']    = ! empty( $input['streaming_enabled'] ) ? '1' : '0';
        $clean['system_prompt']        = sanitize_textarea_field( $input['system_prompt'] ?? '' );
        $clean['ai_rate_limit']        = (string) max( 1, min( 100, absint( $input['ai_rate_limit'] ?? 10 ) ) );
        $clean['tool_rate_limit']      = (string) max( 1, min( 300, absint( $input['tool_rate_limit'] ?? 30 ) ) );
        $clean['max_iterations']       = (string) max( 1, min( 10, absint( $input['max_iterations'] ?? 5 ) ) );
        $clean['conversation_logging'] = ! empty( $input['conversation_logging'] ) ? '1' : '0';
        $clean['max_history']          = (string) max( 5, min( 100, absint( $input['max_history'] ?? 20 ) ) );
        $clean['embedding_enabled']    = ! empty( $input['embedding_enabled'] ) ? '1' : '0';

        // Handle API keys separately (encrypted storage)
        $this->handle_api_key_save( 'openai',     $input['openai_api_key']     ?? '' );
        $this->handle_api_key_save( 'anthropic',  $input['anthropic_api_key']  ?? '' );

        return $clean;
    }

    private function handle_api_key_save( string $provider, string $key ): void {
        if ( empty( $key ) || str_contains( $key, '••••' ) ) {
            return; // No change
        }
        $encrypted = get_option( 'wcaic_api_keys_encrypted', [] );
        if ( ! is_array( $encrypted ) ) {
            $encrypted = [];
        }
        $encrypted[ $provider ] = self::encrypt_key( $key );
        update_option( 'wcaic_api_keys_encrypted', $encrypted, false );
    }

    // -------------------------------------------------------------------------
    // Encryption helpers
    // -------------------------------------------------------------------------
    public static function encrypt_key( string $key ): array {
        $secret  = substr( hash( 'sha256', defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : wp_generate_password( 64, true, true ) ), 0, 32 );
        $iv      = openssl_random_pseudo_bytes( 16 );
        $cipher  = openssl_encrypt( $key, 'aes-256-cbc', $secret, OPENSSL_RAW_DATA, $iv );
        return [
            'key' => base64_encode( $cipher ),
            'iv'  => base64_encode( $iv ),
        ];
    }

    public static function decrypt_key( array $encrypted ): string {
        if ( empty( $encrypted['key'] ) || empty( $encrypted['iv'] ) ) {
            return '';
        }
        $secret = substr( hash( 'sha256', defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : '' ), 0, 32 );
        $result = openssl_decrypt(
            base64_decode( $encrypted['key'] ),
            'aes-256-cbc',
            $secret,
            OPENSSL_RAW_DATA,
            base64_decode( $encrypted['iv'] )
        );
        return $result === false ? '' : $result;
    }

    /**
     * Public accessor for masked key display in theme admin.
     */
    public function get_masked_key_public( string $provider ): string {
        return $this->get_masked_key( $provider );
    }

    private function get_masked_key( string $provider ): string {
        $encrypted = get_option( 'wcaic_api_keys_encrypted', [] );
        if ( ! isset( $encrypted[ $provider ] ) ) {
            return '';
        }
        $key = self::decrypt_key( $encrypted[ $provider ] );
        if ( strlen( $key ) < 8 ) {
            return '';
        }
        return substr( $key, 0, 3 ) . '••••' . substr( $key, -4 );
    }

    // -------------------------------------------------------------------------
    // Field renderers
    // -------------------------------------------------------------------------
    private function get_opt( string $key, $default = '' ) {
        $opts = (array) get_option( 'wcaic_settings', [] );
        return $opts[ $key ] ?? $default;
    }

    public function field_provider(): void {
        $val = $this->get_opt( 'provider', 'openai' );
        echo '<select name="wcaic_settings[provider]">';
        foreach ( [ 'openai' => 'OpenAI', 'anthropic' => 'Anthropic' ] as $k => $label ) {
            printf( '<option value="%s" %s>%s</option>', esc_attr( $k ), selected( $val, $k, false ), esc_html( $label ) );
        }
        echo '</select>';
    }

    public function field_openai_key(): void {
        $masked = $this->get_masked_key( 'openai' );
        echo '<input type="password" name="wcaic_settings[openai_api_key]" value="' . esc_attr( $masked ) . '" class="regular-text" autocomplete="new-password">';
        echo '<p class="description">' . esc_html__( 'Leave unchanged to keep existing key.', 'wc-ai-chatbot' ) . '</p>';
    }

    public function field_openai_model(): void {
        $val     = $this->get_opt( 'openai_model', 'gpt-4o-mini' );
        $models  = [ 'gpt-4o-mini', 'gpt-4o', 'gpt-4-turbo', 'gpt-3.5-turbo' ];
        echo '<select name="wcaic_settings[openai_model]">';
        foreach ( $models as $m ) {
            printf( '<option value="%s" %s>%s</option>', esc_attr( $m ), selected( $val, $m, false ), esc_html( $m ) );
        }
        echo '</select>';
    }

    public function field_anthropic_key(): void {
        $masked = $this->get_masked_key( 'anthropic' );
        echo '<input type="password" name="wcaic_settings[anthropic_api_key]" value="' . esc_attr( $masked ) . '" class="regular-text" autocomplete="new-password">';
        echo '<p class="description">' . esc_html__( 'Leave unchanged to keep existing key.', 'wc-ai-chatbot' ) . '</p>';
    }

    public function field_anthropic_model(): void {
        $val    = $this->get_opt( 'anthropic_model', 'claude-sonnet-4-6' );
        $models = [ 'claude-sonnet-4-6', 'claude-opus-4-6', 'claude-haiku-4-5-20251001' ];
        echo '<select name="wcaic_settings[anthropic_model]">';
        foreach ( $models as $m ) {
            printf( '<option value="%s" %s>%s</option>', esc_attr( $m ), selected( $val, $m, false ), esc_html( $m ) );
        }
        echo '</select>';
    }

    public function field_widget_enabled(): void {
        $val = $this->get_opt( 'widget_enabled', '1' );
        echo '<input type="checkbox" name="wcaic_settings[widget_enabled]" value="1" ' . checked( $val, '1', false ) . '>';
    }

    public function field_widget_position(): void {
        $val = $this->get_opt( 'widget_position', 'bottom-right' );
        echo '<select name="wcaic_settings[widget_position]">';
        foreach ( [ 'bottom-right' => 'Bottom Right', 'bottom-left' => 'Bottom Left' ] as $k => $label ) {
            printf( '<option value="%s" %s>%s</option>', esc_attr( $k ), selected( $val, $k, false ), esc_html( $label ) );
        }
        echo '</select>';
    }

    public function field_primary_color(): void {
        $val = $this->get_opt( 'primary_color', '#2563eb' );
        echo '<input type="color" name="wcaic_settings[primary_color]" value="' . esc_attr( $val ) . '">';
    }

    public function field_greeting(): void {
        $val = $this->get_opt( 'greeting', 'Hi! How can I help?' );
        echo '<input type="text" name="wcaic_settings[greeting]" value="' . esc_attr( $val ) . '" class="regular-text">';
    }

    public function field_streaming(): void {
        $val = $this->get_opt( 'streaming_enabled', '1' );
        echo '<input type="checkbox" name="wcaic_settings[streaming_enabled]" value="1" ' . checked( $val, '1', false ) . '>';
    }

    public function field_system_prompt(): void {
        $val = $this->get_opt( 'system_prompt', '' );
        echo '<textarea name="wcaic_settings[system_prompt]" rows="8" class="large-text">' . esc_textarea( $val ) . '</textarea>';
        echo '<p class="description">' . esc_html__( 'Leave blank to use the default 9-rule shopping assistant prompt.', 'wc-ai-chatbot' ) . '</p>';
    }

    public function field_ai_rate_limit(): void {
        $val = $this->get_opt( 'ai_rate_limit', '10' );
        echo '<input type="number" name="wcaic_settings[ai_rate_limit]" value="' . esc_attr( $val ) . '" min="1" max="100" class="small-text"> ' . esc_html__( 'per minute per session', 'wc-ai-chatbot' );
    }

    public function field_tool_rate_limit(): void {
        $val = $this->get_opt( 'tool_rate_limit', '30' );
        echo '<input type="number" name="wcaic_settings[tool_rate_limit]" value="' . esc_attr( $val ) . '" min="1" max="300" class="small-text"> ' . esc_html__( 'per minute per session', 'wc-ai-chatbot' );
    }

    public function field_max_iterations(): void {
        $val = $this->get_opt( 'max_iterations', '5' );
        echo '<input type="number" name="wcaic_settings[max_iterations]" value="' . esc_attr( $val ) . '" min="1" max="10" class="small-text">';
    }

    public function field_conv_logging(): void {
        $val = $this->get_opt( 'conversation_logging', '1' );
        echo '<input type="checkbox" name="wcaic_settings[conversation_logging]" value="1" ' . checked( $val, '1', false ) . '>';
    }

    public function field_max_history(): void {
        $val = $this->get_opt( 'max_history', '20' );
        echo '<input type="number" name="wcaic_settings[max_history]" value="' . esc_attr( $val ) . '" min="5" max="100" class="small-text"> ' . esc_html__( 'messages', 'wc-ai-chatbot' );
    }

    // -------------------------------------------------------------------------
    // Page renderers
    // -------------------------------------------------------------------------
    public function render_settings_page(): void {
        $template = WCAIC_PATH . 'templates/admin-settings.php';
        if ( file_exists( $template ) ) {
            include $template;
        }
    }

    public function render_conv_log_page(): void {
        WCAIC_Conv_Log_Admin::get_instance()->render();
    }

    public function render_embeddings_page(): void {
        $template = WCAIC_PATH . 'templates/admin-embeddings.php';
        if ( file_exists( $template ) ) {
            include $template;
        }
    }

    public function enqueue_assets( string $hook ): void {
        if ( strpos( $hook, 'wcaic' ) === false ) {
            return;
        }

        wp_enqueue_style( 'wcaic-admin', WCAIC_URL . 'assets/css/chatbot-admin.css', [], WCAIC_VERSION );

        if ( strpos( $hook, 'wcaic-conv-log' ) !== false ) {
            wp_enqueue_style( 'wcaic-conv-log', WCAIC_URL . 'assets/css/admin-conv-log.css', [], WCAIC_VERSION );
        }

        if ( strpos( $hook, 'wcaic-embeddings' ) !== false ) {
            wp_enqueue_style( 'wcaic-embeddings-css', WCAIC_URL . 'assets/css/admin-embeddings.css', [], WCAIC_VERSION );
            wp_enqueue_script( 'wcaic-embeddings-js', WCAIC_URL . 'assets/js/admin-embeddings.js', [ 'jquery' ], WCAIC_VERSION, true );
            wp_localize_script( 'wcaic-embeddings-js', 'wcaicEmbeddings', [
                'restUrl' => esc_url_raw( rest_url() ),
                'nonce'   => wp_create_nonce( 'wp_rest' ),
            ] );
        }
    }
}
