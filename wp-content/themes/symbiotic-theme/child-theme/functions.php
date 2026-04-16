<?php
/**
 * Symbiotic Theme Child — functions.php
 */
defined( 'ABSPATH' ) || exit;

add_action( 'wp_enqueue_scripts', function (): void {
	wp_enqueue_style(
		'symbiotic-child',
		get_stylesheet_uri(),
		[ 'symbiotic-main' ],
		wp_get_theme()->get( 'Version' )
	);
} );
