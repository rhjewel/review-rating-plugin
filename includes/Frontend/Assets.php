<?php
/**
 * Frontend assets.
 *
 * @package ReviewRating
 */

namespace ReviewRating\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Assets {
	/**
	 * Whether assets have already been enqueued.
	 *
	 * @var bool
	 */
	private $enqueued = false;

	/**
	 * Enqueue frontend assets once.
	 *
	 * @return void
	 */
	public function enqueue() {
		if ( $this->enqueued ) {
			return;
		}

		wp_enqueue_style(
			'review-rating-frontend',
			REVIEW_RATING_URL . 'assets/css/review-rating-frontend.css',
			array(),
			$this->asset_version( REVIEW_RATING_PATH . 'assets/css/review-rating-frontend.css' )
		);

		wp_enqueue_script(
			'review-rating',
			REVIEW_RATING_URL . 'assets/js/review-rating.js',
			array(),
			$this->asset_version( REVIEW_RATING_PATH . 'assets/js/review-rating.js' ),
			true
		);

		wp_localize_script(
			'review-rating',
			'reviewRatingPlugin',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'loadMoreNonce' => wp_create_nonce( 'review_rating_load_more' ),
				'loadMoreText'  => esc_html__( 'Load More Reviews', 'review-rating' ),
				'errorText'     => esc_html__( 'Could not load reviews. Please try again.', 'review-rating' ),
			)
		);

		$this->enqueued = true;
	}

	/**
	 * Get cache-busting version.
	 *
	 * @param string $path Asset path.
	 * @return string
	 */
	private function asset_version( $path ) {
		return file_exists( $path ) ? (string) filemtime( $path ) : REVIEW_RATING_VERSION;
	}
}
