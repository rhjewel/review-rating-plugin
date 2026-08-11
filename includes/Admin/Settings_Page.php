<?php
/**
 * Admin settings page.
 *
 * @package ReviewRating
 */

namespace ReviewRating\Admin;

use ReviewRating\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings_Page {
	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'add_settings_submenu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Add submenu.
	 *
	 * @return void
	 */
	public function add_settings_submenu() {
		add_submenu_page(
			'edit.php?post_type=' . Settings::POST_TYPE,
			esc_html__( 'Review Settings', 'review-rating' ),
			esc_html__( 'Settings', 'review-rating' ),
			'manage_options',
			'review-rating-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register setting.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'review_rating_settings_group',
			Settings::OPTION_NAME,
			array(
				'sanitize_callback' => array( $this->settings, 'sanitize_settings' ),
				'default'           => $this->settings->defaults(),
			)
		);
	}

	/**
	 * Enqueue admin CSS.
	 *
	 * @param string $hook Hook suffix.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( false === strpos( $hook, 'review-rating' ) ) {
			return;
		}

		wp_enqueue_style(
			'review-rating-admin',
			REVIEW_RATING_URL . 'assets/css/review-rating.css',
			array(),
			$this->asset_version( REVIEW_RATING_PATH . 'assets/css/review-rating.css' )
		);

		wp_enqueue_script(
			'review-rating-admin',
			REVIEW_RATING_URL . 'assets/js/review-rating.js',
			array(),
			$this->asset_version( REVIEW_RATING_PATH . 'assets/js/review-rating.js' ),
			true
		);
	}

	/**
	 * Get a cache-busting asset version.
	 *
	 * @param string $path Asset path.
	 * @return string
	 */
	private function asset_version( $path ) {
		return file_exists( $path ) ? (string) filemtime( $path ) : REVIEW_RATING_VERSION;
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = $this->settings->get_all();
		$criteria = isset( $settings['criteria'] ) && is_array( $settings['criteria'] ) ? $settings['criteria'] : array();
		?>
		<div class="wrap review-rating-admin-wrap">
			<h1><?php esc_html_e( 'Review & Rating Settings', 'review-rating' ); ?></h1>
			<p class="review-rating-admin-lead"><?php esc_html_e( 'Manage where reviews appear, how rating criteria work, and how new reviews are moderated.', 'review-rating' ); ?></p>

			<form method="post" action="options.php">
				<?php settings_fields( 'review_rating_settings_group' ); ?>

				<div class="review-rating-admin-grid">
					<section class="review-rating-admin-card">
						<h2><?php esc_html_e( 'Reviewable Post Types', 'review-rating' ); ?></h2>
						<p><?php esc_html_e( 'Choose the post types where visitors can submit multi-criteria reviews.', 'review-rating' ); ?></p>

						<div class="review-rating-checkbox-list">
							<?php foreach ( $this->settings->get_reviewable_post_types() as $slug => $post_type ) : ?>
								<label>
									<input
										type="checkbox"
										name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[enabled_post_types][]"
										value="<?php echo esc_attr( $slug ); ?>"
										<?php checked( in_array( $slug, (array) $settings['enabled_post_types'], true ) ); ?>
									>
									<span><?php echo esc_html( $post_type->labels->name ); ?></span>
									<code><?php echo esc_html( $slug ); ?></code>
								</label>
							<?php endforeach; ?>
						</div>
					</section>

					<section class="review-rating-admin-card">
						<h2><?php esc_html_e( 'Rating Criteria', 'review-rating' ); ?></h2>
						<p><?php esc_html_e( 'Add or remove the criteria used in the frontend form and rating summary. Maximum 10 criteria are allowed.', 'review-rating' ); ?></p>

						<div class="review-rating-criteria-list" data-review-rating-repeater data-max="<?php echo esc_attr( Settings::MAX_CRITERIA ); ?>">
							<?php foreach ( $criteria as $key => $item ) : ?>
								<?php $this->render_criteria_row( $key, $item ); ?>
							<?php endforeach; ?>
						</div>

						<button type="button" class="button review-rating-add-criteria" data-review-rating-add>
							<?php esc_html_e( 'Add Criteria', 'review-rating' ); ?>
						</button>

						<p class="description review-rating-criteria-limit">
							<?php
							printf(
								/* translators: %d: maximum criteria count */
								esc_html__( 'You can add up to %d criteria.', 'review-rating' ),
								absint( Settings::MAX_CRITERIA )
							);
							?>
						</p>
					</section>

					<section class="review-rating-admin-card">
						<h2><?php esc_html_e( 'Moderation & Display', 'review-rating' ); ?></h2>

						<?php
						$this->render_toggle( 'require_approval', __( 'Require approval before displaying reviews', 'review-rating' ), $settings );
						$this->render_toggle( 'require_login', __( 'Only logged-in users can submit reviews', 'review-rating' ), $settings );
						$this->render_toggle( 'one_review_per_user', __( 'Allow only one review per email/user for each post', 'review-rating' ), $settings );
						$this->render_toggle( 'show_summary', __( 'Show rating summary by default', 'review-rating' ), $settings );
						$this->render_toggle( 'show_form', __( 'Show review form by default', 'review-rating' ), $settings );
						$this->render_toggle( 'show_reviews', __( 'Show review list by default', 'review-rating' ), $settings );
						$this->render_toggle( 'enable_review_images', __( 'Allow image uploads with reviews', 'review-rating' ), $settings );
						?>
						<label class="review-rating-field" data-review-rating-image-limit <?php if ( empty( $settings['enable_review_images'] ) ) : ?>hidden<?php endif; ?>>
							<span><?php esc_html_e( 'Maximum images per review', 'review-rating' ); ?></span>
							<input
								type="number"
								name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[max_review_images]"
								value="<?php echo esc_attr( $this->settings->get_max_review_images() ); ?>"
								min="1"
								max="<?php echo esc_attr( Settings::MAX_REVIEW_IMAGES_LIMIT ); ?>"
								step="1"
							>
							<small>
								<?php
								printf(
									/* translators: %d: highest allowed image count */
									esc_html__( 'Choose between 1 and %d images.', 'review-rating' ),
									absint( Settings::MAX_REVIEW_IMAGES_LIMIT )
								);
								?>
							</small>
						</label>
						<?php
						$this->render_toggle( 'enable_schema', __( 'Enable aggregate rating schema markup', 'review-rating' ), $settings );
						$this->render_toggle( 'spam_honeypot_enabled', __( 'Enable honeypot spam protection', 'review-rating' ), $settings );
						?>
					</section>

					<section class="review-rating-admin-card">
						<h2><?php esc_html_e( 'Notifications', 'review-rating' ); ?></h2>
						<?php $this->render_toggle( 'enable_email', __( 'Email admin when a review is submitted', 'review-rating' ), $settings ); ?>

						<label class="review-rating-field">
							<span><?php esc_html_e( 'Notification email', 'review-rating' ); ?></span>
							<input
								type="email"
								name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[admin_notification_to]"
								value="<?php echo esc_attr( $settings['admin_notification_to'] ); ?>"
							>
						</label>
					</section>
				</div>

				<?php submit_button( __( 'Save Review Settings', 'review-rating' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render a toggle checkbox.
	 *
	 * @param string $key      Setting key.
	 * @param string $label    Label.
	 * @param array  $settings Settings.
	 * @return void
	 */
	private function render_toggle( $key, $label, array $settings ) {
		?>
		<label class="review-rating-toggle">
			<input
				type="checkbox"
				name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[<?php echo esc_attr( $key ); ?>]"
				value="1"
				<?php if ( 'enable_review_images' === $key ) : ?>data-review-rating-image-toggle<?php endif; ?>
				<?php checked( ! empty( $settings[ $key ] ) ); ?>
			>
			<span><?php echo esc_html( $label ); ?></span>
		</label>
		<?php
	}

	/**
	 * Render one criteria repeater row.
	 *
	 * @param string $key  Criteria key.
	 * @param array  $item Criteria item.
	 * @return void
	 */
	private function render_criteria_row( $key, array $item ) {
		?>
		<div class="review-rating-criteria-row" data-review-rating-row>
			<span class="review-rating-criteria-handle" aria-hidden="true">☰</span>

			<label class="review-rating-criteria-toggle">
				<input
					type="checkbox"
					name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[criteria_rows][<?php echo esc_attr( $key ); ?>][enabled]"
					value="1"
					<?php checked( ! empty( $item['enabled'] ) ); ?>
				>
				<span><?php esc_html_e( 'Active', 'review-rating' ); ?></span>
			</label>

			<label class="review-rating-criteria-key">
				<span class="screen-reader-text"><?php esc_html_e( 'Criteria key', 'review-rating' ); ?></span>
				<input
					type="text"
					name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[criteria_rows][<?php echo esc_attr( $key ); ?>][key]"
					value="<?php echo esc_attr( $key ); ?>"
					placeholder="<?php esc_attr_e( 'criteria_key', 'review-rating' ); ?>"
				>
			</label>

			<label class="review-rating-criteria-label">
				<span class="screen-reader-text"><?php esc_html_e( 'Criteria label', 'review-rating' ); ?></span>
				<input
					type="text"
					name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[criteria_rows][<?php echo esc_attr( $key ); ?>][label]"
					value="<?php echo esc_attr( isset( $item['label'] ) ? $item['label'] : '' ); ?>"
					placeholder="<?php esc_attr_e( 'Criteria label', 'review-rating' ); ?>"
					required
				>
			</label>

			<button type="button" class="button-link-delete review-rating-remove-criteria" data-review-rating-remove>
				<?php esc_html_e( 'Remove', 'review-rating' ); ?>
			</button>
		</div>
		<?php
	}
}
