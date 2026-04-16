<?php
defined( 'ABSPATH' ) || exit;

/**
 * Anthropic Messages API client + SSE streaming.
 * Includes Bug Fix #1: fix_tool_use_inputs() for {} vs [] serialization.
 */
class WCAIC_AI_Client_Anthropic extends WCAIC_AI_Client {

    private const ENDPOINT    = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';

    private function get_api_key(): string {
        $encrypted = get_option( 'wcaic_api_keys_encrypted', [] );
        if ( isset( $encrypted['anthropic'] ) && is_array( $encrypted['anthropic'] ) ) {
            return WCAIC_Admin::decrypt_key( $encrypted['anthropic'] );
        }
        return '';
    }

    private function get_model(): string {
        return $this->settings['anthropic_model'] ?? 'claude-sonnet-4-6';
    }

    // -------------------------------------------------------------------------
    // Normalize messages: strict user/assistant alternation (Anthropic requirement)
    // -------------------------------------------------------------------------
    private function normalize_messages( array $messages ): array {
        $normalized = [];
        $prev_role  = null;

        foreach ( $messages as $msg ) {
            $role = $msg['role'] ?? '';

            // Skip system-role messages (handled separately as top-level param)
            if ( $role === 'system' ) {
                continue;
            }

            if ( $role === $prev_role ) {
                // Merge consecutive same-role messages
                $last  = &$normalized[ count( $normalized ) - 1 ];
                $last_content = $last['content'];
                $this_content = $msg['content'];

                // Ensure both are arrays
                if ( ! is_array( $last_content ) ) {
                    $last_content = [ [ 'type' => 'text', 'text' => (string) $last_content ] ];
                }
                if ( ! is_array( $this_content ) ) {
                    $this_content = [ [ 'type' => 'text', 'text' => (string) $this_content ] ];
                }

                $last['content'] = array_merge( $last_content, $this_content );
            } else {
                $normalized[] = $msg;
                $prev_role    = $role;
            }
        }

        return $normalized;
    }

    /**
     * Bug Fix #1: Cast all tool_use.input fields to stdClass so PHP json_encode
     * produces {} for empty inputs instead of [].
     */
    private function fix_tool_use_inputs( array $messages ): array {
        foreach ( $messages as &$message ) {
            if ( is_array( $message['content'] ) ) {
                foreach ( $message['content'] as &$block ) {
                    if ( isset( $block['type'] ) && $block['type'] === 'tool_use' ) {
                        $block['input'] = (object) $block['input'];
                    }
                }
                unset( $block );
            }
        }
        unset( $message );
        return $messages;
    }

    private function build_messages( array $history, string $system_prompt ): array {
        // Convert OpenAI-style tool messages to Anthropic format
        $converted = [];
        foreach ( $history as $msg ) {
            $role = $msg['role'] ?? '';

            if ( $role === 'system' ) {
                continue;
            }

            if ( $role === 'tool' ) {
                // Convert tool result to Anthropic user message
                $converted[] = [
                    'role'    => 'user',
                    'content' => [ [
                        'type'        => 'tool_result',
                        'tool_use_id' => $msg['tool_call_id'] ?? '',
                        'content'     => $msg['content'] ?? '',
                    ] ],
                ];
                continue;
            }

            if ( $role === 'assistant' && ! empty( $msg['tool_calls'] ) ) {
                // Convert OpenAI tool_calls to Anthropic tool_use blocks
                $content = [];
                if ( ! empty( $msg['content'] ) ) {
                    $content[] = [ 'type' => 'text', 'text' => $msg['content'] ];
                }
                foreach ( $msg['tool_calls'] as $tc ) {
                    $args = json_decode( $tc['function']['arguments'] ?? '{}', true );
                    $content[] = [
                        'type'  => 'tool_use',
                        'id'    => $tc['id'],
                        'name'  => $tc['function']['name'],
                        'input' => is_array( $args ) ? $args : [],
                    ];
                }
                $converted[] = [ 'role' => 'assistant', 'content' => $content ];
                continue;
            }

            $converted[] = $msg;
        }

        // Cap history
        $max       = (int) ( $this->settings['max_history'] ?? 20 );
        $converted = array_slice( $converted, -$max );
        $converted = $this->normalize_messages( $converted );
        $converted = $this->fix_tool_use_inputs( $converted );

        return $converted;
    }

    // -------------------------------------------------------------------------
    // Blocking request
    // -------------------------------------------------------------------------
    protected function send_request( array $history, array $tools, string $system_prompt ): WCAIC_AI_Response|WP_Error {
        $api_key = $this->get_api_key();
        if ( ! $api_key ) {
            return new WP_Error( 'no_api_key', 'Anthropic API key is not configured.' );
        }

        $messages = $this->build_messages( $history, $system_prompt );
        $body     = [
            'model'      => $this->get_model(),
            'system'     => $system_prompt,
            'messages'   => $messages,
            'tools'      => WCAIC_Tool_Definitions::get_tools( 'anthropic' ),
            'max_tokens' => 1024,
        ];

        $raw = wp_remote_post( self::ENDPOINT, [
            'timeout' => 30,
            'headers' => [
                'x-api-key'         => $api_key,
                'anthropic-version' => self::API_VERSION,
                'content-type'      => 'application/json',
            ],
            'body' => wp_json_encode( $body ),
        ] );

        if ( is_wp_error( $raw ) ) {
            return $raw;
        }

        $code      = wp_remote_retrieve_response_code( $raw );
        $body_resp = json_decode( wp_remote_retrieve_body( $raw ), true );

        if ( $code === 429 ) {
            return new WP_Error( 'rate_limit', 'Anthropic rate limit exceeded.' );
        }
        if ( $code === 401 ) {
            return new WP_Error( 'invalid_key', 'Invalid Anthropic API key.' );
        }
        if ( $code >= 500 ) {
            return new WP_Error( 'server_error', 'Anthropic server error.' );
        }
        if ( $code !== 200 ) {
            return new WP_Error( 'api_error', $body_resp['error']['message'] ?? 'Unknown Anthropic error.' );
        }

        return $this->parse_response( $body_resp );
    }

    private function parse_response( array $body ): WCAIC_AI_Response {
        $response = new WCAIC_AI_Response();
        $content  = $body['content'] ?? [];

        $response->prompt_tokens     = $body['usage']['input_tokens']  ?? 0;
        $response->completion_tokens = $body['usage']['output_tokens'] ?? 0;
        $response->finish_reason     = $body['stop_reason'] ?? '';

        foreach ( $content as $block ) {
            if ( ( $block['type'] ?? '' ) === 'text' ) {
                $response->text .= $block['text'];
            } elseif ( ( $block['type'] ?? '' ) === 'tool_use' ) {
                $response->tool_calls[] = [
                    'id'        => $block['id'],
                    'name'      => $block['name'],
                    'arguments' => $block['input'], // already decoded array
                ];
            }
        }

        return $response;
    }

    // -------------------------------------------------------------------------
    // SSE streaming (fopen)
    // -------------------------------------------------------------------------
    protected function send_stream_request( array $history, array $tools, string $system_prompt ): WCAIC_AI_Response|WP_Error {
        $api_key = $this->get_api_key();
        if ( ! $api_key ) {
            return new WP_Error( 'no_api_key', 'Anthropic API key is not configured.' );
        }

        $messages = $this->build_messages( $history, $system_prompt );
        $body     = wp_json_encode( [
            'model'      => $this->get_model(),
            'system'     => $system_prompt,
            'messages'   => $messages,
            'tools'      => WCAIC_Tool_Definitions::get_tools( 'anthropic' ),
            'max_tokens' => 1024,
            'stream'     => true,
        ] );

        $context = stream_context_create( [
            'http' => [
                'method'  => 'POST',
                'timeout' => 30,
                'header'  => implode( "\r\n", [
                    'x-api-key: ' . $api_key,
                    'anthropic-version: ' . self::API_VERSION,
                    'content-type: application/json',
                    'accept: text/event-stream',
                ] ),
                'content' => $body,
            ],
        ] );

        $stream = @fopen( self::ENDPOINT, 'r', false, $context );
        if ( ! $stream ) {
            return new WP_Error( 'stream_error', 'Failed to open Anthropic stream.' );
        }

        $response      = new WCAIC_AI_Response();
        $current_event = '';
        $blocks        = [];        // indexed by content_block index
        $tool_buffers  = [];       // accumulate JSON input per tool_use block
        $stop_reason   = '';

        while ( ! feof( $stream ) ) {
            $line = fgets( $stream, 4096 );
            if ( $line === false ) {
                continue;
            }
            $line = rtrim( $line, "\r\n" );

            if ( strpos( $line, 'event: ' ) === 0 ) {
                $current_event = trim( substr( $line, 7 ) );
                continue;
            }

            if ( strpos( $line, 'data: ' ) !== 0 ) {
                continue;
            }

            $data = json_decode( substr( $line, 6 ), true );
            if ( ! is_array( $data ) ) {
                continue;
            }

            switch ( $current_event ) {
                case 'content_block_start':
                    $idx   = $data['index'] ?? 0;
                    $block = $data['content_block'] ?? [];
                    $blocks[ $idx ] = $block;
                    if ( ( $block['type'] ?? '' ) === 'tool_use' ) {
                        $tool_buffers[ $idx ] = '';
                    }
                    break;

                case 'content_block_delta':
                    $idx   = $data['index'] ?? 0;
                    $delta = $data['delta']  ?? [];
                    $dt    = $delta['type']  ?? '';

                    if ( $dt === 'text_delta' ) {
                        $text = $delta['text'] ?? '';
                        $response->text .= $text;
                        $this->emit_sse( 'token', [ 'text' => $text ] );
                    } elseif ( $dt === 'input_json_delta' ) {
                        $tool_buffers[ $idx ] = ( $tool_buffers[ $idx ] ?? '' ) . ( $delta['partial_json'] ?? '' );
                    }
                    break;

                case 'content_block_stop':
                    $idx   = $data['index'] ?? 0;
                    $block = $blocks[ $idx ] ?? [];
                    if ( ( $block['type'] ?? '' ) === 'tool_use' ) {
                        $input = json_decode( $tool_buffers[ $idx ] ?? '{}', true );
                        $response->tool_calls[] = [
                            'id'        => $block['id'],
                            'name'      => $block['name'],
                            'arguments' => is_array( $input ) ? $input : [],
                        ];
                    }
                    break;

                case 'message_delta':
                    $stop_reason             = $data['delta']['stop_reason'] ?? '';
                    $response->finish_reason = $stop_reason;
                    break;
            }
        }

        fclose( $stream );
        return $response;
    }

    // -------------------------------------------------------------------------
    // History helpers — Anthropic format
    // -------------------------------------------------------------------------
    protected function append_assistant_with_tools( array $history, WCAIC_AI_Response $response ): array {
        $content = [];
        if ( $response->text ) {
            $content[] = [ 'type' => 'text', 'text' => $response->text ];
        }
        foreach ( $response->tool_calls as $tc ) {
            $content[] = [
                'type'  => 'tool_use',
                'id'    => $tc['id'],
                'name'  => $tc['name'],
                'input' => (object) ( is_array( $tc['arguments'] ) ? $tc['arguments'] : [] ),
            ];
        }
        $history[] = [ 'role' => 'assistant', 'content' => $content ];
        return $history;
    }

    protected function append_tool_result( array $history, array $tool_call, array $result ): array {
        $history[] = [
            'role'    => 'user',
            'content' => [ [
                'type'        => 'tool_result',
                'tool_use_id' => $tool_call['id'],
                'content'     => wp_json_encode( $result ),
            ] ],
        ];
        return $history;
    }

    protected function append_text_message( array $history, string $text ): array {
        $history[] = [ 'role' => 'assistant', 'content' => [ [ 'type' => 'text', 'text' => $text ] ] ];
        return $history;
    }

    protected function decode_tool_args( array $tool_call ): array {
        // Anthropic args are already decoded arrays
        $args = $tool_call['arguments'] ?? [];
        return is_array( $args ) ? $args : [];
    }
}
