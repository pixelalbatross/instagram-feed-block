<?php
/**
 * Plugin Name:       Outstand Instagram Feed
 * Description:       Display Instagram posts using a customizable Gutenberg block with list and grid layouts.
 * Plugin URI:        https://outstand.site/?utm_source=wp-plugins&utm_medium=outstand-instagram-feed&utm_campaign=plugin-uri
 * Requires at least: 6.7
 * Requires PHP:      8.2
 * Version:           1.1.2
 * Author:            Outstand
 * Author URI:        https://outstand.site/?utm_source=wp-plugins&utm_medium=outstand-instagram-feed&utm_campaign=author-uri
 * License:           GPL-3.0-or-later
 * License URI:       https://spdx.org/licenses/GPL-3.0-or-later.html
 * Update URI:        https://outstand.site/
 * GitHub Plugin URI: https://github.com/pixelalbatross/outstand-instagram-feed
 * Text Domain:       outstand-instagram-feed
 */

namespace Outstand\WP\InstagramFeed;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'OUTSTAND_INSTAGRAM_FEED_BASENAME', plugin_basename( __FILE__ ) );
define( 'OUTSTAND_INSTAGRAM_FEED_URL', plugin_dir_url( __FILE__ ) );
define( 'OUTSTAND_INSTAGRAM_FEED_PATH', plugin_dir_path( __FILE__ ) );
define( 'OUTSTAND_INSTAGRAM_FEED_INC', OUTSTAND_INSTAGRAM_FEED_PATH . 'includes/' );

if ( ! file_exists( OUTSTAND_INSTAGRAM_FEED_PATH . 'vendor/autoload.php' ) ) {
	return;
}

require_once OUTSTAND_INSTAGRAM_FEED_PATH . 'vendor/autoload.php';

PucFactory::buildUpdateChecker(
	'https://github.com/pixelalbatross/outstand-instagram-feed/',
	__FILE__,
	'outstand-instagram-feed'
)->setBranch( 'main' );

// Activation/Deactivation.
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\Plugin::deactivate' );

/**
 * Load the plugin.
 */
add_action(
	'plugins_loaded',
	function () {
		$plugin = Plugin::get_instance();
		$plugin->enable();
	}
);
