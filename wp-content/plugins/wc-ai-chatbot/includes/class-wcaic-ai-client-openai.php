<?php
defined( 'ABSPATH' ) || exit;

/**
 * OpenAI completions + SSE streaming client.
 */
class WCAIC_AI_Client_OpenAI extends WCAIC_AI_Client {

    private const ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    private function get_api_key(): string {
        $encrypted = get_option( 'wcaic_api_keys_encrypted', [] );
        if ( isset( $encrypted['openai'] ) && is_array( $encrypted['openai'] ) ) {
            return WCAIC_Admin::decrypt_key( $encrypted['openai'] );
        }
        return '';
    }

    private function get_model(): string {
        return $this->settings['openai_model'] ?? 'gpt-4o-mini';
    }

    // -------------------------------------------------------------------------
    // Blocking request
    // -------------------------------------------------------------------------
    protected function send_request( array $history, array $tools, string $system_prompt ): WCAIC_AI_Response|WP_Error {
        $api_key = $this->get_api_key();
        if ( ! $api_key ) {
            return new WP_Error( 'no_api_key', 'OpenAI API key is not configured.' );
        }

        $messages = $this->build_messages( $history, $system_prompt );
        $body     = [
            'model'       => $this->get_model(),
            'messages'    => $messages,
            'tools'       => WCAIC_Tool_Definitions::get_tools( 'openai' ),
            'temperature' => 0.3,
            'max_tokens'  => 1024,
        ];

        $raw = wp_remote_post( self::ENDPOINT, [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode( $body ),
        ] );

        if ( is_wp_error( $raw ) ) {
            return $raw;
        }

        $code = wp_remote_retrieve_response_code( $raw );
        $body = json_decode( wp_remote_retrieve_body( $raw ), true );

        if ( $code === 429 ) {
            return new WP_Error( 'rate_limit', 'OpenAI rate limit or quota exceeded.' );
        }
        if ( $code === 401 ) {
            return new WP_Error( 'invalid_key', 'Invalid OpenAI API key.' );
        }
        if ( $code >= 500 ) {
            return new WP_Error( 'server_error', 'OpenAI server error.' );
        }
        if ( $code !== 200 || ! isset( $body['choices'][0] ) ) {
            return new WP_Error( 'api_error', $body['error']['message'] ?? 'Unknown OpenAI error.' );
        }

        return $this->parse_response( $body );
    }

    private function parse_response( array $body ): WCAIC_AI_Response {
        $response   = new WCAIC_AI_Response();
        $choice     = $body['choices'][0];
        $message    = $choice['message'];
        $finish     = $choice['finish_reason'] ?? '';

        $response->finish_reason      = $finish;
        $response->prompt_tokens      = $body['usage']['prompt_tokens']     ?? 0;
        $response->completion_tokens  = $body['usage']['completion_tokens'] ?? 0;

        if ( ! empty( $message['content'] ) ) {
            $response->text = $message['content'];
        }

        if ( ! empty( $message['tool_calls'] ) ) {
            foreach ( $message['tool_calls'] as $tc ) {
                $response->tool_calls[] = [
                    'id'        => $tc['id'],
                    'name'      => $tc['function']['name'],
                    'arguments' => $tc['function']['arguments'], // JSON string
                ];
            }
        }

        return $response;
    }

    // -------------------------------------------------------------------------
    // Streaming request (fopen SSE)
    // -------------------------------------------------------------------------
    protected function send_stream_request( array $history, array $tools, string $system_prompt ): WCAIC_AI_Response|WP_Error {
        $api_key = $this->get_api_key();
        if ( ! $api_key ) {
            return new WP_Error( 'no_api_key', 'OpenAI API key is not configured.' );
        }

        $messages = $this->build_messages( $history, $system_prompt );
        $body     = wp_json_encode( [
            'model'       => $this->get_model(),
            'messages'    => $messages,
            'tools'       => WCAIC_Tool_Definitions::get_tools( 'openai' ),
            'temperature' => 0.3,
            'max_tokens'  => 1024,
            'stream'      => true,
        ] );

        $context = stream_context_create( [
            'http' => [
                'method'  => 'POST',
                'timeout' => 30,
                'header'  => implode( "\r\n", [
                    'Authorization: Bearer ' . $api_key,
                    'Content-Type: application/json',
                    'Accept: text/event-stream',
                ] ),
                'content' => $body,
            ],
        ] );

        $stream = @fopen( self::ENDPOINT, 'r', false, $context );
        if ( ! $stream ) {
            return new WP_Error( 'stream_error', 'Failed to open OpenAI stream.' );
        }

        $full_text   = '';
        $tool_calls  = [];
        $finish      = '';

        while ( ! feof( $stream ) ) {
            $line = fgets( $stream, 4096 );
            if ( $line === false || trim( $line ) === '' ) {
                continue;
            }

            if ( strpos( $line, 'data: ' ) !== 0 ) {
                continue;
            }

            $data = trim( substr( $line, 6 ) );
            if ( $data === '[DONE]' ) {
                break;
            }

            $json = json_decode( $data, true );
            if ( ! is_array( $json ) || ! isset( $json['choices'][0] ) ) {
                continue;
            }

            $delta  = $json['choices'][0]['delta'] ?? [];
            $finish = $json['choices'][0]['finish_reason'] ?? '';

            // Text token
            if ( isset( $delta['content'] ) && $delta['content'] !== null ) {
                $full_text .= $delta['content'];
                $this->emit_sse( 'token', [ 'text' => $delta['content'] ] );
            }

            // Tool call delta
            if ( ! empty( $delta['tool_calls'] ) ) {
                foreach ( $delta['tool_calls'] as $tc_delta ) {
                    $idx = $tc_delta['index'] ?? 0;
                    if ( ! isset( $tool_calls[ $idx ] ) ) {
                        $tool_calls[ $idx ] = [
                            'id'        => '',
                            'name'      => '',
                            'arguments' => '',
                        ];
                    }
                    if ( isset( $tc_delta['id'] ) ) {
                        $tool_calls[ $idx ]['id'] = $tc_delta['id'];
                    }
                    if ( isset( $tc_delta['function']['name'] ) ) {
                        $tool_calls[ $idx ]['name'] .= $tc_delta['function']['name'];
                    }
                    if ( isset( $tc_delta['function']['arguments'] ) ) {
                        $tool_calls[ $idx ]['arguments'] .= $tc_delta['function']['arguments'];
                    }
                }
            }
        }

        fclose( $stream );

        $response               = new WCAIC_AI_Response();
        $response->text         = $full_text;
        $response->finish_reason = $finish;
        $response->tool_calls   = array_values( $tool_calls );

        return $response;
    }

    // -------------------------------------------------------------------------
    // History management
    // -------------------------------------------------------------------------
    private function build_messages( array $history, string $system_prompt ): array {
        $messages = [ [ 'role' => 'system', 'content' => $system_prompt ] ];

        // Cap history
        $max     = (int) ( $this->settings['max_history'] ?? 20 );
        $history = array_slice( $history, -$max );

        return array_merge( $messages, $history );
    }

    protected function append_assistant_with_tools( array $history, WCAIC_AI_Response $response ): array {
        $tool_calls = array_map( static function ( array $tc ): array {
            return [
                'id'       => $tc['id'],
                'type'     => 'function',
                'function' => [
                    'name'      => $tc['name'],
                    'arguments' => $tc['arguments'],
                ],
            ];
        }, $response->tool_calls );

        $history[] = [
            'role'       => 'assistant',
            'content'    => null,
            'tool_calls' => $tool_calls,
        ];
        return $history;
    }

    protected function append_tool_result( array $history, array $tool_call, array $result ): array {
        $history[] = [
            'role'         => 'tool',
            'tool_call_id' => $tool_call['id'],
            'content'      => wp_json_encode( $result ),
        ];
        return $history;
    }

    protected function append_text_message( array $history, string $text ): array {
        $history[] = [ 'role' => 'assistant', 'content' => $text ];
        return $history;
    }

    protected function decode_tool_args( array $tool_call ): array {
        $args = json_decode( $tool_call['arguments'] ?? '{}', true );
        return is_array( $args ) ? $args : [];
    }
}
