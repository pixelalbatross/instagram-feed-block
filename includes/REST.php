<?php

namespace Outstand\WP\InstagramFeed;

class REST extends BaseModule {

	/**
	 * Routes namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'outstand-instagram-feed/v1';

	/**
	 * Register any hooks and filters.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {

		register_rest_route(
			$this->namespace,
			'/posts',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'permission_callback' => fn() => current_user_can( 'edit_posts' ),
				'callback'            => [ $this, 'get_posts' ],
				'args'                => [
					'totalItems' => [
						'description'       => __( 'Number of posts to return.', 'outstand-instagram-feed' ),
						'type'              => 'integer',
						'minimum'           => 1,
						'maximum'           => Client::POSTS_PER_PAGE,
						'default'           => 6,
						'sanitize_callback' => 'absint',
						'validate_callback' => fn( $value ) => $value >= 1 && $value <= Client::POSTS_PER_PAGE,
					],
				],
			]
		);
	}

	/**
	 * Get posts.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_posts( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {

		$total_items = $request->get_param( 'totalItems' );

		$plugin   = Plugin::get_instance();
		$client   = $plugin->get_client();
		$posts    = $client->get_posts( $total_items );
		$response = rest_ensure_response( $posts );

		return $response;
	}
}
