<?php
defined( 'ABSPATH' ) || exit;
/**
 * Chat widget HTML — toggle button + panel are rendered by chatbot-widget.js.
 * This template is the server-side mount point; JS builds the DOM dynamically.
 * No HTML is output here to avoid duplicate rendering.
 */
// Widget JS builds the full DOM on DOMContentLoaded.
// The template hook is kept so child themes can hook into wcaic_before/after_widget.
do_action( 'wcaic_before_widget' );
do_action( 'wcaic_after_widget' );
