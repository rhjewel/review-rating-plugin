<?php
/**
 * Main plugin controller.
 *
 * @package ReviewRating
 */

namespace ReviewRating;

use ReviewRating\Admin\Settings_Page;
use ReviewRating\Frontend\Assets;
use ReviewRating\Frontend\Form_Handler;
use ReviewRating\Frontend\Shortcodes;
use ReviewRating\Repositories\Review_Repository;
use ReviewRating\Services\Rating_Calculator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {
	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get plugin instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Boot plugin classes.
	 *
	 * @return void
	 */
	public function boot() {
		$settings   = new Settings();
		$repository = new Review_Repository( $settings );
		$calculator = new Rating_Calculator( $repository, $settings );
		$assets     = new Assets();

		$settings->register_hooks();
		( new CPT( $settings, $repository, $calculator ) )->register_hooks();
		( new Settings_Page( $settings ) )->register_hooks();
		( new Form_Handler( $settings, $repository, $calculator ) )->register_hooks();
		( new Shortcodes( $settings, $repository, $calculator, $assets ) )->register_hooks();
	}

	/**
	 * Plugin activation.
	 *
	 * @return void
	 */
	public static function activate() {
		$settings = new Settings();

		if ( false === get_option( Settings::OPTION_NAME, false ) ) {
			add_option( Settings::OPTION_NAME, $settings->defaults() );
		}

		( new CPT( $settings ) )->register_review_post_type();
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation.
	 *
	 * @return void
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * Prevent direct construction.
	 */
	private function __construct() {}
}
