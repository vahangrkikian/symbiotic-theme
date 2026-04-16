<?php
/**
 * Symbiotic Theme — Product Importer
 *
 * Fetches a product URL, parses HTML/JSON for product data,
 * and creates a WooCommerce product with sqft-pricing calculator config.
 *
 * @package SymbioticTheme
 */

defined( 'ABSPATH' ) || exit;

class Symbiotic_Product_Importer {

	/** @var array Step log for progress reporting. */
	private array $steps = [];

	public static function init(): void {
		add_action( 'wp_ajax_sym_import_product_from_url', [ new self(), 'handle_import_ajax' ] );
	}

	/**
	 * AJAX handler for product import.
	 */
	public function handle_import_ajax(): void {
		check_ajax_referer( 'sym_import_product', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ] );
		}

		$url = esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) );
		if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			wp_send_json_error( [ 'message' => 'Invalid URL provided.' ] );
		}

		$result = $this->import_from_url( $url );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message(), 'steps' => $this->steps ] );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Main import method.
	 */
	public function import_from_url( string $url ): array|WP_Error {
		// Step 1: Fetch the page.
		$this->log( 'fetch', "Fetching page content from URL..." );

		$response = wp_remote_get( $url, [
			'timeout'    => 30,
			'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
			'sslverify'  => false,
		] );

		if ( is_wp_error( $response ) ) {
			$this->log( 'error', 'Failed to fetch URL: ' . $response->get_error_message() );
			return $response;
		}

		$status = wp_remote_retrieve_response_code( $response );
		if ( $status !== 200 ) {
			$this->log( 'error', "HTTP error: status {$status}" );
			return new WP_Error( 'http_error', "URL returned HTTP {$status}" );
		}

		$html = wp_remote_retrieve_body( $response );
		if ( empty( $html ) ) {
			return new WP_Error( 'empty_body', 'Empty response body.' );
		}

		$this->log( 'fetch', 'Page fetched: ' . strlen( $html ) . ' bytes' );

		// Step 2: Parse product data.
		$this->log( 'parse', 'Parsing product data...' );
		$product_data = $this->parse_product_page( $html, $url );

		if ( is_wp_error( $product_data ) ) {
			return $product_data;
		}

		$this->log( 'parse', "Found: \"{$product_data['name']}\" with " . count( $product_data['variables'] ) . " option groups" );

		// Step 3: Create WooCommerce product.
		$this->log( 'create', 'Creating WooCommerce product...' );
		$product_id = $this->create_wc_product( $product_data );

		if ( is_wp_error( $product_id ) ) {
			return $product_id;
		}

		$this->log( 'create', "Product created: ID #{$product_id}" );

		// Step 4: Create calculator options.
		$this->log( 'options', 'Creating calculator option groups...' );
		$counts = $this->create_calculator_options( $product_id, $product_data );
		$this->log( 'options', "Created: {$counts['variables']} groups, {$counts['items']} choices, {$counts['filters']} filters" );

		// Step 5: Set formula.
		if ( ! empty( $product_data['formula'] ) ) {
			$this->log( 'formula', 'Setting pricing formula...' );
			update_post_meta( $product_id, '_sqft_formula', sanitize_textarea_field( $product_data['formula'] ) );
			$this->log( 'formula', 'Formula configured' );
		}

		$this->log( 'done', 'Import complete!' );

		return [
			'product_id'      => $product_id,
			'product_name'    => $product_data['name'],
			'variables_count' => $counts['variables'],
			'items_count'     => $counts['items'],
			'filters_count'   => $counts['filters'],
			'edit_url'        => admin_url( "post.php?post={$product_id}&action=edit" ),
			'view_url'        => get_permalink( $product_id ),
			'steps'           => $this->steps,
		];
	}

	/**
	 * Parse product page HTML for product data.
	 * Tries multiple strategies: JSON-LD, __NEXT_DATA__, Open Graph, meta tags, HTML parsing.
	 */
	private function parse_product_page( string $html, string $url ): array|WP_Error {
		$data = [
			'name'         => '',
			'description'  => '',
			'image_url'    => '',
			'source_url'   => $url,
			'variables'    => [],
			'formula'      => '',
			'min_price'    => 0,
		];

		// Strategy 1a: __NEXT_DATA__ (Next.js sites like axiomprint.com).
		if ( preg_match( '/<script\s+id="__NEXT_DATA__"\s+type="application\/json"[^>]*>(.*?)<\/script>/s', $html, $m ) ) {
			$this->log( 'parse', 'Found __NEXT_DATA__ (Next.js app)' );
			$next_data = json_decode( $m[1], true );
			if ( $next_data ) {
				$parsed = $this->parse_nextjs_data( $next_data, $data );
				if ( ! is_wp_error( $parsed ) ) return $parsed;
			}
		}

		// Strategy 1b: Rednao ProductBuilderOptions (tgm-print.com / WooCommerce + Rednao Extra Product Options).
		if ( preg_match( '/var\s+ProductBuilderOptions_\w+\s*=\s*(\{.*?\});\s*</s', $html, $m ) ) {
			$this->log( 'parse', 'Found Rednao ProductBuilderOptions (WooCommerce configurator)' );
			$rednao_data = json_decode( $m[1], true );
			if ( $rednao_data ) {
				$parsed = $this->parse_rednao_data( $rednao_data, $data, $html );
				if ( ! is_wp_error( $parsed ) ) return $parsed;
			}
		}

		// Strategy 2: JSON-LD structured data.
		if ( preg_match_all( '/<script\s+type="application\/ld\+json"[^>]*>(.*?)<\/script>/s', $html, $matches ) ) {
			foreach ( $matches[1] as $json_str ) {
				$ld = json_decode( $json_str, true );
				if ( $ld && ( ( $ld['@type'] ?? '' ) === 'Product' || ( $ld[0]['@type'] ?? '' ) === 'Product' ) ) {
					$this->log( 'parse', 'Found JSON-LD Product schema' );
					$product_ld = ( $ld['@type'] ?? '' ) === 'Product' ? $ld : $ld[0];
					$data['name']        = $product_ld['name'] ?? '';
					$data['description'] = $product_ld['description'] ?? '';
					$data['image_url']   = is_array( $product_ld['image'] ?? '' ) ? ( $product_ld['image'][0] ?? '' ) : ( $product_ld['image'] ?? '' );

					if ( isset( $product_ld['offers'] ) ) {
						$offers = $product_ld['offers'];
						if ( isset( $offers['lowPrice'] ) ) {
							$data['min_price'] = floatval( $offers['lowPrice'] );
						} elseif ( isset( $offers['price'] ) ) {
							$data['min_price'] = floatval( $offers['price'] );
						}
					}
					break;
				}
			}
		}

		// Strategy 3: Open Graph meta tags.
		if ( empty( $data['name'] ) ) {
			if ( preg_match( '/<meta\s+property="og:title"\s+content="([^"]+)"/i', $html, $m ) ) {
				$data['name'] = html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' );
			}
		}
		if ( empty( $data['description'] ) ) {
			if ( preg_match( '/<meta\s+property="og:description"\s+content="([^"]+)"/i', $html, $m ) ) {
				$data['description'] = html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' );
			}
		}
		if ( empty( $data['image_url'] ) ) {
			if ( preg_match( '/<meta\s+property="og:image"\s+content="([^"]+)"/i', $html, $m ) ) {
				$data['image_url'] = $m[1];
			}
		}

		// Strategy 4: HTML title fallback.
		if ( empty( $data['name'] ) ) {
			if ( preg_match( '/<title[^>]*>(.*?)<\/title>/si', $html, $m ) ) {
				$data['name'] = trim( html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' ) );
				// Strip site name suffix.
				$data['name'] = preg_replace( '/\s*[\|–—-]\s*[^|–—-]+$/', '', $data['name'] );
			}
		}

		// Strategy 5: Try to extract options from HTML select/radio elements.
		if ( empty( $data['variables'] ) ) {
			$data['variables'] = $this->parse_html_options( $html );
			if ( ! empty( $data['variables'] ) ) {
				$this->log( 'parse', 'Extracted ' . count( $data['variables'] ) . ' option groups from HTML forms' );
			}
		}

		if ( empty( $data['name'] ) ) {
			return new WP_Error( 'parse_failed', 'Could not extract product name from the page.' );
		}

		return $data;
	}

	/**
	 * Parse Next.js __NEXT_DATA__ for Axiomprint-style products.
	 */
	private function parse_nextjs_data( array $next_data, array $data ): array|WP_Error {
		// Navigate to product data in props.
		$page_props = $next_data['props']['pageProps'] ?? [];
		$product    = $page_props['product'] ?? $page_props['data'] ?? null;

		if ( ! $product ) {
			// Try deeper paths.
			foreach ( $page_props as $val ) {
				if ( is_array( $val ) && isset( $val['title'] ) && isset( $val['variables'] ) ) {
					$product = $val;
					break;
				}
			}
		}

		if ( ! $product ) {
			return new WP_Error( 'no_product', 'Could not find product data in __NEXT_DATA__.' );
		}

		$data['name']        = $product['title'] ?? $product['name'] ?? '';
		$data['description'] = $product['description'] ?? $product['short_description'] ?? '';
		$data['image_url']   = $product['image'] ?? $product['thumbnail'] ?? '';
		$data['formula']     = $product['formula'] ?? '';

		// Parse variables.
		$raw_variables = $product['variables'] ?? [];
		if ( is_array( $raw_variables ) ) {
			foreach ( $raw_variables as $rv ) {
				$var = [
					'slug'     => $this->slugify( $rv['title'] ?? '' ),
					'label'    => $rv['title'] ?? '',
					'var_type' => $this->map_variable_type( $rv['type'] ?? 'list' ),
					'config'   => [],
					'items'    => [],
					'hidden'   => ! empty( $rv['hidden'] ),
				];

				// Variable-level config.
				if ( isset( $rv['config'] ) && is_array( $rv['config'] ) ) {
					$var['config'] = $rv['config'];
				}

				// Parse items.
				$raw_items = $rv['items'] ?? [];
				if ( is_array( $raw_items ) ) {
					foreach ( $raw_items as $ri ) {
						$item = [
							'label'      => $ri['title'] ?? $ri['name'] ?? '',
							'value'      => floatval( $ri['value'] ?? 0 ),
							'base'       => floatval( $ri['base'] ?? 0 ),
							'is_default' => ! empty( $ri['isDefault'] ?? $ri['is_default'] ?? false ),
							'is_hidden'  => ! empty( $ri['isHidden'] ?? $ri['is_hidden'] ?? false ),
							'config'     => [],
							'filters'    => [],
						];

						// Item config.
						if ( isset( $ri['material'] ) ) {
							$item['config']['mass']  = $ri['material']['weight'] ?? '';
							$item['config']['thick'] = $ri['material']['thickness'] ?? '';
						}
						if ( isset( $ri['radius'] ) )  $item['config']['radius']    = $ri['radius'];
						if ( isset( $ri['corners'] ) )  $item['config']['corners']   = $ri['corners'];
						if ( isset( $ri['dayCount'] ) ) $item['config']['day_count'] = $ri['dayCount'];

						// Size: parse "W x H" from title.
						if ( $var['var_type'] === 'size' && preg_match( '/([\d.]+)\s*x\s*([\d.]+)/i', $item['label'], $sm ) ) {
							$item['config']['width']  = $sm[1];
							$item['config']['height'] = $sm[2];
							if ( $item['value'] == 0 ) {
								$item['value'] = floatval( $sm[1] ) * floatval( $sm[2] );
							}
						}

						// Parse filters.
						if ( isset( $ri['filters'] ) && is_array( $ri['filters'] ) ) {
							foreach ( $ri['filters'] as $rf ) {
								$item['filters'][] = [
									'variable_title' => $rf['variable_title'] ?? $rf['variableTitle'] ?? '',
									'item_title'     => $rf['item_title'] ?? $rf['itemTitle'] ?? '',
								];
							}
						}

						$var['items'][] = $item;
					}
				}

				$data['variables'][] = $var;
			}
		}

		return $data;
	}

	/**
	 * Parse Rednao WooCommerce Extra Product Options data (tgm-print.com style).
	 *
	 * The Rednao plugin embeds all product configuration as a JSON object in:
	 *   var ProductBuilderOptions_<varid> = { Options: { Rows: [...] }, Product: {...}, Attributes: [...] }
	 *
	 * Fields have: Id, Type, Label, Options (choices), Conditions (show/hide logic).
	 * Some products also use WooCommerce Attributes for size/quantity/paper options.
	 */
	private function parse_rednao_data( array $rednao, array $data, string $html ): array|WP_Error {
		// Extract product name from page — Rednao JSON doesn't have the product title.
		$data = $this->extract_page_meta( $html, $data );

		// Base price from Rednao Product object.
		$product_info = $rednao['Product'] ?? [];
		if ( ! empty( $product_info['Price'] ) ) {
			$data['min_price'] = floatval( $product_info['Price'] );
		}

		// Build a field ID → field map for condition resolution.
		$fields_by_id = [];
		$rows = $rednao['Options']['Rows'] ?? [];
		foreach ( $rows as $row ) {
			foreach ( $row['Columns'] ?? [] as $col ) {
				$field = $col['Field'] ?? null;
				if ( $field && isset( $field['Id'] ) ) {
					$fields_by_id[ $field['Id'] ] = $field;
				}
			}
		}

		// Parse fields into variables.
		$sort = 0;
		foreach ( $rows as $row ) {
			foreach ( $row['Columns'] ?? [] as $col ) {
				$field = $col['Field'] ?? null;
				if ( ! $field ) continue;

				$type  = $field['Type'] ?? '';
				$label = trim( $field['Label'] ?? '' );

				// Skip fields that don't produce option groups.
				if ( in_array( $type, [ 'fileupload', 'html', 'separator', 'heading' ], true ) ) {
					continue;
				}

				// For number/text fields without options, create a size-type variable.
				if ( in_array( $type, [ 'number', 'text' ], true ) && empty( $field['Options'] ) ) {
					if ( empty( $label ) ) continue;

					$var = [
						'slug'     => $this->slugify( $label ) . '_f' . $field['Id'],
						'label'    => $label,
						'var_type' => $this->map_rednao_field_type( $type, $label ),
						'config'   => [
							'rednao_field_id' => $field['Id'],
							'default'         => $field['DefaultText'] ?? '',
							'min'             => $field['MinimumValue'] ?? '',
							'max'             => $field['MaximumValue'] ?? '',
						],
						'items'    => [],
						'hidden'   => false,
					];

					// Convert conditions to hidden flag.
					$var['hidden'] = $this->rednao_field_is_conditionally_hidden( $field );

					$var['config']['sort_order'] = $sort++;
					$data['variables'][] = $var;
					continue;
				}

				// Skip option-less fields.
				$options = $field['Options'] ?? [];
				if ( empty( $options ) && $type !== 'range' ) continue;

				// Build label — if empty, try to derive from context.
				if ( empty( $label ) ) {
					// Use first option's label context or field ID.
					$label = 'Option ' . $field['Id'];
				}

				// Ensure unique slug by appending field ID.
				$slug = $this->slugify( $label ) . '_f' . $field['Id'];

				$var = [
					'slug'     => $slug,
					'label'    => $label,
					'var_type' => $this->map_rednao_field_type( $type, $label ),
					'config'   => [
						'rednao_field_id' => $field['Id'],
						'rednao_type'     => $type,
					],
					'items'    => [],
					'hidden'   => $this->rednao_field_is_conditionally_hidden( $field ),
				];

				// Range field — create min/max items.
				if ( $type === 'range' ) {
					$var['config']['min'] = $field['MinimumValue'] ?? 0;
					$var['config']['max'] = $field['MaximumValue'] ?? 100;
					$var['config']['default'] = $field['DefaultText'] ?? '';
					$var['config']['sort_order'] = $sort++;
					$data['variables'][] = $var;
					continue;
				}

				// Parse options into items.
				foreach ( $options as $opt_idx => $opt ) {
					$opt_label = trim( $opt['Label'] ?? '' );
					if ( empty( $opt_label ) ) continue;

					$price = $opt['RegularPrice'] ?? '';
					$sale  = $opt['SalePrice'] ?? '';

					$item = [
						'label'      => $opt_label,
						'value'      => is_numeric( $price ) ? floatval( $price ) : 0,
						'base'       => is_numeric( $sale ) && floatval( $sale ) > 0 ? floatval( $sale ) : 0,
						'is_default' => ! empty( $opt['Selected'] ),
						'is_hidden'  => false,
						'config'     => [
							'rednao_option_id' => $opt['Id'] ?? $opt_idx,
							'price_type'       => $opt['PriceType'] ?? 'fixed_amount',
						],
						'filters'    => [],
					];

					// For image pickers, store the image URL.
					if ( ! empty( $opt['URL'] ) && ( $opt['ImageType'] ?? '' ) !== 'none' ) {
						$item['config']['image_url'] = $opt['URL'];
					}

					// Parse size from label (e.g., "3.5 x 2", "8.5\" x 11\"").
					if ( $var['var_type'] === 'size' && preg_match( '/([\d.]+)\s*"?\s*x\s*([\d.]+)/i', $opt_label, $sm ) ) {
						$item['config']['width']  = $sm[1];
						$item['config']['height'] = $sm[2];
						if ( $item['value'] == 0 ) {
							$item['value'] = floatval( $sm[1] ) * floatval( $sm[2] );
						}
					}

					$var['items'][] = $item;
				}

				if ( ! empty( $var['items'] ) ) {
					$var['config']['sort_order'] = $sort++;
					$data['variables'][] = $var;
				}
			}
		}

		// Parse WooCommerce Attributes (used by variable products like brochures).
		$attributes = $rednao['Attributes'] ?? [];
		if ( ! empty( $attributes ) && is_array( $attributes ) ) {
			$this->log( 'parse', 'Found ' . count( $attributes ) . ' WooCommerce product attributes' );

			foreach ( $attributes as $attr ) {
				$attr_name    = $attr['Name'] ?? '';
				$attr_options = $attr['Options'] ?? [];
				if ( empty( $attr_name ) || empty( $attr_options ) ) continue;

				$slug = $this->slugify( $attr_name ) . '_attr';

				$var = [
					'slug'     => $slug,
					'label'    => $attr_name,
					'var_type' => $this->map_rednao_attribute_type( $attr_name ),
					'config'   => [ 'wc_attribute' => $attr['Id'] ?? '' ],
					'items'    => [],
					'hidden'   => false,
				];

				foreach ( $attr_options as $idx => $opt_text ) {
					$opt_text = trim( $opt_text );
					if ( empty( $opt_text ) ) continue;

					$item = [
						'label'      => $opt_text,
						'value'      => 0,
						'base'       => 0,
						'is_default' => $idx === 0,
						'is_hidden'  => false,
						'config'     => [],
						'filters'    => [],
					];

					// Extract per-unit price from quantity labels like "250 ( 0.56/unit )".
					if ( preg_match( '/^(\d+)\s*\(\s*([\d.]+)\s*\/\s*unit\s*\)/i', $opt_text, $qm ) ) {
						$item['value'] = floatval( $qm[1] ); // quantity
						$item['base']  = floatval( $qm[2] ); // per-unit price
						$item['config']['quantity'] = intval( $qm[1] );
						$item['config']['unit_price'] = floatval( $qm[2] );
					}

					// Parse size dimensions.
					if ( $var['var_type'] === 'size' && preg_match( '/([\d.]+)\s*"?\s*x\s*([\d.]+)/i', $opt_text, $sm ) ) {
						$item['config']['width']  = $sm[1];
						$item['config']['height'] = $sm[2];
						$item['value'] = floatval( $sm[1] ) * floatval( $sm[2] );
					}

					$var['items'][] = $item;
				}

				if ( ! empty( $var['items'] ) ) {
					$var['config']['sort_order'] = $sort++;
					$data['variables'][] = $var;
				}
			}
		}

		// Build cross-field filters from Rednao Conditions.
		$this->build_rednao_filters( $data['variables'], $fields_by_id );

		if ( empty( $data['variables'] ) ) {
			return new WP_Error( 'no_options', 'No product options found in Rednao data.' );
		}

		$this->log( 'parse', 'Parsed ' . count( $data['variables'] ) . ' option groups from Rednao configurator' );

		return $data;
	}

	/**
	 * Extract product name, description, and image from HTML meta tags.
	 */
	private function extract_page_meta( string $html, array $data ): array {
		// JSON-LD.
		if ( preg_match_all( '/<script\s+type="application\/ld\+json"[^>]*>(.*?)<\/script>/s', $html, $matches ) ) {
			foreach ( $matches[1] as $json_str ) {
				$ld = json_decode( $json_str, true );
				$product_ld = null;
				if ( $ld && ( $ld['@type'] ?? '' ) === 'Product' ) {
					$product_ld = $ld;
				} elseif ( $ld && is_array( $ld ) && ( $ld[0]['@type'] ?? '' ) === 'Product' ) {
					$product_ld = $ld[0];
				}
				if ( $product_ld ) {
					$data['name']        = $product_ld['name'] ?? '';
					$data['description'] = $product_ld['description'] ?? '';
					$data['image_url']   = is_array( $product_ld['image'] ?? '' )
						? ( $product_ld['image'][0] ?? '' )
						: ( $product_ld['image'] ?? '' );
					if ( isset( $product_ld['offers'] ) ) {
						$offers = $product_ld['offers'];
						if ( isset( $offers['lowPrice'] ) ) {
							$data['min_price'] = floatval( $offers['lowPrice'] );
						} elseif ( isset( $offers['price'] ) ) {
							$data['min_price'] = floatval( $offers['price'] );
						}
					}
					break;
				}
			}
		}

		// Open Graph fallback.
		if ( empty( $data['name'] ) && preg_match( '/<meta\s+property="og:title"\s+content="([^"]+)"/i', $html, $m ) ) {
			$data['name'] = html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' );
		}
		if ( empty( $data['description'] ) && preg_match( '/<meta\s+property="og:description"\s+content="([^"]+)"/i', $html, $m ) ) {
			$data['description'] = html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' );
		}
		if ( empty( $data['image_url'] ) && preg_match( '/<meta\s+property="og:image"\s+content="([^"]+)"/i', $html, $m ) ) {
			$data['image_url'] = $m[1];
		}

		// Title tag fallback.
		if ( empty( $data['name'] ) && preg_match( '/<title[^>]*>(.*?)<\/title>/si', $html, $m ) ) {
			$data['name'] = trim( html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' ) );
			$data['name'] = preg_replace( '/\s*[\|–—-]\s*[^|–—-]+$/', '', $data['name'] );
		}

		return $data;
	}

	/**
	 * Build cross-field dependency filters from Rednao ShowHide conditions.
	 *
	 * Rednao conditions specify: "Show/Hide this field when Field X contains option IDs [1,3]"
	 * We convert these to sqft filter format: item-level filters that reference other variables.
	 */
	private function build_rednao_filters( array &$variables, array $fields_by_id ): void {
		// Build lookup maps: rednao_field_id → variable index, option_id → item index.
		$field_to_var_idx = [];
		$option_to_item   = []; // "field_id::option_id" => [ var_idx, item_idx ]

		foreach ( $variables as $v_idx => $var ) {
			$fid = $var['config']['rednao_field_id'] ?? null;
			if ( $fid !== null ) {
				$field_to_var_idx[ $fid ] = $v_idx;
			}
			foreach ( $var['items'] as $i_idx => $item ) {
				$oid = $item['config']['rednao_option_id'] ?? null;
				if ( $fid !== null && $oid !== null ) {
					$option_to_item[ $fid . '::' . $oid ] = [ $v_idx, $i_idx ];
				}
			}
		}

		// Now iterate fields_by_id to find conditions and create filters.
		foreach ( $fields_by_id as $fid => $field ) {
			$conditions = $field['Conditions'] ?? [];
			if ( empty( $conditions ) ) continue;

			// Find the variable index for this field.
			$target_var_idx = $field_to_var_idx[ $fid ] ?? null;
			if ( $target_var_idx === null ) continue;

			foreach ( $conditions as $cond ) {
				if ( ( $cond['Type'] ?? '' ) !== 'ShowHide' ) continue;

				$show_when_true = $cond['ShowWhenTrue'] ?? true;

				foreach ( $cond['ConditionGroups'] ?? [] as $group ) {
					foreach ( $group['ConditionLines'] ?? [] as $line ) {
						$dep_field_id = intval( $line['FieldId'] ?? 0 );
						$comparison   = $line['Comparison'] ?? '';
						$values       = $line['Value'] ?? [];

						if ( ! $dep_field_id || empty( $values ) ) continue;

						// Find the source variable.
						$source_var_idx = $field_to_var_idx[ $dep_field_id ] ?? null;
						if ( $source_var_idx === null ) continue;

						$source_var = $variables[ $source_var_idx ];

						// For "ShowWhenTrue + Contains": this field shows when dep field has these values.
						// For "ShowWhenFalse + Contains": this field hides when dep field has these values.
						// We create filters on each item of the target variable, pointing to the
						// source variable items that make this field visible.

						$visible_option_ids = [];
						if ( $show_when_true && $comparison === 'Contains' ) {
							// Show when source contains these IDs → these are the enabling options.
							$visible_option_ids = $values;
						} elseif ( ! $show_when_true && $comparison === 'Contains' ) {
							// Hide when source contains these IDs → visible for all OTHER options.
							$all_source_option_ids = [];
							foreach ( $source_var['items'] as $si ) {
								$all_source_option_ids[] = $si['config']['rednao_option_id'] ?? null;
							}
							$visible_option_ids = array_diff( array_filter( $all_source_option_ids ), $values );
						}

						if ( empty( $visible_option_ids ) ) continue;

						// Add filters to each item in the target variable.
						foreach ( $variables[ $target_var_idx ]['items'] as $ti_idx => &$target_item ) {
							foreach ( $visible_option_ids as $src_opt_id ) {
								// Find the source item's label.
								$src_key = $dep_field_id . '::' . $src_opt_id;
								if ( ! isset( $option_to_item[ $src_key ] ) ) continue;

								[ $src_v_idx, $src_i_idx ] = $option_to_item[ $src_key ];
								$src_var  = $variables[ $src_v_idx ];
								$src_item = $src_var['items'][ $src_i_idx ];

								$target_item['filters'][] = [
									'variable_title' => $src_var['label'],
									'variable_slug'  => $src_var['slug'] ?? '',
									'item_title'     => $src_item['label'],
								];
							}
						}
						unset( $target_item );
					}
				}
			}
		}
	}

	/**
	 * Check if a Rednao field is conditionally hidden by default.
	 */
	private function rednao_field_is_conditionally_hidden( array $field ): bool {
		$conditions = $field['Conditions'] ?? [];
		foreach ( $conditions as $cond ) {
			if ( ( $cond['Type'] ?? '' ) === 'ShowHide' ) {
				// If ShowWhenTrue=true, field is hidden by default (needs condition to show).
				// If ShowWhenTrue=false, field is visible by default (hidden when condition met).
				return ! empty( $cond['ShowWhenTrue'] );
			}
		}
		return false;
	}

	/**
	 * Map Rednao field types to sqft-pricing variable types.
	 */
	private function map_rednao_field_type( string $type, string $label = '' ): string {
		$label_lower = strtolower( $label );

		// Infer from label first.
		if ( preg_match( '/\bsize\b/i', $label ) )                            return 'size';
		if ( preg_match( '/\bquantity\b/i', $label ) )                        return 'quantity_tiers';
		if ( preg_match( '/\bturnaround\b|\bproduction\b/i', $label ) )       return 'turnaround';
		if ( preg_match( '/\bmaterial\b|\bpaper\s*stock\b|\bthick/i', $label ) ) return 'material_card';
		if ( preg_match( '/\bshape\b/i', $label ) )                           return 'card';
		if ( preg_match( '/\bwidth\b|\bheight\b/i', $label ) )                return 'size';

		// Then from Rednao field type.
		return match ( $type ) {
			'imagepicker'          => 'card',
			'radio'                => 'radio',
			'dropdown'             => 'list',
			'searchabledropdown'   => 'list',
			'number'               => 'list',
			'range'                => 'list',
			default                => 'list',
		};
	}

	/**
	 * Map WooCommerce attribute names to sqft-pricing variable types.
	 */
	private function map_rednao_attribute_type( string $name ): string {
		$lower = strtolower( $name );
		if ( str_contains( $lower, 'size' ) )          return 'size';
		if ( str_contains( $lower, 'quantity' ) )      return 'quantity_tiers';
		if ( str_contains( $lower, 'paper' ) )         return 'material_card';
		if ( str_contains( $lower, 'thickness' ) )     return 'material_card';
		if ( str_contains( $lower, 'material' ) )      return 'material_card';
		if ( str_contains( $lower, 'turnaround' ) )    return 'turnaround';
		return 'list';
	}

	/**
	 * Parse HTML select/radio/checkbox elements for product options.
	 */
	private function parse_html_options( string $html ): array {
		$variables = [];

		// Find labeled select elements.
		if ( preg_match_all( '/<label[^>]*>([^<]+)<\/label>\s*<select[^>]*name="([^"]*)"[^>]*>(.*?)<\/select>/si', $html, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $m ) {
				$var = [
					'slug'     => $this->slugify( $m[1] ),
					'label'    => trim( $m[1] ),
					'var_type' => 'list',
					'config'   => [],
					'items'    => [],
				];

				if ( preg_match_all( '/<option[^>]*value="([^"]*)"[^>]*>(.*?)<\/option>/si', $m[3], $opts, PREG_SET_ORDER ) ) {
					foreach ( $opts as $idx => $opt ) {
						$var['items'][] = [
							'label'      => trim( strip_tags( $opt[2] ) ),
							'value'      => is_numeric( $opt[1] ) ? floatval( $opt[1] ) : 0,
							'base'       => 0,
							'is_default' => $idx === 0,
							'is_hidden'  => false,
							'config'     => [],
							'filters'    => [],
						];
					}
				}

				if ( ! empty( $var['items'] ) ) {
					$variables[] = $var;
				}
			}
		}

		// Find radio groups.
		if ( preg_match_all( '/<fieldset[^>]*>(.*?)<\/fieldset>/si', $html, $fieldsets ) ) {
			foreach ( $fieldsets[1] as $fs ) {
				$label = '';
				if ( preg_match( '/<legend[^>]*>(.*?)<\/legend>/si', $fs, $lm ) ) {
					$label = trim( strip_tags( $lm[1] ) );
				}
				if ( empty( $label ) ) continue;

				$var = [
					'slug'     => $this->slugify( $label ),
					'label'    => $label,
					'var_type' => 'radio',
					'config'   => [],
					'items'    => [],
				];

				if ( preg_match_all( '/<input[^>]*type="radio"[^>]*value="([^"]*)"[^>]*>\s*<label[^>]*>(.*?)<\/label>/si', $fs, $radios, PREG_SET_ORDER ) ) {
					foreach ( $radios as $idx => $r ) {
						$var['items'][] = [
							'label'      => trim( strip_tags( $r[2] ) ),
							'value'      => is_numeric( $r[1] ) ? floatval( $r[1] ) : 0,
							'base'       => 0,
							'is_default' => $idx === 0,
							'is_hidden'  => false,
							'config'     => [],
							'filters'    => [],
						];
					}
				}

				if ( ! empty( $var['items'] ) ) {
					$variables[] = $var;
				}
			}
		}

		return $variables;
	}

	/**
	 * Create the WooCommerce product.
	 */
	private function create_wc_product( array $data ): int|WP_Error {
		$product_id = wp_insert_post( [
			'post_title'   => sanitize_text_field( $data['name'] ),
			'post_content' => wp_kses_post( $data['description'] ),
			'post_excerpt' => wp_trim_words( wp_strip_all_tags( $data['description'] ), 30 ),
			'post_status'  => 'publish',
			'post_type'    => 'product',
		] );

		if ( is_wp_error( $product_id ) || ! $product_id ) {
			return new WP_Error( 'create_failed', 'Failed to create WooCommerce product.' );
		}

		wp_set_object_terms( $product_id, 'simple', 'product_type' );
		update_post_meta( $product_id, '_regular_price', '0' );
		update_post_meta( $product_id, '_price', '0' );
		update_post_meta( $product_id, '_stock_status', 'instock' );
		update_post_meta( $product_id, '_manage_stock', 'no' );
		update_post_meta( $product_id, '_sqft_calculator_enabled', '1' );
		update_post_meta( $product_id, '_sqft_min_price', $data['min_price'] ?? 0 );
		update_post_meta( $product_id, '_sqft_source_url', esc_url_raw( $data['source_url'] ) );

		// Download and attach product image.
		if ( ! empty( $data['image_url'] ) ) {
			$image_id = $this->sideload_image( $data['image_url'], $product_id );
			if ( $image_id && ! is_wp_error( $image_id ) ) {
				set_post_thumbnail( $product_id, $image_id );
				$this->log( 'create', 'Product image attached' );
			}
		}

		return $product_id;
	}

	/**
	 * Create calculator options from parsed data.
	 */
	private function create_calculator_options( int $product_id, array $data ): array {
		if ( ! class_exists( 'Sqft_Product_Options' ) ) {
			return [ 'variables' => 0, 'items' => 0, 'filters' => 0 ];
		}

		global $wpdb;
		$var_table    = $wpdb->prefix . 'sqft_variables';
		$item_table   = $wpdb->prefix . 'sqft_variable_items';
		$filter_table = $wpdb->prefix . 'sqft_item_filters';

		// Delete existing config.
		Sqft_Product_Options::delete_product_config( $product_id );

		$var_count    = 0;
		$item_count   = 0;
		$filter_count = 0;

		// First pass: create variables and items, build ID maps.
		$var_slug_to_id  = [];
		$item_label_map  = []; // "var_slug::item_label" => item_id

		foreach ( $data['variables'] as $v_order => $var ) {
			$wpdb->insert( $var_table, [
				'product_id' => $product_id,
				'slug'       => $var['slug'],
				'label'      => $var['label'],
				'var_type'   => $var['var_type'],
				'sort_order' => $v_order,
				'config'     => wp_json_encode( array_merge(
					$var['config'] ?? [],
					! empty( $var['hidden'] ) ? [ 'hidden' => '1' ] : []
				) ),
			] );
			$var_id = (int) $wpdb->insert_id;
			$var_slug_to_id[ $var['slug'] ] = $var_id;
			$var_count++;

			$this->log( 'options', "  [{$v_order}] {$var['label']} — " . count( $var['items'] ) . " choices" );

			foreach ( $var['items'] as $i_order => $item ) {
				$wpdb->insert( $item_table, [
					'variable_id'   => $var_id,
					'label'         => $item['label'],
					'value_numeric' => $item['value'],
					'base_cost'     => $item['base'],
					'is_default'    => $item['is_default'] ? 1 : 0,
					'is_hidden'     => $item['is_hidden'] ? 1 : 0,
					'sort_order'    => $i_order,
					'config'        => wp_json_encode( $item['config'] ?? [] ),
				] );
				$item_id = (int) $wpdb->insert_id;
				$item_label_map[ $var['slug'] . '::' . $item['label'] ] = $item_id;
				$item_count++;
			}
		}

		// Second pass: create filters using the ID maps.
		foreach ( $data['variables'] as $var ) {
			$var_id = $var_slug_to_id[ $var['slug'] ] ?? 0;
			if ( ! $var_id ) continue;

			foreach ( $var['items'] as $item ) {
				$item_id = $item_label_map[ $var['slug'] . '::' . $item['label'] ] ?? 0;
				if ( ! $item_id ) continue;

				foreach ( $item['filters'] ?? [] as $filter ) {
					// Resolve filter target by title or slug.
					$dep_var_title  = $filter['variable_title'] ?? '';
					$dep_item_title = $filter['item_title'] ?? '';
					// Prefer explicit slug (from Rednao imports), fall back to slugified title.
					$dep_var_slug   = ! empty( $filter['variable_slug'] )
						? $filter['variable_slug']
						: $this->slugify( $dep_var_title );
					$dep_var_id     = $var_slug_to_id[ $dep_var_slug ] ?? 0;
					$dep_item_id    = $item_label_map[ $dep_var_slug . '::' . $dep_item_title ] ?? 0;

					if ( $dep_var_id && $dep_item_id ) {
						$wpdb->insert( $filter_table, [
							'item_id'                => $item_id,
							'depends_on_variable_id' => $dep_var_id,
							'depends_on_item_id'     => $dep_item_id,
						] );
						$filter_count++;
					}
				}
			}
		}

		return [ 'variables' => $var_count, 'items' => $item_count, 'filters' => $filter_count ];
	}

	/**
	 * Download an image and attach to a post.
	 */
	private function sideload_image( string $url, int $post_id ): int|WP_Error {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = download_url( $url, 15 );
		if ( is_wp_error( $tmp ) ) return $tmp;

		$filename = basename( wp_parse_url( $url, PHP_URL_PATH ) ) ?: 'product-image.jpg';
		$file_array = [
			'name'     => sanitize_file_name( $filename ),
			'tmp_name' => $tmp,
		];

		$attachment_id = media_handle_sideload( $file_array, $post_id );
		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $tmp );
		}
		return $attachment_id;
	}

	/**
	 * Map external variable type names to sqft-pricing types.
	 */
	private function map_variable_type( string $type ): string {
		return match ( strtolower( $type ) ) {
			'shape_list', 'radius_list', 'card', 'next3' => 'card',
			'material_list', 'top_photo'                  => 'material_card',
			'size_new', 'size'                             => 'size',
			'quantity_list', 'quantity'                     => 'quantity_tiers',
			'turnaround'                                   => 'turnaround',
			'radio', 'next'                                => 'radio',
			'pill'                                         => 'pill',
			default                                        => 'list',
		};
	}

	/**
	 * Create a slug from a label.
	 */
	private function slugify( string $text ): string {
		$slug = strtolower( trim( $text ) );
		$slug = preg_replace( '/[^a-z0-9]+/', '_', $slug );
		$slug = trim( $slug, '_' );
		return $slug ?: 'option';
	}

	/**
	 * Add a step to the log.
	 */
	private function log( string $status, string $message ): void {
		$this->steps[] = [ 'status' => $status, 'message' => $message ];
	}
}
