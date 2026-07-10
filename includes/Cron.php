<?php

namespace Outstand\WP\InstagramFeed;

class Cron extends BaseModule {

	/**
	 * Refresh token hook name.
	 *
	 * @var string
	 */
	const REFRESH_TOKEN_HOOK = 'outstand_instagram_feed_refresh_access_token';

	/**
	 * Option storing the consecutive refresh-failure count.
	 *
	 * @var string
	 */
	const RETRY_COUNT_OPTION = 'outstand_instagram_feed_refresh_retries';

	/**
	 * Maximum number of consecutive refresh attempts before giving up.
	 *
	 * @var int
	 */
	const MAX_RETRIES = 3;

	/**
	 * Register any hooks and filters.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'schedule_events' ] );
		add_action( self::REFRESH_TOKEN_HOOK, [ $this, 'refresh_access_token' ] );
		add_action( 'admin_notices', [ $this, 'render_failure_notice' ] );
	}

	/**
	 * Schedule cron events.
	 *
	 * @return void
	 */
	public function schedule_events(): void {
		$plugin   = Plugin::get_instance();
		$settings = $plugin->get_settings();

		if ( $settings->get_auth_status() !== Settings::AUTH_STATUS_AUTHORIZED ) {
			wp_clear_scheduled_hook( self::REFRESH_TOKEN_HOOK );
		}
	}

	/**
	 * Refresh long-lived access token.
	 *
	 * On failure, retries with exponential backoff up to self::MAX_RETRIES
	 * times before marking the connection as failed, so a single transient
	 * API/network error no longer silently kills the connection.
	 *
	 * @return void
	 */
	public function refresh_access_token(): void {
		$plugin       = Plugin::get_instance();
		$client       = $plugin->get_client();
		$settings     = $plugin->get_settings();
		$access_token = $settings->get_access_token();

		if ( empty( $access_token ) ) {
			$settings->set_auth_status( Settings::AUTH_STATUS_REAUTHORIZE );
			return;
		}

		$new_access_token = $client->refresh_access_token( $access_token );

		if ( is_wp_error( $new_access_token ) ) {
			$this->handle_refresh_failure( $new_access_token );
			return;
		}

		// Success: clear the failure counter and reschedule.
		delete_option( self::RETRY_COUNT_OPTION );

		// Store the access token.
		$settings->set_access_token( $new_access_token );

		// Schedule token refresh (30 days from now).
		wp_schedule_single_event( strtotime( '+30 days' ), self::REFRESH_TOKEN_HOOK );
	}

	/**
	 * Surface an admin notice when the connection needs re-authorization.
	 *
	 * @return void
	 */
	public function render_failure_notice(): void {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings    = Plugin::get_instance()->get_settings();
		$auth_status = $settings->get_auth_status();

		if ( Settings::AUTH_STATUS_FAILED !== $auth_status && Settings::AUTH_STATUS_REAUTHORIZE !== $auth_status ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
			esc_html__( 'Outstand Instagram Feed could not refresh its access token. Your feed may stop updating until you reconnect.', 'outstand-instagram-feed' ),
			esc_url( $settings->get_page_url() ),
			esc_html__( 'Reconnect your Instagram account', 'outstand-instagram-feed' )
		);
	}

	/**
	 * Handle a failed refresh attempt with exponential backoff.
	 *
	 * @param  \WP_Error $error The refresh error.
	 * @return void
	 */
	private function handle_refresh_failure( \WP_Error $error ): void {
		$settings = Plugin::get_instance()->get_settings();
		$retries  = (int) get_option( self::RETRY_COUNT_OPTION, 0 );

		Logger::error( 'refresh_access_token failed: ' . $error->get_error_message(), [ 'retries' => $retries ] );

		if ( $retries >= self::MAX_RETRIES ) {
			// Give up: mark failed and reset the counter.
			delete_option( self::RETRY_COUNT_OPTION );
			$settings->set_auth_status( Settings::AUTH_STATUS_FAILED );
			return;
		}

		// Schedule a retry with exponential backoff (1h, 2h, 4h, ...).
		update_option( self::RETRY_COUNT_OPTION, $retries + 1, false );

		$delay = HOUR_IN_SECONDS * ( 2 ** $retries );
		wp_schedule_single_event( time() + $delay, self::REFRESH_TOKEN_HOOK );
	}
}
