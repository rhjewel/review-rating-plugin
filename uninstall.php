<?php
/**
 * Uninstall handler.
 *
 * @package ReviewRating
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'review_rating_settings' );
delete_option( 'review_rating_migrated_110' );
