<?php
/**
 * Post Collector: Queries and structures recent WordPress news posts.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ADNL_Post_Collector {

	/**
	 * Retrieve latest posts formatted for newsletter digest.
	 *
	 * @return array Array of formatted post items.
	 */
	public function get_latest_news_posts() {
		$selection_mode    = get_option( 'adnl_selection_mode', 'auto' );
		$selected_post_ids = get_option( 'adnl_selected_post_ids', array() );

		// If Manual Selection mode is chosen and posts are selected, send ONLY those posts
		if ( 'manual' === $selection_mode && ! empty( $selected_post_ids ) && is_array( $selected_post_ids ) ) {
			$post_types = get_option( 'adnl_post_types', array( 'post' ) );
			$args = array(
				'post_type'      => ! empty( $post_types ) ? $post_types : array( 'post' ),
				'post_status'    => 'publish',
				'post__in'       => array_map( 'intval', $selected_post_ids ),
				'orderby'        => 'post__in',
				'posts_per_page' => count( $selected_post_ids ),
				'no_found_rows'  => true,
			);
			$query = new WP_Query( $args );
			$posts = $query->posts;

			$formatted_posts = array();
			foreach ( $posts as $post ) {
				$formatted_posts[] = $this->format_post( $post );
			}
			wp_reset_postdata();
			return $formatted_posts;
		}

		$posts_count    = intval( get_option( 'adnl_posts_count', 7 ) );
		$lookback_hours = intval( get_option( 'adnl_lookback_hours', 24 ) );
		$post_types     = get_option( 'adnl_post_types', array( 'post' ) );
		$categories     = get_option( 'adnl_categories', array() );
		$fallback       = get_option( 'adnl_fallback_behavior', 'latest' );

		// Cap posts count safely between 1 and 20 (defaults 5-10)
		$posts_count = max( 1, min( 20, $posts_count ) );

		$args = array(
			'post_type'      => ! empty( $post_types ) ? $post_types : array( 'post' ),
			'post_status'    => 'publish',
			'posts_per_page' => $posts_count,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		);

		// Category filter if specified
		if ( ! empty( $categories ) && is_array( $categories ) ) {
			$args['category__in'] = array_map( 'intval', $categories );
		}

		// Lookback date query
		if ( $lookback_hours > 0 ) {
			$args['date_query'] = array(
				array(
					'after'     => sprintf( '%d hours ago', $lookback_hours ),
					'inclusive' => true,
				),
			);
		}

		$query = new WP_Query( $args );
		$posts = $query->posts;

		// If no posts found within lookback timeframe and fallback is 'latest', fetch without date_query
		if ( empty( $posts ) && 'latest' === $fallback ) {
			unset( $args['date_query'] );
			$query = new WP_Query( $args );
			$posts = $query->posts;
		}

		// Ultimate fallback: if still empty, fetch latest published posts without category filter
		if ( empty( $posts ) ) {
			$fallback_args = array(
				'post_type'      => ! empty( $post_types ) ? $post_types : array( 'post' ),
				'post_status'    => 'publish',
				'posts_per_page' => $posts_count,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			);
			$query = new WP_Query( $fallback_args );
			$posts = $query->posts;
		}

		$formatted_posts = array();

		foreach ( $posts as $post ) {
			$formatted_posts[] = $this->format_post( $post );
		}

		wp_reset_postdata();

		return $formatted_posts;
	}

	/**
	 * Format single WP_Post object into structured newsletter item.
	 *
	 * @param WP_Post $post
	 * @return array
	 */
	private function format_post( $post ) {
		$post_id   = $post->ID;
		$title     = get_the_title( $post_id );
		$permalink = get_permalink( $post_id );
		
		// Featured image or fallback
		$thumbnail_id  = get_post_thumbnail_id( $post_id );
		$thumbnail_url = '';
		if ( $thumbnail_id ) {
			$image_src = wp_get_attachment_image_src( $thumbnail_id, 'medium_large' );
			if ( $image_src ) {
				$thumbnail_url = $image_src[0];
			}
		}

		// Excerpt cleaning
		$excerpt = has_excerpt( $post_id ) ? $post->post_excerpt : $post->post_content;
		$excerpt = strip_shortcodes( $excerpt );
		$excerpt = wp_strip_all_tags( $excerpt );
		$excerpt = wp_trim_words( $excerpt, 22, '...' );

		// Category
		$categories = get_the_category( $post_id );
		$category_name = ! empty( $categories ) ? $categories[0]->name : __( 'News', 'auto-daily-newsletter' );

		// Estimated reading time
		$word_count = str_word_count( wp_strip_all_tags( $post->post_content ) );
		$reading_time = max( 1, ceil( $word_count / 200 ) ) . ' min read';

		return array(
			'id'            => $post_id,
			'title'         => $title,
			'permalink'     => $permalink,
			'excerpt'       => $excerpt,
			'date'          => get_the_date( '', $post_id ),
			'author'        => get_the_author_meta( 'display_name', $post->post_author ),
			'category'      => $category_name,
			'thumbnail_url' => $thumbnail_url,
			'read_time'     => $reading_time,
		);
	}

	/**
	 * Retrieve latest published news posts for admin manual curation checklist.
	 *
	 * @param int $limit
	 * @return array
	 */
	public function get_recent_posts_for_selection( $limit = 20 ) {
		$post_types = get_option( 'adnl_post_types', array( 'post' ) );
		$args = array(
			'post_type'      => ! empty( $post_types ) ? $post_types : array( 'post' ),
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		$query = new WP_Query( $args );
		$items = array();
		if ( ! empty( $query->posts ) ) {
			foreach ( $query->posts as $p ) {
				$items[] = $this->format_post( $p );
			}
		}
		wp_reset_postdata();
		return $items;
	}
}
