<?php
/**
 * Frontend shortcodes.
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

class Shortcodes {
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
	 * Assets.
	 *
	 * @var Assets
	 */
	private $assets;

	/**
	 * Constructor.
	 *
	 * @param Settings          $settings   Settings.
	 * @param Review_Repository $repository Repository.
	 * @param Rating_Calculator $calculator Calculator.
	 * @param Assets            $assets     Assets.
	 */
	public function __construct( Settings $settings, Review_Repository $repository, Rating_Calculator $calculator, Assets $assets ) {
		$this->settings   = $settings;
		$this->repository = $repository;
		$this->calculator = $calculator;
		$this->assets     = $assets;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_shortcode( 'review_rating', array( $this, 'render_review_rating' ) );
		add_shortcode( 'review_rating_summary', array( $this, 'render_summary_shortcode' ) );
		add_shortcode( 'review_rating_form', array( $this, 'render_form_shortcode' ) );
		add_shortcode( 'review_rating_list', array( $this, 'render_list_shortcode' ) );
		add_shortcode( 'review_rating_count', array( $this, 'render_count_shortcode' ) );
		add_shortcode( 'review_rating_average', array( $this, 'render_average_shortcode' ) );

		// Legacy shortcodes.
		add_shortcode( 'post_rating', array( $this, 'render_legacy_post_rating' ) );
		add_shortcode( 'post_rating_count', array( $this, 'render_legacy_post_rating_count' ) );
		add_shortcode( 'total_post_rating_count', array( $this, 'render_count_shortcode' ) );
		add_shortcode( 'get_average_rating', array( $this, 'render_average_for_post_type_shortcode' ) );

		add_action( 'wp_ajax_review_rating_load_more', array( $this, 'ajax_load_more_reviews' ) );
		add_action( 'wp_ajax_nopriv_review_rating_load_more', array( $this, 'ajax_load_more_reviews' ) );
	}

	/**
	 * Render full review area.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_review_rating( $atts = array() ) {
		$atts = $this->normalize_atts( $atts );

		if ( ! $this->can_render_for_post( $atts['post_id'] ) ) {
			return '';
		}

		$this->assets->enqueue();

		ob_start();
		?>
		<div class="rrp-review-area" id="rrp-reviews-<?php echo esc_attr( $atts['post_id'] ); ?>">
			<?php $this->render_status_notice(); ?>

			<?php if ( $atts['show_summary'] ) : ?>
				<?php echo $this->render_summary( $atts['post_id'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endif; ?>

			<?php if ( $atts['show_reviews'] ) : ?>
				<?php echo $this->render_review_list( $atts['post_id'], $atts['limit'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endif; ?>

			<?php if ( $atts['show_form'] ) : ?>
				<?php echo $this->render_form( $atts['post_id'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endif; ?>
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Render summary shortcode.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public function render_summary_shortcode( $atts = array() ) {
		$atts = $this->normalize_atts( $atts );

		if ( ! $this->can_render_for_post( $atts['post_id'] ) ) {
			return '';
		}

		$this->assets->enqueue();

		return $this->render_summary( $atts['post_id'] );
	}

	/**
	 * Render form shortcode.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public function render_form_shortcode( $atts = array() ) {
		$atts = $this->normalize_atts( $atts );

		if ( ! $this->can_render_for_post( $atts['post_id'] ) ) {
			return '';
		}

		$this->assets->enqueue();

		return '<div class="rrp-review-area">' . $this->render_status_notice( false ) . $this->render_form( $atts['post_id'] ) . '</div>';
	}

	/**
	 * Render list shortcode.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public function render_list_shortcode( $atts = array() ) {
		$atts = $this->normalize_atts( $atts );

		if ( ! $this->can_render_for_post( $atts['post_id'] ) ) {
			return '';
		}

		$this->assets->enqueue();

		return $this->render_review_list( $atts['post_id'], $atts['limit'] );
	}

	/**
	 * Render count.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public function render_count_shortcode( $atts = array() ) {
		$atts = $this->normalize_atts( $atts );

		if ( ! $this->can_render_for_post( $atts['post_id'] ) ) {
			return '0';
		}

		$data = $this->calculator->get_cached_or_calculate( $atts['post_id'] );

		return esc_html( $data['count'] );
	}

	/**
	 * Render average.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public function render_average_shortcode( $atts = array() ) {
		$atts = $this->normalize_atts( $atts );

		if ( ! $this->can_render_for_post( $atts['post_id'] ) ) {
			return '0';
		}

		$data = $this->calculator->get_cached_or_calculate( $atts['post_id'] );

		return esc_html( number_format_i18n( $data['average'], 1 ) );
	}

	/**
	 * Legacy post rating UI.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public function render_legacy_post_rating( $atts = array() ) {
		$atts = $this->normalize_atts( $atts );

		if ( ! $this->can_render_for_post( $atts['post_id'] ) ) {
			return '';
		}

		$this->assets->enqueue();
		$data = $this->calculator->get_cached_or_calculate( $atts['post_id'] );

		return sprintf(
			'<div class="rating-area rrp-legacy-rating"><ul class="star">%1$s</ul><span>%2$s</span></div>',
			$this->render_stars( $data['average'] ),
			esc_html(
				sprintf(
					/* translators: 1: review count, 2: average rating */
					__( '%1$d Review ( based on %2$s reviews )', 'review-rating' ),
					$data['count'],
					number_format_i18n( $data['average'], 1 )
				)
			)
		);
	}

	/**
	 * Legacy compact rating count.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public function render_legacy_post_rating_count( $atts = array() ) {
		$atts = $this->normalize_atts( $atts );

		if ( ! $this->can_render_for_post( $atts['post_id'] ) ) {
			return '';
		}

		$this->assets->enqueue();
		$data = $this->calculator->get_cached_or_calculate( $atts['post_id'] );

		return sprintf(
			'<div class="rating-text"><div class="rating-stars"><ul>%1$s</ul></div><span class="total">%2$s</span></div>',
			$this->render_stars( $data['average'] ),
			esc_html(
				sprintf(
					/* translators: %d: review count */
					_n( '%d review', '%d reviews', $data['count'], 'review-rating' ),
					$data['count']
				)
			)
		);
	}

	/**
	 * Average by post type shortcode.
	 *
	 * @param array|string $atts Attributes or legacy string.
	 * @return string
	 */
	public function render_average_for_post_type_shortcode( $atts = array() ) {
		if ( is_string( $atts ) ) {
			$post_type = sanitize_key( $atts );
		} else {
			$atts = shortcode_atts(
				array(
					'post_type' => '',
				),
				(array) $atts,
				'get_average_rating'
			);
			$post_type = sanitize_key( $atts['post_type'] );
		}

		if ( ! $post_type || ! post_type_exists( $post_type ) ) {
			return '0';
		}

		$posts = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		$count = 0;
		$total = 0;

		foreach ( $posts as $post_id ) {
			$data = $this->calculator->get_cached_or_calculate( $post_id );

			if ( $data['count'] > 0 ) {
				$total += $data['average'];
				$count++;
			}
		}

		return $count > 0 ? esc_html( number_format_i18n( round( $total / $count, 1 ), 1 ) ) : '0';
	}

	/**
	 * Load more reviews with AJAX.
	 *
	 * @return void
	 */
	public function ajax_load_more_reviews() {
		check_ajax_referer( 'review_rating_load_more', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
		$offset  = isset( $_POST['offset'] ) ? absint( wp_unslash( $_POST['offset'] ) ) : 0;
		$limit   = isset( $_POST['limit'] ) ? absint( wp_unslash( $_POST['limit'] ) ) : 3;

		$limit = $limit > 0 ? min( $limit, 10 ) : 3;

		if ( ! $this->can_render_for_post( $post_id ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Reviews are not available for this post.', 'review-rating' ),
				)
			);
		}

		$reviews = $this->repository->get_reviews_for_post( $post_id, $limit, $offset );
		$total   = $this->repository->count_reviews_for_post( $post_id );
		$html    = $this->render_review_items( $reviews );
		$shown   = $offset + count( $reviews );

		wp_send_json_success(
			array(
				'html'        => $html,
				'next_offset' => $shown,
				'has_more'    => $shown < $total,
			)
		);
	}

	/**
	 * Render rating summary.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function render_summary( $post_id ) {
		$data = $this->calculator->get_cached_or_calculate( $post_id );

		$distribution_labels = array(
			5 => __( 'Excellent', 'review-rating' ),
			4 => __( 'Very good', 'review-rating' ),
			3 => __( 'Average', 'review-rating' ),
			2 => __( 'Poor', 'review-rating' ),
			1 => __( 'Terrible', 'review-rating' ),
		);

		ob_start();
		?>
		<section class="rrp-summary" aria-label="<?php esc_attr_e( 'Customer rating summary', 'review-rating' ); ?>">
			<div class="rrp-summary-overview">
				<strong class="rrp-summary-score"><?php echo esc_html( number_format_i18n( $data['average'], 1 ) ); ?></strong>
				<div class="rrp-summary-meta">
					<ul class="rrp-stars" aria-label="<?php echo esc_attr( sprintf( __( 'Rated %s out of 5', 'review-rating' ), number_format_i18n( $data['average'], 1 ) ) ); ?>">
						<?php echo $this->render_stars( $data['average'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</ul>
					<p>
						<?php
						printf(
							/* translators: 1: average rating, 2: review count */
							esc_html( _n( '%1$s based on %2$s traveler review', '%1$s based on %2$s traveler reviews', $data['count'], 'review-rating' ) ),
							esc_html( number_format_i18n( $data['average'], 1 ) ),
							esc_html( number_format_i18n( $data['count'] ) )
						);
						?>
					</p>
				</div>
			</div>

			<div class="rrp-breakdown">
				<?php foreach ( $distribution_labels as $rating => $label ) : ?>
					<?php
					$count   = isset( $data['distribution'][ $rating ] ) ? absint( $data['distribution'][ $rating ] ) : 0;
					$percent = $data['count'] > 0 ? min( 100, ( $count / $data['count'] ) * 100 ) : 0;
					?>
					<div class="rrp-progress">
						<span class="rrp-progress-label"><?php echo esc_html( $label ); ?></span>
						<div
							class="rrp-progress-track"
							role="progressbar"
							aria-label="<?php echo esc_attr( $label ); ?>"
							aria-valuemin="0"
							aria-valuemax="<?php echo esc_attr( max( 1, $data['count'] ) ); ?>"
							aria-valuenow="<?php echo esc_attr( $count ); ?>"
						>
							<span style="width: <?php echo esc_attr( $percent ); ?>%;"></span>
						</div>
						<strong class="rrp-progress-count"><?php echo esc_html( number_format_i18n( $count ) ); ?></strong>
					</div>
				<?php endforeach; ?>
			</div>
		</section>
		<?php

		return ob_get_clean();
	}

	/**
	 * Render review list.
	 *
	 * @param int $post_id Post ID.
	 * @param int $limit   Limit.
	 * @return string
	 */
	private function render_review_list( $post_id, $limit = 3 ) {
		$limit       = $limit > 0 ? $limit : -1;
		$reviews     = $this->repository->get_reviews_for_post( $post_id, $limit );
		$total       = $this->repository->count_reviews_for_post( $post_id );
		$shown       = count( $reviews );
		$load_more   = $limit > 0 && $shown < $total;
		$button_text = esc_html__( 'Load More', 'review-rating' );

		ob_start();
		?>
		<section class="rrp-list" aria-label="<?php esc_attr_e( 'Customer reviews', 'review-rating' ); ?>">
			<h3><?php esc_html_e( 'Customer Reviews', 'review-rating' ); ?></h3>

			<?php if ( empty( $reviews ) ) : ?>
				<p class="rrp-empty"><?php esc_html_e( 'No reviews yet. Be the first to write one.', 'review-rating' ); ?></p>
			<?php else : ?>
				<ul class="rrp-review-items" data-review-rating-items>
					<?php echo $this->render_review_items( $reviews ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</ul>

				<?php if ( $load_more ) : ?>
					<div class="rrp-load-more-wrap">
						<button
							type="button"
							class="rrp-button rrp-load-more"
							data-review-rating-load-more
							data-post-id="<?php echo esc_attr( $post_id ); ?>"
							data-offset="<?php echo esc_attr( $shown ); ?>"
							data-limit="<?php echo esc_attr( $limit ); ?>"
							data-loading-text="<?php esc_attr_e( 'Loading...', 'review-rating' ); ?>"
						>
							<?php echo esc_html( $button_text ); ?>
						</button>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</section>
		<?php

		return ob_get_clean();
	}

	/**
	 * Render review list items.
	 *
	 * @param array $reviews Reviews.
	 * @return string
	 */
	private function render_review_items( array $reviews ) {
		ob_start();

		foreach ( $reviews as $review ) :
			$name      = get_the_title( $review );
			$email     = get_post_meta( $review->ID, Settings::META_REVIEWER_EMAIL, true );
			$image_ids = $this->repository->get_review_images( $review->ID );
			$time      = sprintf(
				/* translators: %s: human-readable time difference */
				esc_html__( '%s ago', 'review-rating' ),
				human_time_diff( get_post_time( 'U', true, $review ), current_time( 'timestamp', true ) )
			);
			?>
			<li class="rrp-review">
				<div class="rrp-review-avatar-wrap">
					<?php echo get_avatar( $email, 54, 'mystery', $name, array( 'class' => 'rrp-review-avatar' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>

				<div class="rrp-review-body">
					<ul class="rrp-stars" aria-label="<?php echo esc_attr( sprintf( __( 'Rated %s out of 5', 'review-rating' ), number_format_i18n( $this->repository->get_review_average( $review->ID ), 1 ) ) ); ?>">
						<?php echo $this->render_stars( $this->repository->get_review_average( $review->ID ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</ul>

					<div class="rrp-review-meta">
						<strong><?php echo esc_html( $name ); ?>,</strong>
						<time datetime="<?php echo esc_attr( get_the_date( 'c', $review ) ); ?>"><?php echo esc_html( $time ); ?></time>
					</div>

					<p><?php echo esc_html( $review->post_content ); ?></p>

					<?php if ( ! empty( $image_ids ) ) : ?>
						<ul class="rrp-review-images" aria-label="<?php esc_attr_e( 'Review images', 'review-rating' ); ?>">
							<?php foreach ( $image_ids as $attachment_id ) : ?>
								<?php $full_image_url = wp_get_attachment_image_url( $attachment_id, 'full' ); ?>
								<?php if ( $full_image_url && wp_attachment_is_image( $attachment_id ) ) : ?>
									<li>
										<a href="<?php echo esc_url( $full_image_url ); ?>" target="_blank" rel="noopener noreferrer">
											<?php echo wp_get_attachment_image( $attachment_id, 'medium', false, array( 'loading' => 'lazy' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
										</a>
									</li>
								<?php endif; ?>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			</li>
			<?php
		endforeach;

		return ob_get_clean();
	}

	/**
	 * Render review form.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function render_form( $post_id ) {
		if ( $this->settings->get( 'require_login', false ) && ! is_user_logged_in() ) {
			return '<p class="rrp-notice rrp-notice-warning">' . esc_html__( 'Please log in to write a review.', 'review-rating' ) . '</p>';
		}

		$criteria     = $this->settings->get_enabled_criteria();
		$is_logged_in = is_user_logged_in();
		$user         = $is_logged_in ? wp_get_current_user() : null;
		$image_limit  = $this->settings->get_max_review_images();

		ob_start();
		do_action( 'review_rating_before_form', $post_id );
		?>
		<section class="rrp-form-wrap" aria-label="<?php esc_attr_e( 'Write a review', 'review-rating' ); ?>">
			<h3><?php esc_html_e( 'Write a Review', 'review-rating' ); ?></h3>
			<form method="post" enctype="multipart/form-data" class="rrp-form">
				<div class="rrp-rating-fields">
					<?php foreach ( $criteria as $key => $label ) : ?>
						<fieldset class="rrp-rating-field">
							<legend><?php echo esc_html( $label ); ?></legend>
							<div class="rrp-rating-input">
								<?php for ( $i = 5; $i >= 1; $i-- ) : ?>
									<input
										type="radio"
										id="rrp-<?php echo esc_attr( $post_id . '-' . $key . '-' . $i ); ?>"
										name="review_rating_criteria[<?php echo esc_attr( $key ); ?>]"
										value="<?php echo esc_attr( $i ); ?>"
										required
									>
									<label for="rrp-<?php echo esc_attr( $post_id . '-' . $key . '-' . $i ); ?>">
										<span aria-hidden="true">★</span>
										<span class="screen-reader-text">
											<?php
											printf(
												/* translators: 1: criteria label, 2: rating value */
												esc_html__( '%1$s: %2$d out of 5', 'review-rating' ),
												esc_html( $label ),
												absint( $i )
											);
											?>
										</span>
									</label>
								<?php endfor; ?>
							</div>
						</fieldset>
					<?php endforeach; ?>
				</div>

				<label class="rrp-field">
					<span><?php esc_html_e( 'Your feedback', 'review-rating' ); ?></span>
					<textarea name="review_rating_content" rows="5" required></textarea>
				</label>

				<?php if ( $this->settings->get( 'enable_review_images', false ) ) : ?>
					<div class="rrp-image-field">
						<label for="rrp-review-images-<?php echo esc_attr( $post_id ); ?>"><?php esc_html_e( 'Review images', 'review-rating' ); ?></label>
						<input
							type="file"
							id="rrp-review-images-<?php echo esc_attr( $post_id ); ?>"
							name="review_rating_images[]"
							accept="image/jpeg,image/png,image/gif,image/webp,image/avif"
							multiple
							data-review-rating-images
							data-max="<?php echo esc_attr( $image_limit ); ?>"
						>
						<ul class="rrp-image-preview" data-review-rating-image-preview aria-label="<?php esc_attr_e( 'Selected image previews', 'review-rating' ); ?>" hidden></ul>
						<small>
							<?php
							printf(
								/* translators: %d: maximum number of images */
								esc_html__( 'Upload up to %d images.', 'review-rating' ),
								absint( $image_limit )
							);
							?>
						</small>
					</div>
				<?php endif; ?>

				<div class="rrp-form-grid">
					<label class="rrp-field">
						<span><?php esc_html_e( 'Your name', 'review-rating' ); ?></span>
						<input type="text" name="review_rating_name" value="<?php echo esc_attr( $user ? $user->display_name : '' ); ?>" autocomplete="name" <?php if ( ! $is_logged_in ) : ?>required<?php endif; ?>>
					</label>
					<label class="rrp-field">
						<span><?php esc_html_e( 'Email', 'review-rating' ); ?></span>
						<input type="email" name="review_rating_email" value="<?php echo esc_attr( $user ? $user->user_email : '' ); ?>" autocomplete="email" <?php if ( ! $is_logged_in ) : ?>required<?php endif; ?>>
					</label>
				</div>

				<label class="rrp-hp" aria-hidden="true">
					<span><?php esc_html_e( 'Company', 'review-rating' ); ?></span>
					<input type="text" name="review_rating_company" tabindex="-1" autocomplete="off">
				</label>

				<?php wp_nonce_field( 'review_rating_submit', 'review_rating_nonce' ); ?>
				<input type="hidden" name="review_rating_post_id" value="<?php echo esc_attr( $post_id ); ?>">
				<button type="submit" name="review_rating_submit" value="1" class="rrp-button"><?php esc_html_e( 'Submit Review', 'review-rating' ); ?></button>
			</form>
		</section>
		<?php
		do_action( 'review_rating_after_form', $post_id );

		return ob_get_clean();
	}

	/**
	 * Render stars.
	 *
	 * @param float $rating Rating.
	 * @return string
	 */
	private function render_stars( $rating ) {
		$output = '';
		$rating = (float) $rating;

		for ( $i = 1; $i <= 5; $i++ ) {
			$class = $i <= floor( $rating ) ? 'is-filled' : '';

			if ( ! $class && $rating >= $i - 0.5 ) {
				$class = 'is-half';
			}

			$output .= '<li class="' . esc_attr( $class ) . '" aria-hidden="true">★</li>';
		}

		return $output;
	}

	/**
	 * Render submission status notice.
	 *
	 * @param bool $echo Echo output.
	 * @return string
	 */
	private function render_status_notice( $echo = true ) {
		$status = isset( $_GET['review_rating_status'] ) ? sanitize_key( wp_unslash( $_GET['review_rating_status'] ) ) : '';

		if ( ! $status ) {
			return '';
		}

		$messages = array(
			'success'         => __( 'Thank you. Your review has been submitted.', 'review-rating' ),
			'invalid_nonce'   => __( 'Security check failed. Please try again.', 'review-rating' ),
			'login_required'  => __( 'Please log in before submitting a review.', 'review-rating' ),
			'invalid_post'    => __( 'This post cannot receive reviews.', 'review-rating' ),
			'missing_fields'  => __( 'Please complete all required fields.', 'review-rating' ),
			'missing_rating'  => __( 'Please select a rating for every criterion.', 'review-rating' ),
			'too_many_images' => sprintf(
				/* translators: %d: maximum number of images */
				__( 'You can upload a maximum of %d review images.', 'review-rating' ),
				$this->settings->get_max_review_images()
			),
			'invalid_image'   => __( 'One or more review images could not be uploaded. Please use valid image files.', 'review-rating' ),
			'duplicate'       => __( 'You have already submitted a review for this post.', 'review-rating' ),
			'spam'            => __( 'Your review could not be submitted.', 'review-rating' ),
			'error'           => __( 'Something went wrong. Please try again.', 'review-rating' ),
		);

		if ( empty( $messages[ $status ] ) ) {
			return '';
		}

		$output = '<p class="rrp-notice rrp-notice-' . esc_attr( 'success' === $status ? 'success' : 'error' ) . '" role="status" aria-live="polite">' . esc_html( $messages[ $status ] ) . '</p>';

		if ( $echo ) {
			echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return '';
		}

		return $output;
	}

	/**
	 * Normalize shortcode attributes.
	 *
	 * @param array $atts Attributes.
	 * @return array
	 */
	private function normalize_atts( $atts ) {
		$atts = shortcode_atts(
			array(
				'post_id'      => get_the_ID(),
				'show_form'    => $this->settings->get( 'show_form', true ) ? '1' : '0',
				'show_summary' => $this->settings->get( 'show_summary', true ) ? '1' : '0',
				'show_reviews' => $this->settings->get( 'show_reviews', true ) ? '1' : '0',
				'limit'        => 3,
			),
			(array) $atts
		);

		$atts['post_id']      = absint( $atts['post_id'] );
		$atts['show_form']    = filter_var( $atts['show_form'], FILTER_VALIDATE_BOOLEAN );
		$atts['show_summary'] = filter_var( $atts['show_summary'], FILTER_VALIDATE_BOOLEAN );
		$atts['show_reviews'] = filter_var( $atts['show_reviews'], FILTER_VALIDATE_BOOLEAN );
		$atts['limit']        = (int) $atts['limit'];

		return $atts;
	}

	/**
	 * Can render for post.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	private function can_render_for_post( $post_id ) {
		if ( ! $post_id ) {
			return false;
		}

		$post_type = get_post_type( $post_id );

		return $post_type && $this->settings->is_post_type_enabled( $post_type );
	}
}
