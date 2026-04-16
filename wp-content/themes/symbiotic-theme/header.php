<!DOCTYPE html>
<?php
$sym_opts = class_exists( 'Symbiotic_Admin' ) ? Symbiotic_Admin::get_options() : [];
$sym_theme_mode = $sym_opts['theme_mode'] ?? 'dark';
?>
<html <?php language_attributes(); ?> data-theme="<?php echo esc_attr( $sym_theme_mode ); ?>">
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
<?php wp_head(); ?>
</head>
<body <?php body_class( 'sym-page-body' ); ?>>
<?php wp_body_open(); ?>

<?php if ( ! is_front_page() && ! is_shop() && ! is_woocommerce() ) : ?>
<header class="sym-page-header">
	<div class="sym-page-header-inner">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="sym-page-logo">
			<img src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/tgm-logo-main.png' ); ?>" alt="<?php bloginfo( 'name' ); ?>" height="24">
		</a>
		<nav class="sym-page-nav">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Shop</a>
			<a href="<?php echo esc_url( home_url( '/about/' ) ); ?>" <?php echo is_page( 'about' ) ? 'class="active"' : ''; ?>>About</a>
			<a href="<?php echo esc_url( home_url( '/blog/' ) ); ?>" <?php echo is_home() || is_single() ? 'class="active"' : ''; ?>>Blog</a>
			<a href="<?php echo esc_url( home_url( '/faq/' ) ); ?>" <?php echo is_page( 'faq' ) ? 'class="active"' : ''; ?>>FAQ</a>
			<a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" <?php echo is_page( 'contact' ) ? 'class="active"' : ''; ?>>Contact</a>
		</nav>
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="sym-page-cta">
			AI Shopping →
		</a>
	</div>
</header>
<?php endif; ?>
