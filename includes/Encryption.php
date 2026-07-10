<?php

namespace Outstand\WP\InstagramFeed;

use SodiumException;

/**
 * Sodium-based encryption utility for token and secret storage.
 */
class Encryption {

	/**
	 * Derive a 32-byte encryption key from WordPress salts.
	 *
	 * @return string The derived key.
	 */
	private static function get_key(): string {
		return sodium_crypto_generichash(
			wp_salt( 'auth' ) . wp_salt( 'secure_auth' ),
			'',
			SODIUM_CRYPTO_SECRETBOX_KEYBYTES
		);
	}

	/**
	 * Encrypt a plaintext string.
	 *
	 * @param string $plaintext The data to encrypt.
	 * @return string|false Base64-encoded encrypted data, or false on failure.
	 */
	public static function encrypt( string $plaintext ): string|false {
		try {
			$nonce      = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$ciphertext = sodium_crypto_secretbox( $plaintext, $nonce, self::get_key() );
		} catch ( SodiumException | \Exception $e ) {
			Logger::error( 'encrypt_failed: ' . $e->getMessage() );
			return false;
		}

		return base64_encode( $nonce . $ciphertext ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decrypt an encrypted string.
	 *
	 * @param string $encoded Base64-encoded encrypted data.
	 * @return string|false The decrypted data, or false on failure.
	 */
	public static function decrypt( string $encoded ): string|false {
		$decoded = base64_decode( $encoded, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( $decoded === false || strlen( $decoded ) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return false;
		}

		$nonce      = substr( $decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$ciphertext = substr( $decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );

		try {
			return sodium_crypto_secretbox_open( $ciphertext, $nonce, self::get_key() );
		} catch ( SodiumException | \Exception $e ) {
			Logger::error( 'decrypt_failed: ' . $e->getMessage() );
			return false;
		}
	}
}
