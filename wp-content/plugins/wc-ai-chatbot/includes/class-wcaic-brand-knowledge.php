<?php
defined( 'ABSPATH' ) || exit;

/**
 * Brand Knowledge Base — stores brand story, FAQ, policies, and custom knowledge
 * that gets injected into the AI system prompt for brand-aware responses.
 *
 * Storage: wp_options key 'wcaic_brand_knowledge'
 */
class WCAIC_Brand_Knowledge {

    private static string $option_key = 'wcaic_brand_knowledge';

    /**
     * Default knowledge sections.
     */
    public static function defaults(): array {
        return [
            'brand_story' => [
                'label'   => __( 'Brand Story', 'wc-ai-chatbot' ),
                'hint'    => __( 'Your brand origin, mission, values, and what makes you unique. The AI will reference this when customers ask "Who are you?" or "Tell me about this store."', 'wc-ai-chatbot' ),
                'content' => '',
                'enabled' => true,
            ],
            'faq' => [
                'label'   => __( 'FAQ', 'wc-ai-chatbot' ),
                'hint'    => __( 'Common questions and answers. Format: one Q&A per line or use "Q:" and "A:" prefixes. The AI will use these to answer customer questions accurately.', 'wc-ai-chatbot' ),
                'content' => '',
                'enabled' => true,
            ],
            'shipping_policy' => [
                'label'   => __( 'Shipping Policy', 'wc-ai-chatbot' ),
                'hint'    => __( 'Shipping methods, delivery times, costs, international shipping info. The AI will reference this when customers ask about delivery.', 'wc-ai-chatbot' ),
                'content' => '',
                'enabled' => true,
            ],
            'return_policy' => [
                'label'   => __( 'Return & Refund Policy', 'wc-ai-chatbot' ),
                'hint'    => __( 'Return window, conditions, refund process, exchange rules. The AI will reference this for return-related questions.', 'wc-ai-chatbot' ),
                'content' => '',
                'enabled' => true,
            ],
            'warranty_policy' => [
                'label'   => __( 'Warranty & Guarantees', 'wc-ai-chatbot' ),
                'hint'    => __( 'Product warranties, satisfaction guarantees, service commitments.', 'wc-ai-chatbot' ),
                'content' => '',
                'enabled' => false,
            ],
            'custom_knowledge' => [
                'label'   => __( 'Custom Knowledge', 'wc-ai-chatbot' ),
                'hint'    => __( 'Any additional information the AI should know: size guides, care instructions, material details, seasonal promotions, etc.', 'wc-ai-chatbot' ),
                'content' => '',
                'enabled' => false,
            ],
        ];
    }

    /**
     * Get all knowledge sections (merged with defaults).
     */
    public static function get_all(): array {
        $saved    = get_option( self::$option_key, [] );
        if ( ! is_array( $saved ) ) {
            $saved = [];
        }
        $defaults = self::defaults();

        $merged = [];
        foreach ( $defaults as $key => $default ) {
            $merged[ $key ] = [
                'label'   => $default['label'],
                'hint'    => $default['hint'],
                'content' => $saved[ $key ]['content'] ?? $default['content'],
                'enabled' => $saved[ $key ]['enabled'] ?? $default['enabled'],
            ];
        }

        return $merged;
    }

    /**
     * Save knowledge sections.
     */
    public static function save( array $data ): void {
        $defaults = self::defaults();
        $clean    = [];

        foreach ( $defaults as $key => $default ) {
            $clean[ $key ] = [
                'content' => sanitize_textarea_field( $data[ $key ]['content'] ?? '' ),
                'enabled' => ! empty( $data[ $key ]['enabled'] ),
            ];
        }

        update_option( self::$option_key, $clean, false );
    }

    /**
     * Build the knowledge context string for injection into the system prompt.
     * Only includes enabled sections with non-empty content.
     */
    public static function build_context(): string {
        $sections = self::get_all();
        $parts    = [];

        foreach ( $sections as $key => $section ) {
            if ( ! $section['enabled'] || empty( trim( $section['content'] ) ) ) {
                continue;
            }
            $parts[] = "### {$section['label']}\n{$section['content']}";
        }

        if ( empty( $parts ) ) {
            return '';
        }

        return "\n\n## Brand Knowledge\nUse the following information to answer customer questions accurately. Always prefer this information over assumptions.\n\n" . implode( "\n\n", $parts );
    }

    /**
     * Get a single section's content (if enabled).
     */
    public static function get_section( string $key ): string {
        $sections = self::get_all();
        if ( ! isset( $sections[ $key ] ) || ! $sections[ $key ]['enabled'] ) {
            return '';
        }
        return $sections[ $key ]['content'];
    }
}
