<?php
/**
 * Symbiotic Theme — Admin Settings Page
 * Appearance > Symbiotic Theme
 */
defined( 'ABSPATH' ) || exit;

class Symbiotic_Admin {

	const OPTION_KEY = 'symbiotic_theme_options';

	public static function init(): void {
		add_action( 'admin_menu',            [ __CLASS__, 'add_menu' ] );
		add_action( 'admin_init',            [ __CLASS__, 'register_settings' ] );
		add_action( 'admin_init',            [ __CLASS__, 'handle_reset' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
	}

	// -------------------------------------------------------------------------
	// Menu
	// -------------------------------------------------------------------------
	public static function add_menu(): void {
		add_theme_page(
			__( 'Symbiotic Theme', 'symbiotic-theme' ),
			__( 'Symbiotic Theme', 'symbiotic-theme' ),
			'manage_options',
			'symbiotic-theme',
			[ __CLASS__, 'render_page' ]
		);
	}

	// -------------------------------------------------------------------------
	// Settings
	// -------------------------------------------------------------------------
	public static function register_settings(): void {
		register_setting( self::OPTION_KEY, self::OPTION_KEY, [
			'sanitize_callback' => [ __CLASS__, 'sanitize' ],
		] );
	}

	public static function handle_reset(): void {
		if (
			isset( $_GET['sym_reset'], $_GET['_wpnonce'] ) &&
			wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'sym_reset' ) &&
			current_user_can( 'manage_options' )
		) {
			delete_option( self::OPTION_KEY );
			wp_safe_redirect( admin_url( 'themes.php?page=symbiotic-theme&settings-updated=1' ) );
			exit;
		}
	}

	// -------------------------------------------------------------------------
	// Defaults & Options
	// -------------------------------------------------------------------------
	public static function get_defaults(): array {
		return [
			// Theme mode
			'theme_mode'        => 'dark', // 'dark' or 'light'
			// Colors (dark mode defaults)
			'color_bg'          => '#1a1b1f',
			'color_surface'     => '#222328',
			'color_surface_2'   => '#2b2c31',
			'color_text'        => '#e8e8e8',
			'color_text_muted'  => '#888888',
			'color_primary'     => '#9d33d6',
			'color_bot_bubble'  => '#282830',
			// Layout
			'left_max_width'    => '680',
			'right_width'       => '380',
			'border_radius'     => '12',
			// Chat identity
			'bot_name'          => '',
			'bot_avatar_url'    => '',
			'placeholder_text'  => 'Ask about products, prices, availability...',
			'welcome_override'  => '',
			'input_hint'        => '',
			// Typography
			'font_family'       => 'Outfit',
			'base_font_size'    => '15',
			// Layout modes
			'layout_fullwidth'    => '0',
			'show_product_panel'  => '1',
			'show_right_sidebar'  => '0',
			'right_sidebar_width' => '240',
			// Homepage content
			'hero_title'        => 'Printing Solutions,<br/>Powered by AI',
			'hero_subtitle'     => 'Business cards, signage, marketing materials, and more. Our AI advisor helps you choose the perfect product, paper, and finish.',
			'hero_image_url'    => '',
			'promo_title'       => 'Premium Business Cards',
			'promo_text'        => 'Starting at $19.99 — 30+ paper stocks, 10+ finishes, AI-guided selection',
			'promo_image_url'   => '',
			'promo_cta_text'    => 'Explore',
			'promo_cta_query'   => 'Show me premium business cards',
			// Advanced
			'custom_css'        => '',
		];
	}

	public static function get_options(): array {
		return wp_parse_args(
			(array) get_option( self::OPTION_KEY, [] ),
			self::get_defaults()
		);
	}

	// -------------------------------------------------------------------------
	// Sanitize
	// -------------------------------------------------------------------------
	public static function sanitize( array $input ): array {
		$defaults = self::get_defaults();
		$clean    = [];

		foreach ( [ 'color_bg', 'color_surface', 'color_surface_2', 'color_text', 'color_text_muted', 'color_primary', 'color_bot_bubble' ] as $key ) {
			$clean[ $key ] = sanitize_hex_color( $input[ $key ] ?? '' ) ?: $defaults[ $key ];
		}

		foreach ( [ 'left_max_width', 'right_width', 'border_radius', 'base_font_size', 'right_sidebar_width' ] as $key ) {
			$clean[ $key ] = (string) absint( $input[ $key ] ?? $defaults[ $key ] );
		}

		foreach ( [ 'bot_name', 'placeholder_text', 'welcome_override', 'input_hint', 'font_family' ] as $key ) {
			$clean[ $key ] = sanitize_text_field( $input[ $key ] ?? $defaults[ $key ] );
		}

		$clean['bot_avatar_url']     = esc_url_raw( $input['bot_avatar_url'] ?? '' );
		$clean['theme_mode']         = in_array( $input['theme_mode'] ?? 'dark', [ 'dark', 'light' ], true ) ? $input['theme_mode'] : 'dark';
		$clean['layout_fullwidth']   = empty( $input['layout_fullwidth'] )   ? '0' : '1';
		$clean['show_product_panel'] = empty( $input['show_product_panel'] ) ? '0' : '1';
		$clean['show_right_sidebar'] = empty( $input['show_right_sidebar'] ) ? '0' : '1';

		// Homepage content
		$clean['hero_title']       = wp_kses_post( $input['hero_title'] ?? $defaults['hero_title'] );
		$clean['hero_subtitle']    = sanitize_text_field( $input['hero_subtitle'] ?? $defaults['hero_subtitle'] );
		$clean['hero_image_url']   = esc_url_raw( $input['hero_image_url'] ?? '' );
		$clean['promo_title']      = sanitize_text_field( $input['promo_title'] ?? $defaults['promo_title'] );
		$clean['promo_text']       = sanitize_text_field( $input['promo_text'] ?? $defaults['promo_text'] );
		$clean['promo_image_url']  = esc_url_raw( $input['promo_image_url'] ?? '' );
		$clean['promo_cta_text']   = sanitize_text_field( $input['promo_cta_text'] ?? $defaults['promo_cta_text'] );
		$clean['promo_cta_query']  = sanitize_text_field( $input['promo_cta_query'] ?? $defaults['promo_cta_query'] );

		$clean['custom_css']       = wp_strip_all_tags( $input['custom_css'] ?? '' );

		return $clean;
	}

	// -------------------------------------------------------------------------
	// Assets
	// -------------------------------------------------------------------------
	public static function enqueue_assets( string $hook ): void {
		if ( $hook !== 'appearance_page_symbiotic-theme' ) {
			return;
		}
		add_action( 'admin_head',   [ __CLASS__, 'print_admin_styles' ] );
		add_action( 'admin_footer', [ __CLASS__, 'print_admin_scripts' ] );
	}

	public static function print_admin_styles(): void {
		echo '<style>' . self::admin_css() . '</style>'; // phpcs:ignore
	}

	public static function print_admin_scripts(): void {
		$opts = self::get_options();
		$json = wp_json_encode( [
			'color_bg'         => $opts['color_bg'],
			'color_surface'    => $opts['color_surface'],
			'color_surface_2'  => $opts['color_surface_2'],
			'color_primary'    => $opts['color_primary'],
			'color_bot_bubble' => $opts['color_bot_bubble'],
			'color_text'       => $opts['color_text'],
			'color_text_muted' => $opts['color_text_muted'],
			'left_max_width'   => $opts['left_max_width'],
			'right_width'      => $opts['right_width'],
		] );
		echo '<script>window.symAdminOpts=' . $json . ';</script>'; // phpcs:ignore
		echo '<script>' . self::admin_js() . '</script>'; // phpcs:ignore
	}

	// -------------------------------------------------------------------------
	// Render page
	// -------------------------------------------------------------------------
	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// ── First-run setup wizard ──
		if ( self::needs_setup() && ! isset( $_GET['skip-wizard'] ) ) {
			self::render_setup_wizard();
			return;
		}

		$opts  = self::get_options();
		$saved = isset( $_GET['settings-updated'] );
		?>
		<div class="sym-admin-wrap">

			<!-- Header -->
			<div class="sym-admin-header">
				<div class="sym-admin-title">
					<div class="sym-admin-logo">
						<svg width="22" height="22" viewBox="0 0 22 22" fill="none">
							<circle cx="11" cy="11" r="10" fill="#9d33d6"/>
							<path d="M6.5 11.5C7.4 9 9.1 7.8 11 7.8C12.9 7.8 14.6 9 15.5 11.5" stroke="#1a1b1f" stroke-width="1.4" stroke-linecap="round"/>
							<circle cx="11" cy="13.5" r="2.2" fill="#1a1b1f"/>
						</svg>
					</div>
					<div>
						<h1><?php esc_html_e( 'Symbiotic Theme', 'symbiotic-theme' ); ?></h1>
						<p><?php esc_html_e( 'AI-first shopping interface — appearance, persona & knowledge', 'symbiotic-theme' ); ?></p>
					</div>
				</div>
				<div class="sym-admin-header-right">
					<?php if ( $saved ) : ?>
						<span class="sym-saved-badge">✓ <?php esc_html_e( 'Saved', 'symbiotic-theme' ); ?></span>
					<?php endif; ?>
					<a href="<?php echo esc_url( home_url() ); ?>" target="_blank" class="sym-header-link">
						↗ <?php esc_html_e( 'Preview Site', 'symbiotic-theme' ); ?>
					</a>
				</div>
			</div>

			<!-- Body -->
			<div class="sym-admin-body">
				<!-- Tabs -->
				<nav class="sym-tabs">
					<button class="sym-tab sym-tab--active" data-tab="colors">
						<svg width="14" height="14" fill="none" viewBox="0 0 14 14"><circle cx="4" cy="4" r="2.5" fill="currentColor"/><circle cx="10" cy="4" r="2.5" fill="currentColor" opacity=".5"/><circle cx="4" cy="10" r="2.5" fill="currentColor" opacity=".5"/><circle cx="10" cy="10" r="2.5" fill="currentColor" opacity=".3"/></svg>
						<?php esc_html_e( 'Colors', 'symbiotic-theme' ); ?>
					</button>
					<button class="sym-tab" data-tab="layout">
						<svg width="14" height="14" fill="none" viewBox="0 0 14 14"><rect x="1" y="1" width="5" height="12" rx="1" fill="currentColor"/><rect x="8" y="1" width="5" height="12" rx="1" fill="currentColor" opacity=".5"/></svg>
						<?php esc_html_e( 'Layout', 'symbiotic-theme' ); ?>
					</button>
					<button class="sym-tab" data-tab="chat">
						<svg width="14" height="14" fill="none" viewBox="0 0 14 14"><path d="M1 2.5A1.5 1.5 0 0 1 2.5 1h9A1.5 1.5 0 0 1 13 2.5v7A1.5 1.5 0 0 1 11.5 11H5l-4 2V2.5Z" fill="currentColor"/></svg>
						<?php esc_html_e( 'Chat', 'symbiotic-theme' ); ?>
					</button>
					<button class="sym-tab" data-tab="typography">
						<svg width="14" height="14" fill="none" viewBox="0 0 14 14"><text x="1" y="12" font-size="12" fill="currentColor" font-family="serif" font-weight="bold">T</text></svg>
						<?php esc_html_e( 'Typography', 'symbiotic-theme' ); ?>
					</button>
					<button class="sym-tab" data-tab="homepage">
						<svg width="14" height="14" fill="none" viewBox="0 0 14 14"><path d="M3 7l4-5 4 5M3 7v5h8V7" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
						<?php esc_html_e( 'Homepage', 'symbiotic-theme' ); ?>
					</button>
					<button class="sym-tab" data-tab="advanced">
						<svg width="14" height="14" fill="none" viewBox="0 0 14 14"><path d="M2 4h10M2 7h7M2 10h4" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
						<?php esc_html_e( 'Advanced', 'symbiotic-theme' ); ?>
					</button>
					<span class="sym-tab-separator"></span>
					<button class="sym-tab" data-tab="ai-provider">
						<svg width="14" height="14" fill="none" viewBox="0 0 14 14"><path d="M7 1v4M7 9v4M1 7h4M9 7h4" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/><circle cx="7" cy="7" r="2" stroke="currentColor" stroke-width="1.2"/></svg>
						<?php esc_html_e( 'AI Provider', 'symbiotic-theme' ); ?>
					</button>
					<button class="sym-tab" data-tab="ai-persona">
						<svg width="14" height="14" fill="none" viewBox="0 0 14 14"><circle cx="7" cy="5" r="3" stroke="currentColor" stroke-width="1.2"/><path d="M2 13c0-2.8 2.2-5 5-5s5 2.2 5 5" stroke="currentColor" stroke-width="1.2"/></svg>
						<?php esc_html_e( 'AI Persona', 'symbiotic-theme' ); ?>
					</button>
					<button class="sym-tab" data-tab="brand-knowledge">
						<svg width="14" height="14" fill="none" viewBox="0 0 14 14"><path d="M2 2h10v10H2z" stroke="currentColor" stroke-width="1.2"/><path d="M5 5h4M5 7h4M5 9h2" stroke="currentColor" stroke-width="1" stroke-linecap="round"/></svg>
						<?php esc_html_e( 'Brand Knowledge', 'symbiotic-theme' ); ?>
					</button>
					<button class="sym-tab" data-tab="language">
						<svg width="14" height="14" fill="none" viewBox="0 0 14 14"><circle cx="7" cy="7" r="5.5" stroke="currentColor" stroke-width="1.2"/><path d="M2 7h10M7 1.5c-1.5 1.5-2 3.5-2 5.5s.5 4 2 5.5M7 1.5c1.5 1.5 2 3.5 2 5.5s-.5 4-2 5.5" stroke="currentColor" stroke-width="1"/></svg>
						<?php esc_html_e( 'Language', 'symbiotic-theme' ); ?>
					</button>
					<?php if ( class_exists( 'WooCommerce' ) ) : ?>
					<span class="sym-tab-separator"></span>
					<button class="sym-tab" data-tab="shop">
						<svg width="14" height="14" fill="none" viewBox="0 0 14 14"><path d="M1 3l1.5-2h9L13 3M1 3v9a1 1 0 001 1h10a1 1 0 001-1V3M1 3h12M5 6h4" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
						<?php esc_html_e( 'Shop', 'symbiotic-theme' ); ?>
					</button>
					<?php endif; ?>
				</nav>

				<form method="post" action="options.php" class="sym-admin-form">
					<?php settings_fields( self::OPTION_KEY ); ?>

					<!-- ===== COLORS ===== -->
					<div class="sym-tab-panel sym-tab-panel--active" data-tab="colors">
						<div class="sym-two-col">
							<div class="sym-main-col">
								<div class="sym-section">
									<h2><?php esc_html_e( 'Theme Mode', 'symbiotic-theme' ); ?></h2>
									<p class="sym-desc"><?php esc_html_e( 'Choose between dark and light appearance.', 'symbiotic-theme' ); ?></p>
									<div class="sym-field">
										<select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[theme_mode]" style="max-width:200px;">
											<option value="dark" <?php selected( $opts['theme_mode'] ?? 'dark', 'dark' ); ?>>Dark</option>
											<option value="light" <?php selected( $opts['theme_mode'] ?? 'dark', 'light' ); ?>>Light</option>
										</select>
									</div>
								</div>

								<div class="sym-section">
									<h2><?php esc_html_e( 'Background Layers', 'symbiotic-theme' ); ?></h2>
									<p class="sym-desc"><?php esc_html_e( 'Dark layered backgrounds create the chat depth effect.', 'symbiotic-theme' ); ?></p>
									<div class="sym-color-grid">
										<?php self::color_field( 'color_bg',        __( 'Page Background',   'symbiotic-theme' ), $opts, __( 'Outermost dark layer', 'symbiotic-theme' ) ); ?>
										<?php self::color_field( 'color_surface',   __( 'Surface',           'symbiotic-theme' ), $opts, __( 'TopBar, InputBar, right panel', 'symbiotic-theme' ) ); ?>
										<?php self::color_field( 'color_surface_2', __( 'Surface Elevated',  'symbiotic-theme' ), $opts, __( 'Cards, inputs, chips', 'symbiotic-theme' ) ); ?>
										<?php self::color_field( 'color_bot_bubble', __( 'Bot Bubble',       'symbiotic-theme' ), $opts, __( 'AI message background', 'symbiotic-theme' ) ); ?>
									</div>
								</div>

								<div class="sym-section">
									<h2><?php esc_html_e( 'Brand Color', 'symbiotic-theme' ); ?></h2>
									<p class="sym-desc"><?php esc_html_e( 'Drives user bubbles, buttons, focus states, badges, and accent links. Hover and accent variants are auto-computed.', 'symbiotic-theme' ); ?></p>
									<div class="sym-color-grid">
										<?php self::color_field( 'color_primary', __( 'Primary', 'symbiotic-theme' ), $opts, __( 'User bubbles, buttons, focus rings', 'symbiotic-theme' ) ); ?>
									</div>
								</div>

								<div class="sym-section">
									<h2><?php esc_html_e( 'Text Colors', 'symbiotic-theme' ); ?></h2>
									<div class="sym-color-grid">
										<?php self::color_field( 'color_text',       __( 'Primary Text',  'symbiotic-theme' ), $opts, __( 'Main readable text', 'symbiotic-theme' ) ); ?>
										<?php self::color_field( 'color_text_muted', __( 'Muted Text',    'symbiotic-theme' ), $opts, __( 'Labels, hints, placeholders', 'symbiotic-theme' ) ); ?>
									</div>
								</div>
							</div>

							<!-- Live preview -->
							<div class="sym-side-col">
								<div class="sym-preview-card">
									<div class="sym-preview-label"><?php esc_html_e( 'Live Preview', 'symbiotic-theme' ); ?></div>
									<div class="sym-preview-shell" id="prevBg">
										<div class="sym-preview-topbar" id="prevSurface">
											<span class="sym-preview-avatar" id="prevPrimary"></span>
											<div>
												<div class="sym-preview-store-name" id="prevText"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></div>
												<div class="sym-preview-store-sub" id="prevTextMuted"><?php esc_html_e( 'AI Shopping', 'symbiotic-theme' ); ?></div>
											</div>
										</div>
										<div class="sym-preview-messages">
											<div class="sym-preview-bot" id="prevBotBubble">
												<span id="prevBotText">Hello! How can I help?</span>
											</div>
											<div class="sym-preview-user" id="prevUserBubble">Show me shoes</div>
										</div>
										<div class="sym-preview-inputbar" id="prevSurface2">
											<div class="sym-preview-input-field" id="prevSurface2b">
												<span id="prevMuted">Type a message...</span>
											</div>
											<span class="sym-preview-send" id="prevSendBtn">→</span>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- ===== LAYOUT ===== -->
					<div class="sym-tab-panel" data-tab="layout">

						<div class="sym-section">
							<h2><?php esc_html_e( 'Layout Mode', 'symbiotic-theme' ); ?></h2>
							<p class="sym-desc"><?php esc_html_e( 'Choose how the interface fills the viewport.', 'symbiotic-theme' ); ?></p>
							<div class="sym-toggle-group">
								<?php self::toggle_field( 'layout_fullwidth', __( 'Full-width', 'symbiotic-theme' ), $opts, __( 'Stretch the interface to fill the full browser width. Both panels expand to share all available space.', 'symbiotic-theme' ) ); ?>
							</div>
						</div>

						<div class="sym-section">
							<h2><?php esc_html_e( 'Sidebar Visibility', 'symbiotic-theme' ); ?></h2>
							<p class="sym-desc"><?php esc_html_e( 'Enable or disable each panel independently.', 'symbiotic-theme' ); ?></p>
							<div class="sym-toggle-group">
								<?php self::toggle_field( 'show_product_panel', __( 'Product & Cart Panel', 'symbiotic-theme' ), $opts, __( 'Right panel showing products, cart, and checkout. Disable for a pure chat layout.', 'symbiotic-theme' ) ); ?>
								<?php self::toggle_field( 'show_right_sidebar', __( 'Browse Sidebar', 'symbiotic-theme' ), $opts, __( 'Persistent navigation sidebar showing categories, brands, and quick actions.', 'symbiotic-theme' ) ); ?>
							</div>
						</div>

						<div class="sym-section">
							<h2><?php esc_html_e( 'Panel Dimensions', 'symbiotic-theme' ); ?></h2>
							<p class="sym-desc"><?php esc_html_e( 'Fine-tune widths and corner rounding.', 'symbiotic-theme' ); ?></p>
							<div class="sym-fields-stack">
								<?php self::number_field( 'left_max_width',    __( 'Chat Panel Max Width (px)',  'symbiotic-theme' ), $opts, 400, 900, 10, __( 'Max width of the chat column. Ignored in full-width mode.', 'symbiotic-theme' ) ); ?>
								<?php self::number_field( 'right_width',       __( 'Product Panel Width (px)',  'symbiotic-theme' ), $opts, 280, 600, 10, __( 'Fixed width of the product/cart column. 340–440px recommended.', 'symbiotic-theme' ) ); ?>
								<?php self::number_field( 'right_sidebar_width', __( 'Browse Sidebar Width (px)', 'symbiotic-theme' ), $opts, 160, 360, 10, __( 'Width of the navigation sidebar. 200–280px recommended.', 'symbiotic-theme' ) ); ?>
								<?php self::number_field( 'border_radius',     __( 'Border Radius (px)',        'symbiotic-theme' ), $opts, 0,   32,  1,  __( 'Corner rounding on bubbles, cards, and buttons.', 'symbiotic-theme' ) ); ?>
							</div>

							<!-- Layout diagram -->
							<div class="sym-diagram sym-diagram--3col" id="symDiagram">
								<div class="sym-diagram-chat" id="diagChat">
									<span><?php esc_html_e( 'Chat', 'symbiotic-theme' ); ?></span>
									<small id="diagChatLabel">max <?php echo esc_html( $opts['left_max_width'] ); ?>px</small>
								</div>
								<div class="sym-diagram-products" id="diagProducts">
									<span><?php esc_html_e( 'Products', 'symbiotic-theme' ); ?></span>
									<small id="diagProductsLabel"><?php echo esc_html( $opts['right_width'] ); ?>px</small>
								</div>
								<div class="sym-diagram-sidebar" id="diagSidebar" style="display:<?php echo $opts['show_right_sidebar'] === '1' ? 'flex' : 'none'; ?>">
									<span><?php esc_html_e( 'Browse', 'symbiotic-theme' ); ?></span>
									<small id="diagSidebarLabel"><?php echo esc_html( $opts['right_sidebar_width'] ); ?>px</small>
								</div>
							</div>
						</div>
					</div>

					<!-- ===== CHAT ===== -->
					<div class="sym-tab-panel" data-tab="chat">
						<div class="sym-section">
							<h2><?php esc_html_e( 'Bot Identity', 'symbiotic-theme' ); ?></h2>
							<p class="sym-desc"><?php esc_html_e( 'How the AI assistant appears to shoppers.', 'symbiotic-theme' ); ?></p>
							<div class="sym-fields-stack">
								<?php self::text_field( 'bot_name',       __( 'Bot Name',       'symbiotic-theme' ), $opts, __( 'Shown below store name in the top bar. Leave empty for "AI Shopping".', 'symbiotic-theme' ) ); ?>
								<?php self::text_field( 'bot_avatar_url', __( 'Bot Avatar URL', 'symbiotic-theme' ), $opts, __( 'URL to an image for the bot avatar. Leave empty for default color circle.', 'symbiotic-theme' ) ); ?>
							</div>
						</div>

						<div class="sym-section">
							<h2><?php esc_html_e( 'Input & Welcome', 'symbiotic-theme' ); ?></h2>
							<div class="sym-fields-stack">
								<?php self::text_field( 'placeholder_text', __( 'Input Placeholder',       'symbiotic-theme' ), $opts, __( 'Text inside the message input box.', 'symbiotic-theme' ) ); ?>
								<?php self::text_field( 'input_hint',       __( 'Input Hint',              'symbiotic-theme' ), $opts, __( 'Small text shown above the input. Leave empty to hide.', 'symbiotic-theme' ) ); ?>
								<?php self::text_field( 'welcome_override', __( 'Welcome Message Override','symbiotic-theme' ), $opts, __( 'Override the greeting on the welcome screen. Uses plugin greeting if empty.', 'symbiotic-theme' ) ); ?>
							</div>
						</div>

						<div class="sym-section sym-tips">
							<h3>💡 <?php esc_html_e( 'AI Chat Interface Best Practices', 'symbiotic-theme' ); ?></h3>
							<ul>
								<li><?php echo wp_kses( __( '<strong>Enable streaming</strong> in WooCommerce → AI Chatbot → AI Provider for real-time token output — dramatically improves perceived speed.', 'symbiotic-theme' ), [ 'strong' => [] ] ); ?></li>
								<li><?php echo wp_kses( __( '<strong>Specific greeting</strong>: "Ask me to find shoes, compare prices, or check stock." converts better than generic "Hi, how can I help?".', 'symbiotic-theme' ), [ 'strong' => [] ] ); ?></li>
								<li><?php echo wp_kses( __( '<strong>Quick chips</strong> on the welcome screen are auto-populated from WooCommerce categories — use clear, short category names.', 'symbiotic-theme' ), [ 'strong' => [] ] ); ?></li>
								<li><?php echo wp_kses( __( '<strong>System prompt</strong> (WC → AI Chatbot → Advanced): scope the AI strictly to shopping. "You are a shopping assistant for [store]. Only answer product questions."', 'symbiotic-theme' ), [ 'strong' => [] ] ); ?></li>
								<li><?php echo wp_kses( __( '<strong>Semantic embeddings</strong> (WC → AI Embeddings): generate embeddings for all products to enable intent-based search beyond keywords.', 'symbiotic-theme' ), [ 'strong' => [] ] ); ?></li>
							</ul>
						</div>
					</div>

					<!-- ===== TYPOGRAPHY ===== -->
					<div class="sym-tab-panel" data-tab="typography">
						<div class="sym-section">
							<h2><?php esc_html_e( 'Font Settings', 'symbiotic-theme' ); ?></h2>
							<p class="sym-desc"><?php esc_html_e( 'Base font size is applied immediately via CSS variable. Font family change requires updating the Google Fonts import in index.jsx and rebuilding (npm run build).', 'symbiotic-theme' ); ?></p>
							<div class="sym-fields-stack">
								<div class="sym-field">
									<label for="sym_font_family"><?php esc_html_e( 'Font Family', 'symbiotic-theme' ); ?></label>
									<select id="sym_font_family" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[font_family]">
										<?php
										foreach ( [ 'DM Sans', 'Inter', 'Geist', 'Plus Jakarta Sans', 'Outfit', 'Manrope', 'Nunito', 'Sora' ] as $font ) {
											printf(
												'<option value="%s"%s>%s</option>',
												esc_attr( $font ),
												selected( $opts['font_family'], $font, false ),
												esc_html( $font )
											);
										}
										?>
									</select>
									<p class="sym-field-hint"><?php esc_html_e( 'Update the Google Fonts URL in assets/src/index.jsx, then run npm run build.', 'symbiotic-theme' ); ?></p>
								</div>
								<?php self::number_field( 'base_font_size', __( 'Base Font Size (px)', 'symbiotic-theme' ), $opts, 12, 20, 1, __( '14–16px recommended. Applied instantly via --sym-font-size CSS variable.', 'symbiotic-theme' ) ); ?>
							</div>
						</div>
					</div>

					<!-- ===== HOMEPAGE ===== -->
					<div class="sym-tab-panel" data-tab="homepage">
						<div class="sym-section">
							<h2><?php esc_html_e( 'Hero Section', 'symbiotic-theme' ); ?></h2>
							<p class="sym-desc"><?php esc_html_e( 'The main banner area visible on the homepage.', 'symbiotic-theme' ); ?></p>
							<div class="sym-fields-stack">
								<div class="sym-field">
									<label><?php esc_html_e( 'Hero Title', 'symbiotic-theme' ); ?></label>
									<input type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[hero_title]" value="<?php echo esc_attr( $opts['hero_title'] ?? '' ); ?>" class="sym-text-input" style="max-width:100%;">
									<p class="sym-field-hint"><?php esc_html_e( 'Supports <br/> for line break. E.g. "Printing Solutions,<br/>Powered by AI"', 'symbiotic-theme' ); ?></p>
								</div>
								<div class="sym-field">
									<label><?php esc_html_e( 'Hero Subtitle', 'symbiotic-theme' ); ?></label>
									<input type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[hero_subtitle]" value="<?php echo esc_attr( $opts['hero_subtitle'] ?? '' ); ?>" class="sym-text-input" style="max-width:100%;">
								</div>
								<div class="sym-field">
									<label><?php esc_html_e( 'Hero Product Image URL', 'symbiotic-theme' ); ?></label>
									<input type="url" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[hero_image_url]" value="<?php echo esc_attr( $opts['hero_image_url'] ?? '' ); ?>" class="sym-text-input" style="max-width:100%;">
									<p class="sym-field-hint"><?php esc_html_e( 'Product showcase image on the right side of hero. Leave empty for default.', 'symbiotic-theme' ); ?></p>
								</div>
							</div>
						</div>

						<div class="sym-section">
							<h2><?php esc_html_e( 'Promo Banner', 'symbiotic-theme' ); ?></h2>
							<p class="sym-desc"><?php esc_html_e( 'Promotional banner shown below categories on the homepage.', 'symbiotic-theme' ); ?></p>
							<div class="sym-fields-stack">
								<div class="sym-field">
									<label><?php esc_html_e( 'Promo Title', 'symbiotic-theme' ); ?></label>
									<input type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[promo_title]" value="<?php echo esc_attr( $opts['promo_title'] ?? '' ); ?>" class="sym-text-input">
								</div>
								<div class="sym-field">
									<label><?php esc_html_e( 'Promo Text', 'symbiotic-theme' ); ?></label>
									<input type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[promo_text]" value="<?php echo esc_attr( $opts['promo_text'] ?? '' ); ?>" class="sym-text-input" style="max-width:100%;">
								</div>
								<div class="sym-field">
									<label><?php esc_html_e( 'Promo Background Image URL', 'symbiotic-theme' ); ?></label>
									<input type="url" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[promo_image_url]" value="<?php echo esc_attr( $opts['promo_image_url'] ?? '' ); ?>" class="sym-text-input" style="max-width:100%;">
								</div>
								<div class="sym-field">
									<label><?php esc_html_e( 'CTA Button Text', 'symbiotic-theme' ); ?></label>
									<input type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[promo_cta_text]" value="<?php echo esc_attr( $opts['promo_cta_text'] ?? 'Explore' ); ?>" class="sym-text-input" style="max-width:200px;">
								</div>
								<div class="sym-field">
									<label><?php esc_html_e( 'CTA AI Query', 'symbiotic-theme' ); ?></label>
									<input type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[promo_cta_query]" value="<?php echo esc_attr( $opts['promo_cta_query'] ?? '' ); ?>" class="sym-text-input" style="max-width:100%;">
									<p class="sym-field-hint"><?php esc_html_e( 'What the AI should search when user clicks the CTA.', 'symbiotic-theme' ); ?></p>
								</div>
							</div>
						</div>
					</div>

					<!-- ===== ADVANCED ===== -->
					<div class="sym-tab-panel" data-tab="advanced">
						<div class="sym-section">
							<h2><?php esc_html_e( 'Custom CSS', 'symbiotic-theme' ); ?></h2>
							<p class="sym-desc"><?php esc_html_e( 'Injected after the main stylesheet. Use CSS variables (--sym-*) for best compatibility across color scheme changes.', 'symbiotic-theme' ); ?></p>
							<div class="sym-field">
								<label><?php esc_html_e( 'CSS', 'symbiotic-theme' ); ?></label>
								<textarea
									name="<?php echo esc_attr( self::OPTION_KEY ); ?>[custom_css]"
									rows="12"
									class="sym-code-area"
									placeholder="/* Override any --sym-* variable or class */&#10;.sym-bubble--bot { border-color: rgba(255,255,255,0.15); }&#10;&#10;/* Wider right panel on large screens */&#10;@media (min-width: 1400px) { .sym-right-panel { width: 460px; } }"
								><?php echo esc_textarea( $opts['custom_css'] ); ?></textarea>
							</div>
						</div>

						<div class="sym-section">
							<h2><?php esc_html_e( 'CSS Variable Reference', 'symbiotic-theme' ); ?></h2>
							<div class="sym-var-ref">
								<?php
								$vars = [
									'--sym-bg'            => 'Outermost background',
									'--sym-surface'       => 'TopBar, InputBar, right panel bg',
									'--sym-surface-2'     => 'Cards, inputs, chips, secondary elements',
									'--sym-border'        => 'All borders — rgba, auto-derived from surface',
									'--sym-text'          => 'Primary readable text',
									'--sym-text-muted'    => 'Labels, hints, placeholders',
									'--sym-primary'       => 'Brand color — user bubbles, buttons, focus rings',
									'--sym-primary-hover' => 'Darker variant (auto-computed -15%)',
									'--sym-user-bubble'   => 'User message bg (= primary)',
									'--sym-bot-bubble'    => 'Bot message bg',
									'--sym-accent'        => 'Links, streaming cursor (auto-computed +40%)',
									'--sym-radius'        => 'Global border radius',
									'--sym-font'          => 'Full font stack',
									'--sym-font-size'     => 'Base font size (px)',
								];
								foreach ( $vars as $var => $desc ) :
									?>
									<div class="sym-var-row">
										<code><?php echo esc_html( $var ); ?></code>
										<span><?php echo esc_html( $desc ); ?></span>
									</div>
									<?php
								endforeach;
								?>
							</div>
						</div>

						<div class="sym-section">
							<h2><?php esc_html_e( 'Reset to Defaults', 'symbiotic-theme' ); ?></h2>
							<p class="sym-desc"><?php esc_html_e( 'Deletes all custom theme settings and restores the original appearance.', 'symbiotic-theme' ); ?></p>
							<a
								href="<?php echo esc_url( wp_nonce_url( admin_url( 'themes.php?page=symbiotic-theme&sym_reset=1' ), 'sym_reset' ) ); ?>"
								class="sym-reset-btn"
								onclick="return confirm('<?php esc_attr_e( 'Reset all Symbiotic Theme settings to defaults?', 'symbiotic-theme' ); ?>')"
							>
								<?php esc_html_e( 'Reset All Settings', 'symbiotic-theme' ); ?>
							</a>
						</div>
					</div>

					<!-- Save bar (for theme settings form) -->
					<div class="sym-save-bar">
						<?php submit_button( __( 'Save Settings', 'symbiotic-theme' ), 'primary sym-save-btn', 'submit', false ); ?>
						<span class="sym-save-hint"><?php esc_html_e( 'Changes apply instantly — no rebuild needed (except font family).', 'symbiotic-theme' ); ?></span>
					</div>

				</form>

				<?php
				// ── AI Provider panel (outside theme settings form, own save) ──
				$wcaic_settings = (array) get_option( 'wcaic_settings', [] );
				?>
				<div class="sym-tab-panel" data-tab="ai-provider">
					<form method="post" action="options.php">
						<?php settings_fields( 'wcaic_settings_group' ); ?>

						<div class="sym-section">
							<h2><?php esc_html_e( 'AI Provider', 'symbiotic-theme' ); ?></h2>
							<p class="sym-desc"><?php esc_html_e( 'Choose your AI engine. API keys are encrypted with AES-256-CBC and never exposed to the frontend.', 'symbiotic-theme' ); ?></p>
							<div class="sym-fields-stack">
								<?php
								// Render inline since plugin admin fields use wp Settings API
								$provider = $wcaic_settings['provider'] ?? 'openai';
								?>
								<div class="sym-field">
									<label><?php esc_html_e( 'Provider', 'symbiotic-theme' ); ?></label>
									<select name="wcaic_settings[provider]" class="sym-text-input" style="max-width:220px;">
										<option value="openai" <?php selected( $provider, 'openai' ); ?>>OpenAI</option>
										<option value="anthropic" <?php selected( $provider, 'anthropic' ); ?>>Anthropic (Claude)</option>
									</select>
								</div>
								<div class="sym-field">
									<label><?php esc_html_e( 'OpenAI API Key', 'symbiotic-theme' ); ?></label>
									<?php
									$masked_openai = WCAIC_Admin::get_instance()->get_masked_key_public( 'openai' );
									?>
									<input type="password" name="wcaic_settings[openai_api_key]" value="<?php echo esc_attr( $masked_openai ); ?>" class="sym-text-input" autocomplete="new-password">
									<p class="sym-field-hint"><?php esc_html_e( 'Leave unchanged to keep existing key.', 'symbiotic-theme' ); ?></p>
								</div>
								<div class="sym-field">
									<label><?php esc_html_e( 'OpenAI Model', 'symbiotic-theme' ); ?></label>
									<?php $openai_model = $wcaic_settings['openai_model'] ?? 'gpt-4o-mini'; ?>
									<select name="wcaic_settings[openai_model]" class="sym-text-input" style="max-width:220px;">
										<?php foreach ( [ 'gpt-4o-mini', 'gpt-4o', 'gpt-4-turbo', 'gpt-3.5-turbo' ] as $m ) : ?>
											<option value="<?php echo esc_attr( $m ); ?>" <?php selected( $openai_model, $m ); ?>><?php echo esc_html( $m ); ?></option>
										<?php endforeach; ?>
									</select>
								</div>
								<div class="sym-field">
									<label><?php esc_html_e( 'Anthropic API Key', 'symbiotic-theme' ); ?></label>
									<?php
									$masked_anthropic = WCAIC_Admin::get_instance()->get_masked_key_public( 'anthropic' );
									?>
									<input type="password" name="wcaic_settings[anthropic_api_key]" value="<?php echo esc_attr( $masked_anthropic ); ?>" class="sym-text-input" autocomplete="new-password">
									<p class="sym-field-hint"><?php esc_html_e( 'Leave unchanged to keep existing key.', 'symbiotic-theme' ); ?></p>
								</div>
								<div class="sym-field">
									<label><?php esc_html_e( 'Anthropic Model', 'symbiotic-theme' ); ?></label>
									<?php $anthropic_model = $wcaic_settings['anthropic_model'] ?? 'claude-sonnet-4-6'; ?>
									<select name="wcaic_settings[anthropic_model]" class="sym-text-input" style="max-width:280px;">
										<?php foreach ( [ 'claude-sonnet-4-6', 'claude-opus-4-6', 'claude-haiku-4-5-20251001' ] as $m ) : ?>
											<option value="<?php echo esc_attr( $m ); ?>" <?php selected( $anthropic_model, $m ); ?>><?php echo esc_html( $m ); ?></option>
										<?php endforeach; ?>
									</select>
								</div>
							</div>
						</div>

						<div class="sym-section">
							<h2><?php esc_html_e( 'Chat Settings', 'symbiotic-theme' ); ?></h2>
							<div class="sym-fields-stack">
								<div class="sym-field">
									<label><?php esc_html_e( 'Greeting Message', 'symbiotic-theme' ); ?></label>
									<input type="text" name="wcaic_settings[greeting]" value="<?php echo esc_attr( $wcaic_settings['greeting'] ?? 'Hi! How can I help?' ); ?>" class="sym-text-input">
								</div>
								<?php
								$streaming = ! empty( $wcaic_settings['streaming_enabled'] ) && $wcaic_settings['streaming_enabled'] !== '0';
								?>
								<label class="sym-toggle">
									<input type="checkbox" name="wcaic_settings[streaming_enabled]" value="1" <?php checked( $streaming ); ?> class="sym-toggle-input">
									<span class="sym-toggle-track"><span class="sym-toggle-thumb"></span></span>
									<span class="sym-toggle-label">
										<span class="sym-toggle-title"><?php esc_html_e( 'Enable Streaming', 'symbiotic-theme' ); ?></span>
										<span class="sym-toggle-desc"><?php esc_html_e( 'Real-time token output for faster perceived response.', 'symbiotic-theme' ); ?></span>
									</span>
								</label>
								<?php
								$widget_enabled = ! empty( $wcaic_settings['widget_enabled'] ) && $wcaic_settings['widget_enabled'] !== '0';
								?>
								<label class="sym-toggle">
									<input type="checkbox" name="wcaic_settings[widget_enabled]" value="1" <?php checked( $widget_enabled ); ?> class="sym-toggle-input">
									<span class="sym-toggle-track"><span class="sym-toggle-thumb"></span></span>
									<span class="sym-toggle-label">
										<span class="sym-toggle-title"><?php esc_html_e( 'Enable Chat Widget', 'symbiotic-theme' ); ?></span>
										<span class="sym-toggle-desc"><?php esc_html_e( 'Show the AI chat widget on WooCommerce pages.', 'symbiotic-theme' ); ?></span>
									</span>
								</label>
							</div>
						</div>

						<div class="sym-section">
							<h2><?php esc_html_e( 'Rate Limits & Advanced', 'symbiotic-theme' ); ?></h2>
							<div class="sym-fields-stack">
								<div class="sym-field">
									<label><?php esc_html_e( 'Max AI Requests / min', 'symbiotic-theme' ); ?></label>
									<input type="number" name="wcaic_settings[ai_rate_limit]" value="<?php echo esc_attr( $wcaic_settings['ai_rate_limit'] ?? '10' ); ?>" min="1" max="100" class="sym-num-input">
								</div>
								<div class="sym-field">
									<label><?php esc_html_e( 'Max Tool Calls / min', 'symbiotic-theme' ); ?></label>
									<input type="number" name="wcaic_settings[tool_rate_limit]" value="<?php echo esc_attr( $wcaic_settings['tool_rate_limit'] ?? '30' ); ?>" min="1" max="300" class="sym-num-input">
								</div>
								<div class="sym-field">
									<label><?php esc_html_e( 'Max Function-Call Iterations', 'symbiotic-theme' ); ?></label>
									<input type="number" name="wcaic_settings[max_iterations]" value="<?php echo esc_attr( $wcaic_settings['max_iterations'] ?? '5' ); ?>" min="1" max="10" class="sym-num-input">
								</div>
								<?php
								$conv_logging = ! empty( $wcaic_settings['conversation_logging'] ) && $wcaic_settings['conversation_logging'] !== '0';
								?>
								<label class="sym-toggle">
									<input type="checkbox" name="wcaic_settings[conversation_logging]" value="1" <?php checked( $conv_logging ); ?> class="sym-toggle-input">
									<span class="sym-toggle-track"><span class="sym-toggle-thumb"></span></span>
									<span class="sym-toggle-label">
										<span class="sym-toggle-title"><?php esc_html_e( 'Conversation Logging', 'symbiotic-theme' ); ?></span>
										<span class="sym-toggle-desc"><?php esc_html_e( 'Log all AI conversations for analytics and review.', 'symbiotic-theme' ); ?></span>
									</span>
								</label>
							</div>
						</div>

						<!-- Hidden fields for settings that must be preserved -->
						<input type="hidden" name="wcaic_settings[system_prompt]" value="<?php echo esc_attr( $wcaic_settings['system_prompt'] ?? '' ); ?>">
						<input type="hidden" name="wcaic_settings[widget_position]" value="<?php echo esc_attr( $wcaic_settings['widget_position'] ?? 'bottom-right' ); ?>">
						<input type="hidden" name="wcaic_settings[primary_color]" value="<?php echo esc_attr( $wcaic_settings['primary_color'] ?? '#2563eb' ); ?>">
						<input type="hidden" name="wcaic_settings[max_history]" value="<?php echo esc_attr( $wcaic_settings['max_history'] ?? '20' ); ?>">
						<input type="hidden" name="wcaic_settings[embedding_enabled]" value="<?php echo esc_attr( $wcaic_settings['embedding_enabled'] ?? '0' ); ?>">

						<div class="sym-save-bar">
							<?php submit_button( __( 'Save AI Settings', 'symbiotic-theme' ), 'primary sym-save-btn', 'submit', false ); ?>
						</div>
					</form>
				</div>

				<?php
				// ── AI Persona panel ──
				$persona   = WCAIC_Persona::get_settings();
				$presets   = WCAIC_Persona::presets();
				$persona_saved = '';
				if ( isset( $_POST['wcaic_persona_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wcaic_persona_nonce'] ) ), 'wcaic_save_persona' ) ) {
					WCAIC_Persona::save( $_POST['persona'] ?? [] );
					$persona      = WCAIC_Persona::get_settings();
					$persona_saved = true;
				}
				?>
				<div class="sym-tab-panel" data-tab="ai-persona">
					<?php if ( $persona_saved ) : ?>
						<div class="sym-notice sym-notice--success"><?php esc_html_e( 'Persona settings saved.', 'symbiotic-theme' ); ?></div>
					<?php endif; ?>

					<form method="post">
						<?php wp_nonce_field( 'wcaic_save_persona', 'wcaic_persona_nonce' ); ?>

						<div class="sym-section">
							<h2><?php esc_html_e( 'Persona Preset', 'symbiotic-theme' ); ?></h2>
							<p class="sym-desc"><?php esc_html_e( 'Choose a personality archetype for your AI assistant. This shapes how it speaks, recommends, and interacts.', 'symbiotic-theme' ); ?></p>
							<div class="sym-preset-grid">
								<?php foreach ( $presets as $key => $preset ) : ?>
									<label class="sym-preset-card <?php echo $persona['preset'] === $key ? 'sym-preset-card--active' : ''; ?>">
										<input type="radio" name="persona[preset]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $persona['preset'], $key ); ?> class="sym-preset-radio">
										<strong><?php echo esc_html( $preset['label'] ); ?></strong>
										<span><?php echo esc_html( $preset['description'] ); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
						</div>

						<div class="sym-section">
							<h2><?php esc_html_e( 'Personality Spectrum', 'symbiotic-theme' ); ?></h2>
							<p class="sym-desc"><?php esc_html_e( 'Fine-tune the AI personality. Auto-set by presets, or select "Custom" to configure manually.', 'symbiotic-theme' ); ?></p>
							<div class="sym-spectrum-grid">
								<?php
								$spectrums = [
									'selling'     => [ __( 'Supportive Guide', 'symbiotic-theme' ), __( 'Active Recommender', 'symbiotic-theme' ) ],
									'formality'   => [ __( 'Casual / Friendly', 'symbiotic-theme' ), __( 'Professional / Polished', 'symbiotic-theme' ) ],
									'detail'      => [ __( 'Concise', 'symbiotic-theme' ), __( 'Detailed', 'symbiotic-theme' ) ],
									'proactivity' => [ __( 'Reactive Only', 'symbiotic-theme' ), __( 'Anticipatory', 'symbiotic-theme' ) ],
								];
								foreach ( $spectrums as $skey => $labels ) :
									?>
									<div class="sym-spectrum-row">
										<span class="sym-spectrum-label-left"><?php echo esc_html( $labels[0] ); ?></span>
										<input type="range" name="persona[<?php echo esc_attr( $skey ); ?>]" min="0" max="100" value="<?php echo esc_attr( $persona[ $skey ] ); ?>" class="sym-spectrum-slider">
										<span class="sym-spectrum-label-right"><?php echo esc_html( $labels[1] ); ?></span>
										<span class="sym-spectrum-val"><?php echo esc_html( $persona[ $skey ] ); ?></span>
									</div>
								<?php endforeach; ?>
							</div>
						</div>

						<div class="sym-section">
							<h2><?php esc_html_e( 'Custom Rules & Boundaries', 'symbiotic-theme' ); ?></h2>
							<div class="sym-fields-stack">
								<div class="sym-field">
									<label><?php esc_html_e( 'Custom Rules', 'symbiotic-theme' ); ?></label>
									<textarea name="persona[custom_rules]" rows="4" class="sym-code-area"><?php echo esc_textarea( $persona['custom_rules'] ); ?></textarea>
									<p class="sym-field-hint"><?php esc_html_e( 'Additional instructions appended to the persona rules.', 'symbiotic-theme' ); ?></p>
								</div>
								<div class="sym-field">
									<label><?php esc_html_e( 'Prohibited Topics (one per line)', 'symbiotic-theme' ); ?></label>
									<textarea name="persona[prohibited_topics]" rows="3" class="sym-code-area"><?php echo esc_textarea( $persona['prohibited_topics'] ); ?></textarea>
								</div>
								<div class="sym-field">
									<label><?php esc_html_e( 'Prohibited Words (one per line)', 'symbiotic-theme' ); ?></label>
									<textarea name="persona[prohibited_words]" rows="3" class="sym-code-area"><?php echo esc_textarea( $persona['prohibited_words'] ); ?></textarea>
								</div>
								<div class="sym-field">
									<label><?php esc_html_e( 'Off-Topic Response', 'symbiotic-theme' ); ?></label>
									<input type="text" name="persona[off_topic_message]" value="<?php echo esc_attr( $persona['off_topic_message'] ); ?>" class="sym-text-input" style="max-width:100%;">
								</div>
								<div class="sym-field">
									<label><?php esc_html_e( 'Escalation Message', 'symbiotic-theme' ); ?></label>
									<input type="text" name="persona[escalation_message]" value="<?php echo esc_attr( $persona['escalation_message'] ); ?>" class="sym-text-input" style="max-width:100%;">
									<p class="sym-field-hint"><?php esc_html_e( 'Shown when the AI needs to redirect to human support.', 'symbiotic-theme' ); ?></p>
								</div>
							</div>
						</div>

						<div class="sym-save-bar">
							<?php submit_button( __( 'Save Persona', 'symbiotic-theme' ), 'primary sym-save-btn', 'submit', false ); ?>
						</div>
					</form>

					<script>
					document.querySelectorAll('.sym-spectrum-slider').forEach(function(s){
						s.addEventListener('input',function(){ this.nextElementSibling.nextElementSibling.textContent=this.value; });
					});
					</script>
				</div>

				<?php
				// ── Brand Knowledge panel ──
				$knowledge_saved = '';
				if ( isset( $_POST['wcaic_knowledge_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wcaic_knowledge_nonce'] ) ), 'wcaic_save_knowledge' ) ) {
					WCAIC_Brand_Knowledge::save( $_POST['knowledge'] ?? [] );
					$knowledge_saved = true;
				}
				$sections = WCAIC_Brand_Knowledge::get_all();
				?>
				<div class="sym-tab-panel" data-tab="brand-knowledge">
					<?php if ( $knowledge_saved ) : ?>
						<div class="sym-notice sym-notice--success"><?php esc_html_e( 'Brand knowledge saved.', 'symbiotic-theme' ); ?></div>
					<?php endif; ?>

					<div class="sym-section">
						<h2><?php esc_html_e( 'Brand Knowledge Base', 'symbiotic-theme' ); ?></h2>
						<p class="sym-desc"><?php esc_html_e( 'Add your brand information below. The AI assistant uses this to answer customer questions accurately and stay on-brand. Only enabled sections with content are injected into the AI context.', 'symbiotic-theme' ); ?></p>
					</div>

					<form method="post">
						<?php wp_nonce_field( 'wcaic_save_knowledge', 'wcaic_knowledge_nonce' ); ?>

						<?php foreach ( $sections as $key => $section ) : ?>
							<div class="sym-knowledge-card">
								<div class="sym-knowledge-header">
									<label class="sym-toggle" style="margin:0;">
										<input type="checkbox" name="knowledge[<?php echo esc_attr( $key ); ?>][enabled]" value="1" <?php checked( $section['enabled'] ); ?> class="sym-toggle-input">
										<span class="sym-toggle-track"><span class="sym-toggle-thumb"></span></span>
										<span class="sym-toggle-title" style="font-size:14px;"><?php echo esc_html( $section['label'] ); ?></span>
									</label>
								</div>
								<p class="sym-field-hint" style="margin:0 0 10px;"><?php echo esc_html( $section['hint'] ); ?></p>
								<textarea name="knowledge[<?php echo esc_attr( $key ); ?>][content]" rows="5" class="sym-code-area"><?php echo esc_textarea( $section['content'] ); ?></textarea>
							</div>
						<?php endforeach; ?>

						<div class="sym-save-bar">
							<?php submit_button( __( 'Save Brand Knowledge', 'symbiotic-theme' ), 'primary sym-save-btn', 'submit', false ); ?>
						</div>
					</form>
				</div>

				<?php
				// ── Language panel ──
				$lang_settings = WCAIC_Language::get_settings();
				$lang_saved = '';
				if ( isset( $_POST['wcaic_language_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wcaic_language_nonce'] ) ), 'wcaic_save_language' ) ) {
					WCAIC_Language::save( $_POST['language'] ?? [] );
					$lang_settings = WCAIC_Language::get_settings();
					$lang_saved = true;
				}
				$available_langs = WCAIC_Language::available_languages();
				?>
				<div class="sym-tab-panel" data-tab="language">
					<?php if ( $lang_saved ) : ?>
						<div class="sym-notice sym-notice--success"><?php esc_html_e( 'Language settings saved.', 'symbiotic-theme' ); ?></div>
					<?php endif; ?>

					<form method="post">
						<?php wp_nonce_field( 'wcaic_save_language', 'wcaic_language_nonce' ); ?>

						<div class="sym-section">
							<h2><?php esc_html_e( 'Language Mode', 'symbiotic-theme' ); ?></h2>
							<p class="sym-desc"><?php esc_html_e( 'Control how the AI handles multiple languages. Auto-detect responds in the customer\'s language automatically.', 'symbiotic-theme' ); ?></p>
							<div class="sym-fields-stack">
								<div class="sym-field">
									<label><?php esc_html_e( 'Response Language', 'symbiotic-theme' ); ?></label>
									<select name="language[mode]" class="sym-text-input" style="max-width:300px;">
										<?php foreach ( $available_langs as $code => $name ) : ?>
											<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $lang_settings['mode'], $code ); ?>>
												<?php echo esc_html( $name ); ?>
											</option>
										<?php endforeach; ?>
									</select>
									<p class="sym-field-hint"><?php esc_html_e( '"Auto-detect" adapts to each customer. Choose a specific language to always respond in that language.', 'symbiotic-theme' ); ?></p>
								</div>
							</div>
						</div>

						<div class="sym-section">
							<h2><?php esc_html_e( 'Primary Language (Fallback)', 'symbiotic-theme' ); ?></h2>
							<p class="sym-desc"><?php esc_html_e( 'When auto-detect mode is active and the customer\'s language is ambiguous, the AI falls back to this language.', 'symbiotic-theme' ); ?></p>
							<div class="sym-fields-stack">
								<div class="sym-field">
									<label><?php esc_html_e( 'Primary Language', 'symbiotic-theme' ); ?></label>
									<select name="language[primary_language]" class="sym-text-input" style="max-width:300px;">
										<option value=""><?php esc_html_e( '— English (default) —', 'symbiotic-theme' ); ?></option>
										<?php foreach ( $available_langs as $code => $name ) : ?>
											<?php if ( $code === 'auto' ) { continue; } ?>
											<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $lang_settings['primary_language'], $code ); ?>>
												<?php echo esc_html( $name ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</div>
							</div>
						</div>

						<div class="sym-section">
							<h2><?php esc_html_e( 'Supported Languages', 'symbiotic-theme' ); ?></h2>
							<p class="sym-desc"><?php esc_html_e( 'Select which languages the AI should support. Leave empty to support all languages. If a customer writes in an unsupported language, the AI responds in the primary language.', 'symbiotic-theme' ); ?></p>
							<div class="sym-lang-grid">
								<?php foreach ( $available_langs as $code => $name ) : ?>
									<?php if ( $code === 'auto' ) { continue; } ?>
									<label class="sym-lang-check">
										<input type="checkbox" name="language[additional_languages][]" value="<?php echo esc_attr( $code ); ?>"
											<?php echo in_array( $code, $lang_settings['additional_languages'], true ) ? 'checked' : ''; ?>>
										<?php echo esc_html( $name ); ?>
									</label>
								<?php endforeach; ?>
							</div>
						</div>

						<div class="sym-section">
							<h2><?php esc_html_e( 'Custom Language Note', 'symbiotic-theme' ); ?></h2>
							<p class="sym-desc"><?php esc_html_e( 'Additional instructions about language behavior. E.g., "Use formal Armenian when addressing customers" or "Product names should remain in English."', 'symbiotic-theme' ); ?></p>
							<div class="sym-fields-stack">
								<div class="sym-field">
									<textarea name="language[language_note]" rows="3" class="sym-code-area"><?php echo esc_textarea( $lang_settings['language_note'] ); ?></textarea>
								</div>
							</div>
						</div>

						<div class="sym-save-bar">
							<?php submit_button( __( 'Save Language Settings', 'symbiotic-theme' ), 'primary sym-save-btn', 'submit', false ); ?>
						</div>
					</form>
				</div>

				<?php if ( class_exists( 'WooCommerce' ) ) : ?>
				<!-- ===== SHOP ===== -->
				<div class="sym-tab-panel" data-tab="shop">

					<div class="sym-section">
						<h2><?php esc_html_e( 'Import Product from URL', 'symbiotic-theme' ); ?></h2>
						<p class="sym-desc"><?php esc_html_e( 'Paste any product URL from a print shop (axiomprint.com or tgm-print.com). The importer will fetch the page, extract product data, options, and pricing, then create a WooCommerce product with the calculator configurator.', 'symbiotic-theme' ); ?></p>

						<div class="sym-fields-stack">
							<div class="sym-field">
								<label><?php esc_html_e( 'Product URL', 'symbiotic-theme' ); ?></label>
								<div class="sym-import-input-row">
									<input type="url" id="sym-import-url" class="sym-text-input" placeholder="https://tgm-print.com/product/classic-business-cards/" style="flex:1;">
									<button type="button" id="sym-import-btn" class="button button-primary sym-import-btn">
										<?php esc_html_e( 'Import Product', 'symbiotic-theme' ); ?>
									</button>
								</div>
								<p class="sym-field-hint"><?php esc_html_e( 'Supports most e-commerce product pages. The importer extracts: product name, description, images, options/variations, pricing structure.', 'symbiotic-theme' ); ?></p>
							</div>
						</div>
					</div>

					<!-- Import Progress -->
					<div id="sym-import-progress" class="sym-section" style="display:none;">
						<h2><?php esc_html_e( 'Import Progress', 'symbiotic-theme' ); ?></h2>
						<div class="sym-import-log" id="sym-import-log"></div>
						<div class="sym-import-result" id="sym-import-result" style="display:none;"></div>
					</div>

					<!-- Existing Products -->
					<div class="sym-section">
						<h2><?php esc_html_e( 'Calculator Products', 'symbiotic-theme' ); ?></h2>
						<p class="sym-desc"><?php esc_html_e( 'Products with the print calculator configurator enabled.', 'symbiotic-theme' ); ?></p>

						<?php
						global $wpdb;
						$calc_products = $wpdb->get_results(
							"SELECT p.ID, p.post_title, p.post_status, p.post_date
							 FROM {$wpdb->posts} p
							 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
							 WHERE p.post_type = 'product' AND pm.meta_key = '_sqft_calculator_enabled' AND pm.meta_value = '1'
							 ORDER BY p.post_date DESC LIMIT 50"
						);
						?>

						<?php if ( ! empty( $calc_products ) ) : ?>
						<table class="sym-import-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Product', 'symbiotic-theme' ); ?></th>
									<th><?php esc_html_e( 'Options', 'symbiotic-theme' ); ?></th>
									<th><?php esc_html_e( 'Status', 'symbiotic-theme' ); ?></th>
									<th><?php esc_html_e( 'Actions', 'symbiotic-theme' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $calc_products as $cp ) :
									$var_count  = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}sqft_variables WHERE product_id = %d", $cp->ID ) );
									$item_count = $wpdb->get_var( $wpdb->prepare(
										"SELECT COUNT(*) FROM {$wpdb->prefix}sqft_variable_items vi
										 INNER JOIN {$wpdb->prefix}sqft_variables v ON vi.variable_id = v.id
										 WHERE v.product_id = %d", $cp->ID
									) );
								?>
								<tr>
									<td>
										<strong><?php echo esc_html( $cp->post_title ); ?></strong>
										<span class="sym-import-id">#<?php echo esc_html( $cp->ID ); ?></span>
									</td>
									<td><?php echo esc_html( $var_count ); ?> groups, <?php echo esc_html( $item_count ); ?> choices</td>
									<td><span class="sym-status-badge sym-status-badge--<?php echo esc_attr( $cp->post_status ); ?>"><?php echo esc_html( ucfirst( $cp->post_status ) ); ?></span></td>
									<td>
										<a href="<?php echo get_edit_post_link( $cp->ID ); ?>" class="button button-small"><?php esc_html_e( 'Edit', 'symbiotic-theme' ); ?></a>
										<a href="<?php echo get_permalink( $cp->ID ); ?>" target="_blank" class="button button-small"><?php esc_html_e( 'View', 'symbiotic-theme' ); ?></a>
									</td>
								</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						<?php else : ?>
						<p class="sym-empty-state"><?php esc_html_e( 'No calculator products yet. Import one using the form above.', 'symbiotic-theme' ); ?></p>
						<?php endif; ?>
					</div>
				</div>
				<?php endif; ?>

			</div>
		</div>
		<?php
		// Enqueue shop import JS inline.
		if ( class_exists( 'WooCommerce' ) ) {
			self::render_shop_import_script();
		}
	}

	/**
	 * Render the Shop import JS inline (after the admin HTML).
	 */
	private static function render_shop_import_script(): void {
		$nonce = wp_create_nonce( 'sym_import_product' );
		?>
		<script>
		(function() {
			var btn = document.getElementById('sym-import-btn');
			var urlInput = document.getElementById('sym-import-url');
			var progressWrap = document.getElementById('sym-import-progress');
			var log = document.getElementById('sym-import-log');
			var resultWrap = document.getElementById('sym-import-result');

			if (!btn) return;

			btn.addEventListener('click', function() {
				var url = urlInput.value.trim();
				if (!url) { urlInput.focus(); return; }

				btn.disabled = true;
				btn.textContent = 'Importing...';
				progressWrap.style.display = '';
				resultWrap.style.display = 'none';
				log.innerHTML = '';

				addLog('start', 'Starting product import...');
				addLog('fetch', 'Fetching URL: ' + url);

				var formData = new FormData();
				formData.append('action', 'sym_import_product_from_url');
				formData.append('nonce', '<?php echo esc_js( $nonce ); ?>');
				formData.append('url', url);

				fetch(ajaxurl, { method: 'POST', body: formData })
					.then(function(resp) { return resp.json(); })
					.then(function(data) {
						if (data.success) {
							var d = data.data;
							// Show step-by-step log
							if (d.steps && d.steps.length) {
								d.steps.forEach(function(step) { addLog(step.status, step.message); });
							}
							addLog('done', 'Product created successfully!');

							resultWrap.style.display = '';
							resultWrap.innerHTML =
								'<div class="sym-import-success">' +
								'<h3>Product Created: ' + escHtml(d.product_name) + ' (ID: ' + d.product_id + ')</h3>' +
								'<div class="sym-import-stats">' +
								'<span>' + (d.variables_count || 0) + ' option groups</span>' +
								'<span>' + (d.items_count || 0) + ' choices</span>' +
								'<span>' + (d.filters_count || 0) + ' filters</span>' +
								'</div>' +
								'<div class="sym-import-actions">' +
								'<a href="' + d.edit_url + '" class="button button-primary">Edit Product</a> ' +
								'<a href="' + d.view_url + '" target="_blank" class="button">View Product</a>' +
								'</div></div>';
						} else {
							addLog('error', 'Import failed: ' + (data.data?.message || data.data || 'Unknown error'));
						}
					})
					.catch(function(err) {
						addLog('error', 'Network error: ' + err.message);
					})
					.finally(function() {
						btn.disabled = false;
						btn.textContent = 'Import Product';
					});
			});

			function addLog(status, message) {
				var div = document.createElement('div');
				div.className = 'sym-log-entry sym-log-entry--' + status;
				var icons = { start: '&#9654;', fetch: '&#8635;', parse: '&#128270;', create: '&#128230;', options: '&#9881;', formula: '&#128200;', done: '&#10003;', error: '&#10007;' };
				div.innerHTML = '<span class="sym-log-icon">' + (icons[status] || '&#8226;') + '</span>' +
					'<span class="sym-log-text">' + escHtml(message) + '</span>' +
					'<span class="sym-log-time">' + new Date().toLocaleTimeString() + '</span>';
				log.appendChild(div);
				log.scrollTop = log.scrollHeight;
			}

			function escHtml(s) { var d = document.createElement('div'); d.appendChild(document.createTextNode(s)); return d.innerHTML; }
		})();
		</script>
		<style>
			.sym-import-input-row { display: flex; gap: 8px; align-items: center; }
			.sym-import-btn { white-space: nowrap; height: 38px; }
			.sym-import-log { max-height: 320px; overflow-y: auto; background: #1a1b1f; border: 1px solid #333; border-radius: 8px; padding: 12px; font-family: monospace; font-size: 13px; }
			.sym-log-entry { display: flex; align-items: center; gap: 8px; padding: 6px 0; border-bottom: 1px solid rgba(255,255,255,0.05); }
			.sym-log-icon { width: 20px; text-align: center; }
			.sym-log-text { flex: 1; color: rgba(255,255,255,0.8); }
			.sym-log-time { font-size: 11px; color: rgba(255,255,255,0.3); }
			.sym-log-entry--done .sym-log-text { color: #4ade80; font-weight: 600; }
			.sym-log-entry--error .sym-log-text { color: #f87171; font-weight: 600; }
			.sym-log-entry--start .sym-log-text { color: #60a5fa; }
			.sym-log-entry--fetch .sym-log-text { color: #a78bfa; }
			.sym-import-success { background: rgba(74,222,128,0.08); border: 1px solid rgba(74,222,128,0.2); border-radius: 8px; padding: 16px; margin-top: 12px; }
			.sym-import-success h3 { margin: 0 0 8px; font-size: 16px; color: #4ade80; }
			.sym-import-stats { display: flex; gap: 16px; margin-bottom: 12px; }
			.sym-import-stats span { font-size: 13px; color: rgba(255,255,255,0.6); }
			.sym-import-actions { display: flex; gap: 8px; }
			.sym-import-table { width: 100%; border-collapse: collapse; }
			.sym-import-table th, .sym-import-table td { text-align: left; padding: 10px 12px; border-bottom: 1px solid rgba(255,255,255,0.07); }
			.sym-import-table th { font-size: 11px; text-transform: uppercase; color: rgba(255,255,255,0.4); }
			.sym-import-id { font-size: 11px; color: rgba(255,255,255,0.3); margin-left: 6px; }
			.sym-status-badge { font-size: 11px; padding: 2px 8px; border-radius: 4px; }
			.sym-status-badge--publish { background: rgba(74,222,128,0.15); color: #4ade80; }
			.sym-status-badge--draft { background: rgba(255,255,255,0.08); color: rgba(255,255,255,0.5); }
			.sym-empty-state { color: rgba(255,255,255,0.4); font-style: italic; padding: 20px 0; }
		</style>
		<?php
	}

	// -------------------------------------------------------------------------
	// Field helpers
	// -------------------------------------------------------------------------
	private static function toggle_field( string $key, string $label, array $opts, string $desc = '' ): void {
		$name    = esc_attr( self::OPTION_KEY . '[' . $key . ']' );
		$checked = ! empty( $opts[ $key ] ) && $opts[ $key ] !== '0';
		?>
		<label class="sym-toggle">
			<input type="checkbox" name="<?php echo $name; // phpcs:ignore ?>" value="1" <?php checked( $checked ); ?> class="sym-toggle-input" data-toggle-key="<?php echo esc_attr( $key ); ?>">
			<span class="sym-toggle-track"><span class="sym-toggle-thumb"></span></span>
			<span class="sym-toggle-label">
				<span class="sym-toggle-title"><?php echo esc_html( $label ); ?></span>
				<?php if ( $desc ) : ?>
					<span class="sym-toggle-desc"><?php echo esc_html( $desc ); ?></span>
				<?php endif; ?>
			</span>
		</label>
		<?php
	}

	private static function color_field( string $key, string $label, array $opts, string $desc = '' ): void {
		$name  = esc_attr( self::OPTION_KEY . '[' . $key . ']' );
		$value = esc_attr( $opts[ $key ] ?? '#000000' );
		?>
		<div class="sym-field sym-field--color">
			<div class="sym-color-row">
				<input type="color" id="<?php echo esc_attr( $key ); ?>" name="<?php echo $name; // phpcs:ignore ?>" value="<?php echo $value; // phpcs:ignore ?>" data-preview-key="<?php echo esc_attr( $key ); ?>">
				<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
			</div>
			<?php if ( $desc ) : ?>
				<p class="sym-field-hint"><?php echo esc_html( $desc ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function number_field( string $key, string $label, array $opts, int $min, int $max, int $step, string $desc = '' ): void {
		$name  = esc_attr( self::OPTION_KEY . '[' . $key . ']' );
		$value = esc_attr( (string) absint( $opts[ $key ] ?? 0 ) );
		?>
		<div class="sym-field">
			<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
			<input
				type="number"
				id="<?php echo esc_attr( $key ); ?>"
				name="<?php echo $name; // phpcs:ignore ?>"
				value="<?php echo $value; // phpcs:ignore ?>"
				min="<?php echo esc_attr( (string) $min ); ?>"
				max="<?php echo esc_attr( (string) $max ); ?>"
				step="<?php echo esc_attr( (string) $step ); ?>"
				class="sym-num-input"
				data-layout-key="<?php echo esc_attr( $key ); ?>"
			>
			<?php if ( $desc ) : ?>
				<p class="sym-field-hint"><?php echo esc_html( $desc ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function text_field( string $key, string $label, array $opts, string $desc = '' ): void {
		$name  = esc_attr( self::OPTION_KEY . '[' . $key . ']' );
		$value = esc_attr( $opts[ $key ] ?? '' );
		?>
		<div class="sym-field">
			<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
			<input type="text" id="<?php echo esc_attr( $key ); ?>" name="<?php echo $name; // phpcs:ignore ?>" value="<?php echo $value; // phpcs:ignore ?>" class="sym-text-input">
			<?php if ( $desc ) : ?>
				<p class="sym-field-hint"><?php echo esc_html( $desc ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Setup Wizard
	// -------------------------------------------------------------------------
	private static function needs_setup(): bool {
		if ( ! class_exists( 'WCAIC_Admin' ) ) {
			return false;
		}
		$encrypted = get_option( 'wcaic_api_keys_encrypted', [] );
		return empty( $encrypted['openai'] ) && empty( $encrypted['anthropic'] );
	}

	private static function render_setup_wizard(): void {
		$wizard_saved = false;
		if ( isset( $_POST['sym_wizard_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sym_wizard_nonce'] ) ), 'sym_wizard_save' ) ) {
			// Save provider + key
			$provider = in_array( $_POST['wizard_provider'] ?? '', [ 'openai', 'anthropic' ], true ) ? $_POST['wizard_provider'] : 'openai';
			$api_key  = sanitize_text_field( $_POST['wizard_api_key'] ?? '' );
			$preset   = sanitize_text_field( $_POST['wizard_preset'] ?? 'friendly_shop' );

			if ( $api_key ) {
				$settings = (array) get_option( 'wcaic_settings', [] );
				$settings['provider'] = $provider;
				update_option( 'wcaic_settings', $settings, false );

				$encrypted = get_option( 'wcaic_api_keys_encrypted', [] );
				if ( ! is_array( $encrypted ) ) { $encrypted = []; }
				$encrypted[ $provider ] = WCAIC_Admin::encrypt_key( $api_key );
				update_option( 'wcaic_api_keys_encrypted', $encrypted, false );

				WCAIC_Persona::save( [ 'preset' => $preset ] );
				$wizard_saved = true;
			}
		}

		if ( $wizard_saved ) {
			echo '<script>window.location.href = "' . esc_url( admin_url( 'themes.php?page=symbiotic-theme&settings-updated=1' ) ) . '";</script>';
			return;
		}

		$presets = WCAIC_Persona::presets();
		?>
		<div class="sym-admin-wrap">
			<div class="sym-wizard">
				<div class="sym-wizard-header">
					<svg width="40" height="40" viewBox="0 0 40 40" fill="none">
						<circle cx="20" cy="20" r="19" fill="#9d33d6"/>
						<path d="M10 21C12 16 15.6 14 20 14S28 16 30 21" stroke="#1a1b1f" stroke-width="2" stroke-linecap="round"/>
						<circle cx="20" cy="25" r="3.5" fill="#1a1b1f"/>
					</svg>
					<h1><?php esc_html_e( 'Welcome to Symbiotic Theme', 'symbiotic-theme' ); ?></h1>
					<p><?php esc_html_e( 'Let\'s set up your AI shopping assistant in 3 quick steps.', 'symbiotic-theme' ); ?></p>
				</div>

				<form method="post" class="sym-wizard-form">
					<?php wp_nonce_field( 'sym_wizard_save', 'sym_wizard_nonce' ); ?>

					<!-- Step 1: Provider -->
					<div class="sym-wizard-step">
						<div class="sym-wizard-step-num">1</div>
						<div class="sym-wizard-step-body">
							<h2><?php esc_html_e( 'Choose AI Provider', 'symbiotic-theme' ); ?></h2>
							<div class="sym-wizard-provider-grid">
								<label class="sym-wizard-provider">
									<input type="radio" name="wizard_provider" value="anthropic" checked>
									<strong>Anthropic (Claude)</strong>
									<span><?php esc_html_e( 'Best quality. Claude Sonnet 4.6 recommended.', 'symbiotic-theme' ); ?></span>
								</label>
								<label class="sym-wizard-provider">
									<input type="radio" name="wizard_provider" value="openai">
									<strong>OpenAI (GPT)</strong>
									<span><?php esc_html_e( 'GPT-4o-mini. Good balance of quality and cost.', 'symbiotic-theme' ); ?></span>
								</label>
							</div>
						</div>
					</div>

					<!-- Step 2: API Key -->
					<div class="sym-wizard-step">
						<div class="sym-wizard-step-num">2</div>
						<div class="sym-wizard-step-body">
							<h2><?php esc_html_e( 'Enter API Key', 'symbiotic-theme' ); ?></h2>
							<p class="sym-desc"><?php esc_html_e( 'Your key is encrypted with AES-256-CBC and never exposed to the frontend.', 'symbiotic-theme' ); ?></p>
							<input type="password" name="wizard_api_key" class="sym-text-input" style="max-width:100%;" placeholder="sk-... or sk-ant-..." autocomplete="new-password" required>
						</div>
					</div>

					<!-- Step 3: Persona -->
					<div class="sym-wizard-step">
						<div class="sym-wizard-step-num">3</div>
						<div class="sym-wizard-step-body">
							<h2><?php esc_html_e( 'Choose AI Personality', 'symbiotic-theme' ); ?></h2>
							<div class="sym-preset-grid">
								<?php foreach ( $presets as $key => $preset ) : ?>
									<?php if ( $key === 'custom' ) { continue; } ?>
									<label class="sym-preset-card <?php echo $key === 'friendly_shop' ? 'sym-preset-card--active' : ''; ?>">
										<input type="radio" name="wizard_preset" value="<?php echo esc_attr( $key ); ?>" <?php checked( $key, 'friendly_shop' ); ?> class="sym-preset-radio">
										<strong><?php echo esc_html( $preset['label'] ); ?></strong>
										<span><?php echo esc_html( $preset['description'] ); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
						</div>
					</div>

					<div class="sym-wizard-actions">
						<?php submit_button( __( 'Activate AI Assistant', 'symbiotic-theme' ), 'primary sym-save-btn sym-wizard-btn', 'submit', false ); ?>
						<a href="<?php echo esc_url( admin_url( 'themes.php?page=symbiotic-theme&skip-wizard=1' ) ); ?>" class="sym-wizard-skip">
							<?php esc_html_e( 'Skip for now →', 'symbiotic-theme' ); ?>
						</a>
					</div>
				</form>
			</div>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Admin CSS
	// -------------------------------------------------------------------------
	private static function admin_css(): string {
		return '
		/* ── Ethiuni Design Tokens ── */
		/* gold: #9d33d6  gold-light: #C9A87C  gold-bg: rgba(157,51,214,0.09)  gold-border: rgba(157,51,214,0.25) */
		/* bg: #1a1b1f  surface: #1a1a1a  surface2: #222222  surface3: #2a2a2a */
		/* text: #e8e8e8  text-muted: #999  line: rgba(255,255,255,0.08) */

		/* ── Wrapper ── */
		.sym-admin-wrap { max-width: 1020px; font-family: "DM Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }

		/* ── Header ── */
		.sym-admin-header {
			display: flex; align-items: center; justify-content: space-between;
			margin: 20px 0 0; padding: 20px 28px;
			background: #1a1b1f; border-radius: 14px; color: #e8e8e8;
			border: 1px solid rgba(157,51,214,0.15);
		}
		.sym-admin-title { display: flex; align-items: center; gap: 14px; }
		.sym-admin-logo {
			width: 42px; height: 42px; background: rgba(157,51,214,0.12);
			border-radius: 11px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;
			border: 1px solid rgba(157,51,214,0.2);
		}
		.sym-admin-title h1 { color: #fff; font-size: 18px; margin: 0 0 2px; letter-spacing: -0.01em; }
		.sym-admin-title p  { color: #9d33d6; font-size: 12px; margin: 0; font-weight: 500; }
		.sym-admin-header-right { display: flex; align-items: center; gap: 12px; }
		.sym-saved-badge { background: #9d33d6; color: #1a1b1f; padding: 5px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; }
		.sym-header-link { color: #9d33d6; font-size: 13px; text-decoration: none; font-weight: 500; }
		.sym-header-link:hover { color: #C9A87C; }

		/* ── Body ── */
		.sym-admin-body { background: #fff; border: 1px solid #e0dcd6; border-radius: 14px; margin-top: 10px; overflow: hidden; }

		/* ── Tabs ── */
		.sym-tabs { display: flex; background: #1a1a1a; border-bottom: 1px solid rgba(157,51,214,0.15); padding: 0 20px; gap: 2px; flex-wrap: wrap; }
		.sym-tab {
			background: none; border: none; border-bottom: 2px solid transparent;
			padding: 13px 15px; font-size: 12.5px; font-weight: 500; color: rgba(255,255,255,0.5);
			cursor: pointer; display: flex; align-items: center; gap: 6px;
			margin-bottom: -1px; transition: color .15s, border-color .15s;
		}
		.sym-tab:hover { color: rgba(255,255,255,0.8); }
		.sym-tab--active { color: #9d33d6; border-bottom-color: #9d33d6; }
		.sym-tab svg { flex-shrink: 0; }

		/* ── Form ── */
		.sym-admin-form { }
		.sym-tab-panel { display: none; padding: 28px; }
		.sym-tab-panel--active { display: block; }

		/* ── Two-col ── */
		.sym-two-col { display: flex; gap: 32px; align-items: flex-start; }
		.sym-main-col { flex: 1; min-width: 0; }
		.sym-side-col { width: 220px; flex-shrink: 0; position: sticky; top: 32px; }

		/* ── Sections ── */
		.sym-section { margin-bottom: 28px; padding-bottom: 28px; border-bottom: 1px solid #f3f4f6; }
		.sym-section:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
		.sym-section h2 { font-size: 14px; font-weight: 600; color: #111827; margin: 0 0 4px; }
		.sym-desc { font-size: 12.5px; color: #6b7280; margin: 0 0 16px; line-height: 1.5; }

		/* ── Color grid ── */
		.sym-color-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 12px; }
		.sym-fields-stack { display: flex; flex-direction: column; gap: 14px; max-width: 480px; }

		/* ── Fields ── */
		.sym-field { display: flex; flex-direction: column; gap: 5px; }
		.sym-field label, .sym-field > label { font-size: 12.5px; font-weight: 600; color: #374151; }
		.sym-field-hint { font-size: 11.5px; color: #9ca3af; margin: 2px 0 0; line-height: 1.4; }

		.sym-field--color .sym-color-row { display: flex; align-items: center; gap: 8px; }
		.sym-field--color input[type=color] { width: 40px; height: 32px; border: 1px solid #d1d5db; border-radius: 7px; padding: 2px 3px; cursor: pointer; background: none; flex-shrink: 0; }
		.sym-field--color label { font-size: 12.5px; font-weight: 600; color: #374151; margin: 0; }

		.sym-num-input {
			width: 100px; border: 1px solid #d1d5db; border-radius: 8px;
			padding: 7px 10px; font-size: 13px; color: #111827; outline: none;
			transition: border-color .15s;
		}
		.sym-num-input:focus { border-color: #9d33d6; box-shadow: 0 0 0 3px rgba(157,51,214,.12); }

		.sym-text-input {
			width: 100%; max-width: 400px; border: 1px solid #d1d5db; border-radius: 8px;
			padding: 7px 11px; font-size: 13px; color: #111827; outline: none;
			transition: border-color .15s;
		}
		.sym-text-input:focus { border-color: #9d33d6; box-shadow: 0 0 0 3px rgba(157,51,214,.12); }

		select {
			border: 1px solid #d1d5db; border-radius: 8px; padding: 7px 11px;
			font-size: 13px; color: #111827; background: #fff; outline: none;
			width: 100%; max-width: 280px;
		}

		.sym-code-area {
			width: 100%; border: 1px solid #d1d5db; border-radius: 8px;
			padding: 12px 14px; font-family: "Consolas","Monaco",monospace; font-size: 12.5px;
			color: #111827; background: #f9fafb; resize: vertical; outline: none;
			line-height: 1.6;
		}
		.sym-code-area:focus { border-color: #9d33d6; }

		/* ── Live preview ── */
		.sym-preview-card { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; overflow: hidden; }
		.sym-preview-label { padding: 8px 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: #9ca3af; border-bottom: 1px solid #e5e7eb; }
		.sym-preview-shell { overflow: hidden; }
		.sym-preview-topbar { display: flex; align-items: center; gap: 8px; padding: 10px 12px; }
		.sym-preview-avatar { width: 22px; height: 22px; border-radius: 50%; flex-shrink: 0; }
		.sym-preview-store-name { font-size: 12px; font-weight: 700; line-height: 1.2; }
		.sym-preview-store-sub  { font-size: 10px; opacity: .6; }
		.sym-preview-messages { padding: 10px 12px; display: flex; flex-direction: column; gap: 7px; min-height: 70px; }
		.sym-preview-bot  { align-self: flex-start; padding: 7px 10px; border-radius: 12px; font-size: 11px; max-width: 90%; border: 1px solid rgba(255,255,255,0.07); }
		.sym-preview-user { align-self: flex-end; padding: 7px 10px; border-radius: 12px; font-size: 11px; color: #fff; max-width: 70%; }
		.sym-preview-inputbar { display: flex; align-items: center; gap: 6px; padding: 8px 10px 10px; }
		.sym-preview-input-field { flex: 1; padding: 5px 9px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.06); font-size: 10.5px; }
		.sym-preview-send { width: 26px; height: 26px; border-radius: 7px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 13px; flex-shrink: 0; }

		/* ── Toggle switch ── */
		.sym-toggle-group { display: flex; flex-direction: column; gap: 12px; }
		.sym-toggle { display: flex; align-items: flex-start; gap: 12px; cursor: pointer; }
		.sym-toggle-input { position: absolute; opacity: 0; width: 0; height: 0; }
		.sym-toggle-track { width: 40px; height: 22px; background: #d1d5db; border-radius: 11px; position: relative; flex-shrink: 0; margin-top: 2px; transition: background .2s; }
		.sym-toggle-input:checked + .sym-toggle-track { background: #9d33d6; }
		.sym-toggle-thumb { position: absolute; top: 3px; left: 3px; width: 16px; height: 16px; background: #fff; border-radius: 50%; box-shadow: 0 1px 3px rgba(0,0,0,.2); transition: transform .2s; }
		.sym-toggle-input:checked + .sym-toggle-track .sym-toggle-thumb { transform: translateX(18px); }
		.sym-toggle-label { display: flex; flex-direction: column; gap: 2px; }
		.sym-toggle-title { font-size: 13px; font-weight: 600; color: #374151; }
		.sym-toggle-desc  { font-size: 12px; color: #9ca3af; line-height: 1.4; }

		/* ── Layout diagram ── */
		.sym-diagram { display: flex; gap: 3px; margin-top: 20px; height: 72px; border-radius: 10px; overflow: hidden; border: 1px solid #e5e7eb; }
		.sym-diagram-chat { background: rgba(157,51,214,0.09); flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2px; font-size: 12px; font-weight: 600; color: #9d33d6; }
		.sym-diagram-products { background: #f3f4f6; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2px; font-size: 12px; color: #6b7280; transition: width .2s; }
		.sym-diagram-sidebar { background: #fef9c3; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2px; font-size: 12px; color: #854d0e; width: 80px; flex-shrink: 0; transition: width .2s; }
		.sym-diagram-chat small, .sym-diagram-products small, .sym-diagram-sidebar small { font-size: 10px; font-weight: 400; opacity: .7; }

		/* ── Tips ── */
		.sym-tips { background: #f0fdf4; border: 1px solid #bbf7d0 !important; border-radius: 10px; padding: 16px 20px; }
		.sym-tips h3 { font-size: 13px; font-weight: 600; color: #166534; margin: 0 0 10px; }
		.sym-tips ul { margin: 0; padding: 0 0 0 14px; display: flex; flex-direction: column; gap: 7px; }
		.sym-tips li { font-size: 12.5px; color: #15803d; line-height: 1.5; }

		/* ── Variable reference ── */
		.sym-var-ref { border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; }
		.sym-var-row { display: flex; align-items: baseline; gap: 12px; padding: 7px 12px; border-bottom: 1px solid #f3f4f6; font-size: 12.5px; }
		.sym-var-row:last-child { border-bottom: none; }
		.sym-var-row code { background: #f3f4f6; color: #8B6914; padding: 2px 6px; border-radius: 4px; font-size: 11.5px; min-width: 200px; flex-shrink: 0; }
		.sym-var-row span { color: #6b7280; }

		/* ── Save bar ── */
		.sym-save-bar { display: flex; align-items: center; gap: 14px; padding: 14px 28px; background: #f9fafb; border-top: 1px solid #e5e7eb; }
		.sym-save-btn.button-primary { background: #9d33d6 !important; border-color: #8626b8 !important; box-shadow: none !important; padding: 7px 22px !important; height: auto !important; font-size: 13px !important; border-radius: 8px !important; color: #1a1b1f !important; font-weight: 600 !important; }
		.sym-save-btn.button-primary:hover { background: #8626b8 !important; }
		.sym-save-hint { font-size: 12px; color: #9ca3af; }

		/* ── Reset ── */
		.sym-reset-btn { display: inline-flex; align-items: center; background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; padding: 7px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none; }
		.sym-reset-btn:hover { background: #fecaca; color: #7f1d1d; }

		/* ── Tab separator ── */
		.sym-tab-separator { width: 1px; height: 20px; background: rgba(255,255,255,0.12); margin: auto 6px; flex-shrink: 0; }

		/* ── Notice ── */
		.sym-notice { padding: 10px 16px; border-radius: 8px; font-size: 13px; font-weight: 500; margin-bottom: 20px; }
		.sym-notice--success { background: rgba(157,51,214,0.09); color: #9d33d6; border: 1px solid rgba(157,51,214,0.25); }

		/* ── Preset grid ── */
		.sym-preset-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; }
		.sym-preset-card {
			display: flex; flex-direction: column; gap: 4px; cursor: pointer;
			padding: 14px 16px; border: 1px solid #e5e7eb; border-radius: 10px;
			background: #fff; transition: border-color .15s, background .15s;
		}
		.sym-preset-card:hover { border-color: #9d33d6; background: rgba(157,51,214,0.03); }
		.sym-preset-card--active { border-color: #9d33d6; background: rgba(157,51,214,0.06); box-shadow: 0 0 0 2px rgba(157,51,214,0.15); }
		.sym-preset-card strong { font-size: 13px; color: #111827; }
		.sym-preset-card span { font-size: 12px; color: #6b7280; line-height: 1.4; }
		.sym-preset-radio { position: absolute; opacity: 0; width: 0; height: 0; }

		/* ── Spectrum sliders ── */
		.sym-spectrum-grid { display: flex; flex-direction: column; gap: 16px; max-width: 600px; }
		.sym-spectrum-row { display: flex; align-items: center; gap: 10px; }
		.sym-spectrum-label-left, .sym-spectrum-label-right { font-size: 11.5px; color: #6b7280; min-width: 110px; }
		.sym-spectrum-label-left { text-align: right; }
		.sym-spectrum-label-right { text-align: left; }
		.sym-spectrum-slider {
			flex: 1; -webkit-appearance: none; appearance: none; height: 6px;
			background: #e5e7eb; border-radius: 3px; outline: none;
		}
		.sym-spectrum-slider::-webkit-slider-thumb {
			-webkit-appearance: none; width: 18px; height: 18px;
			background: #9d33d6; border-radius: 50%; cursor: pointer;
			border: 2px solid #fff; box-shadow: 0 1px 3px rgba(0,0,0,.2);
		}
		.sym-spectrum-val { font-size: 12px; color: #9d33d6; font-weight: 600; min-width: 28px; text-align: center; }

		/* ── Knowledge cards ── */
		.sym-knowledge-card {
			background: #fff; border: 1px solid #e5e7eb; border-radius: 10px;
			padding: 16px 20px; margin-bottom: 12px;
		}
		.sym-knowledge-header { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }

		/* ── Language grid ── */
		.sym-lang-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 6px; }
		.sym-lang-check { display: flex; align-items: center; gap: 6px; font-size: 12.5px; color: #374151; padding: 5px 8px; border-radius: 6px; cursor: pointer; transition: background 0.15s; }
		.sym-lang-check:hover { background: rgba(157,51,214,0.06); }
		.sym-lang-check input[type=checkbox] { accent-color: #9d33d6; }

		/* ── Setup Wizard ── */
		.sym-wizard { max-width: 600px; margin: 40px auto; }
		.sym-wizard-header { text-align: center; margin-bottom: 32px; }
		.sym-wizard-header svg { margin-bottom: 16px; }
		.sym-wizard-header h1 { font-size: 24px; font-weight: 700; color: #111827; margin: 0 0 8px; }
		.sym-wizard-header p { font-size: 15px; color: #6b7280; margin: 0; }
		.sym-wizard-form { background: #fff; border: 1px solid #e0dcd6; border-radius: 14px; overflow: hidden; }
		.sym-wizard-step { display: flex; gap: 20px; padding: 24px 28px; border-bottom: 1px solid #f3f4f6; }
		.sym-wizard-step:last-of-type { border-bottom: none; }
		.sym-wizard-step-num { width: 32px; height: 32px; border-radius: 50%; background: #9d33d6; color: #1a1b1f; font-size: 14px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 2px; }
		.sym-wizard-step-body { flex: 1; }
		.sym-wizard-step-body h2 { font-size: 15px; font-weight: 600; color: #111827; margin: 0 0 8px; }
		.sym-wizard-provider-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
		.sym-wizard-provider { display: flex; flex-direction: column; gap: 4px; padding: 14px; border-radius: 10px; border: 2px solid #e5e7eb; cursor: pointer; transition: all 0.15s; }
		.sym-wizard-provider:has(input:checked) { border-color: #9d33d6; background: rgba(157,51,214,0.06); }
		.sym-wizard-provider input { position: absolute; opacity: 0; width: 0; height: 0; }
		.sym-wizard-provider strong { font-size: 13px; color: #111827; }
		.sym-wizard-provider span { font-size: 12px; color: #6b7280; }
		.sym-wizard-actions { display: flex; align-items: center; gap: 16px; padding: 20px 28px; background: #f9fafb; border-top: 1px solid #e5e7eb; }
		.sym-wizard-btn { font-size: 15px !important; padding: 10px 28px !important; }
		.sym-wizard-skip { font-size: 13px; color: #6b7280; text-decoration: none; }
		.sym-wizard-skip:hover { color: #9d33d6; }

		/* ── Responsive ── */
		@media (max-width: 800px) {
			.sym-two-col { flex-direction: column; }
			.sym-side-col { width: 100%; position: static; }
			.sym-spectrum-label-left, .sym-spectrum-label-right { min-width: 70px; font-size: 10.5px; }
			.sym-preset-grid { grid-template-columns: 1fr; }
			.sym-wizard-provider-grid { grid-template-columns: 1fr; }
		}
		';
	}

	// -------------------------------------------------------------------------
	// Admin JS
	// -------------------------------------------------------------------------
	private static function admin_js(): string {
		return '
		(function () {
			var opts = window.symAdminOpts || {};

			/* ── Tab switching ── */
			document.querySelectorAll(".sym-tab").forEach(function (tab) {
				tab.addEventListener("click", function () {
					var target = this.dataset.tab;
					document.querySelectorAll(".sym-tab").forEach(function (t) { t.classList.remove("sym-tab--active"); });
					document.querySelectorAll(".sym-tab-panel").forEach(function (p) { p.classList.remove("sym-tab-panel--active"); });
					this.classList.add("sym-tab--active");
					var panel = document.querySelector(".sym-tab-panel[data-tab=" + target + "]");
					if (panel) panel.classList.add("sym-tab-panel--active");
				});
			});

			/* ── Preview update map ── */
			var previewMap = {
				color_bg:          function (v) { applyBg("prevBg", v); },
				color_surface:     function (v) { applyBg("prevSurface", v); },
				color_surface_2:   function (v) { applyBg("prevSurface2", v); applyBg("prevSurface2b", v); },
				color_primary:     function (v) { applyBg("prevPrimary", v); applyBg("prevUserBubble", v); applyBg("prevSendBtn", v); },
				color_bot_bubble:  function (v) { applyBg("prevBotBubble", v); },
				color_text:        function (v) { applyColor("prevText", v); applyColor("prevBotText", v); },
				color_text_muted:  function (v) { applyColor("prevTextMuted", v); applyColor("prevMuted", v); },
			};

			function applyBg(id, v)    { var el = document.getElementById(id); if (el) el.style.background = v; }
			function applyColor(id, v) { var el = document.getElementById(id); if (el) el.style.color = v; }

			/* Apply saved values on load */
			Object.keys(previewMap).forEach(function (k) { if (opts[k]) previewMap[k](opts[k]); });

			/* Bind color pickers */
			document.querySelectorAll("input[type=color][data-preview-key]").forEach(function (inp) {
				inp.addEventListener("input", function () {
					var fn = previewMap[this.dataset.previewKey];
					if (fn) fn(this.value);
				});
			});

			/* ── Layout diagram ── */
			var rightInput   = document.getElementById("right_width");
			var leftInput    = document.getElementById("left_max_width");
			var sidebarInput = document.getElementById("right_sidebar_width");
			function updateDiagram() {
				var lLabel  = document.getElementById("diagChatLabel");
				var rLabel  = document.getElementById("diagProductsLabel");
				var rBlock  = document.getElementById("diagProducts");
				var sLabel  = document.getElementById("diagSidebarLabel");
				var sBlock  = document.getElementById("diagSidebar");
				if (leftInput  && lLabel) lLabel.textContent = "max " + leftInput.value + "px";
				if (rightInput && rLabel) rLabel.textContent = rightInput.value + "px";
				if (rightInput && rBlock) {
					var rw = Math.min(Math.max(parseInt(rightInput.value, 10) / 8, 60), 180);
					rBlock.style.width = rw + "px";
				}
				if (sidebarInput && sLabel) sLabel.textContent = sidebarInput.value + "px";
				if (sidebarInput && sBlock) {
					var sw = Math.min(Math.max(parseInt(sidebarInput.value, 10) / 4, 50), 120);
					sBlock.style.width = sw + "px";
				}
			}
			if (leftInput)    leftInput.addEventListener("input", updateDiagram);
			if (rightInput)   rightInput.addEventListener("input", updateDiagram);
			if (sidebarInput) sidebarInput.addEventListener("input", updateDiagram);

			/* ── Toggle → diagram visibility ── */
			document.querySelectorAll(".sym-toggle-input[data-toggle-key]").forEach(function (inp) {
				inp.addEventListener("change", function () {
					var key    = this.dataset.toggleKey;
					var active = this.checked;
					if (key === "show_right_sidebar") {
						var sb = document.getElementById("diagSidebar");
						if (sb) sb.style.display = active ? "flex" : "none";
					}
					if (key === "show_product_panel") {
						var pb = document.getElementById("diagProducts");
						if (pb) pb.style.display = active ? "flex" : "none";
					}
					if (key === "layout_fullwidth") {
						var dc = document.getElementById("diagChat");
						if (dc) dc.style.background = active ? "rgba(157,51,214,0.18)" : "rgba(157,51,214,0.09)";
					}
				});
			});

			updateDiagram();
		})();
		';
	}
}
