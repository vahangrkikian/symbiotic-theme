<?php
defined( 'ABSPATH' ) || exit;

/**
 * Admin UI: conversation log viewer with detail view.
 */
class WCAIC_Conv_Log_Admin {

    private static ?self $instance = null;
    private const PER_PAGE = 50;

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function render(): void {
        // Detail view?
        if ( isset( $_GET['view_id'] ) ) {
            $this->render_detail( absint( $_GET['view_id'] ) );
            return;
        }

        $this->render_list();
    }

    // ── List View ──
    private function render_list(): void {
        $page   = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
        $offset = ( $page - 1 ) * self::PER_PAGE;
        $rows   = WCAIC_Conversation_Logger::get_recent( self::PER_PAGE, $offset );
        $total  = WCAIC_Conversation_Logger::count_all();
        $pages  = ceil( $total / self::PER_PAGE );
        ?>
        <div class="wrap wcaic-conv-log-wrap">
            <h1><?php esc_html_e( 'AI Chat Conversation Log', 'wc-ai-chatbot' ); ?></h1>
            <p class="description"><?php printf( esc_html__( 'Total conversations: %d', 'wc-ai-chatbot' ), $total ); ?></p>

            <table class="wp-list-table widefat fixed striped wcaic-conv-table">
                <thead>
                    <tr>
                        <th style="width:40px"><?php esc_html_e( 'ID', 'wc-ai-chatbot' ); ?></th>
                        <th style="width:100px"><?php esc_html_e( 'Session', 'wc-ai-chatbot' ); ?></th>
                        <th><?php esc_html_e( 'Last Message', 'wc-ai-chatbot' ); ?></th>
                        <th><?php esc_html_e( 'Last Reply', 'wc-ai-chatbot' ); ?></th>
                        <th style="width:140px"><?php esc_html_e( 'Provider', 'wc-ai-chatbot' ); ?></th>
                        <th style="width:60px"><?php esc_html_e( 'Turns', 'wc-ai-chatbot' ); ?></th>
                        <th style="width:140px"><?php esc_html_e( 'Last Activity', 'wc-ai-chatbot' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $rows ) ) : ?>
                        <tr><td colspan="7"><?php esc_html_e( 'No conversations logged yet.', 'wc-ai-chatbot' ); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ( $rows as $row ) :
                            $detail_url = add_query_arg( 'view_id', $row['id'] );
                        ?>
                            <tr style="cursor:pointer;" onclick="window.location='<?php echo esc_url( $detail_url ); ?>'">
                                <td><a href="<?php echo esc_url( $detail_url ); ?>">#<?php echo esc_html( $row['id'] ); ?></a></td>
                                <td class="wcaic-session-col"><?php echo esc_html( substr( $row['session_id'], 0, 12 ) . '…' ); ?></td>
                                <td class="wcaic-msg-col"><?php echo esc_html( wp_trim_words( $row['user_message'], 12 ) ); ?></td>
                                <td class="wcaic-msg-col"><?php echo esc_html( wp_trim_words( $row['ai_reply'], 12 ) ); ?></td>
                                <td><?php echo esc_html( $row['provider'] . '/' . $row['model'] ); ?></td>
                                <td><?php echo esc_html( $row['message_count'] ); ?></td>
                                <td><?php echo esc_html( $row['last_activity'] ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ( $pages > 1 ) : ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php
                        echo paginate_links( [
                            'base'    => add_query_arg( 'paged', '%#%' ),
                            'format'  => '',
                            'current' => $page,
                            'total'   => $pages,
                        ] );
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <style>
            .wcaic-conv-table tbody tr:hover { background: #f0f6fc; }
            .wcaic-msg-col { max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        </style>
        <?php
    }

    // ── Detail View ──
    private function render_detail( int $id ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'wcaic_conversation_log';
        $row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );

        if ( ! $row ) {
            echo '<div class="wrap"><h1>Conversation not found</h1></div>';
            return;
        }

        $back_url = remove_query_arg( 'view_id' );
        $messages = json_decode( $row['messages'] ?? '[]', true ) ?: [];
        ?>
        <div class="wrap wcaic-conv-detail-wrap">
            <p>
                <a href="<?php echo esc_url( $back_url ); ?>" class="button">← Back to Log</a>
                <button type="button" class="button" id="wcaic-copy-conv" style="margin-left:8px;">📋 Copy Conversation as Text</button>
                <span id="wcaic-copy-status" style="margin-left:8px;color:green;display:none;">Copied!</span>
            </p>
            <h1>Conversation #<?php echo esc_html( $row['id'] ); ?></h1>

            <div class="wcaic-detail-meta">
                <table class="widefat" style="max-width:600px;">
                    <tr><th>Session</th><td><?php echo esc_html( $row['session_id'] ); ?></td></tr>
                    <tr><th>Provider / Model</th><td><?php echo esc_html( $row['provider'] . ' / ' . $row['model'] ); ?></td></tr>
                    <tr><th>Messages</th><td><?php echo esc_html( $row['message_count'] ); ?></td></tr>
                    <tr><th>Tokens (prompt / completion)</th><td><?php echo esc_html( $row['prompt_tokens'] . ' / ' . $row['completion_tokens'] ); ?></td></tr>
                    <tr><th>Loop Iterations</th><td><?php echo esc_html( $row['loop_iterations'] ); ?></td></tr>
                    <tr><th>Flagged</th><td><?php echo $row['flagged'] ? '<span style="color:red;">Yes</span>' : 'No'; ?></td></tr>
                    <tr><th>Created</th><td><?php echo esc_html( $row['created_at'] ); ?></td></tr>
                    <tr><th>Last Activity</th><td><?php echo esc_html( $row['last_activity'] ); ?></td></tr>
                </table>
            </div>

            <h2 style="margin-top:24px;">Conversation History (<?php echo count( $messages ); ?> messages)</h2>

            <div class="wcaic-chat-thread">
                <?php foreach ( $messages as $i => $msg ) :
                    $role    = $msg['role'] ?? 'unknown';
                    $content = $msg['content'] ?? '';
                    $blocks  = self::parse_message_blocks( $content );
                ?>
                <div class="wcaic-chat-msg wcaic-chat-msg--<?php echo esc_attr( $role ); ?>">
                    <div class="wcaic-chat-role">
                        <span class="wcaic-role-badge wcaic-role-badge--<?php echo esc_attr( $role ); ?>">
                            <?php echo esc_html( $role === 'assistant' ? 'AI' : ( $role === 'user' ? 'User' : ucfirst( $role ) ) ); ?>
                        </span>
                        <span class="wcaic-chat-idx">#<?php echo $i; ?></span>
                    </div>
                    <div class="wcaic-chat-content">
                        <?php foreach ( $blocks as $block ) : ?>
                            <?php if ( $block['type'] === 'text' ) : ?>
                                <div class="wcaic-block-text"><?php echo esc_html( $block['text'] ); ?></div>
                            <?php elseif ( $block['type'] === 'tool_use' ) : ?>
                                <div class="wcaic-block-tool">
                                    <span class="wcaic-tool-name">🔧 <?php echo esc_html( $block['name'] ); ?></span>
                                    <pre class="wcaic-tool-input"><?php echo esc_html( json_encode( $block['input'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
                                </div>
                            <?php elseif ( $block['type'] === 'tool_result' ) : ?>
                                <div class="wcaic-block-result">
                                    <span class="wcaic-result-label">📋 Tool Result</span>
                                    <pre class="wcaic-tool-output"><?php
                                        $json = json_decode( $block['content'] ?? '', true );
                                        if ( $json ) {
                                            echo esc_html( json_encode( $json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
                                        } else {
                                            echo esc_html( substr( $block['content'] ?? '', 0, 2000 ) );
                                        }
                                    ?></pre>
                                </div>
                            <?php else : ?>
                                <div class="wcaic-block-text"><?php echo esc_html( is_string( $content ) ? $content : json_encode( $content ) ); ?></div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <style>
            .wcaic-conv-detail-wrap { max-width: 1000px; }
            .wcaic-detail-meta th { width: 180px; font-weight: 600; }
            .wcaic-chat-thread { margin-top: 16px; display: flex; flex-direction: column; gap: 0; }

            .wcaic-chat-msg {
                display: flex; gap: 12px; padding: 14px 16px;
                border-bottom: 1px solid #e0e0e0;
            }
            .wcaic-chat-msg--user { background: #f7f7f7; }
            .wcaic-chat-msg--assistant { background: #fff; }

            .wcaic-chat-role { min-width: 60px; display: flex; flex-direction: column; align-items: center; gap: 2px; }
            .wcaic-role-badge {
                display: inline-block; padding: 2px 8px; border-radius: 4px;
                font-size: 11px; font-weight: 700; text-transform: uppercase;
            }
            .wcaic-role-badge--user { background: #e3f2fd; color: #1565c0; }
            .wcaic-role-badge--assistant { background: #f3e5f5; color: #7b1fa2; }
            .wcaic-chat-idx { font-size: 10px; color: #999; }

            .wcaic-chat-content { flex: 1; min-width: 0; }

            .wcaic-block-text { font-size: 14px; line-height: 1.6; color: #333; white-space: pre-wrap; word-break: break-word; }

            .wcaic-block-tool {
                background: #fff8e1; border: 1px solid #ffe082; border-radius: 6px;
                padding: 10px; margin: 6px 0;
            }
            .wcaic-tool-name { font-weight: 700; font-size: 13px; color: #f57f17; }
            .wcaic-tool-input {
                margin: 6px 0 0; padding: 8px; background: #fffde7; border-radius: 4px;
                font-size: 12px; overflow-x: auto; max-height: 200px; overflow-y: auto;
            }

            .wcaic-block-result {
                background: #e8f5e9; border: 1px solid #a5d6a7; border-radius: 6px;
                padding: 10px; margin: 6px 0;
            }
            .wcaic-result-label { font-weight: 700; font-size: 13px; color: #2e7d32; }
            .wcaic-tool-output {
                margin: 6px 0 0; padding: 8px; background: #f1f8e9; border-radius: 4px;
                font-size: 11px; overflow-x: auto; max-height: 300px; overflow-y: auto;
                white-space: pre-wrap; word-break: break-word;
            }
        </style>

        <!-- Hidden plain-text for copy -->
        <textarea id="wcaic-conv-text" style="position:absolute;left:-9999px;" readonly><?php
            echo esc_textarea( self::build_plain_text( $row, $messages ) );
        ?></textarea>

        <script>
        document.getElementById('wcaic-copy-conv').addEventListener('click', function() {
            var text = document.getElementById('wcaic-conv-text').value;
            navigator.clipboard.writeText(text).then(function() {
                var s = document.getElementById('wcaic-copy-status');
                s.style.display = 'inline';
                setTimeout(function() { s.style.display = 'none'; }, 2000);
            });
        });
        </script>
        <?php
    }

    /**
     * Build plain text version of conversation for clipboard copy.
     */
    private static function build_plain_text( array $row, array $messages ): string {
        $lines = [];
        $lines[] = "=== Conversation #{$row['id']} ===";
        $lines[] = "Session: {$row['session_id']}";
        $lines[] = "Provider: {$row['provider']}/{$row['model']}";
        $lines[] = "Messages: {$row['message_count']} | Created: {$row['created_at']}";
        $lines[] = str_repeat( '─', 60 );
        $lines[] = '';

        foreach ( $messages as $i => $msg ) {
            $role = strtoupper( $msg['role'] ?? 'unknown' );
            $content = $msg['content'] ?? '';

            if ( is_string( $content ) ) {
                $lines[] = "[#{$i}] {$role}: {$content}";
            } elseif ( is_array( $content ) ) {
                $parts = [];
                foreach ( $content as $block ) {
                    if ( ! is_array( $block ) ) continue;
                    $type = $block['type'] ?? '';
                    if ( $type === 'text' ) {
                        $parts[] = $block['text'] ?? '';
                    } elseif ( $type === 'tool_use' ) {
                        $input = json_encode( $block['input'] ?? [], JSON_UNESCAPED_SLASHES );
                        $parts[] = "→ TOOL: {$block['name']}({$input})";
                    } elseif ( $type === 'tool_result' ) {
                        $result = $block['content'] ?? '';
                        $json = json_decode( $result, true );
                        if ( $json ) {
                            // Summarize tool results (they can be huge).
                            $summary = json_encode( $json, JSON_UNESCAPED_SLASHES );
                            if ( strlen( $summary ) > 500 ) {
                                $summary = substr( $summary, 0, 500 ) . '...';
                            }
                            $parts[] = "← RESULT: {$summary}";
                        } else {
                            $parts[] = "← RESULT: " . substr( $result, 0, 500 );
                        }
                    }
                }
                $lines[] = "[#{$i}] {$role}: " . implode( "\n    ", $parts );
            }
            $lines[] = '';
        }

        return implode( "\n", $lines );
    }

    /**
     * Parse a message's content into displayable blocks.
     */
    private static function parse_message_blocks( $content ): array {
        // String content (simple text message).
        if ( is_string( $content ) ) {
            return $content ? [ [ 'type' => 'text', 'text' => $content ] ] : [];
        }

        // Array of content blocks (Anthropic format).
        if ( is_array( $content ) ) {
            $blocks = [];
            foreach ( $content as $block ) {
                if ( ! is_array( $block ) ) {
                    $blocks[] = [ 'type' => 'text', 'text' => (string) $block ];
                    continue;
                }
                $type = $block['type'] ?? '';
                switch ( $type ) {
                    case 'text':
                        $blocks[] = [ 'type' => 'text', 'text' => $block['text'] ?? '' ];
                        break;
                    case 'tool_use':
                        $blocks[] = [
                            'type'  => 'tool_use',
                            'name'  => $block['name'] ?? '',
                            'input' => $block['input'] ?? [],
                        ];
                        break;
                    case 'tool_result':
                        $blocks[] = [
                            'type'    => 'tool_result',
                            'content' => $block['content'] ?? '',
                        ];
                        break;
                    default:
                        $blocks[] = [ 'type' => 'text', 'text' => json_encode( $block ) ];
                }
            }
            return $blocks;
        }

        return [];
    }
}
