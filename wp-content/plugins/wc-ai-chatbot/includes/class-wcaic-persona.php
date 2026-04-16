<?php
defined( 'ABSPATH' ) || exit;

/**
 * AI Persona Engine — configurable brand personality for the AI assistant.
 *
 * Supports preset personas and custom spectrum-based configuration.
 * Storage: wp_options key 'wcaic_persona'
 */
class WCAIC_Persona {

    private static string $option_key = 'wcaic_persona';

    /**
     * Persona presets — ready-to-use configurations.
     */
    public static function presets(): array {
        return [
            'friendly_shop' => [
                'label'       => __( 'Friendly Local Shop', 'wc-ai-chatbot' ),
                'description' => __( 'Warm, approachable, like chatting with a knowledgeable friend.', 'wc-ai-chatbot' ),
                'selling'     => 30,
                'formality'   => 20,
                'detail'      => 50,
                'proactivity' => 40,
                'rules'       => "Be warm and conversational. Use a friendly, approachable tone as if the customer just walked into your shop. Share genuine opinions when asked. It's okay to be enthusiastic about products you think suit the customer.",
            ],
            'luxury_boutique' => [
                'label'       => __( 'Luxury Boutique', 'wc-ai-chatbot' ),
                'description' => __( 'Refined, attentive, understated elegance.', 'wc-ai-chatbot' ),
                'selling'     => 20,
                'formality'   => 80,
                'detail'      => 70,
                'proactivity' => 30,
                'rules'       => "Maintain a refined, polished tone. Never be pushy. Emphasize craftsmanship, quality, and exclusivity. Use elegant language. Let the products speak for themselves. Address the customer with respect and attentiveness.",
            ],
            'tech_expert' => [
                'label'       => __( 'Tech Expert', 'wc-ai-chatbot' ),
                'description' => __( 'Knowledgeable, precise, specification-focused.', 'wc-ai-chatbot' ),
                'selling'     => 40,
                'formality'   => 50,
                'detail'      => 90,
                'proactivity' => 60,
                'rules'       => "Focus on specifications, comparisons, and technical details. Be precise and data-driven. Proactively mention compatibility, performance benchmarks, and technical advantages. Help customers make informed decisions based on their technical needs.",
            ],
            'enthusiastic_guide' => [
                'label'       => __( 'Enthusiastic Guide', 'wc-ai-chatbot' ),
                'description' => __( 'Energetic, passionate, great for lifestyle brands.', 'wc-ai-chatbot' ),
                'selling'     => 60,
                'formality'   => 10,
                'detail'      => 50,
                'proactivity' => 80,
                'rules'       => "Be energetic and passionate about the products. Share excitement naturally. Proactively suggest complementary items and new arrivals. Paint a picture of how products fit into the customer's lifestyle. Keep the energy positive and inspiring.",
            ],
            'print_consultant' => [
                'label'       => __( 'Print Consultant', 'wc-ai-chatbot' ),
                'description' => __( 'Professional print advisor. Knows paper stocks, finishes, and file prep. Great for printing companies.', 'wc-ai-chatbot' ),
                'selling'     => 50,
                'formality'   => 60,
                'detail'      => 80,
                'proactivity' => 70,
                'rules'       => "You are a professional print consultant. Know paper stocks (uncoated, gloss, matte, linen, kraft, silk laminated, soft touch), finishes (spot UV, foil stamping, embossing, die-cutting), and standard sizes. Recommend products based on the customer's use case. Always mention relevant file preparation requirements. Proactively suggest complementary items (e.g., business cards + letterhead + envelopes for a rebrand). Warn about common file preparation mistakes. Mention turnaround time options when discussing products.",
            ],
            'custom' => [
                'label'       => __( 'Custom', 'wc-ai-chatbot' ),
                'description' => __( 'Define your own persona with custom rules and spectrum settings.', 'wc-ai-chatbot' ),
                'selling'     => 40,
                'formality'   => 50,
                'detail'      => 50,
                'proactivity' => 50,
                'rules'       => '',
            ],
        ];
    }

    /**
     * Default persona settings.
     */
    public static function defaults(): array {
        return [
            'preset'           => 'friendly_shop',
            'selling'          => 30,
            'formality'        => 20,
            'detail'           => 50,
            'proactivity'      => 40,
            'custom_rules'     => '',
            'prohibited_topics' => '',
            'prohibited_words'  => '',
            'escalation_message' => __( 'I\'d be happy to connect you with our support team for more help with this.', 'wc-ai-chatbot' ),
            'off_topic_message'  => __( 'I\'m here to help you with shopping and product questions. Is there anything about our products I can help with?', 'wc-ai-chatbot' ),
            'max_conversation_length' => 50,
        ];
    }

    /**
     * Get current persona settings.
     */
    public static function get_settings(): array {
        $saved    = get_option( self::$option_key, [] );
        $defaults = self::defaults();
        if ( ! is_array( $saved ) ) {
            $saved = [];
        }
        return array_merge( $defaults, $saved );
    }

    /**
     * Save persona settings.
     */
    public static function save( array $data ): void {
        $presets = self::presets();
        $clean   = [];

        $clean['preset'] = array_key_exists( $data['preset'] ?? '', $presets ) ? $data['preset'] : 'friendly_shop';

        // If a non-custom preset is selected, apply its spectrum values
        if ( $clean['preset'] !== 'custom' && isset( $presets[ $clean['preset'] ] ) ) {
            $preset = $presets[ $clean['preset'] ];
            $clean['selling']     = $preset['selling'];
            $clean['formality']   = $preset['formality'];
            $clean['detail']      = $preset['detail'];
            $clean['proactivity'] = $preset['proactivity'];
        } else {
            $clean['selling']     = max( 0, min( 100, absint( $data['selling'] ?? 40 ) ) );
            $clean['formality']   = max( 0, min( 100, absint( $data['formality'] ?? 50 ) ) );
            $clean['detail']      = max( 0, min( 100, absint( $data['detail'] ?? 50 ) ) );
            $clean['proactivity'] = max( 0, min( 100, absint( $data['proactivity'] ?? 50 ) ) );
        }

        $clean['custom_rules']       = sanitize_textarea_field( $data['custom_rules'] ?? '' );
        $clean['prohibited_topics']   = sanitize_textarea_field( $data['prohibited_topics'] ?? '' );
        $clean['prohibited_words']    = sanitize_textarea_field( $data['prohibited_words'] ?? '' );
        $clean['escalation_message']  = sanitize_text_field( $data['escalation_message'] ?? '' );
        $clean['off_topic_message']   = sanitize_text_field( $data['off_topic_message'] ?? '' );
        $clean['max_conversation_length'] = max( 10, min( 200, absint( $data['max_conversation_length'] ?? 50 ) ) );

        update_option( self::$option_key, $clean, false );
    }

    /**
     * Build persona rules for the system prompt based on spectrum values.
     */
    public static function build_persona_rules(): string {
        $settings = self::get_settings();
        $presets  = self::presets();
        $rules    = [];

        // Preset-specific rules
        $preset_key = $settings['preset'];
        if ( $preset_key !== 'custom' && isset( $presets[ $preset_key ] ) && ! empty( $presets[ $preset_key ]['rules'] ) ) {
            $rules[] = $presets[ $preset_key ]['rules'];
        }

        // Spectrum-derived rules
        $selling     = (int) $settings['selling'];
        $formality   = (int) $settings['formality'];
        $detail      = (int) $settings['detail'];
        $proactivity = (int) $settings['proactivity'];

        // Selling posture
        if ( $selling < 25 ) {
            $rules[] = 'Be purely supportive. Never push products. Only show items when the customer explicitly asks.';
        } elseif ( $selling > 75 ) {
            $rules[] = 'Actively recommend products that match the conversation context. Suggest complementary items and upsells when relevant.';
        }

        // Formality
        if ( $formality < 25 ) {
            $rules[] = 'Use a casual, conversational tone. Short sentences. Contractions are fine.';
        } elseif ( $formality > 75 ) {
            $rules[] = 'Use a professional, polished tone. Complete sentences. Avoid slang or overly casual language.';
        }

        // Detail level
        if ( $detail < 25 ) {
            $rules[] = 'Keep replies very concise — 1-2 sentences maximum. Only expand if asked.';
        } elseif ( $detail > 75 ) {
            $rules[] = 'Provide detailed, thorough responses. Include specifications, comparisons, and context. 3-5 sentences when explaining products.';
        } else {
            $rules[] = 'Keep replies moderate in length — 2-3 sentences. Expand when the question warrants it.';
        }

        // Proactivity
        if ( $proactivity < 25 ) {
            $rules[] = 'Only respond to what the customer asks. Do not volunteer additional information or suggestions.';
        } elseif ( $proactivity > 75 ) {
            $rules[] = 'Proactively suggest related items, mention ongoing promotions, and anticipate follow-up questions.';
        }

        // Custom rules
        if ( ! empty( $settings['custom_rules'] ) ) {
            $rules[] = $settings['custom_rules'];
        }

        // Prohibited topics
        if ( ! empty( $settings['prohibited_topics'] ) ) {
            $topics = array_filter( array_map( 'trim', explode( "\n", $settings['prohibited_topics'] ) ) );
            if ( ! empty( $topics ) ) {
                $rules[] = 'Never discuss or engage with these topics: ' . implode( ', ', $topics ) . '. Politely redirect to shopping.';
            }
        }

        // Prohibited words
        if ( ! empty( $settings['prohibited_words'] ) ) {
            $words = array_filter( array_map( 'trim', explode( "\n", $settings['prohibited_words'] ) ) );
            if ( ! empty( $words ) ) {
                $rules[] = 'Never use these words or brand names in your responses: ' . implode( ', ', $words ) . '.';
            }
        }

        if ( empty( $rules ) ) {
            return '';
        }

        return "\n\n## Brand Persona\n" . implode( "\n", $rules );
    }
}
