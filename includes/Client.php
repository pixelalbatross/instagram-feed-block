<?php

namespace Outstand\WP\InstagramFeed;

class Client {

	/**
	 * Cache key for posts.
	 *
	 * @var string
	 */
	public const CACHE_KEY = 'outstand_instagram_feed_posts';

	/**
	 * Posts per page.
	 *
	 * @var int
	 */
	public const POSTS_PER_PAGE = 50;

	/**
	 * Authorization URL.
	 *
	 * @var string
	 */
	private const AUTHORIZATION_URL = 'https://www.instagram.com/oauth/authorize';

	/**
	 * Access token URL.
	 *
	 * @var string
	 */
	private const ACCESS_TOKEN_URL = 'https://api.instagram.com/oauth/access_token';

	/**
	 * Long-lived access token URL.
	 *
	 * @var string
	 */
	private const LONG_LIVED_ACCESS_TOKEN_URL = 'https://graph.instagram.com/access_token';

	/**
	 * Refresh access token URL.
	 *
	 * @var string
	 */
	private const REFRESH_ACCESS_TOKEN_URL = 'https://graph.instagram.com/refresh_access_token';

	/**
	 * Media URL.
	 *
	 * @var string
	 */
	private const MEDIA_URL = 'https://graph.instagram.com/me/media';

	/**
	 * Cache duration in seconds (5 minutes).
	 *
	 * @var int
	 */
	private const CACHE_DURATION = 5 * MINUTE_IN_SECONDS;

	/**
	 * App ID.
	 *
	 * @var string
	 */
	private string $app_id;

	/**
	 * App Secret.
	 *
	 * @var string
	 */
	private string $app_secret;

	/**
	 * Access Token.
	 *
	 * @var string
	 */
	private string $access_token;

	/**
	 * Constructor.
	 *
	 * @param string $app_id       App ID.
	 * @param string $app_secret   App Secret.
	 * @param string $access_token Access Token.
	 */
	public function __construct( string $app_id = '', string $app_secret = '', string $access_token = '' ) {
		$this->app_id       = $app_id;
		$this->app_secret   = $app_secret;
		$this->access_token = $access_token;
	}

	/**
	 * Get authorization URL.
	 *
	 * @param string $redirect_uri OAuth redirect URI.
	 * @param string $state        CSRF state token (WP nonce) round-tripped through the OAuth flow.
	 * @return string
	 */
	public function get_authorization_url( string $redirect_uri, string $state = '' ): string {

		$permissions = [
			'instagram_business_basic',
		];

		return add_query_arg(
			[
				'force_reauth'  => 'true',
				'client_id'     => $this->app_id,
				'redirect_uri'  => $redirect_uri,
				'response_type' => 'code',
				'scope'         => implode( ',', $permissions ),
				'state'         => $state,
			],
			self::AUTHORIZATION_URL
		);
	}

	/**
	 * Exchange authorization code for short-lived access token.
	 *
	 * This method exchanges the authorization code received from Instagram
	 * for a short-lived access token (valid for 1 hour).
	 *
	 * @param string $code         Authorization code from Instagram.
	 * @param string $redirect_uri OAuth redirect URI used in authorization.
	 * @return string|\WP_Error Short-lived access token or error.
	 */
	public function get_short_lived_access_token( string $code, string $redirect_uri ): string|\WP_Error {

		$code = sanitize_text_field( $code );

		if ( empty( $code ) ) {
			return new \WP_Error(
				'outstand_instagram_feed_invalid_authorization_code',
				__( 'Invalid authorization code.', 'outstand-instagram-feed' )
			);
		}

		$response = wp_remote_post(
			self::ACCESS_TOKEN_URL,
			[
				'body'    => [
					'client_id'     => $this->app_id,
					'client_secret' => $this->app_secret,
					'redirect_uri'  => $redirect_uri,
					'code'          => $code,
					'grant_type'    => 'authorization_code',
				],
				'timeout' => 30,
			]
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error(
				'outstand_instagram_feed_token_exchange_network_error',
				sprintf(
					/* translators: %s: Network error message */
					__( 'Network error while exchanging authorization code for access token: %s', 'outstand-instagram-feed' ),
					$response->get_error_message()
				)
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! empty( $data['error_type'] ) ) {
			return new \WP_Error(
				'outstand_instagram_feed_token_exchange_api_error',
				sprintf(
					/* translators: %s: API error message */
					__( 'Instagram API error during token exchange: %s', 'outstand-instagram-feed' ),
					$data['error_message'] ?? __( 'Unknown error.', 'outstand-instagram-feed' )
				)
			);
		}

		if ( empty( $data['access_token'] ) ) {
			return new \WP_Error(
				'outstand_instagram_feed_no_short_lived_token',
				__( 'Instagram API did not return an access token. Please check your app credentials.', 'outstand-instagram-feed' )
			);
		}

		return $data['access_token'];
	}

	/**
	 * Get long-lived access token.
	 *
	 * @param string $access_token Short-lived access token.
	 * @return string|\WP_Error
	 */
	public function get_long_lived_access_token( string $access_token ): string|\WP_Error {

		$response = wp_remote_get(
			add_query_arg(
				[
					'grant_type'    => 'ig_exchange_token',
					'client_secret' => $this->app_secret,
					'access_token'  => $access_token,
				],
				self::LONG_LIVED_ACCESS_TOKEN_URL
			)
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error(
				'outstand_instagram_feed_error',
				sprintf(
					/* translators: %s: Error message */
					__( 'Error: %s', 'outstand-instagram-feed' ),
					$response->get_error_message()
				)
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( empty( $data['access_token'] ) ) {
			return new \WP_Error(
				'outstand_instagram_feed_no_long_lived_token',
				__( 'No long-lived access token received.', 'outstand-instagram-feed' )
			);
		}

		return $data['access_token'];
	}

	/**
	 * Refresh access token.
	 *
	 * @param string $access_token Current access token.
	 * @return string|\WP_Error
	 */
	public function refresh_access_token( string $access_token ): string|\WP_Error {

		$response = wp_remote_get(
			add_query_arg(
				[
					'grant_type'   => 'ig_refresh_token',
					'access_token' => $access_token,
				],
				self::REFRESH_ACCESS_TOKEN_URL
			)
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error(
				'outstand_instagram_feed_error',
				sprintf(
					/* translators: %s: Error message */
					__( 'Error: %s', 'outstand-instagram-feed' ),
					$response->get_error_message()
				)
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( empty( $data['access_token'] ) ) {
			return new \WP_Error(
				'outstand_instagram_feed_no_refreshed_token',
				__( 'No refreshed access token received.', 'outstand-instagram-feed' )
			);
		}

		return $data['access_token'];
	}

	/**
	 * Get media.
	 *
	 * @return array|\WP_Error
	 */
	public function get_media(): array|\WP_Error {

		if ( empty( $this->access_token ) ) {
			return new \WP_Error(
				'outstand_instagram_feed_invalid_access_token',
				__( 'Invalid access token.', 'outstand-instagram-feed' )
			);
		}

		$fields = [
			'id',
			'media_type',
			'media_url',
			'caption',
			'timestamp',
			'permalink',
			'thumbnail_url',
		];

		$response = wp_remote_get(
			add_query_arg(
				[
					'fields'       => implode( ',', $fields ),
					'access_token' => $this->access_token,
					'limit'        => self::POSTS_PER_PAGE,
				],
				self::MEDIA_URL
			),
			[
				'timeout' => 30,
			]
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error(
				'outstand_instagram_feed_error',
				sprintf(
					/* translators: %s: Error message */
					__( 'Error: %s', 'outstand-instagram-feed' ),
					$response->get_error_message()
				)
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( empty( $data['data'] ) ) {
			return [];
		}

		return $data['data'];
	}

	/**
	 * Get posts.
	 *
	 * @param int $total Number of posts to return.
	 * @return false|array
	 */
	public function get_posts( int $total = self::POSTS_PER_PAGE ): array|false {

		$cached_posts = get_transient( self::CACHE_KEY );

		if ( $cached_posts !== false && is_array( $cached_posts ) ) {
			return $this->limit_posts( $cached_posts, $total );
		}

		$media = $this->get_media();

		if ( is_wp_error( $media ) ) {
			return false;
		}

		$posts = $this->format_posts( $media );

		set_transient( self::CACHE_KEY, $posts, self::CACHE_DURATION );

		return $this->limit_posts( $posts, $total );
	}

	/**
	 * Format posts.
	 *
	 * @param array $posts Raw media posts.
	 * @return array
	 */
	private function format_posts( array $posts ): array {

		$formatted_posts = [];
		foreach ( $posts as $post_data ) {

			$caption = '';
			if ( ! empty( $post_data['caption'] ) ) {
				$caption = trim( wp_strip_all_tags( $post_data['caption'] ) );
			}

			$thumbnail_url = '';
			if ( ! empty( $post_data['thumbnail_url'] ) ) {
				$thumbnail_url = esc_url_raw( $post_data['thumbnail_url'] );
			}

			$post = [
				'id'            => sanitize_key( $post_data['id'] ),
				'media_type'    => sanitize_text_field( strtolower( $post_data['media_type'] ) ),
				'media_url'     => esc_url_raw( $post_data['media_url'] ),
				'caption'       => $caption,
				'timestamp'     => sanitize_text_field( $post_data['timestamp'] ),
				'permalink'     => esc_url_raw( $post_data['permalink'] ),
				'thumbnail_url' => $thumbnail_url,
			];

			/**
			 * Filters the post.
			 *
			 * @param array $post      The sanitized post.
			 * @param array $post_data The raw post data.
			 */
			$post = apply_filters( 'outstand_instagram_feed_post', $post, $post_data );

			$formatted_posts[] = $post;
		}

		return $formatted_posts;
	}

	/**
	 * Limit posts to specified total.
	 *
	 * @param array $posts Array of posts.
	 * @param int   $total Total number of posts to return.
	 * @return array
	 */
	private function limit_posts( array $posts, int $total ): array {
		if ( $total === 0 ) {
			return $posts;
		}

		return array_slice( $posts, 0, $total );
	}
}
