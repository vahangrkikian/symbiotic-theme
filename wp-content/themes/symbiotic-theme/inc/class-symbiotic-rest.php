<?php
defined( 'ABSPATH' ) || exit;

/**
 * Theme-specific REST endpoints: symbiotic/v1
 */
class Symbiotic_REST {

    public static function init(): void {
        add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
    }

    public static function register_routes(): void {
        register_rest_route( 'symbiotic/v1', '/theme-data', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'theme_data' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'symbiotic/v1', '/navigation', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'navigation' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'symbiotic/v1', '/cart', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_cart' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'symbiotic/v1', '/cart/remove', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'remove_cart_item' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'symbiotic/v1', '/cart/update', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'update_cart_item' ],
            'permission_callback' => '__return_true',
        ] );

        // Checkout endpoints.
        register_rest_route( 'symbiotic/v1', '/checkout', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'checkout_get' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'symbiotic/v1', '/checkout/address', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'checkout_address' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'symbiotic/v1', '/checkout/shipping', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'checkout_shipping' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'symbiotic/v1', '/checkout/place-order', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'checkout_place_order' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'symbiotic/v1', '/add-to-cart', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'add_to_cart' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'symbiotic/v1', '/products', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'products' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'symbiotic/v1', '/calculator/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'calculator' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'id' => [ 'validate_callback' => function( $v ) { return is_numeric( $v ); } ],
            ],
        ] );
    }

    public static function theme_data( WP_REST_Request $request ): WP_REST_Response {
        $settings = (array) get_option( 'wcaic_settings', [] );

        $logo_url = '';
        $logo_id  = get_theme_mod( 'custom_logo' );
        if ( $logo_id ) {
            $logo_url = wp_get_attachment_url( $logo_id ) ?: '';
        }

        $total_products   = (int) wp_count_posts( 'product' )->publish;
        $total_categories = (int) wp_count_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => true ] );

        return rest_ensure_response( [
            'storeName'        => get_bloginfo( 'name' ),
            'storeLogo'        => esc_url( $logo_url ),
            'storeCurrency'    => get_woocommerce_currency(),
            'primaryColor'     => $settings['primary_color'] ?? '#6366f1',
            'greeting'         => $settings['greeting']      ?? 'Hi! How can I help you today?',
            'totalProducts'    => $total_products,
            'totalCategories'  => $total_categories,
        ] );
    }

    public static function navigation( WP_REST_Request $request ): WP_REST_Response {
        $categories = [];
        $terms      = get_terms( [
            'taxonomy'   => 'product_cat',
            'hide_empty' => true,
            'number'     => 20,
            'orderby'    => 'count',
            'order'      => 'DESC',
        ] );
        if ( ! is_wp_error( $terms ) ) {
            foreach ( $terms as $term ) {
                $thumb_id = get_term_meta( $term->term_id, 'thumbnail_id', true );
                $thumb    = $thumb_id ? wp_get_attachment_url( $thumb_id ) : '';
                $categories[] = [
                    'id'        => $term->term_id,
                    'name'      => $term->name,
                    'slug'      => $term->slug,
                    'count'     => $term->count,
                    'thumbnail' => esc_url( (string) $thumb ),
                ];
            }
        }

        $brands = [];
        $brand_terms = get_terms( [
            'taxonomy'   => 'product_tag',
            'hide_empty' => true,
            'number'     => 20,
            'orderby'    => 'count',
            'order'      => 'DESC',
        ] );
        if ( ! is_wp_error( $brand_terms ) ) {
            foreach ( $brand_terms as $term ) {
                $brands[] = [
                    'id'    => $term->term_id,
                    'name'  => $term->name,
                    'slug'  => $term->slug,
                    'count' => $term->count,
                ];
            }
        }

        return rest_ensure_response( [
            'categories' => $categories,
            'brands'     => $brands,
        ] );
    }

    /**
     * GET /symbiotic/v1/products — list all published products with calculator flag.
     */
    public static function products( WP_REST_Request $request ): WP_REST_Response {
        $products = [];
        $args = [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => 50,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        $category = sanitize_text_field( $request->get_param( 'category' ) ?? '' );
        if ( ! empty( $category ) ) {
            $args['tax_query'] = [ [
                'taxonomy' => 'product_cat',
                'field'    => 'slug',
                'terms'    => $category,
            ] ];
        }

        $query = new WP_Query( $args );

        foreach ( $query->posts as $post ) {
            $product = wc_get_product( $post->ID );
            if ( ! $product ) continue;

            $image_id  = $product->get_image_id();
            $image_url = $image_id ? wp_get_attachment_url( $image_id ) : wc_placeholder_img_src();
            $gallery   = array_map( 'wp_get_attachment_url', $product->get_gallery_image_ids() );

            $has_calc = get_post_meta( $post->ID, '_sqft_calculator_enabled', true ) === '1';

            $products[] = [
                'id'             => $post->ID,
                'name'           => $product->get_name(),
                'slug'           => $product->get_slug(),
                'price'          => $product->get_price(),
                'regular_price'  => $product->get_regular_price(),
                'image_url'      => esc_url( (string) $image_url ),
                'gallery'        => $gallery,
                'short_description' => wp_strip_all_tags( $product->get_short_description() ),
                'has_calculator' => $has_calc,
                'in_stock'       => $product->is_in_stock(),
            ];
        }

        return rest_ensure_response( $products );
    }

    /**
     * GET /symbiotic/v1/calculator/{id} — full calculator config for a product.
     */
    public static function calculator( WP_REST_Request $request ): WP_REST_Response {
        $product_id = (int) $request->get_param( 'id' );
        $product    = wc_get_product( $product_id );

        if ( ! $product ) {
            return new WP_REST_Response( [ 'error' => 'Product not found' ], 404 );
        }

        $enabled = get_post_meta( $product_id, '_sqft_calculator_enabled', true );
        if ( $enabled !== '1' || ! class_exists( 'Sqft_Product_Options' ) ) {
            return new WP_REST_Response( [ 'error' => 'Calculator not enabled' ], 404 );
        }

        $config    = Sqft_Product_Options::get_product_config( $product_id );
        $image_id  = $product->get_image_id();
        $image_url = $image_id ? wp_get_attachment_url( $image_id ) : wc_placeholder_img_src();
        $gallery   = array_map( 'wp_get_attachment_url', $product->get_gallery_image_ids() );

        // Build frontend-ready variables with resolved filters.
        $variables = self::prepare_variables( $config['variables'] );

        return rest_ensure_response( [
            'product_id'        => $product_id,
            'product_name'      => $product->get_name(),
            'description'       => wp_strip_all_tags( $product->get_description() ),
            'short_description' => wp_strip_all_tags( $product->get_short_description() ),
            'image_url'         => esc_url( (string) $image_url ),
            'gallery'           => $gallery,
            'formula'           => $config['formula'],
            'min_price'         => $config['min_price'],
            'variables'         => $variables,
            'currency_symbol'   => html_entity_decode( get_woocommerce_currency_symbol() ),
        ] );
    }

    /**
     * Prepare variables array for frontend with resolved filter IDs → slugs/labels.
     */
    // =========================================================================
    // CHECKOUT ENDPOINTS
    // =========================================================================

    /**
     * GET /symbiotic/v1/checkout — get checkout state (cart + addresses + shipping + payment methods).
     */
    public static function checkout_get( WP_REST_Request $request ): WP_REST_Response {
        self::ensure_cart();
        $cart = WC()->cart;
        $cart->calculate_totals();

        if ( $cart->is_empty() ) {
            return new WP_REST_Response( [ 'error' => 'Cart is empty.' ], 400 );
        }

        $customer = WC()->customer;

        // Cart items.
        $items = [];
        foreach ( $cart->get_cart() as $key => $item ) {
            $product = wc_get_product( $item['product_id'] );
            if ( ! $product ) continue;
            $image_id  = $product->get_image_id();
            $image_url = $image_id ? wp_get_attachment_url( $image_id ) : wc_placeholder_img_src();
            $options   = [];
            if ( ! empty( $item['sqft_selections'] ) ) {
                foreach ( $item['sqft_selections'] as $slug => $sel ) {
                    if ( ! empty( $sel['label'] ) ) {
                        $options[] = ucfirst( str_replace( '_', ' ', $slug ) ) . ': ' . $sel['label'];
                    }
                }
            }
            $items[] = [
                'key'        => $key,
                'name'       => $product->get_name(),
                'quantity'   => $item['quantity'],
                'price'      => wc_format_decimal( $item['line_total'] / max( 1, $item['quantity'] ), 2 ),
                'line_total' => wc_format_decimal( $item['line_total'], 2 ),
                'image_url'  => esc_url( (string) $image_url ),
                'options'    => $options,
            ];
        }

        // Saved address.
        $address = [
            'first_name' => $customer->get_billing_first_name() ?: $customer->get_shipping_first_name(),
            'last_name'  => $customer->get_billing_last_name() ?: $customer->get_shipping_last_name(),
            'email'      => $customer->get_billing_email(),
            'phone'      => $customer->get_billing_phone(),
            'address_1'  => $customer->get_shipping_address_1() ?: $customer->get_billing_address_1(),
            'address_2'  => $customer->get_shipping_address_2() ?: $customer->get_billing_address_2(),
            'city'       => $customer->get_shipping_city() ?: $customer->get_billing_city(),
            'state'      => $customer->get_shipping_state() ?: $customer->get_billing_state(),
            'postcode'   => $customer->get_shipping_postcode() ?: $customer->get_billing_postcode(),
            'country'    => $customer->get_shipping_country() ?: $customer->get_billing_country() ?: 'US',
        ];

        // Shipping methods.
        $shipping_methods = [];
        $packages = WC()->shipping()->calculate_shipping( $cart->get_shipping_packages() );
        foreach ( $packages as $pkg ) {
            foreach ( $pkg['rates'] ?? [] as $rate ) {
                $shipping_methods[] = [
                    'id'    => $rate->get_id(),
                    'label' => $rate->get_label(),
                    'cost'  => wc_format_decimal( $rate->get_cost(), 2 ),
                ];
            }
        }

        // Chosen shipping.
        $chosen = WC()->session->get( 'chosen_shipping_methods', [] );

        // Payment gateways.
        $gateways = [];
        foreach ( WC()->payment_gateways()->get_available_payment_gateways() as $gw ) {
            $gateways[] = [
                'id'          => $gw->id,
                'title'       => $gw->get_title(),
                'description' => $gw->get_description(),
            ];
        }

        // Totals.
        $totals = [
            'subtotal' => wc_format_decimal( $cart->get_subtotal(), 2 ),
            'shipping' => wc_format_decimal( $cart->get_shipping_total(), 2 ),
            'tax'      => wc_format_decimal( $cart->get_total_tax(), 2 ),
            'discount' => wc_format_decimal( $cart->get_discount_total(), 2 ),
            'total'    => wc_format_decimal( $cart->get_total( 'edit' ), 2 ),
        ];

        return rest_ensure_response( [
            'items'            => $items,
            'item_count'       => $cart->get_cart_contents_count(),
            'address'          => $address,
            'shipping_methods' => $shipping_methods,
            'chosen_shipping'  => $chosen[0] ?? '',
            'payment_gateways' => $gateways,
            'totals'           => $totals,
            'currency_symbol'  => html_entity_decode( get_woocommerce_currency_symbol() ),
            'needs_shipping'   => $cart->needs_shipping(),
        ] );
    }

    /**
     * POST /symbiotic/v1/checkout/address — set billing/shipping address.
     */
    public static function checkout_address( WP_REST_Request $request ): WP_REST_Response {
        self::ensure_cart();

        $fields = [ 'first_name', 'last_name', 'email', 'phone', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country' ];
        $customer = WC()->customer;

        foreach ( $fields as $f ) {
            $val = sanitize_text_field( $request->get_param( $f ) ?? '' );
            if ( $val === '' && ! in_array( $f, [ 'address_2', 'phone', 'state' ], true ) ) continue;

            // Set both billing and shipping.
            $setter_b = "set_billing_{$f}";
            $setter_s = "set_shipping_{$f}";
            if ( method_exists( $customer, $setter_b ) ) $customer->$setter_b( $val );
            if ( method_exists( $customer, $setter_s ) && $f !== 'email' && $f !== 'phone' ) $customer->$setter_s( $val );
        }

        $customer->save();
        WC()->cart->calculate_shipping();
        WC()->cart->calculate_totals();

        // Return updated shipping methods.
        $shipping_methods = [];
        $packages = WC()->shipping()->calculate_shipping( WC()->cart->get_shipping_packages() );
        foreach ( $packages as $pkg ) {
            foreach ( $pkg['rates'] ?? [] as $rate ) {
                $shipping_methods[] = [
                    'id'    => $rate->get_id(),
                    'label' => $rate->get_label(),
                    'cost'  => wc_format_decimal( $rate->get_cost(), 2 ),
                ];
            }
        }

        $totals = [
            'subtotal' => wc_format_decimal( WC()->cart->get_subtotal(), 2 ),
            'shipping' => wc_format_decimal( WC()->cart->get_shipping_total(), 2 ),
            'tax'      => wc_format_decimal( WC()->cart->get_total_tax(), 2 ),
            'total'    => wc_format_decimal( WC()->cart->get_total( 'edit' ), 2 ),
        ];

        return rest_ensure_response( [
            'success'          => true,
            'shipping_methods' => $shipping_methods,
            'totals'           => $totals,
        ] );
    }

    /**
     * POST /symbiotic/v1/checkout/shipping — select a shipping method.
     */
    public static function checkout_shipping( WP_REST_Request $request ): WP_REST_Response {
        self::ensure_cart();

        $method_id = sanitize_text_field( $request->get_param( 'method_id' ) ?? '' );
        if ( empty( $method_id ) ) {
            return new WP_REST_Response( [ 'success' => false, 'message' => 'Shipping method required.' ], 400 );
        }

        WC()->session->set( 'chosen_shipping_methods', [ $method_id ] );
        WC()->cart->calculate_totals();

        return rest_ensure_response( [
            'success' => true,
            'totals'  => [
                'subtotal' => wc_format_decimal( WC()->cart->get_subtotal(), 2 ),
                'shipping' => wc_format_decimal( WC()->cart->get_shipping_total(), 2 ),
                'tax'      => wc_format_decimal( WC()->cart->get_total_tax(), 2 ),
                'total'    => wc_format_decimal( WC()->cart->get_total( 'edit' ), 2 ),
            ],
        ] );
    }

    /**
     * POST /symbiotic/v1/checkout/place-order — create order and process payment.
     */
    public static function checkout_place_order( WP_REST_Request $request ): WP_REST_Response {
        self::ensure_cart();

        $cart = WC()->cart;
        if ( $cart->is_empty() ) {
            return new WP_REST_Response( [ 'success' => false, 'message' => 'Cart is empty.' ], 400 );
        }

        $payment_method = sanitize_text_field( $request->get_param( 'payment_method' ) ?? 'cod' );
        $customer       = WC()->customer;

        // Validate required fields.
        $errors = [];
        if ( ! $customer->get_billing_first_name() ) $errors[] = 'First name is required.';
        if ( ! $customer->get_billing_last_name() )  $errors[] = 'Last name is required.';
        if ( ! $customer->get_billing_email() )      $errors[] = 'Email is required.';
        if ( ! $customer->get_billing_address_1() && ! $customer->get_shipping_address_1() ) $errors[] = 'Address is required.';
        if ( ! $customer->get_billing_city() && ! $customer->get_shipping_city() ) $errors[] = 'City is required.';

        if ( ! empty( $errors ) ) {
            return new WP_REST_Response( [ 'success' => false, 'message' => implode( ' ', $errors ) ], 400 );
        }

        try {
            // Create order.
            $order = wc_create_order( [
                'customer_id' => get_current_user_id(),
                'status'      => 'pending',
            ] );

            if ( is_wp_error( $order ) ) {
                return new WP_REST_Response( [ 'success' => false, 'message' => $order->get_error_message() ], 500 );
            }

            // Add cart items to order.
            foreach ( $cart->get_cart() as $item ) {
                $product = wc_get_product( $item['product_id'] );
                if ( ! $product ) continue;

                $order_item_id = $order->add_product( $product, $item['quantity'], [
                    'subtotal' => $item['line_subtotal'],
                    'total'    => $item['line_total'],
                ] );

                // Save calculator selections as order meta.
                if ( ! empty( $item['sqft_selections'] ) && $order_item_id ) {
                    $order_item = $order->get_item( $order_item_id );
                    foreach ( $item['sqft_selections'] as $slug => $sel ) {
                        if ( ! empty( $sel['label'] ) ) {
                            $order_item->add_meta_data(
                                ucfirst( str_replace( '_', ' ', $slug ) ),
                                $sel['label']
                            );
                        }
                    }
                    $order_item->save();
                }
            }

            // Set addresses.
            $address_fields = [ 'first_name', 'last_name', 'email', 'phone', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country' ];
            foreach ( $address_fields as $f ) {
                $getter_b = "get_billing_{$f}";
                $getter_s = "get_shipping_{$f}";
                $setter_b = "set_billing_{$f}";
                $setter_s = "set_shipping_{$f}";

                if ( method_exists( $customer, $getter_b ) ) {
                    $val = $customer->$getter_b();
                    if ( method_exists( $order, $setter_b ) ) $order->$setter_b( $val );
                }
                if ( method_exists( $customer, $getter_s ) && $f !== 'email' && $f !== 'phone' ) {
                    $val = $customer->$getter_s();
                    if ( method_exists( $order, $setter_s ) ) $order->$setter_s( $val );
                }
            }

            // Shipping.
            if ( $cart->needs_shipping() ) {
                $chosen = WC()->session->get( 'chosen_shipping_methods', [] );
                $packages = WC()->shipping()->get_packages();
                foreach ( $packages as $pkg ) {
                    $rate = $pkg['rates'][ $chosen[0] ?? '' ] ?? null;
                    if ( $rate ) {
                        $shipping_item = new WC_Order_Item_Shipping();
                        $shipping_item->set_method_title( $rate->get_label() );
                        $shipping_item->set_method_id( $rate->get_method_id() );
                        $shipping_item->set_total( $rate->get_cost() );
                        $order->add_item( $shipping_item );
                    }
                }
            }

            // Payment method.
            $order->set_payment_method( $payment_method );
            $gateways = WC()->payment_gateways()->get_available_payment_gateways();
            if ( isset( $gateways[ $payment_method ] ) ) {
                $order->set_payment_method_title( $gateways[ $payment_method ]->get_title() );
            }

            $order->calculate_totals();
            $order->save();

            // Process payment.
            if ( isset( $gateways[ $payment_method ] ) ) {
                $result = $gateways[ $payment_method ]->process_payment( $order->get_id() );
                if ( $result['result'] === 'success' ) {
                    $order->update_status( 'processing', 'Order placed via in-chat checkout.' );
                }
            } else {
                $order->update_status( 'pending' );
            }

            // Clear cart.
            $cart->empty_cart();

            return rest_ensure_response( [
                'success'    => true,
                'order_id'   => $order->get_id(),
                'status'     => $order->get_status(),
                'total'      => wc_format_decimal( $order->get_total(), 2 ),
                'currency'   => $order->get_currency(),
                'payment'    => $order->get_payment_method_title(),
                'order_url'  => $order->get_view_order_url(),
            ] );

        } catch ( \Exception $e ) {
            return new WP_REST_Response( [ 'success' => false, 'message' => $e->getMessage() ], 500 );
        }
    }

    /**
     * Ensure WC session/cart is available for REST calls.
     */
    private static function ensure_cart(): void {
        if ( ! WC()->session ) {
            WC()->session = new WC_Session_Handler();
            WC()->session->init();
        }
        if ( ! WC()->cart ) {
            WC()->cart = new WC_Cart();
            WC()->cart->get_cart_from_session();
        }
        if ( ! WC()->customer ) {
            WC()->customer = new WC_Customer( get_current_user_id(), true );
        }
    }

    /**
     * GET /symbiotic/v1/cart — full cart contents with item details.
     */
    public static function get_cart( WP_REST_Request $request ): WP_REST_Response {
        self::ensure_cart();

        $cart  = WC()->cart;
        $items = [];

        foreach ( $cart->get_cart() as $key => $item ) {
            $product = wc_get_product( $item['product_id'] );
            if ( ! $product ) continue;

            $image_id  = $product->get_image_id();
            $image_url = $image_id ? wp_get_attachment_url( $image_id ) : wc_placeholder_img_src();
            $line_price = $item['line_total'] / max( 1, $item['quantity'] );

            // Get sqft selections if present.
            $options = [];
            if ( ! empty( $item['sqft_selections'] ) ) {
                foreach ( $item['sqft_selections'] as $slug => $sel ) {
                    if ( ! empty( $sel['label'] ) ) {
                        $options[] = [
                            'name'  => ucfirst( str_replace( '_', ' ', $slug ) ),
                            'value' => $sel['label'],
                        ];
                    }
                }
            }

            $items[] = [
                'key'        => $key,
                'product_id' => $item['product_id'],
                'name'       => $product->get_name(),
                'quantity'   => $item['quantity'],
                'price'      => wc_format_decimal( $line_price, 2 ),
                'line_total' => wc_format_decimal( $item['line_total'], 2 ),
                'image_url'  => esc_url( (string) $image_url ),
                'options'    => $options,
            ];
        }

        $cart->calculate_totals();

        return rest_ensure_response( [
            'items'        => $items,
            'item_count'   => $cart->get_cart_contents_count(),
            'subtotal'     => wc_format_decimal( $cart->get_subtotal(), 2 ),
            'total'        => wc_format_decimal( $cart->get_cart_contents_total(), 2 ),
            'currency'     => get_woocommerce_currency(),
            'currency_symbol' => html_entity_decode( get_woocommerce_currency_symbol() ),
            'checkout_url' => wc_get_checkout_url(),
        ] );
    }

    /**
     * POST /symbiotic/v1/cart/remove
     */
    public static function remove_cart_item( WP_REST_Request $request ): WP_REST_Response {
        self::ensure_cart();
        $key = sanitize_text_field( $request->get_param( 'key' ) );

        if ( ! $key || ! WC()->cart->get_cart_item( $key ) ) {
            return new WP_REST_Response( [ 'success' => false, 'message' => 'Item not found.' ], 404 );
        }

        WC()->cart->remove_cart_item( $key );
        WC()->cart->calculate_totals();

        return rest_ensure_response( [
            'success'    => true,
            'item_count' => WC()->cart->get_cart_contents_count(),
            'total'      => wc_format_decimal( WC()->cart->get_cart_contents_total(), 2 ),
        ] );
    }

    /**
     * POST /symbiotic/v1/cart/update
     */
    public static function update_cart_item( WP_REST_Request $request ): WP_REST_Response {
        self::ensure_cart();
        $key = sanitize_text_field( $request->get_param( 'key' ) );
        $qty = absint( $request->get_param( 'quantity' ) );

        if ( ! $key || ! WC()->cart->get_cart_item( $key ) ) {
            return new WP_REST_Response( [ 'success' => false, 'message' => 'Item not found.' ], 404 );
        }

        if ( $qty === 0 ) {
            WC()->cart->remove_cart_item( $key );
        } else {
            WC()->cart->set_quantity( $key, $qty );
        }

        WC()->cart->calculate_totals();

        return rest_ensure_response( [
            'success'    => true,
            'item_count' => WC()->cart->get_cart_contents_count(),
            'total'      => wc_format_decimal( WC()->cart->get_cart_contents_total(), 2 ),
        ] );
    }

    /**
     * POST /symbiotic/v1/add-to-cart — add a configured calculator product to cart.
     */
    public static function add_to_cart( WP_REST_Request $request ): WP_REST_Response {
        $product_id = absint( $request->get_param( 'product_id' ) );
        $selections = $request->get_param( 'selections' );
        $price      = floatval( $request->get_param( 'calculated_price' ) );

        if ( ! $product_id ) {
            return new WP_REST_Response( [ 'success' => false, 'message' => 'Product ID required.' ], 400 );
        }

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return new WP_REST_Response( [ 'success' => false, 'message' => 'Product not found.' ], 404 );
        }

        // Sanitize selections.
        $clean = [];
        if ( is_array( $selections ) ) {
            foreach ( $selections as $slug => $data ) {
                $clean[ sanitize_key( $slug ) ] = [
                    'id'     => absint( $data['id'] ?? 0 ),
                    'label'  => sanitize_text_field( $data['label'] ?? '' ),
                    'value'  => floatval( $data['value'] ?? 0 ),
                    'base'   => floatval( $data['base'] ?? 0 ),
                    'config' => isset( $data['config'] ) && is_array( $data['config'] )
                        ? array_map( 'sanitize_text_field', $data['config'] )
                        : [],
                ];
            }
        }

        self::ensure_cart();

        $cart_item_data = [
            'sqft_selections' => $clean,
            'sqft_unique_key' => md5( wp_json_encode( $clean ) ),
        ];

        $cart_key = WC()->cart->add_to_cart( $product_id, 1, 0, [], $cart_item_data );

        if ( ! $cart_key ) {
            return new WP_REST_Response( [ 'success' => false, 'message' => 'Failed to add to cart.' ], 500 );
        }

        WC()->cart->calculate_totals();

        // Build selection summary.
        $summary = [];
        foreach ( $clean as $slug => $data ) {
            if ( ! empty( $data['label'] ) ) {
                $summary[] = $data['label'];
            }
        }

        return rest_ensure_response( [
            'success'       => true,
            'product_name'  => $product->get_name(),
            'configuration' => implode( ', ', $summary ),
            'cart_key'      => $cart_key,
            'cart_total'    => strip_tags( WC()->cart->get_cart_total() ),
            'cart_count'    => WC()->cart->get_cart_contents_count(),
            'cart_url'      => wc_get_cart_url(),
            'checkout_url'  => wc_get_checkout_url(),
        ] );
    }

    private static function prepare_variables( array $variables ): array {
        $var_id_to_slug  = [];
        $item_id_to_data = [];
        foreach ( $variables as $var ) {
            $var_id_to_slug[ (int) $var['id'] ] = $var['slug'];
            foreach ( $var['items'] as $item ) {
                $item_id_to_data[ (int) $item['id'] ] = [ 'label' => $item['label'], 'var_slug' => $var['slug'] ];
            }
        }

        $output = [];
        foreach ( $variables as $var ) {
            $vc = $var['config'] ?? [];
            $js_var = [
                'id'       => (int) $var['id'],
                'slug'     => $var['slug'],
                'label'    => $var['label'],
                'type'     => $var['var_type'],
                'config'   => $vc,
                'isHidden' => ! empty( $vc['hidden'] ),
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
                foreach ( $item['filters'] as $f ) {
                    $dv = (int) ( $f['depends_on_variable_id'] ?? 0 );
                    $di = (int) ( $f['depends_on_item_id'] ?? 0 );
                    $js_item['filters'][] = [
                        'variableSlug' => $var_id_to_slug[ $dv ] ?? '',
                        'itemId'       => $di,
                        'itemLabel'    => $item_id_to_data[ $di ]['label'] ?? '',
                    ];
                }
                $js_var['items'][] = $js_item;
            }
            $output[] = $js_var;
        }
        return $output;
    }
}
