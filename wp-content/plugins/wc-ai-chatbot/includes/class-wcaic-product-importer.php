<?php
defined( 'ABSPATH' ) || exit;

/**
 * Browser-like HTTP product scraping for the admin importer.
 */
class WCAIC_Product_Importer {

    private array $settings;

    public function __construct() {
        $this->settings = (array) get_option( 'wcaic_settings', [] );
    }

    /**
     * Fetch a URL with browser-like headers.
     *
     * @param string $url Target URL.
     * @return array{success: bool, body: string, status: int}
     */
    public function fetch( string $url ): array {
        $url = esc_url_raw( $url );
        if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
            return [ 'success' => false, 'body' => '', 'status' => 0 ];
        }

        $response = wp_remote_get( $url, [
            'timeout'    => 15,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'headers'    => [
                'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Cache-Control'   => 'no-cache',
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            return [ 'success' => false, 'body' => $response->get_error_message(), 'status' => 0 ];
        }

        return [
            'success' => true,
            'body'    => wp_remote_retrieve_body( $response ),
            'status'  => wp_remote_retrieve_response_code( $response ),
        ];
    }

    /**
     * Basic HTML extraction: grab title and description from a product page.
     *
     * @param string $html Raw HTML.
     * @return array{title: string, description: string}
     */
    public function extract_product_data( string $html ): array {
        $data = [ 'title' => '', 'description' => '' ];

        // Title from <title>
        if ( preg_match( '/<title[^>]*>(.*?)<\/title>/is', $html, $m ) ) {
            $data['title'] = html_entity_decode( strip_tags( $m[1] ) );
        }

        // Meta description
        if ( preg_match( '/<meta[^>]+name=["\']description["\'][^>]+content=["\'](.*?)["\']/is', $html, $m ) ) {
            $data['description'] = html_entity_decode( strip_tags( $m[1] ) );
        }

        return $data;
    }

    /**
     * Create a WooCommerce simple product from scraped data.
     *
     * @param array $data Product data.
     * @return int|WP_Error Product ID on success.
     */
    public function create_product( array $data ): int|WP_Error {
        if ( empty( $data['name'] ) ) {
            return new WP_Error( 'missing_name', 'Product name is required.' );
        }

        $product = new WC_Product_Simple();
        $product->set_name( sanitize_text_field( $data['name'] ) );
        $product->set_status( 'draft' ); // Draft until reviewed

        if ( ! empty( $data['description'] ) ) {
            $product->set_description( wp_kses_post( $data['description'] ) );
        }

        if ( ! empty( $data['price'] ) ) {
            $product->set_regular_price( wc_format_decimal( $data['price'] ) );
        }

        if ( ! empty( $data['sku'] ) ) {
            $product->set_sku( sanitize_text_field( $data['sku'] ) );
        }

        $product_id = $product->save();
        if ( ! $product_id ) {
            return new WP_Error( 'save_failed', 'Failed to save product.' );
        }

        return $product_id;
    }
}
