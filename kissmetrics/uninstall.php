<?php
/**
 * Removes all Kissmetrics options when the plugin is deleted.
 *
 * @package Kissmetrics
 */

// Only run when WordPress is performing an uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$kissmetrics_options = array(
	'kissmetrics_key',
	'kissmetrics_identify_users',
	'kissmetrics_track_login',
	'kissmetrics_track_signup_view',
	'kissmetrics_track_signup',
	'kissmetrics_track_views',
	'kissmetrics_track_links',
	'kissmetrics_track_comment_links',
	'kissmetrics_track_social',
	'kissmetrics_track_search',
	'kissmetrics_track_comment',
	'kissmetrics_identify_unregistered',
	'kissmetrics_track_ecommerce',
);

foreach ( $kissmetrics_options as $kissmetrics_option ) {
	delete_option( $kissmetrics_option );
}
