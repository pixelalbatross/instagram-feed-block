<?php

namespace Outstand\WP\InstagramFeed;

use RuntimeException;

/**
 * Trait for resolving asset dependencies and versions from build manifests.
 */
trait GetAssetInfo {

	/**
	 * Path to the dist directory.
	 *
	 * @var ?string
	 */
	public $dist_path = null;

	/**
	 * Fallback version to use if asset file is not found.
	 *
	 * @var ?string
	 */
	public $fallback_version = null;

	/**
	 * Setup asset variables.
	 *
	 * @param string $dist_path        Path to the dist directory.
	 * @param string $fallback_version Fallback version to use if asset file is not found.
	 *
	 * @return void
	 */
	public function setup_asset_vars( string $dist_path, string $fallback_version ) {
		$this->dist_path        = trailingslashit( $dist_path );
		$this->fallback_version = $fallback_version;
	}

	/**
	 * Get asset info from extracted asset files.
	 *
	 * @param string  $slug      Asset slug as defined in build/webpack configuration.
	 * @param ?string $attribute Optional attribute to get. Can be version or dependencies.
	 *
	 * @throws RuntimeException If asset variables are not set.
	 *
	 * @return string|array
	 */
	public function get_asset_info( string $slug, ?string $attribute = null ) {

		if ( is_null( $this->dist_path ) || is_null( $this->fallback_version ) ) {
			throw new RuntimeException( 'Asset variables not set. Please run setup_asset_vars() before calling get_asset_info().' );
		}

		if ( file_exists( $this->dist_path . $slug . '.asset.php' ) ) {
			$asset = require $this->dist_path . $slug . '.asset.php';
		} else {
			$asset = [
				'version'      => $this->fallback_version,
				'dependencies' => [],
			];
		}

		if ( empty( $attribute ) ) {
			return $asset;
		}

		if ( isset( $asset[ $attribute ] ) ) {
			return $asset[ $attribute ];
		}

		return '';
	}
}
