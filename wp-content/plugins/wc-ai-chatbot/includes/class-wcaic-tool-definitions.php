<?php
defined( 'ABSPATH' ) || exit;

/**
 * 8 tool schemas for both OpenAI and Anthropic formats.
 */
class WCAIC_Tool_Definitions {

    public static function get_tools( string $format = 'openai' ): array {
        $tools = self::definitions();
        if ( $format === 'anthropic' ) {
            return self::to_anthropic( $tools );
        }
        return self::to_openai( $tools );
    }

    private static function definitions(): array {
        return [
            [
                'name'        => 'search_products',
                'description' => 'Search WooCommerce products by keyword, category, brand, price range, or sale status. Always call this before recommending any product.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'query'    => [ 'type' => 'string',  'description' => 'Keyword search query' ],
                        'category' => [ 'type' => 'string',  'description' => 'Category slug to filter by' ],
                        'brand'    => [ 'type' => 'string',  'description' => 'Brand/product attribute slug' ],
                        'min_price'=> [ 'type' => 'number',  'description' => 'Minimum price filter' ],
                        'max_price'=> [ 'type' => 'number',  'description' => 'Maximum price filter' ],
                        'on_sale'  => [ 'type' => 'boolean', 'description' => 'Filter to sale items only' ],
                        'per_page' => [ 'type' => 'integer', 'description' => 'Results per page (default 5, max 10)' ],
                    ],
                    'required'   => [],
                ],
            ],
            [
                'name'        => 'get_product_details',
                'description' => 'Get full product details including variations, gallery images, attributes, and reviews.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'product_id' => [ 'type' => 'integer', 'description' => 'WooCommerce product ID' ],
                    ],
                    'required'   => [ 'product_id' ],
                ],
            ],
            [
                'name'        => 'add_to_cart',
                'description' => 'Add a product to the WooCommerce cart. For variable products, include variation_id and variation attributes.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'product_id'   => [ 'type' => 'integer', 'description' => 'Product ID to add' ],
                        'quantity'     => [ 'type' => 'integer', 'description' => 'Quantity to add (default 1)' ],
                        'variation_id' => [ 'type' => 'integer', 'description' => 'Variation ID for variable products' ],
                        'variation'    => [
                            'type'        => 'object',
                            'description' => 'Variation attributes, e.g. {"attribute_pa_color":"blue"}',
                            'additionalProperties' => [ 'type' => 'string' ],
                        ],
                    ],
                    'required'   => [ 'product_id' ],
                ],
            ],
            [
                'name'        => 'remove_from_cart',
                'description' => 'Remove an item from the cart by its cart item key.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'item_key' => [ 'type' => 'string', 'description' => 'Cart item key (from get_cart result)' ],
                    ],
                    'required'   => [ 'item_key' ],
                ],
            ],
            [
                'name'        => 'update_cart_quantity',
                'description' => 'Update the quantity of a cart item. Set quantity to 0 to remove the item.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'item_key' => [ 'type' => 'string',  'description' => 'Cart item key' ],
                        'quantity' => [ 'type' => 'integer', 'description' => 'New quantity (0 removes item)' ],
                    ],
                    'required'   => [ 'item_key', 'quantity' ],
                ],
            ],
            [
                'name'        => 'get_cart',
                'description' => 'Get the current contents and totals of the WooCommerce cart.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => (object) [],
                    'required'   => [],
                ],
            ],
            [
                'name'        => 'apply_coupon',
                'description' => 'Apply a coupon code to the cart.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'coupon_code' => [ 'type' => 'string', 'description' => 'Coupon code to apply' ],
                    ],
                    'required'   => [ 'coupon_code' ],
                ],
            ],
            [
                'name'        => 'get_checkout_url',
                'description' => 'Get the WooCommerce checkout URL with a summary of the current cart.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => (object) [],
                    'required'   => [],
                ],
            ],
            [
                'name'        => 'get_order_status',
                'description' => 'Get the status and details of a specific order. The customer must be logged in and own the order.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'order_id' => [ 'type' => 'integer', 'description' => 'WooCommerce order ID' ],
                    ],
                    'required'   => [ 'order_id' ],
                ],
            ],
            [
                'name'        => 'get_customer_orders',
                'description' => 'List the logged-in customer\'s recent orders with status and totals.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'limit' => [ 'type' => 'integer', 'description' => 'Number of orders to return (default 5, max 10)' ],
                    ],
                    'required'   => [],
                ],
            ],
            [
                'name'        => 'get_store_policies',
                'description' => 'Get the store\'s policies (shipping, returns, warranty) from the brand knowledge base. Use when a customer asks about returns, refunds, shipping, or store policies.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'policy_type' => [ 'type' => 'string', 'description' => 'Policy type: shipping_policy, return_policy, warranty_policy, or all' ],
                    ],
                    'required'   => [],
                ],
            ],
            [
                'name'        => 'compare_products',
                'description' => 'Compare two or more products side-by-side on price, features, ratings, and availability.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'product_ids' => [
                            'type'        => 'array',
                            'items'       => [ 'type' => 'integer' ],
                            'description' => 'Array of 2-4 product IDs to compare',
                        ],
                    ],
                    'required'   => [ 'product_ids' ],
                ],
            ],
            [
                'name'        => 'estimate_shipping',
                'description' => 'Estimate shipping costs for the current cart based on destination country/state.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'country' => [ 'type' => 'string', 'description' => 'Destination country code (e.g. US, GB, DE)' ],
                        'state'   => [ 'type' => 'string', 'description' => 'State or province code (e.g. CA, NY)' ],
                        'postcode'=> [ 'type' => 'string', 'description' => 'Postal/ZIP code' ],
                    ],
                    'required'   => [ 'country' ],
                ],
            ],
            [
                'name'        => 'get_product_calculator',
                'description' => 'Get a configurable print product calculator with all options (shape, size, paper stock, finishing, quantity, turnaround). Use this when a customer asks about customizable/configurable print products like business cards, banners, or signs. Returns the full calculator configuration for the customer to interactively configure and see real-time pricing.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'product_id' => [ 'type' => 'integer', 'description' => 'Product ID of the configurable product' ],
                    ],
                    'required'   => [ 'product_id' ],
                ],
            ],
            [
                'name'        => 'add_calculator_to_cart',
                'description' => 'Add a configured print product to cart with specific option selections (shape, size, paper, quantity, etc). The selections object maps variable slugs to selected item IDs from the calculator.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'product_id'  => [ 'type' => 'integer', 'description' => 'Product ID' ],
                        'selections'  => [
                            'type'        => 'object',
                            'description' => 'Map of variable_slug to selected item data: { "Shape": { "id": 1, "label": "Rectangle" }, "Quantity": { "id": 5, "label": "250" }, ... }',
                            'additionalProperties' => [ 'type' => 'object' ],
                        ],
                        'calculated_price' => [ 'type' => 'number', 'description' => 'The calculated price from the frontend calculator' ],
                    ],
                    'required'   => [ 'product_id', 'selections' ],
                ],
            ],
        ];
    }

    private static function to_openai( array $tools ): array {
        return array_map( static function ( array $tool ): array {
            return [
                'type'     => 'function',
                'function' => [
                    'name'        => $tool['name'],
                    'description' => $tool['description'],
                    'parameters'  => $tool['parameters'],
                ],
            ];
        }, $tools );
    }

    private static function to_anthropic( array $tools ): array {
        return array_map( static function ( array $tool ): array {
            return [
                'name'         => $tool['name'],
                'description'  => $tool['description'],
                'input_schema' => $tool['parameters'],
            ];
        }, $tools );
    }
}
