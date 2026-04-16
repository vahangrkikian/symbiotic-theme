<?php
// For WooCommerce product pages, render React workspace
if ( is_singular( 'product' ) ) {
	get_header();
	echo '<div id="symbiotic-root"></div>';
	get_footer();
	return;
}
?>
<?php get_header(); ?>

<main class="sym-page-main">
	<article class="sym-page-article sym-page-article--post">
		<?php while ( have_posts() ) : the_post(); ?>
			<div class="sym-post-meta">
				<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date( 'F j, Y' ) ); ?></time>
				<?php
				$cats = get_the_category();
				if ( $cats ) {
					echo '<span class="sym-post-cat">' . esc_html( $cats[0]->name ) . '</span>';
				}
				?>
				<span class="sym-post-reading"><?php echo esc_html( ceil( str_word_count( get_the_content() ) / 200 ) ); ?> min read</span>
			</div>
			<h1 class="sym-page-title"><?php the_title(); ?></h1>
			<?php if ( has_post_thumbnail() ) : ?>
				<div class="sym-post-hero">
					<?php the_post_thumbnail( 'large', [ 'class' => 'sym-post-hero-img' ] ); ?>
				</div>
			<?php endif; ?>
			<div class="sym-page-content sym-prose">
				<?php the_content(); ?>
			</div>
			<div class="sym-post-footer">
				<a href="<?php echo esc_url( home_url( '/blog/' ) ); ?>" class="sym-post-back">← Back to Blog</a>
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="sym-post-cta">Ask AI about this topic →</a>
			</div>
		<?php endwhile; ?>
	</article>
</main>

<?php get_footer(); ?>
