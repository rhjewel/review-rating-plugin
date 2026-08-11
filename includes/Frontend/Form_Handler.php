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

		$image_files = array();

		if ( $this->settings->get( 'enable_review_images', false ) ) {
			$image_files = $this->validate_review_images();

			if ( is_wp_error( $image_files ) ) {
				$this->redirect_with_status( $redirect, $image_files->get_error_code() );
			}
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

		if ( ! empty( $image_files ) ) {
			$attachment_ids = $this->upload_review_images( $review_id, $image_files );

			if ( is_wp_error( $attachment_ids ) ) {
				wp_delete_post( $review_id, true );
				$this->redirect_with_status( $redirect, 'invalid_image' );
			}

			$this->repository->set_review_images( $review_id, $attachment_ids );
		}

		if ( ! $this->settings->get( 'require_approval', true ) ) {
			$this->calculator->recalculate_post_cache( $post_id );
		}

		$this->maybe_send_notification( $review_id, $post_id, $name );

		$this->redirect_with_status( $redirect, 'success' );
	}

	/**
	 * Validate and normalize submitted review images.
	 *
	 * @return array|\WP_Error
	 */
	private function validate_review_images() {
		if ( empty( $_FILES['review_rating_images']['name'] ) || ! is_array( $_FILES['review_rating_images']['name'] ) ) {
			return array();
		}

		$uploads = $_FILES['review_rating_images']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$files   = array();

		foreach ( array_keys( $uploads['name'] ) as $index ) {
			$name  = isset( $uploads['name'][ $index ] ) ? sanitize_file_name( wp_unslash( $uploads['name'][ $index ] ) ) : '';
			$error = isset( $uploads['error'][ $index ] ) ? absint( $uploads['error'][ $index ] ) : UPLOAD_ERR_NO_FILE;

			if ( '' === $name || UPLOAD_ERR_NO_FILE === $error ) {
				continue;
			}

			$files[] = array(
				'name'     => $name,
				'type'     => isset( $uploads['type'][ $index ] ) ? sanitize_mime_type( wp_unslash( $uploads['type'][ $index ] ) ) : '',
				'tmp_name' => isset( $uploads['tmp_name'][ $index ] ) ? $uploads['tmp_name'][ $index ] : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				'error'    => $error,
				'size'     => isset( $uploads['size'][ $index ] ) ? absint( $uploads['size'][ $index ] ) : 0,
			);
		}

		if ( count( $files ) > $this->settings->get_max_review_images() ) {
			return new \WP_Error( 'too_many_images' );
		}

		$allowed_mimes = $this->get_allowed_image_mimes();

		foreach ( $files as $file ) {
			if ( UPLOAD_ERR_OK !== $file['error'] || ! $file['tmp_name'] || ! $file['size'] ) {
				return new \WP_Error( 'invalid_image' );
			}

			$file_data = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $allowed_mimes );

			if ( empty( $file_data['ext'] ) || empty( $file_data['type'] ) || 0 !== strpos( $file_data['type'], 'image/' ) ) {
				return new \WP_Error( 'invalid_image' );
			}
		}

		return $files;
	}

	/**
	 * Upload validated review images to the WordPress media library.
	 *
	 * @param int   $review_id Review ID.
	 * @param array $files     Normalized files.
	 * @return array|\WP_Error
	 */
	private function upload_review_images( $review_id, array $files ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$attachment_ids = array();
		$file_key       = 'review_rating_single_image';
		$had_original   = array_key_exists( $file_key, $_FILES );
		$original_file  = $had_original ? $_FILES[ $file_key ] : null;

		foreach ( $files as $file ) {
			$_FILES[ $file_key ] = $file;

			$attachment_id = media_handle_upload(
				$file_key,
				$review_id,
				array(),
				array(
					'test_form' => false,
					'mimes'     => $this->get_allowed_image_mimes(),
				)
			);

			if ( is_wp_error( $attachment_id ) ) {
				foreach ( $attachment_ids as $uploaded_id ) {
					wp_delete_attachment( $uploaded_id, true );
				}

				$this->restore_upload_file( $file_key, $had_original, $original_file );
				return $attachment_id;
			}

			$attachment_ids[] = absint( $attachment_id );
		}

		$this->restore_upload_file( $file_key, $had_original, $original_file );

		return $attachment_ids;
	}

	/**
	 * Get MIME types that WordPress permits for image uploads.
	 *
	 * @return array
	 */
	private function get_allowed_image_mimes() {
		$safe_image_mimes = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif' );

		return array_filter(
			get_allowed_mime_types(),
			static function ( $mime_type ) use ( $safe_image_mimes ) {
				return in_array( $mime_type, $safe_image_mimes, true );
			}
		);
	}

	/**
	 * Restore the temporary single-file upload slot.
	 *
	 * @param string $file_key      Temporary file key.
	 * @param bool   $had_original  Whether the key existed.
	 * @param mixed  $original_file Original value.
	 * @return void
	 */
	private function restore_upload_file( $file_key, $had_original, $original_file ) {
		if ( $had_original ) {
			$_FILES[ $file_key ] = $original_file;
			return;
		}

		unset( $_FILES[ $file_key ] );
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
