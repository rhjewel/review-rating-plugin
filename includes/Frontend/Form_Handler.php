<?php
/**
 * Frontend review submission handler.
 *
 * @package ReviewRating
 */

namespace ReviewRating\Frontend;

use ReviewRating\Repositories\Review_Repository;
use ReviewRating\Services\Rating_Calculator;
use ReviewRating\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Form_Handler {
	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Repository.
	 *
	 * @var Review_Repository
	 */
	private $repository;

	/**
	 * Calculator.
	 *
	 * @var Rating_Calculator
	 */
	private $calculator;

	/**
	 * Constructor.
	 *
	 * @param Settings          $settings   Settings.
	 * @param Review_Repository $repository Repository.
	 * @param Rating_Calculator $calculator Calculator.
	 */
	public function __construct( Settings $settings, Review_Repository $repository, Rating_Calculator $calculator ) {
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
		add_action( 'template_redirect', array( $this, 'handle_submission' ) );
	}

	/**
	 * Handle review submission.
	 *
	 * @return void
	 */
	public function handle_submission() {
		if ( empty( $_POST['review_rating_submit'] ) ) {
			return;
		}

		$redirect = $this->get_fallback_redirect_url();

		if ( empty( $_POST['review_rating_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['review_rating_nonce'] ) ), 'review_rating_submit' ) ) {
			$this->redirect_with_status( $redirect, 'invalid_nonce' );
		}

		if ( $this->settings->get( 'require_login', false ) && ! is_user_logged_in() ) {
			$this->redirect_with_status( $redirect, 'login_required' );
		}

		if ( $this->settings->get( 'spam_honeypot_enabled', true ) && ! empty( $_POST['review_rating_company'] ) ) {
			$this->redirect_with_status( $redirect, 'spam' );
		}

		$post_id   = isset( $_POST['review_rating_post_id'] ) ? absint( wp_unslash( $_POST['review_rating_post_id'] ) ) : 0;
		$post_type = $post_id ? get_post_type( $post_id ) : '';
		$redirect  = $this->get_redirect_url( $post_id );

		if ( ! $post_id || ! $post_type || ! $this->settings->is_post_type_enabled( $post_type ) ) {
			$this->redirect_with_status( $redirect, 'invalid_post' );
		}

		$name    = isset( $_POST['review_rating_name'] ) ? sanitize_text_field( wp_unslash( $_POST['review_rating_name'] ) ) : '';
		$email   = isset( $_POST['review_rating_email'] ) ? sanitize_email( wp_unslash( $_POST['review_rating_email'] ) ) : '';
		$content = isset( $_POST['review_rating_content'] ) ? sanitize_textarea_field( wp_unslash( $_POST['review_rating_content'] ) ) : '';

		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			$name = $name ? $name : $user->display_name;
			$email = $email ? $email : $user->user_email;
		}

		if ( '' === $name || '' === $content || ( ! is_user_logged_in() && '' === $email ) ) {
			$this->redirect_with_status( $redirect, 'missing_fields' );
		}

		if ( $this->settings->get( 'one_review_per_user', false ) && $this->repository->has_reviewer_reviewed_post( $post_id, $email ) ) {
			$this->redirect_with_status( $redirect, 'duplicate' );
		}

		$criteria = $this->sanitize_ratings( isset( $_POST['review_rating_criteria'] ) ? $_POST['review_rating_criteria'] : array() );

		if ( empty( $criteria ) ) {
			$this->redirect_with_status( $redirect, 'missing_rating' );
		}

		$average = $this->calculator->calculate_review_average( $criteria );

		do_action( 'review_rating_before_review_insert', $post_id, $criteria );

		$review_id = $this->repository->insert_review(
			array(
				'post_id'          => $post_id,
				'post_type'        => $post_type,
				'reviewer_name'    => $name,
				'reviewer_email'   => $email,
				'content'          => $content,
				'criteria'         => $criteria,
				'average'          => $average,
				'require_approval' => $this->settings->get( 'require_approval', true ),
			)
		);

		if ( is_wp_error( $review_id ) ) {
			$this->redirect_with_status( $redirect, 'error' );
		}

		if ( ! $this->settings->get( 'require_approval', true ) ) {
			$this->calculator->recalculate_post_cache( $post_id );
		}

		$this->maybe_send_notification( $review_id, $post_id, $name );

		$this->redirect_with_status( $redirect, 'success' );
	}

	/**
	 * Sanitize submitted ratings.
	 *
	 * @param array $raw_ratings Raw ratings.
	 * @return array
	 */
	private function sanitize_ratings( $raw_ratings ) {
		$raw_ratings = is_array( $raw_ratings ) ? wp_unslash( $raw_ratings ) : array();
		$criteria    = array();

		foreach ( $this->settings->get_enabled_criteria() as $key => $label ) {
			$value = isset( $raw_ratings[ $key ] ) ? absint( $raw_ratings[ $key ] ) : 0;

			if ( $value < 1 || $value > 5 ) {
				return array();
			}

			$criteria[ $key ] = $value;
		}

		return $criteria;
	}

	/**
	 * Send admin notification.
	 *
	 * @param int    $review_id Review ID.
	 * @param int    $post_id   Reviewed post ID.
	 * @param string $name      Reviewer name.
	 * @return void
	 */
	private function maybe_send_notification( $review_id, $post_id, $name ) {
		if ( ! $this->settings->get( 'enable_email', false ) ) {
			return;
		}

		$to = $this->settings->get( 'admin_notification_to', get_option( 'admin_email' ) );

		if ( ! is_email( $to ) ) {
			return;
		}

		$subject = sprintf(
			/* translators: %s: reviewer name */
			__( 'New review submitted by %s', 'review-rating' ),
			$name
		);

		$message = sprintf(
			/* translators: 1: post title, 2: edit review URL */
			__( "A new review was submitted for %1\$s.\n\nModerate it here: %2\$s", 'review-rating' ),
			get_the_title( $post_id ),
			get_edit_post_link( $review_id, '' )
		);

		wp_mail( $to, $subject, $message );
	}

	/**
	 * Redirect with status.
	 *
	 * @param string $url    URL.
	 * @param string $status Status.
	 * @return void
	 */
	private function redirect_with_status( $url, $status ) {
		$url = remove_query_arg( 'review_rating_status', $url );

		wp_safe_redirect( add_query_arg( 'review_rating_status', sanitize_key( $status ), $url ) );
		exit;
	}

	/**
	 * Get redirect URL for the reviewed post.
	 *
	 * @param int $post_id Reviewed post ID.
	 * @return string
	 */
	private function get_redirect_url( $post_id ) {
		$permalink = $post_id ? get_permalink( $post_id ) : '';

		if ( $permalink ) {
			return $permalink;
		}

		return $this->get_fallback_redirect_url();
	}

	/**
	 * Get safe fallback URL.
	 *
	 * @return string
	 */
	private function get_fallback_redirect_url() {
		$referer = wp_get_referer();

		return $referer ? $referer : home_url( '/' );
	}
}
