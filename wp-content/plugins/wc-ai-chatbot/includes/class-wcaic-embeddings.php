<?php
defined( 'ABSPATH' ) || exit;

/**
 * Semantic search via OpenAI text-embedding-3-small (512 dimensions).
 */
class WCAIC_Embeddings {

    private const MODEL      = 'text-embedding-3-small';
    private const DIMENSIONS = 512;
    private const ENDPOINT   = 'https://api.openai.com/v1/embeddings';
    private const THRESHOLD  = 0.25;

    private array $settings;

    public function __construct() {
        $this->settings = (array) get_option( 'wcaic_settings', [] );
    }

    public function is_active(): bool {
        if ( empty( $this->settings['embedding_enabled'] ) ) {
            return false;
        }
        // Need at least 1 indexed product
        global $wpdb;
        $count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_wcaic_embedding' LIMIT 1"
        );
        return (int) $count > 0;
    }

    // -------------------------------------------------------------------------
    // Indexing
    // -------------------------------------------------------------------------
    public function generate_embedding( int $product_id ): bool|WP_Error {
        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return new WP_Error( 'not_found', 'Product not found.' );
        }

        $text = $this->build_product_text( $product );
        $hash = md5( $text );

        // Skip if unchanged
        $stored_hash = get_post_meta( $product_id, '_wcaic_embedding_hash', true );
        if ( $stored_hash === $hash ) {
            return true;
        }

        $embedding = $this->get_embedding( $text );
        if ( is_wp_error( $embedding ) ) {
            return $embedding;
        }

        update_post_meta( $product_id, '_wcaic_embedding',      wp_json_encode( $embedding ) );
        update_post_meta( $product_id, '_wcaic_embedding_hash', $hash );

        return true;
    }

    private function build_product_text( WC_Product $product ): string {
        $parts = [ $product->get_name() ];

        $short = wp_strip_all_tags( $product->get_short_description() );
        if ( $short ) {
            $parts[] = $short;
        }

        $desc = wp_strip_all_tags( $product->get_description() );
        if ( $desc ) {
            $parts[] = substr( $desc, 0, 500 );
        }

        // Category names
        $cats = get_the_terms( $product->get_id(), 'product_cat' );
        if ( $cats && ! is_wp_error( $cats ) ) {
            $parts[] = implode( ' ', array_column( (array) $cats, 'name' ) );
        }

        // Brand (tags)
        $tags = get_the_terms( $product->get_id(), 'product_tag' );
        if ( $tags && ! is_wp_error( $tags ) ) {
            $parts[] = implode( ' ', array_column( (array) $tags, 'name' ) );
        }

        // Attributes
        foreach ( $product->get_attributes() as $attr ) {
            $parts[] = implode( ' ', (array) $attr->get_options() );
        }

        $sku = $product->get_sku();
        if ( $sku ) {
            $parts[] = $sku;
        }

        return implode( ' | ', $parts );
    }

    // -------------------------------------------------------------------------
    // Search
    // -------------------------------------------------------------------------
    public function search_by_query( string $query, int $per_page = 5 ): array {
        $query_embedding = $this->get_embedding( $query );
        if ( is_wp_error( $query_embedding ) || empty( $query_embedding ) ) {
            return [];
        }

        // Load all indexed embeddings
        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_wcaic_embedding'",
            ARRAY_A
        );

        $scores = [];
        foreach ( $rows as $row ) {
            $product_id       = (int) $row['post_id'];
            $product_embedding = json_decode( $row['meta_value'], true );
            if ( ! is_array( $product_embedding ) ) {
                continue;
            }
            $score = $this->cosine_similarity( $query_embedding, $product_embedding );
            if ( $score >= self::THRESHOLD ) {
                $scores[ $product_id ] = $score;
            }
        }

        if ( empty( $scores ) ) {
            return [];
        }

        arsort( $scores );
        return array_slice( array_keys( $scores ), 0, $per_page );
    }

    // -------------------------------------------------------------------------
    // API
    // -------------------------------------------------------------------------
    private function get_embedding( string $text ): array|WP_Error {
        $encrypted = get_option( 'wcaic_api_keys_encrypted', [] );
        $api_key   = '';
        if ( isset( $encrypted['openai'] ) ) {
            $api_key = WCAIC_Admin::decrypt_key( $encrypted['openai'] );
        }
        if ( ! $api_key ) {
            return new WP_Error( 'no_api_key', 'OpenAI API key not configured.' );
        }

        $response = wp_remote_post( self::ENDPOINT, [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode( [
                'model'      => self::MODEL,
                'input'      => $text,
                'dimensions' => self::DIMENSIONS,
            ] ),
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( isset( $body['data'][0]['embedding'] ) ) {
            return $body['data'][0]['embedding'];
        }

        return new WP_Error( 'api_error', $body['error']['message'] ?? 'Embedding failed.' );
    }

    // -------------------------------------------------------------------------
    // Math
    // -------------------------------------------------------------------------
    private function cosine_similarity( array $a, array $b ): float {
        $dot = 0.0;
        $mag_a = 0.0;
        $mag_b = 0.0;
        $count = min( count( $a ), count( $b ) );
        for ( $i = 0; $i < $count; $i++ ) {
            $dot   += $a[ $i ] * $b[ $i ];
            $mag_a += $a[ $i ] ** 2;
            $mag_b += $b[ $i ] ** 2;
        }
        $denom = sqrt( $mag_a ) * sqrt( $mag_b );
        return $denom > 0 ? $dot / $denom : 0.0;
    }

    public function index_all_products(): array {
        $products = wc_get_products( [ 'status' => 'publish', 'limit' => -1, 'return' => 'ids' ] );
        $indexed  = 0;
        $errors   = 0;
        foreach ( $products as $id ) {
            $result = $this->generate_embedding( $id );
            if ( is_wp_error( $result ) ) {
                $errors++;
            } else {
                $indexed++;
            }
        }
        return [ 'indexed' => $indexed, 'errors' => $errors, 'total' => count( $products ) ];
    }
}
