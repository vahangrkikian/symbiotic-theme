<?php if ( ! is_front_page() && ! is_shop() && ! is_woocommerce() ) : ?>
<footer class="sym-page-footer">
	<div class="sym-page-footer-inner">
		<div class="sym-footer-col">
			<div class="sym-footer-brand">
				<img src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/tgm-logo-main.png' ); ?>" alt="<?php bloginfo( 'name' ); ?>" height="20">
			</div>
			<p class="sym-footer-tagline"><?php bloginfo( 'description' ); ?></p>
		</div>
		<div class="sym-footer-col">
			<h4>Shop</h4>
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>">AI Shopping</a>
			<a href="<?php echo esc_url( wc_get_cart_url() ); ?>">Cart</a>
			<a href="<?php echo esc_url( wc_get_checkout_url() ); ?>">Checkout</a>
		</div>
		<div class="sym-footer-col">
			<h4>Company</h4>
			<a href="<?php echo esc_url( home_url( '/about/' ) ); ?>">About</a>
			<a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Contact</a>
			<a href="<?php echo esc_url( home_url( '/blog/' ) ); ?>">Blog</a>
		</div>
		<div class="sym-footer-col">
			<h4>Resources</h4>
			<a href="<?php echo esc_url( home_url( '/faq/' ) ); ?>">FAQ</a>
			<a href="<?php echo esc_url( home_url( '/file-preparation-guide/' ) ); ?>">File Prep Guide</a>
			<a href="<?php echo esc_url( home_url( '/shipping-delivery/' ) ); ?>">Shipping</a>
		</div>
	</div>
	<div class="sym-footer-bottom">
		<p>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?>. Powered by Symbiotic Theme.</p>
	</div>
</footer>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>
