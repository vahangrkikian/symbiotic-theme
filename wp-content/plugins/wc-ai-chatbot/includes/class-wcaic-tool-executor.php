<?php
defined( 'ABSPATH' ) || exit;

/**
 * Tool execution engine — all WooCommerce integrations.
 */
class WCAIC_Tool_Executor {

    private static array $allowed_tools = [
        'search_products',
        'get_product_details',
        'add_to_cart',
        'remove_from_cart',
        'update_cart_quantity',
        'get_cart',
        'apply_coupon',
        'get_checkout_url',
        'get_order_status',
        'get_customer_orders',
        'get_store_policies',
        'compare_products',
        'estimate_shipping',
        'get_product_calculator',
        'add_calculator_to_cart',
    ];

    public static function execute( string $tool_name, array $args, string $session_id = '', array $settings = [] ): array|WP_Error {
        // Whitelist check
        if ( ! in_array( $tool_name, self::$allowed_tools, true ) ) {
            return new WP_Error( 'invalid_tool', 'Unknown tool: ' . esc_html( $tool_name ) );
        }

        // Tool rate limit
        if ( $session_id ) {
            $limit  = (int) ( $settings['tool_rate_limit'] ?? 30 );
            $result = WCAIC_Rate_Limiter::check( $session_id, 'tool', $limit );
            if ( ! $result['allowed'] ) {
                return new WP_Error( 'tool_rate_limit', 'Tool rate limit exceeded. Retry in ' . $result['retry_after'] . 's.' );
            }
        }

        return match ( $tool_name ) {
            'search_products'      => self::search_products( $args ),
            'get_product_details'  => self::get_product_details( $args ),
            'add_to_cart'          => self::add_to_cart( $args ),
            'remove_from_cart'     => self::remove_from_cart( $args ),
            'update_cart_quantity' => self::update_cart_quantity( $args ),
            'get_cart'             => self::get_cart(),
            'apply_coupon'         => self::apply_coupon( $args ),
            'get_checkout_url'     => self::get_checkout_url(),
            'get_order_status'     => self::get_order_status( $args ),
            'get_customer_orders'  => self::get_customer_orders( $args ),
            'get_store_policies'   => self::get_store_policies( $args ),
            'compare_products'     => self::compare_products( $args ),
            'estimate_shipping'       => self::estimate_shipping( $args ),
            'get_product_calculator'  => self::get_product_calculator( $args ),
            'add_calculator_to_cart'  => self::add_calculator_to_cart( $args ),
            default                   => new WP_Error( 'invalid_tool', 'Unknown tool.' ),
        };
    }

    // -------------------------------------------------------------------------
    // Tool: search_products
    // -------------------------------------------------------------------------
    private static function search_products( array $args ): array {
        $query     = sanitize_text_field( $args['query']    ?? '' );
        $category  = sanitize_title( $args['category']     ?? '' );
        $brand     = sanitize_title( $args['brand']        ?? '' );
        $min_price = isset( $args['min_price'] ) ? floatval( $args['min_price'] ) : null;
        $max_price = isset( $args['max_price'] ) ? floatval( $args['max_price'] ) : null;
        $on_sale   = ! empty( $args['on_sale'] );
        $per_page  = min( 10, max( 1, absint( $args['per_page'] ?? 5 ) ) );

        // Transient cache
        $cache_key = 'wcaic_products_' . md5( wp_json_encode( $args ) );
        $cached    = get_transient( $cache_key );
        if ( false !== $cached ) {
            return $cached;
        }

        // Semantic search first (if enabled)
        $embeddings = new WCAIC_Embeddings();
        if ( $embeddings->is_active() && $query ) {
            $semantic = $embeddings->search_by_query( $query, $per_page );
            if ( ! empty( $semantic ) ) {
                $products = self::enrich_products( $semantic );
                $result   = [ 'products' => $products, 'total' => count( $products ), 'query' => $args ];
                set_transient( $cache_key, $result, 300 );
                return $result;
            }
        }

        // WP_Query args
        $wp_args = [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'tax_query'      => [ 'relation' => 'AND' ],
            'meta_query'     => [ 'relation' => 'AND' ],
        ];

        // Redundancy detection (Bug Fix #2)
        $q_norm = strtolower( trim( $query ) );
        $c_norm = strtolower( trim( $category ) );
        $b_norm = strtolower( trim( $brand ) );
        $skip_s = ( $query !== '' && ( $q_norm === $c_norm || $q_norm === $b_norm ) );

        if ( $query && ! $skip_s ) {
            $wp_args['s'] = $query;
        }

        if ( $category ) {
            $wp_args['tax_query'][] = [
                'taxonomy' => 'product_cat',
                'field'    => 'slug',
                'terms'    => $category,
            ];
        }

        if ( $brand ) {
            $wp_args['tax_query'][] = [
                'taxonomy' => 'product_tag',
                'field'    => 'slug',
                'terms'    => $brand,
            ];
        }

        if ( $min_price !== null ) {
            $wp_args['meta_query'][] = [
                'key'     => '_price',
                'value'   => $min_price,
                'compare' => '>=',
                'type'    => 'NUMERIC',
            ];
        }

        if ( $max_price !== null ) {
            $wp_args['meta_query'][] = [
                'key'     => '_price',
                'value'   => $max_price,
                'compare' => '<=',
                'type'    => 'NUMERIC',
            ];
        }

        if ( $on_sale ) {
            $wp_args['meta_query'][] = [
                'key'     => '_sale_price',
                'value'   => '',
                'compare' => '!=',
            ];
            $wp_args['meta_query'][] = [
                'key'     => '_sale_price',
                'compare' => 'EXISTS',
            ];
        }

        $the_query = new WP_Query( $wp_args );
        $post_ids  = wp_list_pluck( $the_query->posts, 'ID' );
        $products  = self::enrich_products( $post_ids );

        $result = [
            'products' => $products,
            'total'    => $the_query->found_posts,
            'query'    => $args,
        ];

        set_transient( $cache_key, $result, 300 );
        return $result;
    }

    private static function enrich_products( array $product_ids ): array {
        $products = [];
        foreach ( $product_ids as $id ) {
            $product = wc_get_product( $id );
            if ( ! $product ) {
                continue;
            }
            $products[] = self::format_product( $product );
        }
        return $products;
    }

    private static function format_product( WC_Product $product ): array {
        $terms      = get_the_terms( $product->get_id(), 'product_cat' );
        $cat_names  = [];
        if ( $terms && ! is_wp_error( $terms ) ) {
            $cat_names = array_map( static fn( $t ) => $t->name, $terms );
        }

        $brand = '';
        $brand_terms = get_the_terms( $product->get_id(), 'product_tag' );
        if ( $brand_terms && ! is_wp_error( $brand_terms ) ) {
            $brand = $brand_terms[0]->name ?? '';
        }

        $image_id  = $product->get_image_id();
        $image_url = $image_id ? wp_get_attachment_url( $image_id ) : wc_placeholder_img_src();

        return [
            'id'                => $product->get_id(),
            'name'              => $product->get_name(),
            'slug'              => $product->get_slug(),
            'price'             => wc_format_decimal( $product->get_price(), 2 ),
            'regular_price'     => wc_format_decimal( $product->get_regular_price(), 2 ),
            'sale_price'        => wc_format_decimal( $product->get_sale_price(), 2 ),
            'on_sale'           => $product->is_on_sale(),
            'image_url'         => esc_url( (string) $image_url ),
            'permalink'         => get_permalink( $product->get_id() ),
            'short_description' => wp_strip_all_tags( $product->get_short_description() ),
            'category_names'    => $cat_names,
            'brand'             => $brand,
            'rating'            => (float) $product->get_average_rating(),
            'review_count'      => (int) $product->get_review_count(),
            'in_stock'          => $product->is_in_stock(),
            'stock_status'      => $product->get_stock_status(),
            'has_calculator'    => get_post_meta( $product->get_id(), '_sqft_calculator_enabled', true ) === '1',
        ];
    }

    // -------------------------------------------------------------------------
    // Tool: get_product_details
    // -------------------------------------------------------------------------
    private static function get_product_details( array $args ): array|WP_Error {
        $product_id = absint( $args['product_id'] ?? 0 );
        if ( ! $product_id ) {
            return new WP_Error( 'invalid_id', 'Product ID is required.' );
        }

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return new WP_Error( 'not_found', 'Product not found.' );
        }

        $data = self::format_product( $product );

        // Full description
        $data['description'] = wp_strip_all_tags( $product->get_description() );

        // Gallery images
        $gallery_ids   = $product->get_gallery_image_ids();
        $data['gallery_images'] = array_map( 'wp_get_attachment_url', $gallery_ids );

        // Attributes
        $attributes = [];
        foreach ( $product->get_attributes() as $attr ) {
            $label  = wc_attribute_label( $attr->get_name() );
            $values = $attr->is_taxonomy()
                ? wc_get_product_terms( $product_id, $attr->get_name(), [ 'fields' => 'names' ] )
                : $attr->get_options();
            $attributes[ $label ] = $values;
        }
        $data['attributes'] = $attributes;
        $data['sku']        = $product->get_sku();
        $data['weight']     = $product->get_weight();
        $data['dimensions'] = [
            'length' => $product->get_length(),
            'width'  => $product->get_width(),
            'height' => $product->get_height(),
        ];

        // Variations
        $data['variations'] = [];
        if ( $product->is_type( 'variable' ) ) {
            foreach ( $product->get_available_variations() as $var ) {
                $var_product = wc_get_product( $var['variation_id'] );
                if ( ! $var_product ) {
                    continue;
                }
                $data['variations'][] = [
                    'id'         => $var['variation_id'],
                    'price'      => wc_format_decimal( $var_product->get_price(), 2 ),
                    'attributes' => $var['attributes'],
                    'in_stock'   => $var_product->is_in_stock(),
                    'stock_qty'  => $var_product->get_stock_quantity(),
                ];
            }
        }

        // Reviews
        $comments = get_comments( [
            'post_id' => $product_id,
            'status'  => 'approve',
            'number'  => 5,
        ] );
        $data['reviews'] = array_map( static function ( $comment ) {
            return [
                'rating'  => (int) get_comment_meta( $comment->comment_ID, 'rating', true ),
                'author'  => sanitize_text_field( $comment->comment_author ),
                'content' => wp_strip_all_tags( $comment->comment_content ),
            ];
        }, $comments );

        return $data;
    }

    // -------------------------------------------------------------------------
    // Tool: add_to_cart
    // -------------------------------------------------------------------------
    private static function add_to_cart( array $args ): array|WP_Error {
        $product_id   = absint( $args['product_id']   ?? 0 );
        $quantity     = max( 1, absint( $args['quantity']     ?? 1 ) );
        $variation_id = absint( $args['variation_id'] ?? 0 );
        $variation    = (array) ( $args['variation']  ?? [] );

        if ( ! $product_id ) {
            return new WP_Error( 'invalid_id', 'Product ID is required.' );
        }

        $product = wc_get_product( $product_id );
        if ( ! $product || ! $product->is_purchasable() ) {
            return new WP_Error( 'not_purchasable', 'Product is not available for purchase.' );
        }

        // Variable product handling
        if ( $product->is_type( 'variable' ) ) {
            if ( ! $variation_id ) {
                $variations = [];
                foreach ( $product->get_available_variations() as $var ) {
                    $var_product = wc_get_product( $var['variation_id'] );
                    if ( ! $var_product ) {
                        continue;
                    }
                    $variations[] = [
                        'id'         => $var['variation_id'],
                        'price'      => wc_format_decimal( $var_product->get_price(), 2 ),
                        'attributes' => $var['attributes'],
                    ];
                }
                return [
                    'success'              => false,
                    'needs_variation'      => true,
                    'available_variations' => $variations,
                    'message'              => 'This product has variations. Please specify a variation.',
                ];
            }

            $var_product = wc_get_product( $variation_id );
            if ( ! $var_product || ! $var_product->is_in_stock() ) {
                return new WP_Error( 'out_of_stock', 'Selected variation is out of stock.' );
            }
        } elseif ( ! $product->is_in_stock() ) {
            return new WP_Error( 'out_of_stock', 'Product is out of stock.' );
        }

        $added = WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variation );
        if ( ! $added ) {
            return new WP_Error( 'cart_error', 'Failed to add product to cart.' );
        }

        WC()->cart->calculate_totals();

        return [
            'success'        => true,
            'product_name'   => $product->get_name(),
            'quantity_added' => $quantity,
            'cart_total'     => WC()->cart->get_cart_total(),
            'cart_count'     => WC()->cart->get_cart_contents_count(),
        ];
    }

    // -------------------------------------------------------------------------
    // Tool: remove_from_cart
    // -------------------------------------------------------------------------
    private static function remove_from_cart( array $args ): array|WP_Error {
        $item_key = sanitize_text_field( $args['item_key'] ?? '' );
        if ( ! $item_key ) {
            return new WP_Error( 'missing_key', 'Cart item key is required.' );
        }

        $cart_item = WC()->cart->get_cart_item( $item_key );
        if ( ! $cart_item ) {
            return new WP_Error( 'not_found', 'Cart item not found.' );
        }

        $product_name = wc_get_product( $cart_item['product_id'] )?->get_name() ?? 'Unknown';
        WC()->cart->remove_cart_item( $item_key );
        WC()->cart->calculate_totals();

        return [
            'success'      => true,
            'removed_item' => $product_name,
            'cart_total'   => WC()->cart->get_cart_total(),
            'cart_count'   => WC()->cart->get_cart_contents_count(),
        ];
    }

    // -------------------------------------------------------------------------
    // Tool: update_cart_quantity
    // -------------------------------------------------------------------------
    private static function update_cart_quantity( array $args ): array|WP_Error {
        $item_key = sanitize_text_field( $args['item_key'] ?? '' );
        $quantity = (int) ( $args['quantity'] ?? -1 );

        if ( ! $item_key ) {
            return new WP_Error( 'missing_key', 'Cart item key is required.' );
        }
        if ( $quantity < 0 ) {
            return new WP_Error( 'invalid_qty', 'Quantity must be 0 or greater.' );
        }

        $cart_item = WC()->cart->get_cart_item( $item_key );
        if ( ! $cart_item ) {
            return new WP_Error( 'not_found', 'Cart item not found.' );
        }

        $product_name = wc_get_product( $cart_item['product_id'] )?->get_name() ?? 'Unknown';

        if ( $quantity === 0 ) {
            WC()->cart->remove_cart_item( $item_key );
        } else {
            WC()->cart->set_quantity( $item_key, $quantity );
        }

        WC()->cart->calculate_totals();

        return [
            'success'      => true,
            'product_name' => $product_name,
            'new_quantity' => $quantity,
            'cart_total'   => WC()->cart->get_cart_total(),
            'cart_count'   => WC()->cart->get_cart_contents_count(),
        ];
    }

    // -------------------------------------------------------------------------
    // Tool: get_cart
    // -------------------------------------------------------------------------
    private static function get_cart(): array {
        $cart       = WC()->cart;
        $cart_items = [];

        foreach ( $cart->get_cart() as $key => $item ) {
            $product = wc_get_product( $item['product_id'] );
            if ( ! $product ) {
                continue;
            }
            $image_id  = $product->get_image_id();
            $image_url = $image_id ? wp_get_attachment_url( $image_id ) : wc_placeholder_img_src();

            $cart_items[] = [
                'item_key'   => $key,
                'product_id' => $item['product_id'],
                'name'       => $product->get_name(),
                'quantity'   => $item['quantity'],
                'price'      => wc_format_decimal( $item['line_subtotal'] / max( 1, $item['quantity'] ), 2 ),
                'line_total' => wc_format_decimal( $item['line_total'], 2 ),
                'image_url'  => esc_url( (string) $image_url ),
            ];
        }

        $cart->calculate_totals();
        $coupons = [];
        foreach ( $cart->get_applied_coupons() as $code ) {
            $coupon    = new WC_Coupon( $code );
            $coupons[] = [
                'code'            => $code,
                'discount_amount' => wc_format_decimal( $cart->get_coupon_discount_amount( $code ), 2 ),
            ];
        }

        return [
            'items'            => $cart_items,
            'item_count'       => $cart->get_cart_contents_count(),
            'totals'           => [
                'subtotal' => wc_format_decimal( $cart->get_subtotal(), 2 ),
                'discount' => wc_format_decimal( $cart->get_discount_total(), 2 ),
                'shipping' => wc_format_decimal( $cart->get_shipping_total(), 2 ),
                'total'    => wc_format_decimal( $cart->get_cart_contents_total(), 2 ),
            ],
            'coupons_applied'  => $coupons,
        ];
    }

    // -------------------------------------------------------------------------
    // Tool: apply_coupon
    // -------------------------------------------------------------------------
    private static function apply_coupon( array $args ): array|WP_Error {
        $code = sanitize_text_field( $args['coupon_code'] ?? '' );
        if ( ! $code ) {
            return new WP_Error( 'missing_code', 'Coupon code is required.' );
        }

        // Clear wc notices before applying
        wc_clear_notices();
        $result = WC()->cart->apply_coupon( $code );

        $notices = wc_get_notices( 'success' );
        $errors  = wc_get_notices( 'error' );
        wc_clear_notices();

        $message = '';
        if ( ! empty( $notices ) ) {
            $message = wp_strip_all_tags( $notices[0]['notice'] ?? '' );
        } elseif ( ! empty( $errors ) ) {
            $message = wp_strip_all_tags( $errors[0]['notice'] ?? '' );
        }

        WC()->cart->calculate_totals();
        $discount = WC()->cart->get_coupon_discount_amount( $code );

        return [
            'success'         => (bool) $result,
            'coupon_code'     => $code,
            'discount_amount' => wc_format_decimal( $discount, 2 ),
            'message'         => $message ?: ( $result ? 'Coupon applied successfully.' : 'Failed to apply coupon.' ),
        ];
    }

    // -------------------------------------------------------------------------
    // Tool: get_checkout_url
    // -------------------------------------------------------------------------
    private static function get_checkout_url(): array {
        $cart     = WC()->cart;
        $cart->calculate_totals();
        $items    = [];

        foreach ( $cart->get_cart() as $item ) {
            $product = wc_get_product( $item['product_id'] );
            if ( ! $product ) {
                continue;
            }
            $items[] = [
                'name'       => $product->get_name(),
                'quantity'   => $item['quantity'],
                'line_total' => wc_format_decimal( $item['line_total'], 2 ),
            ];
        }

        return [
            'checkout_url' => wc_get_checkout_url(),
            'cart_summary' => [
                'items' => $items,
                'total' => wc_format_decimal( $cart->get_cart_contents_total(), 2 ),
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Tool: get_order_status
    // -------------------------------------------------------------------------
    private static function get_order_status( array $args ): array|WP_Error {
        $order_id = absint( $args['order_id'] ?? 0 );
        if ( ! $order_id ) {
            return new WP_Error( 'invalid_id', 'Order ID is required.' );
        }

        // Verify customer owns this order
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return new WP_Error( 'not_logged_in', 'You need to be logged in to check order status.' );
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return new WP_Error( 'not_found', 'Order not found.' );
        }

        if ( (int) $order->get_customer_id() !== $user_id ) {
            return new WP_Error( 'not_authorized', 'You can only view your own orders.' );
        }

        $items = [];
        foreach ( $order->get_items() as $item ) {
            $items[] = [
                'name'     => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'total'    => wc_format_decimal( $item->get_total(), 2 ),
            ];
        }

        return [
            'order_id'     => $order->get_id(),
            'status'       => wc_get_order_status_name( $order->get_status() ),
            'status_key'   => $order->get_status(),
            'date_created' => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i' ) : '',
            'total'        => wc_format_decimal( $order->get_total(), 2 ),
            'currency'     => $order->get_currency(),
            'items'        => $items,
            'shipping'     => [
                'method' => $order->get_shipping_method(),
                'total'  => wc_format_decimal( $order->get_shipping_total(), 2 ),
            ],
            'payment_method' => $order->get_payment_method_title(),
        ];
    }

    // -------------------------------------------------------------------------
    // Tool: get_customer_orders
    // -------------------------------------------------------------------------
    private static function get_customer_orders( array $args ): array|WP_Error {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return new WP_Error( 'not_logged_in', 'You need to be logged in to view your orders.' );
        }

        $limit  = min( 10, max( 1, absint( $args['limit'] ?? 5 ) ) );
        $orders = wc_get_orders( [
            'customer_id' => $user_id,
            'limit'       => $limit,
            'orderby'     => 'date',
            'order'       => 'DESC',
            'status'      => array_keys( wc_get_order_statuses() ),
        ] );

        $result = [];
        foreach ( $orders as $order ) {
            $item_names = [];
            foreach ( $order->get_items() as $item ) {
                $item_names[] = $item->get_name() . ' x' . $item->get_quantity();
            }
            $result[] = [
                'order_id'     => $order->get_id(),
                'status'       => wc_get_order_status_name( $order->get_status() ),
                'date_created' => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d' ) : '',
                'total'        => wc_format_decimal( $order->get_total(), 2 ),
                'items_summary' => implode( ', ', $item_names ),
            ];
        }

        return [
            'orders'     => $result,
            'total_found' => count( $result ),
            'customer'   => wp_get_current_user()->display_name,
        ];
    }

    // -------------------------------------------------------------------------
    // Tool: get_store_policies
    // -------------------------------------------------------------------------
    private static function get_store_policies( array $args ): array {
        $type = sanitize_text_field( $args['policy_type'] ?? 'all' );

        $policies = [];
        $sections = [ 'shipping_policy', 'return_policy', 'warranty_policy' ];

        foreach ( $sections as $key ) {
            if ( $type !== 'all' && $type !== $key ) {
                continue;
            }
            $content = WCAIC_Brand_Knowledge::get_section( $key );
            if ( ! empty( $content ) ) {
                $policies[ $key ] = $content;
            }
        }

        if ( empty( $policies ) ) {
            return [
                'message'  => 'No policy information has been configured for this store.',
                'policies' => [],
            ];
        }

        return [
            'policies' => $policies,
        ];
    }

    // -------------------------------------------------------------------------
    // Tool: compare_products
    // -------------------------------------------------------------------------
    private static function compare_products( array $args ): array|WP_Error {
        $ids = array_map( 'absint', (array) ( $args['product_ids'] ?? [] ) );
        $ids = array_filter( $ids );

        if ( count( $ids ) < 2 ) {
            return new WP_Error( 'too_few', 'At least 2 product IDs are required for comparison.' );
        }
        if ( count( $ids ) > 4 ) {
            $ids = array_slice( $ids, 0, 4 );
        }

        $products = [];
        foreach ( $ids as $id ) {
            $product = wc_get_product( $id );
            if ( ! $product ) {
                continue;
            }
            $data = self::format_product( $product );
            $data['description'] = wp_strip_all_tags( $product->get_short_description() );
            $data['sku']         = $product->get_sku();
            $data['weight']      = $product->get_weight();

            // Attributes for comparison
            $attributes = [];
            foreach ( $product->get_attributes() as $attr ) {
                $label  = wc_attribute_label( $attr->get_name() );
                $values = $attr->is_taxonomy()
                    ? wc_get_product_terms( $id, $attr->get_name(), [ 'fields' => 'names' ] )
                    : $attr->get_options();
                $attributes[ $label ] = is_array( $values ) ? implode( ', ', $values ) : (string) $values;
            }
            $data['attributes'] = $attributes;

            $products[] = $data;
        }

        if ( count( $products ) < 2 ) {
            return new WP_Error( 'not_found', 'Could not find enough products to compare.' );
        }

        return [
            'comparison' => $products,
            'count'      => count( $products ),
        ];
    }

    // -------------------------------------------------------------------------
    // Tool: estimate_shipping
    // -------------------------------------------------------------------------
    private static function estimate_shipping( array $args ): array|WP_Error {
        $country  = strtoupper( sanitize_text_field( $args['country'] ?? '' ) );
        $state    = sanitize_text_field( $args['state'] ?? '' );
        $postcode = sanitize_text_field( $args['postcode'] ?? '' );

        if ( empty( $country ) ) {
            return new WP_Error( 'missing_country', 'Destination country is required.' );
        }

        $cart = WC()->cart;
        if ( ! $cart || $cart->is_empty() ) {
            return new WP_Error( 'empty_cart', 'Cart is empty. Add items before estimating shipping.' );
        }

        // Set shipping destination
        WC()->customer->set_shipping_country( $country );
        if ( $state ) {
            WC()->customer->set_shipping_state( $state );
        }
        if ( $postcode ) {
            WC()->customer->set_shipping_postcode( $postcode );
        }

        $cart->calculate_totals();
        $packages = WC()->shipping()->calculate_shipping( $cart->get_shipping_packages() );

        $methods = [];
        foreach ( $packages as $package ) {
            foreach ( $package['rates'] ?? [] as $rate ) {
                $methods[] = [
                    'method' => $rate->get_label(),
                    'cost'   => wc_format_decimal( $rate->get_cost(), 2 ),
                ];
            }
        }

        if ( empty( $methods ) ) {
            return [
                'message'     => 'No shipping methods available for this destination.',
                'destination' => [ 'country' => $country, 'state' => $state, 'postcode' => $postcode ],
                'methods'     => [],
            ];
        }

        return [
            'destination' => [ 'country' => $country, 'state' => $state, 'postcode' => $postcode ],
            'methods'     => $methods,
            'cart_total'  => wc_format_decimal( $cart->get_cart_contents_total(), 2 ),
        ];
    }

    // -------------------------------------------------------------------------
    // Tool: get_product_calculator
    // -------------------------------------------------------------------------
    private static function get_product_calculator( array $args ): array|WP_Error {
        $product_id = absint( $args['product_id'] ?? 0 );
        if ( ! $product_id ) {
            return new WP_Error( 'invalid_id', 'Product ID is required.' );
        }

        // Check if sqft-pricing plugin is active and product has calculator.
        if ( ! class_exists( 'Sqft_Product_Options' ) ) {
            return new WP_Error( 'no_calculator', 'Product calculator plugin is not active.' );
        }

        $enabled = get_post_meta( $product_id, '_sqft_calculator_enabled', true );
        if ( $enabled !== '1' ) {
            return new WP_Error( 'not_configurable', 'This product does not have a calculator.' );
        }

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return new WP_Error( 'not_found', 'Product not found.' );
        }

        $config = Sqft_Product_Options::get_product_config( $product_id );

        // Build a simplified variables list for the AI to understand.
        $ai_summary = [];
        foreach ( $config['variables'] as $var ) {
            $var_info = [
                'name'    => $var['label'],
                'slug'    => $var['slug'],
                'type'    => $var['var_type'],
                'options' => [],
            ];
            foreach ( $var['items'] as $item ) {
                if ( $item['is_hidden'] ) continue;
                $var_info['options'][] = [
                    'id'      => (int) $item['id'],
                    'label'   => $item['label'],
                    'default' => (bool) $item['is_default'],
                ];
            }
            $ai_summary[] = $var_info;
        }

        // Prepare full frontend config (same format as Sqft_Frontend).
        $frontend_config = self::prepare_calculator_for_frontend( $config, $product_id );

        $image_id  = $product->get_image_id();
        $image_url = $image_id ? wp_get_attachment_url( $image_id ) : wc_placeholder_img_src();

        return [
            'product_id'   => $product_id,
            'product_name' => $product->get_name(),
            'image_url'    => esc_url( (string) $image_url ),
            'description'  => wp_strip_all_tags( $product->get_short_description() ?: $product->get_description() ),
            'formula'      => $config['formula'],
            'min_price'    => $config['min_price'],
            'variables'    => $frontend_config['variables'],
            'ai_summary'   => $ai_summary,
        ];
    }

    /**
     * Prepare calculator config for frontend (mirrors Sqft_Frontend::prepare_variables_for_js).
     */
    private static function prepare_calculator_for_frontend( array $config, int $product_id ): array {
        $variables = $config['variables'];

        // Build lookup maps.
        $var_id_to_slug = [];
        $item_id_to_data = [];
        foreach ( $variables as $var ) {
            $var_id_to_slug[ (int) $var['id'] ] = $var['slug'];
            foreach ( $var['items'] as $item ) {
                $item_id_to_data[ (int) $item['id'] ] = [
                    'label'    => $item['label'],
                    'var_slug' => $var['slug'],
                ];
            }
        }

        $output = [];
        foreach ( $variables as $var ) {
            $var_config = $var['config'] ?? [];
            $js_var = [
                'id'       => (int) $var['id'],
                'slug'     => $var['slug'],
                'label'    => $var['label'],
                'type'     => $var['var_type'],
                'config'   => $var_config,
                'isHidden' => ! empty( $var_config['hidden'] ),
                'items'    => [],
            ];

            foreach ( $var['items'] as $item ) {
                $js_item = [
                    'id'        => (int) $item['id'],
                    'label'     => $item['label'],
                    'value'     => floatval( $item['value_numeric'] ),
                    'base'      => floatval( $item['base_cost'] ),
                    'isDefault' => (bool) $item['is_default'],
                    'isHidden'  => (bool) $item['is_hidden'],
                    'config'    => $item['config'],
                    'filters'   => [],
                ];

                foreach ( $item['filters'] as $filter ) {
                    $dep_var_id   = (int) ( $filter['depends_on_variable_id'] ?? 0 );
                    $dep_item_id  = (int) ( $filter['depends_on_item_id'] ?? 0 );
                    $dep_var_slug = $var_id_to_slug[ $dep_var_id ] ?? '';
                    $dep_item_label = $item_id_to_data[ $dep_item_id ]['label'] ?? '';

                    $js_item['filters'][] = [
                        'variableSlug' => $dep_var_slug,
                        'itemId'       => $dep_item_id,
                        'itemLabel'    => $dep_item_label,
                    ];
                }

                $js_var['items'][] = $js_item;
            }

            $output[] = $js_var;
        }

        return [ 'variables' => $output ];
    }

    // -------------------------------------------------------------------------
    // Tool: add_calculator_to_cart
    // -------------------------------------------------------------------------
    private static function add_calculator_to_cart( array $args ): array|WP_Error {
        $product_id = absint( $args['product_id'] ?? 0 );
        $selections = (array) ( $args['selections'] ?? [] );

        if ( ! $product_id ) {
            return new WP_Error( 'invalid_id', 'Product ID is required.' );
        }

        if ( empty( $selections ) ) {
            return new WP_Error( 'no_selections', 'Calculator selections are required.' );
        }

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return new WP_Error( 'not_found', 'Product not found.' );
        }

        // Sanitize selections.
        $clean_selections = [];
        foreach ( $selections as $slug => $data ) {
            $clean_selections[ sanitize_key( $slug ) ] = [
                'id'     => absint( $data['id'] ?? 0 ),
                'label'  => sanitize_text_field( $data['label'] ?? '' ),
                'value'  => floatval( $data['value'] ?? 0 ),
                'base'   => floatval( $data['base'] ?? 0 ),
                'config' => isset( $data['config'] ) && is_array( $data['config'] )
                    ? array_map( 'sanitize_text_field', $data['config'] )
                    : [],
            ];
        }

        // Add to cart with calculator data.
        $cart_item_data = [
            'sqft_selections' => $clean_selections,
            'sqft_unique_key' => md5( wp_json_encode( $clean_selections ) ),
        ];

        $added = WC()->cart->add_to_cart( $product_id, 1, 0, [], $cart_item_data );
        if ( ! $added ) {
            return new WP_Error( 'cart_error', 'Failed to add configured product to cart.' );
        }

        WC()->cart->calculate_totals();

        // Build selection summary for AI response.
        $summary_parts = [];
        foreach ( $clean_selections as $slug => $data ) {
            if ( ! empty( $data['label'] ) ) {
                $summary_parts[] = $data['label'];
            }
        }

        return [
            'success'        => true,
            'product_name'   => $product->get_name(),
            'configuration'  => implode( ', ', $summary_parts ),
            'cart_total'     => WC()->cart->get_cart_total(),
            'cart_count'     => WC()->cart->get_cart_contents_count(),
        ];
    }
}
