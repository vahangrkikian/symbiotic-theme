<?php
defined( 'ABSPATH' ) || exit;

/**
 * Normalized AI response object returned by both providers.
 */
class WCAIC_AI_Response {

    public string $text        = '';
    public array  $tool_calls  = [];
    public int    $prompt_tokens     = 0;
    public int    $completion_tokens = 0;
    public string $finish_reason    = '';
    public string $raw_response     = '';

    public function has_tool_calls(): bool {
        return ! empty( $this->tool_calls );
    }

    public function has_text(): bool {
        return $this->text !== '';
    }
}
