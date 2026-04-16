<?php
defined( 'ABSPATH' ) || exit;
if ( ! current_user_can( 'manage_woocommerce' ) ) {
    wp_die( esc_html__( 'You do not have permission to access this page.', 'wc-ai-chatbot' ) );
}
?>
<div class="wrap wcaic-importer-wrap">
    <h1><?php esc_html_e( 'Product Importer', 'wc-ai-chatbot' ); ?></h1>
    <p><?php esc_html_e( 'Scrape a product URL and create a draft WooCommerce product for review.', 'wc-ai-chatbot' ); ?></p>

    <table class="form-table">
        <tr>
            <th><?php esc_html_e( 'Product URL', 'wc-ai-chatbot' ); ?></th>
            <td>
                <input type="url" id="wcaic-import-url" class="wcaic-url-field regular-text"
                       placeholder="https://example.com/product-page">
                <?php wp_nonce_field( 'wcaic_import_product', 'wcaic-import-nonce' ); ?>
            </td>
        </tr>
    </table>

    <button id="wcaic-import-btn" class="button button-primary" type="button">
        <?php esc_html_e( 'Import Product', 'wc-ai-chatbot' ); ?>
    </button>

    <div id="wcaic-import-result" class="wcaic-result" style="display:none"></div>

    <script>
    document.getElementById('wcaic-import-btn').addEventListener('click', function() {
        const res = document.getElementById('wcaic-import-result');
        res.style.display = 'block';
        res.textContent = 'Importing…';
    });
    </script>
</div>
