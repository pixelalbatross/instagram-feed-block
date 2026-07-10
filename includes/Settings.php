<?php

namespace Outstand\WP\InstagramFeed;

class Settings extends BaseModule {

	/**
	 * Authorization status: Authorized.
	 *
	 * @var string
	 */
	const AUTH_STATUS_AUTHORIZED = 'authorized';

	/**
	 * Authorization status: User denied.
	 *
	 * @var string
	 */
	const AUTH_STATUS_USER_DENIED = 'user_denied';

	/**
	 * Authorization status: Failed.
	 *
	 * @var string
	 */
	const AUTH_STATUS_FAILED = 'failed';

	/**
	 * Authorization status: Reauthorize.
	 *
	 * @var string
	 */
	const AUTH_STATUS_REAUTHORIZE = 'reauthorize';

	/**
	 * Authorization status: Not connected.
	 *
	 * @var string
	 */
	const AUTH_STATUS_NOT_CONNECTED = 'not_connected';

	/**
	 * Authorization status: Connecting.
	 *
	 * @var string
	 */
	const AUTH_STATUS_CONNECTING = 'connecting';

	/**
	 * OAuth redirect URI.
	 *
	 * @var string
	 */
	const REDIRECT_URI = 'outstand-instagram-feed/oauth-callback';

	/**
	 * Settings option name.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'outstand_instagram_feed_settings';

	/**
	 * Nonce action for the OAuth `state` CSRF token.
	 *
	 * @var string
	 */
	const OAUTH_NONCE_ACTION = 'outstand_instagram_feed_oauth';

	/**
	 * Tag prefix marking an encrypted stored secret.
	 *
	 * @var string
	 */
	const ENCRYPTION_TAG = 'enc:v1:';

	/**
	 * The settings page slug name.
	 *
	 * @access protected
	 * @var    string
	 */
	protected string $page_slug = 'outstand-instagram-feed';

	/**
	 * The settings page URL name.
	 *
	 * @access protected
	 * @var    string
	 */
	protected string $page_url = 'options-general.php?page=outstand-instagram-feed';

	/**
	 * The settings group name.
	 *
	 * @access protected
	 * @var    string
	 */
	protected string $option_group = 'outstand_instagram_feed_settings_group';

	/**
	 * Register any hooks and filters.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'add_rewrite_rules' ] );
		add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'update_option_' . self::OPTION_NAME, [ $this, 'update_settings' ], 10, 2 );
		add_action( 'template_redirect', [ $this, 'handle_authorization_redirect' ] );
	}

	/**
	 * Add rewrite rules.
	 *
	 * @return void
	 */
	public function add_rewrite_rules(): void {

		add_rewrite_rule(
			self::REDIRECT_URI . '/?$',
			'index.php?outstand-instagram-feed-auth=1',
			'top'
		);
	}

	/**
	 * Add settings page.
	 *
	 * @return void
	 */
	public function add_settings_page(): void {

		$hook_suffix = add_options_page(
			__( 'Outstand Instagram Feed', 'outstand-instagram-feed' ),
			__( 'Instagram Feed', 'outstand-instagram-feed' ),
			'manage_options',
			$this->get_page_slug(),
			[ $this, 'render_settings_page' ]
		);

		if ( ! empty( $hook_suffix ) ) {
			add_action( 'load-' . $hook_suffix, [ $this, 'clear_cache' ] );
			add_action( 'load-' . $hook_suffix, [ $this, 'add_help_tabs' ] );
		}
	}

	/**
	 * Clear cache on settings save.
	 *
	 * @return void
	 */
	public function clear_cache(): void {

		if ( ! is_admin() ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_GET['page'] ) && $_GET['page'] === $this->get_page_slug() && isset( $_GET['settings-updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			delete_transient( Client::CACHE_KEY );
		}
	}

	/**
	 * Add contextual help tabs.
	 *
	 * @return void
	 */
	public function add_help_tabs(): void {
		$screen = get_current_screen();

		if ( ! $screen ) {
			return;
		}

		// Requirements tab.
		$screen->add_help_tab(
			[
				'id'      => 'account-requirements',
				'title'   => __( 'Requirements', 'outstand-instagram-feed' ),
				'content' => $this->get_requirements_help_content(),
			]
		);

		// Setup Instructions tab.
		$screen->add_help_tab(
			[
				'id'      => 'setup-instructions',
				'title'   => __( 'Setup Instructions', 'outstand-instagram-feed' ),
				'content' => $this->get_setup_instructions_help_content(),
			]
		);

		// Add help sidebar.
		$screen->set_help_sidebar( $this->get_help_sidebar_content() );
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<form action="options.php" method="post">
				<?php
				settings_fields( $this->option_group );
				do_settings_sections( $this->get_page_slug() );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Register all the setting fields.
	 *
	 * @return void
	 */
	public function register_settings(): void {

		register_setting(
			$this->option_group,
			self::OPTION_NAME,
			[
				'sanitize_callback' => [ $this, 'sanitize_settings' ],
			]
		);

		add_settings_section(
			'app_settings',
			'',
			[ $this, 'display_app_settings_section' ],
			$this->get_page_slug()
		);

		if ( ! defined( 'OUTSTAND_INSTAGRAM_FEED_APP_ID' ) || ! defined( 'OUTSTAND_INSTAGRAM_FEED_APP_SECRET' ) ) {
			add_settings_field(
				'app_id',
				__( 'App ID', 'outstand-instagram-feed' ),
				[ $this, 'display_app_id_field' ],
				$this->get_page_slug(),
				'app_settings',
				[
					'label_for' => 'app_id',
				]
			);

			add_settings_field(
				'app_secret',
				__( 'App Secret', 'outstand-instagram-feed' ),
				[ $this, 'display_app_secret_field' ],
				$this->get_page_slug(),
				'app_settings',
				[
					'label_for' => 'app_secret',
				]
			);
		}

		add_settings_field(
			'auth_status',
			__( 'Status', 'outstand-instagram-feed' ),
			[ $this, 'display_auth_status_field' ],
			$this->get_page_slug(),
			'app_settings',
			[
				'label_for' => 'auth_status',
			]
		);
	}

	/**
	 * Sanitize settings before saving.
	 *
	 * Sanitizes each known field and drops any unknown keys. Non-form fields
	 * (access_token, auth_status) are preserved from the existing option so
	 * they are not lost on save. The App Secret is stored encrypted; a blank
	 * or unchanged submission keeps the existing ciphertext (and does not
	 * re-encrypt, so credential-change detection stays accurate).
	 *
	 * @param  mixed $input The submitted settings.
	 * @return array
	 */
	public function sanitize_settings( mixed $input ): array {

		$input    = is_array( $input ) ? $input : [];
		$existing = get_option( self::OPTION_NAME, [] );

		$output = [];

		$output['app_id'] = isset( $input['app_id'] )
			? sanitize_text_field( $input['app_id'] )
			: (string) ( $existing['app_id'] ?? '' );

		$submitted_secret = isset( $input['app_secret'] ) ? trim( sanitize_text_field( $input['app_secret'] ) ) : '';
		$existing_secret  = (string) ( $existing['app_secret'] ?? '' );

		if ( $submitted_secret === '' ) {
			// Blank submission: keep the stored (encrypted) secret.
			$output['app_secret'] = $existing_secret;
		} elseif ( $submitted_secret === self::decrypt_secret( $existing_secret ) ) {
			// Unchanged: keep existing ciphertext, avoiding a spurious re-encrypt.
			$output['app_secret'] = $existing_secret;
		} else {
			$output['app_secret'] = self::encrypt_secret( $submitted_secret );
		}

		// Preserve server-managed fields; never accept them from POST.
		$output['access_token'] = (string) ( $existing['access_token'] ?? '' );
		$output['auth_status']  = (string) ( $existing['auth_status'] ?? self::AUTH_STATUS_NOT_CONNECTED );

		return $output;
	}

	/**
	 * Display the "App Settings" section.
	 *
	 * @return void
	 */
	public function display_app_settings_section(): void {

		if ( ! empty( $this->get_app_id() ) && ! empty( $this->get_app_secret() ) ) {
			return;
		}

		?>
		<div class="notice notice-info">
			<p>
				<?php esc_html_e( 'For detailed setup instructions, click the "Help" tab in the top-right corner of this page.', 'outstand-instagram-feed' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Display the "App ID" field.
	 *
	 * @return void
	 */
	public function display_app_id_field(): void {
		$settings = get_option( self::OPTION_NAME, [] );
		$value    = ! empty( $settings['app_id'] ) ? $settings['app_id'] : '';

		printf(
			'<input size="50" id="app_id" name="%s[app_id]" type="text" value="%s">',
			esc_attr( self::OPTION_NAME ),
			esc_attr( $value )
		);
	}

	/**
	 * Display the "App Secret" field.
	 *
	 * @return void
	 */
	public function display_app_secret_field(): void {
		$has_secret = '' !== $this->get_app_secret();

		// Never render the stored secret. When one exists, show a placeholder;
		// submitting the field blank keeps the existing value.
		$placeholder = $has_secret
			? __( 'Leave blank to keep the current secret', 'outstand-instagram-feed' )
			: '';

		printf(
			'<input size="50" id="app_secret" name="%s[app_secret]" type="password" value="" placeholder="%s" autocomplete="off">',
			esc_attr( self::OPTION_NAME ),
			esc_attr( $placeholder )
		);
	}

	/**
	 * Display the "Authorization Status" field.
	 *
	 * @return void
	 */
	public function display_auth_status_field(): void {
		$plugin       = Plugin::get_instance();
		$client       = $plugin->get_client();
		$auth_status  = $this->get_auth_status();
		$access_token = $this->get_access_token();

		// Override status if we have a token.
		if ( ! empty( $access_token ) ) {
			$auth_status = self::AUTH_STATUS_AUTHORIZED;
		}

		$status_text = __( '⏳ Not Connected', 'outstand-instagram-feed' );
		$button_text = __( 'Connect Instagram Account', 'outstand-instagram-feed' );

		switch ( $auth_status ) {
			case self::AUTH_STATUS_AUTHORIZED:
				$status_text = __( '✅ Connected', 'outstand-instagram-feed' );
				$button_text = __( 'Reauthorize', 'outstand-instagram-feed' );
				break;

			case self::AUTH_STATUS_USER_DENIED:
				$status_text = __( '⚠️ Denied', 'outstand-instagram-feed' );
				$button_text = __( 'Try Again', 'outstand-instagram-feed' );
				break;

			case self::AUTH_STATUS_FAILED:
				$status_text = __( '❌ Failed', 'outstand-instagram-feed' );
				$button_text = __( 'Try Again', 'outstand-instagram-feed' );
				break;
		}

		$action_button = '';
		if ( ! empty( $this->get_app_id() ) && ! empty( $this->get_app_secret() ) ) {
			$state = wp_create_nonce( self::OAUTH_NONCE_ACTION );

			$action_button = sprintf(
				'<a href="%1$s" class="button button-secondary">%2$s</a>',
				esc_url( $client->get_authorization_url( $this->get_redirect_uri(), $state ) ),
				esc_html( $button_text )
			);
		}

		printf(
			'<div style="display: flex; align-items: center; gap: 12px;">
				<span>%1$s</span>
				%2$s
			</div>',
			esc_html( $status_text ),
			wp_kses_post( $action_button )
		);
	}

	/**
	 * Handle settings update.
	 *
	 * @param  mixed $old_value The old option value.
	 * @param  mixed $value     The new option value.
	 * @return void
	 */
	public function update_settings( $old_value, $value ): void {

		if (
			defined( 'OUTSTAND_INSTAGRAM_FEED_APP_ID' )
			&& defined( 'OUTSTAND_INSTAGRAM_FEED_APP_SECRET' )
		) {
			return;
		}

		$credentials_changed = false;
		if ( isset( $old_value['app_id'] ) && isset( $value['app_id'] ) && $old_value['app_id'] !== $value['app_id'] ) {
			$credentials_changed = true;
		}

		if ( isset( $old_value['app_secret'] ) && isset( $value['app_secret'] ) && $old_value['app_secret'] !== $value['app_secret'] ) {
			$credentials_changed = true;
		}

		// If credentials changed, clear existing auth data.
		if ( $credentials_changed ) {
			$this->clear_auth();
			delete_transient( Client::CACHE_KEY );
			wp_unschedule_hook( Cron::REFRESH_TOKEN_HOOK );
		}
	}

	/**
	 * Handle authorization redirect from Instagram OAuth flow.
	 *
	 * This method processes the callback from Instagram's OAuth authorization.
	 *
	 * It handles three scenarios:
	 * 1. User denied authorization (error parameter present).
	 * 2. Authorization failed (no code parameter).
	 * 3. Successful authorization (code parameter present).
	 *
	 * @return void
	 */
	public function handle_authorization_redirect(): void {

		// Check if this is an Instagram authorization callback.
		$process_auth = get_query_var( 'outstand-instagram-feed-auth' );

		if ( empty( $process_auth ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Verify the CSRF `state` token before acting on any callback data.
		// Without this, an attacker could complete the OAuth dance with their
		// own account and get an admin to visit the callback (login CSRF).
		// The `state` value is itself the nonce; it is verified immediately below.
		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! wp_verify_nonce( $state, self::OAUTH_NONCE_ACTION ) ) {
			Logger::error( 'oauth_state_invalid_or_expired' );
			$this->set_auth_status( self::AUTH_STATUS_FAILED );
			wp_safe_redirect( $this->get_page_url() );
			exit();
		}

		// Check for user denial or authorization errors.
		$error = isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( $_GET['error'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! empty( $error ) ) {
			// User denied authorization or other error occurred.
			$this->set_auth_status( self::AUTH_STATUS_USER_DENIED );
			wp_safe_redirect( $this->get_page_url() );
			exit();
		}

		// Get authorization code from Instagram.
		$code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( empty( $code ) ) {
			// No authorization code received - authorization failed.
			$this->set_auth_status( self::AUTH_STATUS_FAILED );
			wp_safe_redirect( $this->get_page_url() );
			exit();
		}

		// Exchange authorization code for short-lived access token (valid for 1 hour).
		$short_lived_access_token = $this->get_short_lived_access_token();

		if ( is_wp_error( $short_lived_access_token ) ) {
			// Failed to exchange code for token - likely invalid credentials or network issue.
			$this->set_auth_status( self::AUTH_STATUS_FAILED );
			wp_safe_redirect( $this->get_page_url() );
			exit();
		}

		// Exchange short-lived token for long-lived token (valid for 60 days).
		$long_lived_access_token = $this->get_long_lived_access_token( $short_lived_access_token );

		if ( is_wp_error( $long_lived_access_token ) ) {
			// Failed to get long-lived token - API error or invalid short-lived token.
			$this->set_auth_status( self::AUTH_STATUS_FAILED );
			wp_safe_redirect( $this->get_page_url() );
			exit();
		}

		// Store the long-lived access token and mark as authorized.
		$this->set_access_token( $long_lived_access_token );

		// Schedule token refresh (30 days from now).
		wp_schedule_single_event( strtotime( '+30 days' ), Cron::REFRESH_TOKEN_HOOK );

		wp_safe_redirect( $this->get_page_url() );
		exit();
	}

	/**
	 * Update auth status.
	 *
	 * @param string $auth_status Authorization status.
	 * @return void
	 */
	public function set_auth_status( string $auth_status ): void {
		$settings                = get_option( self::OPTION_NAME, [] );
		$settings['auth_status'] = $auth_status;
		update_option( self::OPTION_NAME, $settings );
	}

	/**
	 * Set access token.
	 *
	 * @param string $access_token Access token.
	 * @return void
	 */
	public function set_access_token( string $access_token ): void {
		$settings                 = get_option( self::OPTION_NAME, [] );
		$settings['access_token'] = self::encrypt_secret( $access_token );
		$settings['auth_status']  = self::AUTH_STATUS_AUTHORIZED;
		update_option( self::OPTION_NAME, $settings );
	}

	/**
	 * Clear authentication data.
	 *
	 * @return void
	 */
	public function clear_auth(): void {
		$settings                 = get_option( self::OPTION_NAME, [] );
		$settings['auth_status']  = self::AUTH_STATUS_NOT_CONNECTED;
		$settings['access_token'] = '';
		update_option( self::OPTION_NAME, $settings );
	}

	/**
	 * Get current auth status.
	 *
	 * @return string
	 */
	public function get_auth_status(): string {
		$settings = get_option( self::OPTION_NAME, [] );
		return $settings['auth_status'] ?? self::AUTH_STATUS_NOT_CONNECTED;
	}

	/**
	 * Get current access token.
	 *
	 * @return string
	 */
	public function get_access_token(): string {
		$settings = get_option( self::OPTION_NAME, [] );
		return self::decrypt_secret( (string) ( $settings['access_token'] ?? '' ) );
	}

	/**
	 * Get the App ID.
	 *
	 * @return string
	 */
	public function get_app_id(): string {

		if ( defined( 'OUTSTAND_INSTAGRAM_FEED_APP_ID' ) && defined( 'OUTSTAND_INSTAGRAM_FEED_APP_SECRET' ) ) {
			return OUTSTAND_INSTAGRAM_FEED_APP_ID;
		}

		$settings = get_option( self::OPTION_NAME, [] );
		return isset( $settings['app_id'] ) ? $settings['app_id'] : '';
	}

	/**
	 * Get the App Secret.
	 *
	 * @return string
	 */
	public function get_app_secret(): string {

		if ( defined( 'OUTSTAND_INSTAGRAM_FEED_APP_ID' ) && defined( 'OUTSTAND_INSTAGRAM_FEED_APP_SECRET' ) ) {
			return OUTSTAND_INSTAGRAM_FEED_APP_SECRET;
		}

		$settings = get_option( self::OPTION_NAME, [] );
		return self::decrypt_secret( (string) ( $settings['app_secret'] ?? '' ) );
	}

	/**
	 * Get the page slug.
	 *
	 * @return string
	 */
	public function get_page_slug(): string {
		return $this->page_slug;
	}

	/**
	 * Get the page URL.
	 *
	 * @return string
	 */
	public function get_page_url(): string {
		return admin_url( $this->page_url );
	}

	/**
	 * Get requirements help content.
	 *
	 * @return string
	 */
	protected function get_requirements_help_content(): string {

		$content = sprintf(
			'<p><strong>%s</strong></p>',
			__( 'Before you begin, ensure you have:', 'outstand-instagram-feed' )
		);

		$requirements = [
			sprintf(
				/* translators: %1$s: Open strong tag, %2$s: Close strong tag. */
				__( 'An Instagram %1$sBusiness%2$s or %1$sCreator%2$s account', 'outstand-instagram-feed' ),
				'<strong>',
				'</strong>'
			),
		];

		if ( ! defined( 'OUTSTAND_INSTAGRAM_FEED_APP_ID' ) || ! defined( 'OUTSTAND_INSTAGRAM_FEED_APP_SECRET' ) ) {
			$requirements[] = __( 'A Facebook Developer account', 'outstand-instagram-feed' );
		}

		$content .= sprintf(
			'<ul>%s</ul>',
			implode(
				'',
				array_map(
					function ( $requirement ) {
						return sprintf( '<li>%s</li>', $requirement );
					},
					$requirements
				)
			)
		);

		return $content;
	}

	/**
	 * Get setup instructions help content.
	 *
	 * @return string
	 */
	protected function get_setup_instructions_help_content(): string {
		$domain       = wp_parse_url( home_url(), PHP_URL_HOST );
		$redirect_uri = $this->get_redirect_uri();

		$create_app_steps = [
			'title' => __( 'Create App', 'outstand-instagram-feed' ),
			'steps' => [
				sprintf(
					/* translators: %1$s: Open anchor tag, %2$s: Close anchor tag. */
					__( 'Go to %1$sFacebook Developers Portal%2$s and click %3$sCreate App%4$s.', 'outstand-instagram-feed' ),
					sprintf( '<a href="%1$s" rel="noopener" target="_blank">', esc_url( 'https://developers.facebook.com/apps/' ) ),
					'</a>',
					'<strong>',
					'</strong>'
				),
				sprintf(
					/* translators: %1$s: Open em tag, %2$s: Close em tag. */
					__( 'Enter your app name (e.g., %1$sMy Website Social Feed%2$s or %1$sCompany Social Media%2$s) and your contact email address.', 'outstand-instagram-feed' ),
					'<em>',
					'</em>'
				),
				sprintf(
					/* translators: %1$s: Open strong tag, %2$s: Close strong tag. */
					__( 'Click %1$sNext%2$s.', 'outstand-instagram-feed' ),
					'<strong>',
					'</strong>'
				),
				sprintf(
					/* translators: %1$s: Open strong tag, %2$s: Close strong tag. */
					__( 'Under %1$sFilter by%2$s, choose %1$sOthers%2$s, then select %1$sOther%2$s as the use case and click %1$sNext%2$s.', 'outstand-instagram-feed' ),
					'<strong>',
					'</strong>'
				),
				sprintf(
					/* translators: %1$s: Open strong tag, %2$s: Close strong tag. */
					__( 'Select %1$sBusiness%2$s as app type and click %1$sNext%2$s.', 'outstand-instagram-feed' ),
					'<strong>',
					'</strong>'
				),
				sprintf(
					/* translators: %1$s: Open strong tag, %2$s: Close strong tag. */
					__( 'Click %1$sCreate app%2$s.', 'outstand-instagram-feed' ),
					'<strong>',
					'</strong>'
				),
			],
		];

		$configure_app_inner_steps = [
			sprintf(
				/* translators: %1$s: Open strong tag, %2$s: Close strong tag. */
				__( 'Add your %1$sPrivacy Policy URL%2$s and %1$sTerms of Service URL%2$s.', 'outstand-instagram-feed' ),
				'<strong>',
				'</strong>'
			),
			sprintf(
				/* translators: %1$s: Open code tag, %2$s: Close code tag, %3$s: Website domain. */
				__( 'Add your website domain to %1$sApp domains%2$s: %3$s%4$s%5$s', 'outstand-instagram-feed' ),
				'<strong>',
				'</strong>',
				'<code>',
				$domain,
				'</code>'
			),
			sprintf(
				/* translators: %1$s: Open strong tag, %2$s: Close strong tag. */
				__( 'Click %1$sSave changes%2$s.', 'outstand-instagram-feed' ),
				'<strong>',
				'</strong>'
			),
		];

		$configure_app_roles_steps = [
			sprintf(
				/* translators: %1$s: Open strong tag, %2$s: Close strong tag. */
				__( 'Go to Instagram', 'outstand-instagram-feed' ),
				'<strong>',
				'</strong>'
			),
			sprintf(
				/* translators: %1$s: Open strong tag, %2$s: Close strong tag. */
				__( 'Go to %1$sSettings%2$s > %1$sApps and websites%2$s > %1$sTester Invites%2$s.', 'outstand-instagram-feed' ),
				'<strong>',
				'</strong>'
			),
			sprintf(
				/* translators: %1$s: Open strong tag, %2$s: Close strong tag. */
				__( 'Accept the invitation.', 'outstand-instagram-feed' ),
				'<strong>',
				'</strong>'
			),
		];

		$configure_app_steps = [
			'title' => __( 'Configure App', 'outstand-instagram-feed' ),
			'steps' => [
				sprintf(
					/* translators: %1$s: Open strong tag, %2$s: Close strong tag, %3$s: List of steps. */
					__( 'Go to %1$sApp settings%2$s > %1$sBasic%2$s%3$s', 'outstand-instagram-feed' ),
					'<strong>',
					'</strong>',
					sprintf(
						'<ul class="ul-disc" style="margin-top: 0.5em;">%s</ul>',
						implode(
							'',
							array_map(
								function ( $inner_step ) {
									return sprintf( '<li>%s</li>', $inner_step );
								},
								$configure_app_inner_steps
							)
						)
					)
				),
				sprintf(
					/* translators: %1$s: Open strong tag, %2$s: Close strong tag. */
					__( 'In the app dashboard, under %1$sProducts%2$s, click %1$sSet up%2$s in %1$sInstagram%2$s.', 'outstand-instagram-feed' ),
					'<strong>',
					'</strong>'
				),
				sprintf(
					/* translators: %1$s: Open strong tag, %2$s: Close strong tag. */
					__( 'Copy your %1$sInstagram App ID%2$s and %1$sInstagram App Secret%2$s. Do NOT use the credentials from %1$sApp settings%2$s > %1$sBasic%2$s.', 'outstand-instagram-feed' ),
					'<strong>',
					'</strong>'
				),
				sprintf(
					/* translators: %1$s: Open strong tag, %2$s: Close strong tag. */
					__( 'Click %1$sSet up%2$s under %1$sSet up Instagram business login%2$s.', 'outstand-instagram-feed' ),
					'<strong>',
					'</strong>'
				),
				sprintf(
					/* translators: %1$s: Open code tag, %2$s: Close code tag, %3$s: Valid OAuth Redirect URI. */
					__( 'Add the following %1$sRedirect URL%2$s: %3$s%4$s%5$s', 'outstand-instagram-feed' ),
					'<strong>',
					'</strong>',
					'<code>',
					esc_url( $redirect_uri ),
					'</code>'
				),
				sprintf(
					/* translators: %1$s: Open strong tag, %2$s: Close strong tag. */
					__( 'Click %1$sSave%2$s.', 'outstand-instagram-feed' ),
					'<strong>',
					'</strong>'
				),
				sprintf(
					/* translators: %1$s: Open strong tag, %2$s: Close strong tag. */
					__( 'Go to %1$sApp Roles%2$s > %1$sRoles%2$s.', 'outstand-instagram-feed' ),
					'<strong>',
					'</strong>'
				),
				sprintf(
					/* translators: %1$s: Open strong tag, %2$s: Close strong tag. */
					__( 'Click %1$sAdd People%2$s.', 'outstand-instagram-feed' ),
					'<strong>',
					'</strong>'
				),
				sprintf(
					/* translators: %1$s: Open strong tag, %2$s: Close strong tag. */
					__( 'Select %1$sInstagram Tester%2$s role.', 'outstand-instagram-feed' ),
					'<strong>',
					'</strong>'
				),
				sprintf(
					/* translators: %1$s: Open strong tag, %2$s: Close strong tag. */
					__( 'Search for the account connected to your Instagram and click %1$sAdd%2$s.', 'outstand-instagram-feed' ),
					'<strong>',
					'</strong>'
				),
				sprintf(
					/* translators: %1$s: List of steps. */
					__( 'The person must accept the invitation:%1$s', 'outstand-instagram-feed' ),
					sprintf(
						'<ul class="ul-disc" style="margin-top: 0.5em;">%s</ul>',
						implode(
							'',
							array_map(
								function ( $inner_step ) {
									return sprintf( '<li>%s</li>', $inner_step );
								},
								$configure_app_roles_steps
							)
						)
					)
				),
			],
		];

		$configure_plugin_steps = [
			'title' => __( 'Configure Plugin', 'outstand-instagram-feed' ),
			'steps' => [
				sprintf(
					/* translators: %1$s: Open strong tag, %2$s: Close strong tag. */
					__( 'Enter your %1$sInstagram App ID%2$s and %1$sInstagram App Secret%2$s and click %1$sSave%2$s.', 'outstand-instagram-feed' ),
					'<strong>',
					'</strong>'
				),
				sprintf(
					/* translators: %1$s: Open strong tag, %2$s: Close strong tag. */
					__( 'Click %1$sConnect Instagram Account%2$s.', 'outstand-instagram-feed' ),
					'<strong>',
					'</strong>'
				),
				__( 'You will be redirected to Instagram where you can log in with your username and password.', 'outstand-instagram-feed' ),
				__( 'After login, you will see a permissions window. The only permission required is the one that mentions "View profile and access media". All others you can leave unchanged or toggle off.', 'outstand-instagram-feed' ),
				sprintf(
					/* translators: %1$s: Open strong tag, %2$s: Close strong tag. */
					__( 'Click %1$sAllow%2$s.', 'outstand-instagram-feed' ),
					'<strong>',
					'</strong>'
				),
				sprintf(
					/* translators: %1$s: Open strong tag, %2$s: Close strong tag. */
					__( 'You\'ll be redirected back to WordPress. Your connection will appear as %1$sConnected%2$s in the plugin settings.', 'outstand-instagram-feed' ),
					'<strong>',
					'</strong>'
				),
			],
		];

		$setup_steps = [
			'create_app'       => $create_app_steps,
			'configure_app'    => $configure_app_steps,
			'configure_plugin' => $configure_plugin_steps,
		];

		if ( defined( 'OUTSTAND_INSTAGRAM_FEED_APP_ID' ) && defined( 'OUTSTAND_INSTAGRAM_FEED_APP_SECRET' ) ) {
			unset( $setup_steps['create_app'] );
			unset( $setup_steps['configure_app'] );
			unset( $setup_steps['configure_plugin']['steps'][0] );
		}

		$step_number = 1;
		$total_steps = count( $setup_steps );

		$content = '';
		foreach ( $setup_steps as $setup_step ) {

			if ( $total_steps > 1 ) {
				$content .= sprintf(
					'<h3>%s</h3>',
					sprintf(
						/* translators: %1$s: Step number, %2$s: Step title. */
						__( 'Step %1$s: %2$s', 'outstand-instagram-feed' ),
						$step_number,
						$setup_step['title']
					)
				);
			}

			$content .= sprintf(
				'<ol>%s</ol>',
				implode(
					'',
					array_map(
						function ( $step ) {
							return sprintf( '<li>%s</li>', $step );
						},
						$setup_step['steps']
					)
				)
			);

			++$step_number;
		}

		return $content;
	}

	/**
	 * Get help sidebar content.
	 *
	 * @return string
	 */
	protected function get_help_sidebar_content(): string {

		$links = [
			sprintf(
				'<a href="%1$s" target="_blank" rel="noopener">%2$s</a>',
				esc_url( 'https://github.com/pixelalbatross/outstand-instagram-feed' ),
				__( 'Plugin Documentation', 'outstand-instagram-feed' )
			),
			sprintf(
				'<a href="%1$s" target="_blank" rel="noopener">%2$s</a>',
				esc_url( 'https://developers.facebook.com/docs/instagram-platform/instagram-api-with-instagram-login' ),
				__( 'Instagram API Documentation', 'outstand-instagram-feed' )
			),
			sprintf(
				'<a href="%1$s" target="_blank" rel="noopener">%2$s</a>',
				esc_url( 'https://help.instagram.com/502981923235522' ),
				__( 'Convert to Business Account', 'outstand-instagram-feed' )
			),
		];

		$content = sprintf(
			'<p><strong>%s</strong></p>',
			__( 'For more information:', 'outstand-instagram-feed' )
		);

		$content .= sprintf(
			'<ul>%s</ul>',
			implode(
				'',
				array_map(
					function ( $link ) {
						return sprintf(
							'<li>%s</li>',
							$link
						);
					},
					$links
				)
			)
		);

		return $content;
	}

	/**
	 * Get redirect URI.
	 *
	 * @access protected
	 * @return string
	 */
	protected function get_redirect_uri(): string {
		return home_url( self::REDIRECT_URI, 'https' );
	}

	/**
	 * Get short-lived access token.
	 *
	 * @access protected
	 * @return string|\WP_Error
	 */
	protected function get_short_lived_access_token(): string|\WP_Error {

		if ( empty( $this->get_app_id() ) || empty( $this->get_app_secret() ) ) {
			return new \WP_Error(
				'outstand_instagram_feed_invalid_settings',
				__( 'Invalid settings.', 'outstand-instagram-feed' )
			);
		}

		$code = sanitize_text_field( wp_unslash( $_GET['code'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification

		if ( empty( $code ) ) {
			return new \WP_Error(
				'outstand_instagram_feed_no_authorization_code',
				__( 'No authorization code received.', 'outstand-instagram-feed' )
			);
		}

		$redirect_uri = $this->get_redirect_uri();
		return Plugin::get_instance()->get_client()->get_short_lived_access_token( $code, $redirect_uri );
	}

	/**
	 * Get long-lived access token.
	 *
	 * @access protected
	 * @param  string $access_token Short-lived access token.
	 * @return string|\WP_Error
	 */
	protected function get_long_lived_access_token( string $access_token ): string|\WP_Error {

		if ( empty( $this->get_app_id() ) || empty( $this->get_app_secret() ) ) {
			return new \WP_Error(
				'outstand_instagram_feed_invalid_settings',
				__( 'Invalid settings.', 'outstand-instagram-feed' )
			);
		}

		return Plugin::get_instance()->get_client()->get_long_lived_access_token( $access_token );
	}

	/**
	 * Encrypt a secret for storage, returning an `enc:v1:` tagged ciphertext.
	 *
	 * @param  string $plaintext Plain secret.
	 * @return string Encrypted string, or empty string on failure/empty input.
	 */
	private static function encrypt_secret( string $plaintext ): string {

		if ( $plaintext === '' ) {
			return '';
		}

		$cipher = Encryption::encrypt( $plaintext );

		if ( $cipher === false ) {
			Logger::error( 'encrypt_secret: encryption failed; storing empty' );
			return '';
		}

		return self::ENCRYPTION_TAG . $cipher;
	}

	/**
	 * Decrypt a stored secret. Handles legacy plaintext transparently.
	 *
	 * @param  string $value Stored value.
	 * @return string Plain secret.
	 */
	private static function decrypt_secret( string $value ): string {

		if ( $value === '' ) {
			return '';
		}

		if ( strpos( $value, self::ENCRYPTION_TAG ) !== 0 ) {
			return $value; // Legacy plaintext.
		}

		$decrypted = Encryption::decrypt( substr( $value, strlen( self::ENCRYPTION_TAG ) ) );

		if ( $decrypted === false ) {
			Logger::error( 'decrypt_secret: failed; returning empty' );
			return '';
		}

		return $decrypted;
	}
}
