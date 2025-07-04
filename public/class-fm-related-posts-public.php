<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://former-model.com
 * @since      1.0.0
 *
 * @package    Fm_Related_Posts
 * @subpackage Fm_Related_Posts/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Fm_Related_Posts
 * @subpackage Fm_Related_Posts/public
 * @author     Geoff Cordner <geoffcordner@gmail.com>
 */
class Fm_Related_Posts_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
		
		// add_filter( 'render_block', array( $this, 'inject_after_post_content_block' ), 10, 2 );
		add_shortcode( 'fm_related_posts', array( $this, 'render_related_posts_shortcode' ) );


	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Fm_Related_Posts_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Fm_Related_Posts_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/fm-related-posts-public.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Fm_Related_Posts_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Fm_Related_Posts_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/fm-related-posts-public.js', array( 'jquery' ), $this->version, false );

	}

	public function inject_after_post_content_block( $block_content, $block ) {
	if ( is_singular( 'post' ) && isset( $block['blockName'] ) && $block['blockName'] === 'core/post-content' ) {
		$related = $this->get_related_posts_html( get_the_ID() );
		return $block_content . $related;
	}

	return $block_content;
}

public function render_related_posts_shortcode( $atts ) {
	if ( ! is_singular( 'post' ) ) {
		return '';
	}

	$post_id = get_the_ID();
	$related = $this->get_related_posts_html( $post_id );

	// Optionally wrap in full-width styling for FSE themes
	return '<div class="wp-block-group alignwide">' . $related . '</div>';
}



private function get_related_posts_html( $post_id ) {
	// Optional: Add daily variation to cache
	$cache_key = 'fm_related_posts_' . $post_id . '_' . date( 'Ymd' );
	$related_posts = get_transient( $cache_key );

	if ( false === $related_posts ) {
		// STEP 1: Define high-frequency terms to ignore
		$ignored_categories = [
			'advice-without-strings',
			'architect-on-demand',
			'an-online-architect',
		];

		$ignored_tags = [
			'advice-without-strings',
			'online-architect',
			'architect-on-demand',
			'online-architectural-services',
			'diy-architect',
			'diy-ally',
		];

		// STEP 2: Get current post's meaningful categories/tags
		$current_cats = array_filter(
			wp_get_post_categories( $post_id, [ 'fields' => 'all' ] ),
			function( $cat ) use ( $ignored_categories ) {
				return ! in_array( $cat->slug, $ignored_categories );
			}
		);

		$current_tags = array_filter(
			wp_get_post_tags( $post_id, [ 'fields' => 'all' ] ),
			function( $tag ) use ( $ignored_tags ) {
				return ! in_array( $tag->slug, $ignored_tags );
			}
		);

		$current_cat_ids = wp_list_pluck( $current_cats, 'term_id' );
		$current_tag_ids = wp_list_pluck( $current_tags, 'term_id' );

		if ( empty( $current_cat_ids ) && empty( $current_tag_ids ) ) {
			return '';
		}

		// STEP 3: Get candidates
		$candidate_args = [
			'post__not_in' => [ $post_id ],
			'posts_per_page' => 50,
			'post_type' => 'post',
			'ignore_sticky_posts' => true,
			'tax_query' => [],
		];

		if ( ! empty( $current_cat_ids ) ) {
			$candidate_args['tax_query'][] = [
				'taxonomy' => 'category',
				'field'    => 'term_id',
				'terms'    => $current_cat_ids,
			];
		}

		if ( ! empty( $current_tag_ids ) ) {
			$candidate_args['tax_query'][] = [
				'taxonomy' => 'post_tag',
				'field'    => 'term_id',
				'terms'    => $current_tag_ids,
			];
		}

		if ( count( $candidate_args['tax_query'] ) > 1 ) {
			$candidate_args['tax_query']['relation'] = 'OR';
		}

		$query = new WP_Query( $candidate_args );
		$candidates = $query->have_posts() ? $query->posts : [];

		// STEP 4: Score the candidates
		$scored_posts = [];

		foreach ( $candidates as $candidate ) {
			$score = 0;

			$candidate_tags = wp_get_post_tags( $candidate->ID, [ 'fields' => 'ids' ] );
			$candidate_cats = wp_get_post_categories( $candidate->ID );

			$score += count( array_intersect( $candidate_tags, $current_tag_ids ) ) * 1.0;
			$score += count( array_intersect( $candidate_cats, $current_cat_ids ) ) * 0.5;

			if ( $score > 0 ) {
				$scored_posts[ $candidate->ID ] = [
					'post'  => $candidate,
					'score' => $score,
				];
			}
		}

		// STEP 5: Sort by score, then randomly select from top 6
		usort( $scored_posts, function( $a, $b ) {
			return $b['score'] <=> $a['score'];
		} );

		$top_pool = array_slice( array_column( $scored_posts, 'post' ), 0, 6 );

		if ( count( $top_pool ) > 3 ) {
			shuffle( $top_pool );
		}

		$related_posts = array_slice( $top_pool, 0, 3 );

		set_transient( $cache_key, $related_posts, DAY_IN_SECONDS );
	}

	// STEP 6: Output the grid
	if ( empty( $related_posts ) ) {
		return '';
	}

	global $post;
	$original_post = $post;

	ob_start();
	?>
	<div class="fm-related-posts-grid">
		<?php foreach ( $related_posts as $related_post ) : ?>
			<?php
			$post = $related_post;
			setup_postdata( $post );
			?>
			<div class="fm-related-post">
				<a href="<?php the_permalink(); ?>">
					<?php if ( has_post_thumbnail() ) : ?>
						<?php the_post_thumbnail( 'medium' ); ?>
					<?php endif; ?>
					<h4><?php the_title(); ?></h4>
				</a>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
	$post = $original_post;
	wp_reset_postdata();

	echo '<!-- Related Post IDs: ' . implode( ', ', wp_list_pluck( $related_posts, 'ID' ) ) . ' -->';

	return ob_get_clean();
}






}
