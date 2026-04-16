<?php
defined( 'ABSPATH' ) || exit;
if ( ! current_user_can( 'manage_woocommerce' ) ) {
    wp_die( esc_html__( 'You do not have permission to access this page.', 'wc-ai-chatbot' ) );
}

global $wpdb;
$indexed_count = (int) $wpdb->get_var(
    "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = '_wcaic_embedding'"
);
$total_products = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'"
);
$settings = (array) get_option( 'wcaic_settings', [] );
?>
<div class="wrap wcaic-embeddings-wrap">
    <h1><?php esc_html_e( 'Semantic Search Embeddings', 'wc-ai-chatbot' ); ?></h1>
    <p><?php esc_html_e( 'Index your products using OpenAI text-embedding-3-small (512 dimensions) for semantic similarity search.', 'wc-ai-chatbot' ); ?></p>

    <div class="wcaic-index-status">
        <strong><?php esc_html_e( 'Indexed products:', 'wc-ai-chatbot' ); ?></strong>
        <?php echo esc_html( $indexed_count . ' / ' . $total_products ); ?>

        <div class="wcaic-progress-bar">
            <div class="wcaic-progress-fill" id="wcaic-progress-fill"
                 style="width: <?php echo $total_products > 0 ? esc_attr( round( $indexed_count / $total_products * 100 ) ) : 0; ?>%"></div>
        </div>

        <div id="wcaic-index-status" style="display:none"></div>
        <div id="wcaic-index-results"></div>
    </div>

    <?php if ( empty( $settings['embedding_enabled'] ) ) : ?>
        <p class="wcaic-api-notice"><?php esc_html_e( 'Enable embeddings in the Settings tab first.', 'wc-ai-chatbot' ); ?></p>
    <?php else : ?>
        <button id="wcaic-index-all" class="button button-primary button-hero" type="button">
            <?php esc_html_e( 'Index All Products', 'wc-ai-chatbot' ); ?>
        </button>
        <p class="description"><?php esc_html_e( 'Uses the OpenAI API key from Settings. Only re-indexes products whose content has changed.', 'wc-ai-chatbot' ); ?></p>
    <?php endif; ?>
</div>
