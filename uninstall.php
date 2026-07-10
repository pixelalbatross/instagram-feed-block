<?php
/**
 * Uninstall handler for Outstand Instagram Feed.
 *
 * Removes all plugin options, transients, and scheduled events.
 *
 * @package Outstand\WP\InstagramFeed
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Plugin options.
delete_option( 'outstand_instagram_feed_settings' );
delete_option( 'outstand_instagram_feed_rewrite_rules_version' );

// Transients.
delete_transient( 'outstand_instagram_feed_posts' );

// Scheduled events.
wp_clear_scheduled_hook( 'outstand_instagram_feed_refresh_access_token' );
