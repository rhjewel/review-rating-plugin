<?php
/**
 * Review data access.
 *
 * @package ReviewRating
 */

namespace ReviewRating\Repositories;

use ReviewRating\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Review_Repository {
	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings instance.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Get approved reviews for a post.
	 *
	 * @param int $post_id Reviewed post ID.
	 * @param int $limit   Number of reviews.
	 * @param int $offset  Query offset.
	 * @return array
	 */
	public function get_reviews_for_post( $post_id, $limit = -1, $offset = 0 ) {
		return get_posts(
			array(
				'post_type'      => Settings::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => (int) $limit,
				'offset'         => absint( $offset ),
				'orderby'        => 'date',
				'order'          => 'DESC',
				'meta_query'     => $this->get_post_reviews_meta_query( $post_id ),
			)
		);
	}

	/**
	 * Count approved reviews for a post.
	 *
	 * @param int $post_id Reviewed post ID.
	 * @return int
	 */
	public function count_reviews_for_post( $post_id ) {
		$query = new \WP_Query(
			array(
				'post_type'      => Settings::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => false,
				'meta_query'     => $this->get_post_reviews_meta_query( $post_id ),
			)
		);

		return absint( $query->found_posts );
	}

	/**
	 * Insert a review.
	 *
	 * @param array $data Review data.
	 * @return int|\WP_Error
	 */
	public function insert_review( array $data ) {
		$post_status = ! empty( $data['require_approval'] ) ? 'pending' : 'publish';
		$review_id   = wp_insert_post(
			array(
				'post_title'   => $data['reviewer_name'],
				'post_content' => $data['content'],
				'post_type'    => Settings::POST_TYPE,
				'post_status'  => $post_status,
			),
			true
		);

		if ( is_wp_error( $review_id ) ) {
			return $review_id;
		}

		update_post_meta( $review_id, Settings::META_POST_ID, absint( $data['post_id'] ) );
		update_post_meta( $review_id, Settings::META_POST_TYPE, sanitize_key( $data['post_type'] ) );
		update_post_meta( $review_id, Settings::META_REVIEWER_NAME, sanitize_text_field( $data['reviewer_name'] ) );
		update_post_meta( $review_id, Settings::META_REVIEWER_EMAIL, sanitize_email( $data['reviewer_email'] ) );
		update_post_meta( $review_id, Settings::META_CRITERIA, $data['criteria'] );
		update_post_meta( $review_id, Settings::META_AVERAGE, (float) $data['average'] );

		// Legacy keys keep older theme integrations and existing shortcodes usable.
		update_post_meta( $review_id, '_review_post_id', absint( $data['post_id'] ) );
		update_post_meta( $review_id, '_rating_overall', (float) $data['average'] );

		foreach ( $data['criteria'] as $key => $value ) {
			update_post_meta( $review_id, '_rating_' . sanitize_key( $key ), absint( $value ) );
		}

		do_action( 'review_rating_after_review_insert', $review_id, $data );

		return $review_id;
	}

	/**
	 * Get criteria ratings from a review.
	 *
	 * @param int $review_id Review ID.
	 * @return array
	 */
	public function get_review_criteria( $review_id ) {
		$criteria = get_post_meta( $review_id, Settings::META_CRITERIA, true );

		if ( is_array( $criteria ) ) {
			return array_map( 'absint', $criteria );
		}

		$legacy = array();

		foreach ( $this->settings->get_enabled_criteria() as $key => $label ) {
			$value = get_post_meta( $review_id, '_rating_' . $key, true );

			if ( '' !== $value ) {
				$legacy[ $key ] = absint( $value );
			}
		}

		return $legacy;
	}

	/**
	 * Get one review average.
	 *
	 * @param int $review_id Review ID.
	 * @return float
	 */
	public function get_review_average( $review_id ) {
		$average = get_post_meta( $review_id, Settings::META_AVERAGE, true );

		if ( '' === $average ) {
			$average = get_post_meta( $review_id, '_rating_overall', true );
		}

		return (float) $average;
	}

	/**
	 * Store images attached to a review.
	 *
	 * @param int   $review_id      Review ID.
	 * @param array $attachment_ids Attachment IDs.
	 * @return void
	 */
	public function set_review_images( $review_id, array $attachment_ids ) {
		$attachment_ids = array_values( array_filter( array_map( 'absint', $attachment_ids ) ) );
		$attachment_ids = array_slice( $attachment_ids, 0, $this->settings->get_max_review_images() );

		if ( empty( $attachment_ids ) ) {
			delete_post_meta( $review_id, Settings::META_IMAGES );
			return;
		}

		update_post_meta( $review_id, Settings::META_IMAGES, $attachment_ids );
	}

	/**
	 * Get images attached to a review.
	 *
	 * @param int $review_id Review ID.
	 * @return array
	 */
	public function get_review_images( $review_id ) {
		$attachment_ids = get_post_meta( $review_id, Settings::META_IMAGES, true );

		if ( ! is_array( $attachment_ids ) ) {
			return array();
		}

		return array_slice( array_values( array_filter( array_map( 'absint', $attachment_ids ) ) ), 0, $this->settings->get_max_review_images() );
	}

	/**
	 * Determine whether this visitor/user already reviewed the post.
	 *
	 * @param int    $post_id Reviewed post ID.
	 * @param string $email   Reviewer email.
	 * @return bool
	 */
	public function has_reviewer_reviewed_post( $post_id, $email ) {
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			$email = $user && $user->user_email ? $user->user_email : $email;
		}

		if ( empty( $email ) ) {
			return false;
		}

		$query = get_posts(
			array(
				'post_type'      => Settings::POST_TYPE,
				'post_status'    => array( 'pending', 'publish', 'draft' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => Settings::META_POST_ID,
						'value' => absint( $post_id ),
					),
					array(
						'key'   => Settings::META_REVIEWER_EMAIL,
						'value' => sanitize_email( $email ),
					),
				),
			)
		);

		return ! empty( $query );
	}

	/**
	 * Build meta query for reviews attached to a post.
	 *
	 * @param int $post_id Reviewed post ID.
	 * @return array
	 */
	private function get_post_reviews_meta_query( $post_id ) {
		return array(
			'relation' => 'OR',
			array(
				'key'   => Settings::META_POST_ID,
				'value' => absint( $post_id ),
			),
			array(
				'key'   => '_review_post_id',
				'value' => absint( $post_id ),
			),
		);
	}
}
