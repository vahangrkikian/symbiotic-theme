<?php
defined( 'ABSPATH' ) || exit;
if ( ! current_user_can( 'manage_woocommerce' ) ) {
    wp_die( esc_html__( 'You do not have permission to access this page.', 'wc-ai-chatbot' ) );
}
$active_tab = sanitize_text_field( $_GET['tab'] ?? 'general' );
?>
<div class="wrap wcaic-settings-wrap">
    <h1><?php esc_html_e( 'WC AI Chatbot Settings', 'wc-ai-chatbot' ); ?></h1>

    <?php settings_errors( 'wcaic_settings' ); ?>

    <nav class="nav-tab-wrapper">
        <a href="?page=wcaic-settings&tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e( 'General', 'wc-ai-chatbot' ); ?>
        </a>
        <a href="?page=wcaic-settings&tab=persona" class="nav-tab <?php echo $active_tab === 'persona' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e( 'AI Persona', 'wc-ai-chatbot' ); ?>
        </a>
        <a href="?page=wcaic-settings&tab=knowledge" class="nav-tab <?php echo $active_tab === 'knowledge' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e( 'Brand Knowledge', 'wc-ai-chatbot' ); ?>
        </a>
    </nav>

    <?php if ( $active_tab === 'general' ) : ?>

        <div class="wcaic-api-notice">
            <?php esc_html_e( 'API keys are stored encrypted using AES-256-CBC. They are never exposed to the frontend.', 'wc-ai-chatbot' ); ?>
        </div>

        <form method="post" action="options.php">
            <?php
            settings_fields( 'wcaic_settings_group' );
            do_settings_sections( 'wcaic-settings' );
            submit_button( __( 'Save Settings', 'wc-ai-chatbot' ) );
            ?>
        </form>

    <?php elseif ( $active_tab === 'persona' ) : ?>

        <?php
        $persona   = WCAIC_Persona::get_settings();
        $presets   = WCAIC_Persona::presets();
        $saved_msg = '';
        if ( isset( $_POST['wcaic_persona_nonce'] ) && wp_verify_nonce( $_POST['wcaic_persona_nonce'], 'wcaic_save_persona' ) ) {
            WCAIC_Persona::save( $_POST['persona'] ?? [] );
            $persona   = WCAIC_Persona::get_settings();
            $saved_msg = __( 'Persona settings saved.', 'wc-ai-chatbot' );
        }
        ?>

        <?php if ( $saved_msg ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $saved_msg ); ?></p></div>
        <?php endif; ?>

        <form method="post">
            <?php wp_nonce_field( 'wcaic_save_persona', 'wcaic_persona_nonce' ); ?>

            <h2><?php esc_html_e( 'Persona Preset', 'wc-ai-chatbot' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Select Persona', 'wc-ai-chatbot' ); ?></th>
                    <td>
                        <fieldset>
                            <?php foreach ( $presets as $key => $preset ) : ?>
                                <label style="display:block;margin-bottom:8px;">
                                    <input type="radio" name="persona[preset]" value="<?php echo esc_attr( $key ); ?>"
                                        <?php checked( $persona['preset'], $key ); ?>
                                        class="wcaic-preset-radio">
                                    <strong><?php echo esc_html( $preset['label'] ); ?></strong>
                                    &mdash; <span class="description"><?php echo esc_html( $preset['description'] ); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </fieldset>
                    </td>
                </tr>
            </table>

            <h2><?php esc_html_e( 'Personality Spectrum', 'wc-ai-chatbot' ); ?></h2>
            <p class="description"><?php esc_html_e( 'Fine-tune the AI personality. These values auto-set when you choose a preset, or select "Custom" to set them manually.', 'wc-ai-chatbot' ); ?></p>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Selling Posture', 'wc-ai-chatbot' ); ?></th>
                    <td>
                        <span class="description"><?php esc_html_e( 'Supportive Guide', 'wc-ai-chatbot' ); ?></span>
                        <input type="range" name="persona[selling]" min="0" max="100" value="<?php echo esc_attr( $persona['selling'] ); ?>" class="wcaic-spectrum" style="width:300px;vertical-align:middle;">
                        <span class="description"><?php esc_html_e( 'Active Recommender', 'wc-ai-chatbot' ); ?></span>
                        <span class="wcaic-spectrum-val"><?php echo esc_html( $persona['selling'] ); ?></span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Formality', 'wc-ai-chatbot' ); ?></th>
                    <td>
                        <span class="description"><?php esc_html_e( 'Casual / Friendly', 'wc-ai-chatbot' ); ?></span>
                        <input type="range" name="persona[formality]" min="0" max="100" value="<?php echo esc_attr( $persona['formality'] ); ?>" class="wcaic-spectrum" style="width:300px;vertical-align:middle;">
                        <span class="description"><?php esc_html_e( 'Professional / Polished', 'wc-ai-chatbot' ); ?></span>
                        <span class="wcaic-spectrum-val"><?php echo esc_html( $persona['formality'] ); ?></span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Detail Level', 'wc-ai-chatbot' ); ?></th>
                    <td>
                        <span class="description"><?php esc_html_e( 'Concise', 'wc-ai-chatbot' ); ?></span>
                        <input type="range" name="persona[detail]" min="0" max="100" value="<?php echo esc_attr( $persona['detail'] ); ?>" class="wcaic-spectrum" style="width:300px;vertical-align:middle;">
                        <span class="description"><?php esc_html_e( 'Detailed', 'wc-ai-chatbot' ); ?></span>
                        <span class="wcaic-spectrum-val"><?php echo esc_html( $persona['detail'] ); ?></span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Proactivity', 'wc-ai-chatbot' ); ?></th>
                    <td>
                        <span class="description"><?php esc_html_e( 'Reactive Only', 'wc-ai-chatbot' ); ?></span>
                        <input type="range" name="persona[proactivity]" min="0" max="100" value="<?php echo esc_attr( $persona['proactivity'] ); ?>" class="wcaic-spectrum" style="width:300px;vertical-align:middle;">
                        <span class="description"><?php esc_html_e( 'Anticipatory', 'wc-ai-chatbot' ); ?></span>
                        <span class="wcaic-spectrum-val"><?php echo esc_html( $persona['proactivity'] ); ?></span>
                    </td>
                </tr>
            </table>

            <h2><?php esc_html_e( 'Custom Rules & Boundaries', 'wc-ai-chatbot' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Custom Rules', 'wc-ai-chatbot' ); ?></th>
                    <td>
                        <textarea name="persona[custom_rules]" rows="4" class="large-text"><?php echo esc_textarea( $persona['custom_rules'] ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'Additional instructions for the AI. These are appended to the persona rules.', 'wc-ai-chatbot' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Prohibited Topics', 'wc-ai-chatbot' ); ?></th>
                    <td>
                        <textarea name="persona[prohibited_topics]" rows="3" class="large-text"><?php echo esc_textarea( $persona['prohibited_topics'] ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'One topic per line. The AI will not discuss these subjects (e.g., competitor names, politics).', 'wc-ai-chatbot' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Prohibited Words', 'wc-ai-chatbot' ); ?></th>
                    <td>
                        <textarea name="persona[prohibited_words]" rows="3" class="large-text"><?php echo esc_textarea( $persona['prohibited_words'] ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'One word or phrase per line. The AI will avoid using these in responses.', 'wc-ai-chatbot' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Off-Topic Response', 'wc-ai-chatbot' ); ?></th>
                    <td>
                        <input type="text" name="persona[off_topic_message]" value="<?php echo esc_attr( $persona['off_topic_message'] ); ?>" class="large-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Escalation Message', 'wc-ai-chatbot' ); ?></th>
                    <td>
                        <input type="text" name="persona[escalation_message]" value="<?php echo esc_attr( $persona['escalation_message'] ); ?>" class="large-text">
                        <p class="description"><?php esc_html_e( 'Shown when the AI cannot handle a request and needs to redirect to human support.', 'wc-ai-chatbot' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Max Conversation Length', 'wc-ai-chatbot' ); ?></th>
                    <td>
                        <input type="number" name="persona[max_conversation_length]" value="<?php echo esc_attr( $persona['max_conversation_length'] ); ?>" min="10" max="200" class="small-text">
                        <span class="description"><?php esc_html_e( 'messages before suggesting human support', 'wc-ai-chatbot' ); ?></span>
                    </td>
                </tr>
            </table>

            <?php submit_button( __( 'Save Persona', 'wc-ai-chatbot' ) ); ?>
        </form>

        <script>
        document.querySelectorAll('.wcaic-spectrum').forEach(function(slider) {
            slider.addEventListener('input', function() {
                this.nextElementSibling.nextElementSibling.textContent = this.value;
            });
        });
        </script>

    <?php elseif ( $active_tab === 'knowledge' ) : ?>

        <?php
        $saved_msg = '';
        if ( isset( $_POST['wcaic_knowledge_nonce'] ) && wp_verify_nonce( $_POST['wcaic_knowledge_nonce'], 'wcaic_save_knowledge' ) ) {
            WCAIC_Brand_Knowledge::save( $_POST['knowledge'] ?? [] );
            $saved_msg = __( 'Brand knowledge saved.', 'wc-ai-chatbot' );
        }
        $sections = WCAIC_Brand_Knowledge::get_all();
        ?>

        <?php if ( $saved_msg ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $saved_msg ); ?></p></div>
        <?php endif; ?>

        <p class="description" style="margin:15px 0;">
            <?php esc_html_e( 'Add your brand information below. The AI assistant will use this knowledge to answer customer questions accurately and stay on-brand. Only enabled sections with content are injected into the AI context.', 'wc-ai-chatbot' ); ?>
        </p>

        <form method="post">
            <?php wp_nonce_field( 'wcaic_save_knowledge', 'wcaic_knowledge_nonce' ); ?>

            <?php foreach ( $sections as $key => $section ) : ?>
                <div class="wcaic-knowledge-section" style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:15px 20px;margin-bottom:15px;">
                    <h3 style="margin-top:0;display:flex;align-items:center;gap:10px;">
                        <label>
                            <input type="checkbox" name="knowledge[<?php echo esc_attr( $key ); ?>][enabled]" value="1"
                                <?php checked( $section['enabled'] ); ?>>
                            <?php echo esc_html( $section['label'] ); ?>
                        </label>
                    </h3>
                    <p class="description" style="margin-bottom:8px;"><?php echo esc_html( $section['hint'] ); ?></p>
                    <textarea name="knowledge[<?php echo esc_attr( $key ); ?>][content]" rows="6" class="large-text" style="font-family:monospace;"><?php echo esc_textarea( $section['content'] ); ?></textarea>
                </div>
            <?php endforeach; ?>

            <?php submit_button( __( 'Save Brand Knowledge', 'wc-ai-chatbot' ) ); ?>
        </form>

    <?php endif; ?>
</div>
