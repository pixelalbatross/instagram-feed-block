<?php

namespace Outstand\WP\InstagramFeed;

class Blocks extends BaseModule {

	/**
	 * Register any hooks and filters.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'register_blocks' ] );
		add_action( 'enqueue_block_editor_assets', [ $this, 'blocks_editor_scripts' ] );
	}

	/**
	 * Registers the block using the metadata loaded from the `block.json` file.
	 * Behind the scenes, it registers also all assets so they can be enqueued
	 * through the block editor in the corresponding context.
	 */
	public function register_blocks(): void {

		$block_json_files = glob( OUTSTAND_INSTAGRAM_FEED_PATH . 'build/*/block.json' );

		foreach ( $block_json_files as $filename ) {

			$args = [];
			if ( str_contains( $filename, 'instagram-post-template' ) ) {
				$args = [
					'skip_inner_blocks' => true,
					'render_callback'   => [ $this, 'render_block_instagram_feed_template' ],
				];
			}

			$block_folder = dirname( $filename );
			$block_type   = register_block_type_from_metadata( $block_folder, $args );

			if ( ! empty( $block_type->editor_script_handles ) ) {
				foreach ( $block_type->editor_script_handles as $handle ) {
					wp_set_script_translations(
						$handle,
						'outstand-instagram-feed',
						OUTSTAND_INSTAGRAM_FEED_PATH . 'languages'
					);
				}
			}
		}
	}

	/**
	 * Enqueue editor-only JavaScript for blocks.
	 *
	 * @return void
	 */
	public function blocks_editor_scripts(): void {

		$settings    = Plugin::get_instance()->get_settings();
		$auth_status = $settings->get_auth_status();

		wp_localize_script(
			'outstand-instagram-feed-editor-script',
			'outstandInstagramFeed',
			[
				'isActive'    => $auth_status === Settings::AUTH_STATUS_AUTHORIZED,
				'settingsUrl' => $settings->get_page_url(),
			]
		);
	}

	/**
	 * Renders the `outstand/instagram-post-template` block on the server.
	 *
	 * @param  array     $attributes The block attributes.
	 * @param  string    $content    The block content.
	 * @param  \WP_Block $instance   The block instance.
	 * @return string
	 */
	public function render_block_instagram_feed_template( array $attributes, string $content, \WP_Block $instance ): string {

		$posts = Plugin::get_instance()->get_client()->get_posts( $instance->context['totalItems'] );

		$classnames = [];

		if ( isset( $instance->context['displayLayout'] ) ) {
			if ( isset( $instance->context['displayLayout']['type'] ) && 'flex' === $instance->context['displayLayout']['type'] ) {
				$classnames[] = 'is-flex-container';
				$classnames[] = "columns-{$instance->context['displayLayout']['columns']}";
			}
		}

		if ( isset( $attributes['style']['elements']['link']['color']['text'] ) ) {
			$classnames[] = 'has-link-color';
		}

		$wrapper_attributes = get_block_wrapper_attributes(
			[
				'class' => implode( ' ', $classnames ),
			]
		);

		$content = '';
		foreach ( $posts as $post ) {
			$block_instance = $instance->parsed_block;

			// Set the block name to one that does not correspond to an existing registered block.
			// This ensures that for the inner instances of the Instagram Feed Template block, we do not render any block supports.
			$block_instance['blockName'] = 'core/null';

			$filter_block_context = static function ( $context ) use ( $post ) {
				$context['mediaId']      = $post['id'];
				$context['mediaType']    = $post['media_type'];
				$context['mediaUrl']     = $post['media_url'];
				$context['caption']      = $post['caption'];
				$context['timestamp']    = $post['timestamp'];
				$context['permalink']    = $post['permalink'];
				$context['thumbnailUrl'] = $post['thumbnail_url'];
				return $context;
			};

			add_filter( 'render_block_context', $filter_block_context, 1 );
			$inner_block_content = ( new \WP_Block( $block_instance ) )->render( [ 'dynamic' => false ] );
			remove_filter( 'render_block_context', $filter_block_context, 1 );

			$content .= sprintf(
				'<li class="wp-block-outstand-instagram-post">%1$s</li>',
				$inner_block_content
			);
		}

		return sprintf(
			'<ul %1$s>%2$s</ul>',
			$wrapper_attributes,
			$content
		);
	}
}
