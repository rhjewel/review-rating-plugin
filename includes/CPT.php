<?php
/**
 * Review custom post type and admin list behavior.
 *
 * @package ReviewRating
 */

namespace ReviewRating;

use ReviewRating\Repositories\Review_Repository;
use ReviewRating\Services\Rating_Calculator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CPT {
	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Repository.
	 *
	 * @var Review_Repository|null
	 */
	private $repository;

	/**
	 * Calculator.
	 *
	 * @var Rating_Calculator|null
	 */
	private $calculator;

	/**
	 * Constructor.
	 *
	 * @param Settings               $settings   Settings.
	 * @param Review_Repository|null $repository Repository.
	 * @param Rating_Calculator|null $calculator Calculator.
	 */
	public function __construct( Settings $settings, ?Review_Repository $repository = null, ?Rating_Calculator $calculator = null ) {
		$this->settings   = $settings;
		$this->repository = $repository;
		$this->calculator = $calculator;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register_review_post_type' ) );
		add_action( 'init', array( $this, 'register_review_meta' ) );

		add_filter( 'manage_' . Settings::POST_TYPE . '_posts_columns', array( $this, 'add_custom_columns' ) );
		add_action( 'manage_' . Settings::POST_TYPE . '_posts_custom_column', array( $this, 'render_custom_columns' ), 10, 2 );
		add_filter( 'manage_edit-' . Settings::POST_TYPE . '_sortable_columns', array( $this, 'sortable_columns' ) );

		add_action( 'restrict_manage_posts', array( $this, 'add_post_type_filter' ) );
		add_action( 'pre_get_posts', array( $this, 'filter_reviews_query' ) );

		add_action( 'admin_post_review_rating_toggle_status', array( $this, 'toggle_review_status' ) );
		add_action( 'transition_post_status', array( $this, 'recalculate_after_status_change' ), 10, 3 );
		add_action( 'deleted_post', array( $this, 'recalculate_after_delete' ), 10, 2 );
		add_action( 'admin_init', array( $this, 'migrate_legacy_review_meta' ) );
	}

	/**
	 * Register review post type.
	 *
	 * @return void
	 */
	public function register_review_post_type() {
		$labels = array(
			'name'               => esc_html_x( 'Reviews & Ratings', 'post type general name', 'review-rating' ),
			'singular_name'      => esc_html_x( 'Review & Rating', 'post type singular name', 'review-rating' ),
			'menu_name'          => esc_html__( 'Reviews & Ratings', 'review-rating' ),
			'all_items'          => esc_html__( 'All Reviews', 'review-rating' ),
			'edit_item'          => esc_html__( 'Edit Review', 'review-rating' ),
			'view_item'          => esc_html__( 'View Review', 'review-rating' ),
			'search_items'       => esc_html__( 'Search Reviews', 'review-rating' ),
			'not_found'          => esc_html__( 'No reviews found', 'review-rating' ),
			'not_found_in_trash' => esc_html__( 'No reviews found in Trash', 'review-rating' ),
		);

		register_post_type(
			Settings::POST_TYPE,
			array(
				'labels'              => $labels,
				'description'         => esc_html__( 'Multi-criteria review submissions.', 'review-rating' ),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'publicly_queryable'  => false,
				'has_archive'         => false,
				'exclude_from_search' => true,
				'show_in_rest'        => false,
				'supports'            => array( 'title', 'editor' ),
				'capability_type'     => 'post',
				'capabilities'        => array(
					'create_posts' => 'do_not_allow',
				),
				'map_meta_cap'        => true,
				'menu_icon'           => 'dashicons-star-filled',
			)
		);
	}

	/**
	 * Register post meta.
	 *
	 * @return void
	 */
	public function register_review_meta() {
		$auth_callback = static function () {
			return current_user_can( 'edit_posts' );
		};

		foreach ( array( Settings::META_POST_ID, Settings::META_POST_TYPE, Settings::META_REVIEWER_NAME, Settings::META_REVIEWER_EMAIL, Settings::META_AVERAGE ) as $meta_key ) {
			register_post_meta(
				Settings::POST_TYPE,
				$meta_key,
				array(
					'single'        => true,
					'type'          => 'string',
					'auth_callback' => $auth_callback,
					'show_in_rest'  => false,
				)
			);
		}

		register_post_meta(
			Settings::POST_TYPE,
			Settings::META_CRITERIA,
			array(
				'single'        => true,
				'type'          => 'array',
				'auth_callback' => $auth_callback,
				'show_in_rest'  => false,
			)
		);
	}

	/**
	 * Add admin columns.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function add_custom_columns( $columns ) {
		$new_columns = array();

		$new_columns['cb']                 = isset( $columns['cb'] ) ? $columns['cb'] : '';
		$new_columns['title']              = esc_html__( 'Reviewer', 'review-rating' );
		$new_columns['review_rating']      = esc_html__( 'Rating', 'review-rating' );
		$new_columns['review_target']      = esc_html__( 'Reviewed Post', 'review-rating' );
		$new_columns['review_post_type']   = esc_html__( 'Post Type', 'review-rating' );
		$new_columns['review_message']     = esc_html__( 'Review', 'review-rating' );
		$new_columns['review_moderation']  = esc_html__( 'Moderation', 'review-rating' );
		$new_columns['date']               = isset( $columns['date'] ) ? $columns['date'] : esc_html__( 'Date', 'review-rating' );

		return $new_columns;
	}

	/**
	 * Render admin columns.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Review ID.
	 * @return void
	 */
	public function render_custom_columns( $column, $post_id ) {
		if ( 'review_rating' === $column ) {
			$average = get_post_meta( $post_id, Settings::META_AVERAGE, true );

			if ( '' === $average ) {
				$average = get_post_meta( $post_id, '_rating_overall', true );
			}

			echo '<strong>' . esc_html( number_format_i18n( (float) $average, 1 ) ) . '</strong> / 5';
		}

		if ( 'review_target' === $column ) {
			$target_id = $this->get_review_target_id( $post_id );
			$title     = $target_id ? get_the_title( $target_id ) : '';

			if ( $target_id && $title ) {
				echo '<a href="' . esc_url( get_edit_post_link( $target_id ) ) . '">' . esc_html( $title ) . '</a>';
			} else {
				echo esc_html__( 'Unknown', 'review-rating' );
			}
		}

		if ( 'review_post_type' === $column ) {
			$post_type = get_post_meta( $post_id, Settings::META_POST_TYPE, true );

			if ( ! $post_type ) {
				$target_id = $this->get_review_target_id( $post_id );
				$post_type = $target_id ? get_post_type( $target_id ) : '';
			}

			$obj = $post_type ? get_post_type_object( $post_type ) : null;
			echo esc_html( $obj ? $obj->labels->singular_name : $post_type );
		}

		if ( 'review_message' === $column ) {
			echo esc_html( wp_trim_words( get_post_field( 'post_content', $post_id ), 18, '...' ) );
		}

		if ( 'review_moderation' === $column ) {
			$this->render_status_toggle( $post_id );
		}
	}

	/**
	 * Sortable columns.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function sortable_columns( $columns ) {
		$columns['review_rating'] = 'review_rating';

		return $columns;
	}

	/**
	 * Add reviewed post type filter.
	 *
	 * @param string $post_type Current post type.
	 * @return void
	 */
	public function add_post_type_filter( $post_type ) {
		if ( Settings::POST_TYPE !== $post_type ) {
			return;
		}

		$current = isset( $_GET['review_rating_post_type'] ) ? sanitize_key( wp_unslash( $_GET['review_rating_post_type'] ) ) : '';

		echo '<select name="review_rating_post_type">';
		echo '<option value="">' . esc_html__( 'All reviewed post types', 'review-rating' ) . '</option>';

		$reviewable_post_types = $this->settings->get_reviewable_post_types();

		foreach ( $this->settings->get_enabled_post_types() as $slug ) {
			if ( empty( $reviewable_post_types[ $slug ] ) ) {
				continue;
			}

			$object = $reviewable_post_types[ $slug ];

			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $slug ),
				selected( $current, $slug, false ),
				esc_html( $object->labels->singular_name )
			);
		}

		echo '</select>';
	}

	/**
	 * Filter admin query.
	 *
	 * @param \WP_Query $query Query.
	 * @return void
	 */
	public function filter_reviews_query( $query ) {
		global $pagenow;

		if ( ! is_admin() || 'edit.php' !== $pagenow || Settings::POST_TYPE !== $query->get( 'post_type' ) || ! $query->is_main_query() ) {
			return;
		}

		$selected_post_type = isset( $_GET['review_rating_post_type'] ) ? sanitize_key( wp_unslash( $_GET['review_rating_post_type'] ) ) : '';

		if ( $selected_post_type && $this->settings->is_post_type_enabled( $selected_post_type ) ) {
			$query->set(
				'meta_query',
				array(
					array(
						'key'   => Settings::META_POST_TYPE,
						'value' => $selected_post_type,
					),
				)
			);
		}

		if ( 'review_rating' === $query->get( 'orderby' ) ) {
			$query->set( 'meta_key', Settings::META_AVERAGE );
			$query->set( 'orderby', 'meta_value_num' );
		}
	}

	/**
	 * Toggle review status.
	 *
	 * @return void
	 */
	public function toggle_review_status() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You are not allowed to moderate reviews.', 'review-rating' ) );
		}

		$review_id = isset( $_GET['review_id'] ) ? absint( wp_unslash( $_GET['review_id'] ) ) : 0;

		if ( ! $review_id || Settings::POST_TYPE !== get_post_type( $review_id ) ) {
			wp_die( esc_html__( 'Invalid review.', 'review-rating' ) );
		}

		check_admin_referer( 'review_rating_toggle_status_' . $review_id );

		$new_status = 'publish' === get_post_status( $review_id ) ? 'pending' : 'publish';

		wp_update_post(
			array(
				'ID'          => $review_id,
				'post_status' => $new_status,
			)
		);

		$target_id = $this->get_review_target_id( $review_id );

		if ( $target_id && $this->calculator ) {
			$this->calculator->recalculate_post_cache( $target_id );
		}

		wp_safe_redirect( admin_url( 'edit.php?post_type=' . Settings::POST_TYPE ) );
		exit;
	}

	/**
	 * Recalculate aggregate after status changes.
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Post object.
	 * @return void
	 */
	public function recalculate_after_status_change( $new_status, $old_status, $post ) {
		if ( Settings::POST_TYPE !== $post->post_type || $new_status === $old_status || ! $this->calculator ) {
			return;
		}

		$target_id = $this->get_review_target_id( $post->ID );

		if ( $target_id ) {
			$this->calculator->recalculate_post_cache( $target_id );
		}
	}

	/**
	 * Recalculate after deletion.
	 *
	 * @param int      $post_id Deleted post ID.
	 * @param \WP_Post $post    Deleted post.
	 * @return void
	 */
	public function recalculate_after_delete( $post_id, $post ) {
		if ( ! $post || Settings::POST_TYPE !== $post->post_type || ! $this->calculator ) {
			return;
		}

		$target_id = $this->get_review_target_id( $post_id );

		if ( $target_id ) {
			$this->calculator->recalculate_post_cache( $target_id );
		}
	}

	/**
	 * Render approve/unapprove button.
	 *
	 * @param int $post_id Review ID.
	 * @return void
	 */
	private function render_status_toggle( $post_id ) {
		$status = get_post_status( $post_id );
		$url    = wp_nonce_url(
			admin_url( 'admin-post.php?action=review_rating_toggle_status&review_id=' . absint( $post_id ) ),
			'review_rating_toggle_status_' . absint( $post_id )
		);

		if ( 'publish' === $status ) {
			echo '<span class="review-rating-status review-rating-status-approved">' . esc_html__( 'Approved', 'review-rating' ) . '</span> ';
			echo '<a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Unapprove', 'review-rating' ) . '</a>';
			return;
		}

		echo '<span class="review-rating-status review-rating-status-pending">' . esc_html__( 'Pending', 'review-rating' ) . '</span> ';
		echo '<a class="button button-primary" href="' . esc_url( $url ) . '">' . esc_html__( 'Approve', 'review-rating' ) . '</a>';
	}

	/**
	 * Get reviewed post ID.
	 *
	 * @param int $review_id Review ID.
	 * @return int
	 */
	private function get_review_target_id( $review_id ) {
		$target_id = get_post_meta( $review_id, Settings::META_POST_ID, true );

		if ( ! $target_id ) {
			$target_id = get_post_meta( $review_id, '_review_post_id', true );
		}

		return absint( $target_id );
	}

	/**
	 * Backfill new meta keys for reviews created by version 1.0.0.
	 *
	 * @return void
	 */
	public function migrate_legacy_review_meta() {
		if ( get_option( 'review_rating_migrated_110' ) ) {
			return;
		}

		$reviews = get_posts(
			array(
				'post_type'      => Settings::POST_TYPE,
				'post_status'    => array( 'pending', 'publish', 'draft' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( $reviews as $review_id ) {
			$target_id = $this->get_review_target_id( $review_id );

			if ( ! $target_id ) {
				continue;
			}

			$post_type = get_post_type( $target_id );

			if ( ! get_post_meta( $review_id, Settings::META_POST_ID, true ) ) {
				update_post_meta( $review_id, Settings::META_POST_ID, $target_id );
			}

			if ( $post_type && ! get_post_meta( $review_id, Settings::META_POST_TYPE, true ) ) {
				update_post_meta( $review_id, Settings::META_POST_TYPE, $post_type );
			}

			if ( ! get_post_meta( $review_id, Settings::META_REVIEWER_NAME, true ) ) {
				update_post_meta( $review_id, Settings::META_REVIEWER_NAME, get_the_title( $review_id ) );
			}

			if ( ! get_post_meta( $review_id, Settings::META_CRITERIA, true ) ) {
				$criteria = array();

				foreach ( $this->settings->get_enabled_criteria() as $key => $label ) {
					$value = get_post_meta( $review_id, '_rating_' . $key, true );

					if ( '' !== $value ) {
						$criteria[ $key ] = absint( $value );
					}
				}

				if ( ! empty( $criteria ) ) {
					update_post_meta( $review_id, Settings::META_CRITERIA, $criteria );
				}
			}

			if ( ! get_post_meta( $review_id, Settings::META_AVERAGE, true ) ) {
				update_post_meta( $review_id, Settings::META_AVERAGE, (float) get_post_meta( $review_id, '_rating_overall', true ) );
			}

			if ( $target_id && $this->calculator ) {
				$this->calculator->recalculate_post_cache( $target_id );
			}
		}

		update_option( 'review_rating_migrated_110', 1 );
	}
}
