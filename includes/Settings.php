<?php
/**
 * Plugin settings.
 *
 * @package ReviewRating
 */

namespace ReviewRating;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings {
	const OPTION_NAME = 'review_rating_settings';
	const POST_TYPE   = 'review-rating';
	const MAX_CRITERIA = 10;

	const META_POST_ID        = '_review_rating_post_id';
	const META_POST_TYPE      = '_review_rating_post_type';
	const META_REVIEWER_NAME  = '_review_rating_reviewer_name';
	const META_REVIEWER_EMAIL = '_review_rating_reviewer_email';
	const META_CRITERIA       = '_review_rating_criteria';
	const META_AVERAGE        = '_review_rating_average';
	const META_COUNT          = '_review_rating_count';
	const META_CRITERIA_AVG   = '_review_rating_criteria_average';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Load translations.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'review-rating', false, dirname( REVIEW_RATING_BASENAME ) . '/languages' );
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public function defaults() {
		return array(
			'enabled_post_types'     => array( 'post' ),
			'criteria'               => array(
				'overall'     => array(
					'label'   => __( 'Overall', 'review-rating' ),
					'enabled' => true,
				),
				'transport'   => array(
					'label'   => __( 'Transport', 'review-rating' ),
					'enabled' => true,
				),
				'food'        => array(
					'label'   => __( 'Food', 'review-rating' ),
					'enabled' => true,
				),
				'hospitality' => array(
					'label'   => __( 'Hospitality', 'review-rating' ),
					'enabled' => true,
				),
				'destination' => array(
					'label'   => __( 'Destination', 'review-rating' ),
					'enabled' => true,
				),
			),
			'require_login'          => false,
			'require_approval'       => true,
			'one_review_per_user'    => false,
			'show_summary'           => true,
			'show_form'              => true,
			'show_reviews'           => true,
			'enable_schema'          => false,
			'enable_email'           => false,
			'admin_notification_to'  => get_option( 'admin_email' ),
			'spam_honeypot_enabled' => true,
		);
	}

	/**
	 * Get all settings.
	 *
	 * @return array
	 */
	public function get_all() {
		$settings = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		return wp_parse_args( $settings, $this->defaults() );
	}

	/**
	 * Get one setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback value.
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		$settings = $this->get_all();

		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
	}

	/**
	 * Sanitize full settings payload.
	 *
	 * @param array $input Raw settings.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$defaults = $this->defaults();
		$input    = is_array( $input ) ? $input : array();

		$settings = $defaults;

		$available_post_types = array_keys( $this->get_reviewable_post_types() );
		$enabled_post_types   = isset( $input['enabled_post_types'] ) && is_array( $input['enabled_post_types'] )
			? array_map( 'sanitize_key', wp_unslash( $input['enabled_post_types'] ) )
			: array();

		$settings['enabled_post_types'] = array_values( array_intersect( $enabled_post_types, $available_post_types ) );

		if ( empty( $settings['enabled_post_types'] ) && ! empty( $available_post_types ) ) {
			$settings['enabled_post_types'] = array( reset( $available_post_types ) );
		}

		$raw_criteria = isset( $input['criteria_rows'] ) ? $input['criteria_rows'] : ( isset( $input['criteria'] ) ? $input['criteria'] : array() );
		$settings['criteria'] = $this->sanitize_criteria( $raw_criteria );

		foreach ( array( 'require_login', 'require_approval', 'one_review_per_user', 'show_summary', 'show_form', 'show_reviews', 'enable_schema', 'enable_email', 'spam_honeypot_enabled' ) as $key ) {
			$settings[ $key ] = ! empty( $input[ $key ] );
		}

		$settings['admin_notification_to'] = isset( $input['admin_notification_to'] )
			? sanitize_email( wp_unslash( $input['admin_notification_to'] ) )
			: $defaults['admin_notification_to'];

		return $settings;
	}

	/**
	 * Sanitize rating criteria.
	 *
	 * @param array $criteria Raw criteria.
	 * @return array
	 */
	private function sanitize_criteria( $criteria ) {
		$defaults = $this->defaults();
		$criteria = is_array( $criteria ) ? $criteria : array();
		$clean    = array();
		$count    = 0;

		foreach ( $criteria as $key => $item ) {
			if ( $count >= self::MAX_CRITERIA ) {
				break;
			}

			$item = is_array( $item ) ? $item : array();
			$key  = isset( $item['key'] ) ? sanitize_key( wp_unslash( $item['key'] ) ) : sanitize_key( $key );

			$label = isset( $item['label'] ) ? sanitize_text_field( wp_unslash( $item['label'] ) ) : '';

			if ( '' === $label ) {
				continue;
			}

			if ( '' === $key ) {
				$key = $this->create_criteria_key( $label );
			}

			$base_key = $key;
			$suffix   = 2;

			while ( isset( $clean[ $key ] ) ) {
				$key = $base_key . '_' . $suffix;
				$suffix++;
			}

			$clean[ $key ] = array(
				'label'   => $label,
				'enabled' => ! empty( $item['enabled'] ),
			);

			$count++;
		}

		if ( empty( $clean ) ) {
			$clean = $defaults['criteria'];
		}

		$has_enabled = false;

		foreach ( $clean as $item ) {
			if ( ! empty( $item['enabled'] ) ) {
				$has_enabled = true;
				break;
			}
		}

		if ( ! $has_enabled ) {
			$first_key = array_key_first( $clean );

			if ( null !== $first_key ) {
				$clean[ $first_key ]['enabled'] = true;
			}
		}

		return $clean;
	}

	/**
	 * Create a stable criteria key from label text.
	 *
	 * @param string $label Criteria label.
	 * @return string
	 */
	private function create_criteria_key( $label ) {
		$key = sanitize_title( $label );
		$key = str_replace( '-', '_', $key );

		return $key ? sanitize_key( $key ) : 'criteria';
	}

	/**
	 * Get enabled criteria.
	 *
	 * @return array
	 */
	public function get_enabled_criteria() {
		$criteria = $this->get( 'criteria', array() );
		$enabled  = array();

		foreach ( $criteria as $key => $item ) {
			if ( ! empty( $item['enabled'] ) && ! empty( $item['label'] ) ) {
				$enabled[ $key ] = $item['label'];
			}
		}

		return apply_filters( 'review_rating_criteria', $enabled );
	}

	/**
	 * Get reviewable public post types.
	 *
	 * @return array
	 */
	public function get_reviewable_post_types() {
		$post_types = get_post_types(
			array(
				'public' => true,
			),
			'objects'
		);

		unset( $post_types['attachment'], $post_types[ self::POST_TYPE ] );

		return apply_filters( 'review_rating_available_post_types', $post_types );
	}

	/**
	 * Get enabled post type slugs.
	 *
	 * @return array
	 */
	public function get_enabled_post_types() {
		$enabled = $this->get( 'enabled_post_types', array( 'post' ) );

		return array_values( array_filter( array_map( 'sanitize_key', (array) $enabled ) ) );
	}

	/**
	 * Check if a post type can receive reviews.
	 *
	 * @param string $post_type Post type.
	 * @return bool
	 */
	public function is_post_type_enabled( $post_type ) {
		return in_array( $post_type, $this->get_enabled_post_types(), true );
	}
}
