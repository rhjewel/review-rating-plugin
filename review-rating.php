<?php
/**
 * Plugin Name: Review & Rating
 * Description: Multi-criteria reviews and ratings for posts and custom post types.
 * Version: 1.1.0
 * Author: RH Jewel
 * Author URI: https://rh-jewel.com/
 * Text Domain: review-rating
 * Domain Path: /languages
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package ReviewRating
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'REVIEW_RATING_VERSION', '1.1.0' );
define( 'REVIEW_RATING_FILE', __FILE__ );
define( 'REVIEW_RATING_PATH', plugin_dir_path( __FILE__ ) );
define( 'REVIEW_RATING_URL', plugin_dir_url( __FILE__ ) );
define( 'REVIEW_RATING_BASENAME', plugin_basename( __FILE__ ) );

require_once REVIEW_RATING_PATH . 'includes/Settings.php';
require_once REVIEW_RATING_PATH . 'includes/Repositories/Review_Repository.php';
require_once REVIEW_RATING_PATH . 'includes/Services/Rating_Calculator.php';
require_once REVIEW_RATING_PATH . 'includes/CPT.php';
require_once REVIEW_RATING_PATH . 'includes/Admin/Settings_Page.php';
require_once REVIEW_RATING_PATH . 'includes/Frontend/Assets.php';
require_once REVIEW_RATING_PATH . 'includes/Frontend/Form_Handler.php';
require_once REVIEW_RATING_PATH . 'includes/Frontend/Shortcodes.php';
require_once REVIEW_RATING_PATH . 'includes/Plugin.php';

register_activation_hook( __FILE__, array( 'ReviewRating\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ReviewRating\\Plugin', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		ReviewRating\Plugin::instance()->boot();
	}
);
