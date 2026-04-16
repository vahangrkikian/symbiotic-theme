<?php
defined( 'ABSPATH' ) || exit;

/**
 * Multi-language support for the AI assistant.
 * Detects customer language and injects instructions into the system prompt.
 *
 * Storage: wp_options key 'wcaic_language'
 */
class WCAIC_Language {

    private static string $option_key = 'wcaic_language';

    /**
     * Supported language configurations.
     */
    public static function available_languages(): array {
        return [
            'auto'  => __( 'Auto-detect (respond in customer\'s language)', 'wc-ai-chatbot' ),
            'en'    => 'English',
            'es'    => 'Español',
            'fr'    => 'Français',
            'de'    => 'Deutsch',
            'it'    => 'Italiano',
            'pt'    => 'Português',
            'nl'    => 'Nederlands',
            'ru'    => 'Русский',
            'ja'    => '日本語',
            'ko'    => '한국어',
            'zh'    => '中文',
            'ar'    => 'العربية',
            'hy'    => 'Հայերեն',
            'tr'    => 'Türkçe',
            'pl'    => 'Polski',
            'sv'    => 'Svenska',
            'da'    => 'Dansk',
            'fi'    => 'Suomi',
            'no'    => 'Norsk',
            'he'    => 'עברית',
            'hi'    => 'हिन्दी',
            'th'    => 'ไทย',
            'vi'    => 'Tiếng Việt',
        ];
    }

    /**
     * RTL languages.
     */
    public static function rtl_languages(): array {
        return [ 'ar', 'he' ];
    }

    /**
     * Default settings.
     */
    public static function defaults(): array {
        return [
            'mode'               => 'auto',       // 'auto' | specific locale code
            'primary_language'   => '',            // fallback language when auto-detect unclear
            'additional_languages' => [],          // allowed languages (empty = all)
            'language_note'      => '',            // custom instruction about language behavior
        ];
    }

    /**
     * Get current language settings.
     */
    public static function get_settings(): array {
        $saved = get_option( self::$option_key, [] );
        if ( ! is_array( $saved ) ) {
            $saved = [];
        }
        return array_merge( self::defaults(), $saved );
    }

    /**
     * Save language settings.
     */
    public static function save( array $data ): void {
        $available = self::available_languages();
        $clean = [];

        $clean['mode'] = array_key_exists( $data['mode'] ?? 'auto', $available ) ? $data['mode'] : 'auto';
        $clean['primary_language'] = array_key_exists( $data['primary_language'] ?? '', $available ) ? $data['primary_language'] : '';
        $clean['language_note'] = sanitize_textarea_field( $data['language_note'] ?? '' );

        // Additional languages: filter to valid codes
        $additional = array_filter( array_map( 'sanitize_text_field', (array) ( $data['additional_languages'] ?? [] ) ) );
        $clean['additional_languages'] = array_values( array_intersect( $additional, array_keys( $available ) ) );

        update_option( self::$option_key, $clean, false );
    }

    /**
     * Detect customer locale from WordPress and browser.
     */
    public static function detect_locale(): string {
        // WordPress locale first
        $wp_locale = get_locale();
        $lang = substr( $wp_locale, 0, 2 );

        // Check if a specific user language is available via browser Accept-Language
        if ( isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) {
            $browser_lang = substr( sanitize_text_field( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ), 0, 2 );
            $available = self::available_languages();
            if ( array_key_exists( $browser_lang, $available ) ) {
                $lang = $browser_lang;
            }
        }

        return strtolower( $lang );
    }

    /**
     * Check if detected language is RTL.
     */
    public static function is_rtl(): bool {
        $settings = self::get_settings();
        $lang = $settings['mode'] === 'auto' ? self::detect_locale() : $settings['mode'];
        return in_array( $lang, self::rtl_languages(), true );
    }

    /**
     * Build the language instruction for the system prompt.
     */
    public static function build_instruction(): string {
        $settings = self::get_settings();
        $parts = [];

        if ( $settings['mode'] === 'auto' ) {
            $parts[] = 'Detect the language of each customer message and respond in that same language. If you cannot determine the language, respond in English.';

            if ( ! empty( $settings['primary_language'] ) ) {
                $available = self::available_languages();
                $name = $available[ $settings['primary_language'] ] ?? $settings['primary_language'];
                $parts[] = "The store's primary language is {$name}. Default to this when the customer's language is ambiguous.";
            }

            if ( ! empty( $settings['additional_languages'] ) ) {
                $available = self::available_languages();
                $names = array_map( fn( $code ) => $available[ $code ] ?? $code, $settings['additional_languages'] );
                $parts[] = 'Supported languages: ' . implode( ', ', $names ) . '. If a customer writes in an unsupported language, respond politely in the primary language.';
            }
        } else {
            $available = self::available_languages();
            $name = $available[ $settings['mode'] ] ?? $settings['mode'];
            $parts[] = "Always respond in {$name}, regardless of what language the customer uses.";
        }

        // Custom language note
        if ( ! empty( $settings['language_note'] ) ) {
            $parts[] = $settings['language_note'];
        }

        if ( empty( $parts ) ) {
            return '';
        }

        return "\n\n## Language\n" . implode( "\n", $parts );
    }
}
