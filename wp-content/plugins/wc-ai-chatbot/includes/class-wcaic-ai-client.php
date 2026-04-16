<?php
defined( 'ABSPATH' ) || exit;

/**
 * Abstract base AI client. Handles function-calling loop and attachment pipeline.
 */
abstract class WCAIC_AI_Client {

    protected array  $settings     = [];
    protected array  $attachments  = [];
    protected int    $loop_count   = 0;
    protected int    $max_loops    = 5;

    protected string $system_prompt_template = <<<PROMPT
You are a helpful shopping assistant for {{store_name}}. Follow these rules:
1. Never use emoji.
2. Never use bullet lists — write in prose only.
3. Keep each reply to 1-2 sentences maximum.
4. Always call search_products before recommending any product.
5. Use tools for all actions (cart, details, pricing, etc.).
6. Never invent product names, prices, or availability.
7. If a tool returns an error, tell the user clearly and briefly.
8. Stay focused on shopping — politely decline off-topic requests.
9. Keep your tone warm but brief.
10. IMPORTANT: When the customer refers to "the first one", "the second one", "that product", etc., look at the previous tool results in the conversation history to identify which product they mean. Use the product ID from the previous search_products result to call get_product_details or get_product_calculator. Never say you don't have context — the tool results in the conversation contain the data.
11. When showing product details, always mention the product name, price, and key features.
12. CRITICAL: When a customer says "show me", "show the product", "I want to see it", "show product page", "show details", or similar — ALWAYS call get_product_details (or get_product_calculator for configurable products) to display the visual product card. Never just describe a product in text when the customer wants to SEE it. The tool call generates a visual product card with images and options in the chat. If the product has a calculator, always use get_product_calculator so the customer can configure options and see pricing.
13. Never just paste a URL and tell the customer to go there. Always use tools to show product information directly in the conversation.
Store currency: {{currency}}. Available categories: {{categories}}.
PROMPT;

    // Factory
    public static function create( array $settings ): self {
        $provider = $settings['provider'] ?? 'openai';
        if ( $provider === 'anthropic' ) {
            return new WCAIC_AI_Client_Anthropic( $settings );
        }
        return new WCAIC_AI_Client_OpenAI( $settings );
    }

    public function __construct( array $settings ) {
        $this->settings  = $settings;
        $this->max_loops = (int) ( $settings['max_iterations'] ?? 5 );
    }

    // -------------------------------------------------------------------------
    // Public: blocking chat
    // -------------------------------------------------------------------------
    public function process_chat( array $history, array $tools, array $settings ): array {
        $this->attachments = [];
        $this->loop_count  = 0;

        $system_prompt  = $this->build_system_prompt();
        $total_prompt   = 0;
        $total_complete = 0;

        while ( $this->loop_count < $this->max_loops ) {
            $this->loop_count++;

            $response = $this->send_request( $history, $tools, $system_prompt );

            if ( is_wp_error( $response ) ) {
                WCAIC_Logger::error( 'AI request failed: ' . $response->get_error_message() );
                return [
                    'text'        => 'Sorry, I encountered an error. Please try again.',
                    'attachments' => $this->attachments,
                    'error'       => 'An internal error occurred. Please try again shortly.',
                ];
            }

            $total_prompt   += $response->prompt_tokens;
            $total_complete += $response->completion_tokens;

            if ( $response->has_tool_calls() ) {
                $history = $this->append_assistant_with_tools( $history, $response );

                foreach ( $response->tool_calls as $tool_call ) {
                    $args   = $this->decode_tool_args( $tool_call );
                    $result = WCAIC_Tool_Executor::execute(
                        $tool_call['name'],
                        $args,
                        $this->get_session_id( $settings ),
                        $settings
                    );

                    if ( is_wp_error( $result ) ) {
                        $result = [ 'error' => $result->get_error_message() ];
                    } else {
                        $this->collect_attachment( $tool_call['name'], $result );
                    }

                    $history = $this->append_tool_result( $history, $tool_call, $result );
                }
                continue;
            }

            if ( $response->has_text() ) {
                $history = $this->append_text_message( $history, $response->text );
                return [
                    'text'              => $response->text,
                    'attachments'       => $this->attachments,
                    'history'           => $history,
                    'prompt_tokens'     => $total_prompt,
                    'completion_tokens' => $total_complete,
                    'loop_iterations'   => $this->loop_count,
                ];
            }

            break;
        }

        return [
            'text'              => 'I was unable to complete your request. Please try again.',
            'attachments'       => $this->attachments,
            'history'           => $history,
            'prompt_tokens'     => $total_prompt,
            'completion_tokens' => $total_complete,
            'loop_iterations'   => $this->loop_count,
        ];
    }

    // -------------------------------------------------------------------------
    // Public: streaming chat
    // -------------------------------------------------------------------------
    public function process_chat_stream( array $history, array $tools, array $settings ): array {
        $this->attachments = [];
        $this->loop_count  = 0;
        $system_prompt     = $this->build_system_prompt();

        $this->emit_sse( 'status', [ 'state' => 'thinking' ] );

        while ( $this->loop_count < $this->max_loops ) {
            $this->loop_count++;

            // Tool phases: no token streaming, just execute tools
            if ( $this->loop_count > 1 || ! $this->provider_supports_streaming() ) {
                $response = $this->send_request( $history, $tools, $system_prompt );
            } else {
                // First iteration: stream
                $response = $this->send_stream_request( $history, $tools, $system_prompt );
            }

            if ( is_wp_error( $response ) ) {
                WCAIC_Logger::error( 'AI stream failed: ' . $response->get_error_message() );
                $this->emit_sse( 'error', [ 'message' => 'An error occurred. Please try again.' ] );
                return [ 'history' => $history, 'text' => '' ];
            }

            if ( $response->has_tool_calls() ) {
                $history = $this->append_assistant_with_tools( $history, $response );

                foreach ( $response->tool_calls as $tool_call ) {
                    $args   = $this->decode_tool_args( $tool_call );
                    $result = WCAIC_Tool_Executor::execute(
                        $tool_call['name'],
                        $args,
                        $this->get_session_id( $settings ),
                        $settings
                    );

                    if ( is_wp_error( $result ) ) {
                        $result = [ 'error' => $result->get_error_message() ];
                    } else {
                        $this->collect_attachment( $tool_call['name'], $result );
                    }

                    $history = $this->append_tool_result( $history, $tool_call, $result );
                }
                continue;
            }

            // Final text — append assistant reply to history.
            if ( $response->has_text() ) {
                $history = $this->append_text_message( $history, $response->text );
            }

            $this->emit_sse( 'attachments', [ 'attachments' => $this->attachments ] );

            $cart_data = [
                'item_count' => WC()->cart ? WC()->cart->get_cart_contents_count() : 0,
                'total'      => WC()->cart ? wc_format_decimal( WC()->cart->get_cart_contents_total(), 2 ) : '0.00',
            ];
            $this->emit_sse( 'done', [ 'cart' => $cart_data ] );
            return [ 'history' => $history, 'text' => $response->text ?? '' ];
        }

        $this->emit_sse( 'error', [ 'message' => 'Max iterations reached.' ] );
        return [ 'history' => $history, 'text' => '' ];
    }

    // -------------------------------------------------------------------------
    // Attachment pipeline
    // -------------------------------------------------------------------------
    protected function collect_attachment( string $tool_name, array $result ): void {
        $attachment = match ( $tool_name ) {
            'search_products'      => array_merge( [ 'type' => 'products' ], $result ),
            'get_product_details'  => [ 'type' => 'product_detail', 'product' => $result ],
            'add_to_cart'          => [ 'type' => 'cart_action', 'action' => 'add',    'data' => $result ],
            'remove_from_cart'     => [ 'type' => 'cart_action', 'action' => 'remove', 'data' => $result ],
            'update_cart_quantity' => [ 'type' => 'cart_action', 'action' => 'update', 'data' => $result ],
            'get_cart'             => [ 'type' => 'cart',         'data' => $result ],
            'apply_coupon'         => [ 'type' => 'cart_action', 'action' => 'coupon', 'data' => $result ],
            'get_checkout_url'     => [ 'type' => 'checkout',    'data' => $result ],
            'get_order_status'     => [ 'type' => 'order',       'data' => $result ],
            'get_customer_orders'  => [ 'type' => 'orders',      'data' => $result ],
            'get_store_policies'   => [ 'type' => 'policies',    'data' => $result ],
            'compare_products'     => [ 'type' => 'comparison',  'data' => $result ],
            'estimate_shipping'       => [ 'type' => 'shipping',    'data' => $result ],
            'get_product_calculator'  => [ 'type' => 'calculator',  'data' => $result ],
            'add_calculator_to_cart'  => [ 'type' => 'cart_action', 'action' => 'add', 'data' => $result ],
            default                   => [],
        };
        if ( ! empty( $attachment ) ) {
            $this->attachments[] = $attachment;
        }
    }

    // -------------------------------------------------------------------------
    // System prompt builder
    // -------------------------------------------------------------------------
    protected function build_system_prompt(): string {
        $template = $this->settings['system_prompt'] ?? '';
        if ( empty( $template ) ) {
            $template = $this->system_prompt_template;
        }

        $categories  = [];
        $terms       = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => true, 'number' => 20 ] );
        if ( ! is_wp_error( $terms ) ) {
            $categories = array_column( (array) $terms, 'name' );
        }

        $replacements = [
            '{{store_name}}'  => get_bloginfo( 'name' ),
            '{{currency}}'    => get_woocommerce_currency(),
            '{{categories}}'  => implode( ', ', $categories ),
        ];

        $prompt = str_replace( array_keys( $replacements ), array_values( $replacements ), $template );

        // Inject persona rules
        $persona_rules = WCAIC_Persona::build_persona_rules();
        if ( ! empty( $persona_rules ) ) {
            $prompt .= $persona_rules;
        }

        // Inject brand knowledge
        $brand_context = WCAIC_Brand_Knowledge::build_context();
        if ( ! empty( $brand_context ) ) {
            $prompt .= $brand_context;
        }

        // Inject escalation settings
        $persona_settings = WCAIC_Persona::get_settings();
        if ( ! empty( $persona_settings['off_topic_message'] ) ) {
            $prompt .= "\n\nWhen customers ask about non-shopping topics, respond with: \"{$persona_settings['off_topic_message']}\"";
        }
        if ( ! empty( $persona_settings['escalation_message'] ) ) {
            $prompt .= "\nIf you cannot help with a request or it requires human intervention, say: \"{$persona_settings['escalation_message']}\"";
        }

        // Inject language instructions
        $lang_instruction = WCAIC_Language::build_instruction();
        if ( ! empty( $lang_instruction ) ) {
            $prompt .= $lang_instruction;
        }

        // Inject calculator product awareness
        $prompt .= self::build_calculator_instructions();

        return $prompt;
    }

    /**
     * Build calculator product instructions for the system prompt.
     */
    private static function build_calculator_instructions(): string {
        if ( ! class_exists( 'Sqft_Product_Options' ) ) {
            return '';
        }

        // Find products with calculator enabled.
        global $wpdb;
        $calculator_products = $wpdb->get_results(
            "SELECT p.ID, p.post_title FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = 'product' AND p.post_status = 'publish'
             AND pm.meta_key = '_sqft_calculator_enabled' AND pm.meta_value = '1'
             LIMIT 20"
        );

        if ( empty( $calculator_products ) ) {
            return '';
        }

        $product_list = [];
        foreach ( $calculator_products as $p ) {
            $product_list[] = "- \"{$p->post_title}\" (ID: {$p->ID})";
        }

        return "\n\n" .
            "CONFIGURABLE PRINT PRODUCTS:\n" .
            "The following products have an interactive price calculator. " .
            "When a customer asks about these products, use the get_product_calculator tool to show " .
            "the full configurator with all options. The customer can select shape, size, paper stock, " .
            "finishing, quantity, turnaround, and more — the price updates in real time.\n" .
            "Do NOT use add_to_cart for these — use add_calculator_to_cart instead after the customer configures.\n" .
            implode( "\n", $product_list );
    }

    // -------------------------------------------------------------------------
    // SSE emitter
    // -------------------------------------------------------------------------
    protected function emit_sse( string $event, array $data ): void {
        echo "event: {$event}\n";
        echo 'data: ' . wp_json_encode( $data ) . "\n\n";
        if ( ob_get_level() > 0 ) {
            ob_flush();
        }
        flush();
    }

    protected function provider_supports_streaming(): bool {
        return true;
    }

    protected function get_session_id( array $settings ): string {
        return $settings['session_id'] ?? '';
    }

    // -------------------------------------------------------------------------
    // Abstract methods — implemented per provider
    // -------------------------------------------------------------------------
    abstract protected function send_request( array $history, array $tools, string $system_prompt ): WCAIC_AI_Response|WP_Error;
    abstract protected function send_stream_request( array $history, array $tools, string $system_prompt ): WCAIC_AI_Response|WP_Error;
    abstract protected function append_assistant_with_tools( array $history, WCAIC_AI_Response $response ): array;
    abstract protected function append_tool_result( array $history, array $tool_call, array $result ): array;
    abstract protected function append_text_message( array $history, string $text ): array;
    abstract protected function decode_tool_args( array $tool_call ): array;
}
