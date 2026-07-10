<?php

namespace Outstand\WP\InstagramFeed;

class Plugin {

	/**
	 * Rewrite rules version option name.
	 *
	 * @var string
	 */
	const REWRITE_RULES_VERSION_OPTION_NAME = 'outstand_instagram_feed_rewrite_rules_version';

	/**
	 * Singleton instance of the Plugin.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Instagram API instance.
	 *
	 * @var Client|null
	 */
	private ?Client $client = null;

	/**
	 * Settings instance.
	 *
	 * @var Settings|null
	 */
	private ?Settings $settings = null;

	/**
	 * Rewrite rules version.
	 *
	 * @var string
	 */
	private string $rewrite_rules_version = '0.2.0';

	/**
	 * Conditionally creates the singleton instance if absent, else
	 * returns the previously saved instance.
	 *
	 * @return Plugin
	 */
	public static function get_instance(): Plugin {

		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Deactivate the plugin.
	 *
	 * Only unschedules cron events and clears transient caches. User data
	 * (credentials, access token, settings) is preserved so that a temporary
	 * deactivation does not force a full re-setup and re-authorization. Option
	 * cleanup belongs in uninstall.php.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( Cron::REFRESH_TOKEN_HOOK );
		delete_transient( Client::CACHE_KEY );
	}

	/**
	 * Enable plugin functionality.
	 *
	 * @return void
	 */
	public function enable(): void {

		$modules = [
			new Blocks(),
			new Cron(),
			new REST(),
			$this->get_settings(),
		];

		foreach ( $modules as $module ) {
			if ( $module instanceof BaseModule && $module->can_register() ) {
				$module->register();
			}
		}

		add_action( 'init', [ $this, 'i18n' ] );
		add_action( 'wp_loaded', [ $this, 'flush_if_changed' ], 10000 );
		add_filter( 'plugin_action_links_' . OUTSTAND_INSTAGRAM_FEED_BASENAME, [ $this, 'plugin_action_links' ] );
		add_filter( 'query_vars', [ $this, 'add_query_vars' ] );
	}

	/**
	 * Registers the default textdomain.
	 *
	 * @return void
	 */
	public function i18n(): void {
		$locale = apply_filters( 'plugin_locale', get_locale(), 'outstand-instagram-feed' );
		load_textdomain( 'outstand-instagram-feed', WP_LANG_DIR . '/outstand-instagram-feed/outstand-instagram-feed-' . $locale . '.mo' );
		load_plugin_textdomain( 'outstand-instagram-feed', false, plugin_basename( OUTSTAND_INSTAGRAM_FEED_PATH ) . '/languages/' );
	}

	/**
	 * Conditionally flush rewrite rules.
	 *
	 * @return void
	 */
	public function flush_if_changed(): void {

		$db_rewrite_rules_version = get_option( self::REWRITE_RULES_VERSION_OPTION_NAME );

		if ( empty( $db_rewrite_rules_version ) || version_compare( $this->rewrite_rules_version, $db_rewrite_rules_version ) > 0 ) {
			update_option( self::REWRITE_RULES_VERSION_OPTION_NAME, $this->rewrite_rules_version );

			flush_rewrite_rules( false );
		}
	}

	/**
	 * Add settings page link along with plugin action links.
	 *
	 * @param  array $links An array of plugin action links.
	 * @return array
	 */
	public function plugin_action_links( array $links ): array {

		$links = array_merge(
			[
				sprintf(
					'<a href="%1$s">%2$s</a>',
					admin_url( 'options-general.php?page=outstand-instagram-feed' ),
					esc_html__( 'Settings', 'outstand-instagram-feed' )
				),
			],
			$links
		);

		return $links;
	}

	/**
	 * Update query vars.
	 *
	 * @param  array $query_vars Allowlist of query variable names.
	 * @return array
	 */
	public function add_query_vars( array $query_vars ): array {
		$query_vars[] = 'outstand-instagram-feed-auth';
		return $query_vars;
	}

	/**
	 * Get Client instance.
	 *
	 * @return Client
	 */
	public function get_client(): Client {

		if ( $this->client === null ) {
			$settings     = $this->get_settings();
			$app_id       = $settings->get_app_id();
			$app_secret   = $settings->get_app_secret();
			$access_token = $settings->get_access_token();

			$this->client = new Client( $app_id, $app_secret, $access_token );
		}

		return $this->client;
	}

	/**
	 * Get Settings instance.
	 *
	 * @return Settings
	 */
	public function get_settings(): Settings {

		if ( $this->settings === null ) {
			$this->settings = new Settings();
		}

		return $this->settings;
	}
}
