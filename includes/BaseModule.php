<?php

namespace Outstand\WP\InstagramFeed;

/**
 * Abstract base class for plugin modules.
 *
 * Provides registration lifecycle and conditional loading support.
 */
abstract class BaseModule {

	/**
	 * Registers the module hooks.
	 *
	 * @return void
	 */
	abstract public function register(): void;

	/**
	 * Whether this module can be registered.
	 *
	 * @return bool
	 */
	public function can_register(): bool {
		return true;
	}
}
