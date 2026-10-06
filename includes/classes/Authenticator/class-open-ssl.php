<?php
/**
 * Open SSL encrypt / decrypt class.
 *
 * @package   wp2fa
 * @copyright 2026 Melapress
 * @license   https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link      https://wordpress.org/plugins/wp-2fa/
 * @since     2.0.0
 */

declare(strict_types=1);

namespace WP2FA\Authenticator;

defined( 'ABSPATH' ) || exit;

use WP2FA\WP2FA;

use function WP2FA\Core\wp_salt;

/**
 * Open_SSL - Class for encryption and decryption of the string using open_ssl method
 *
 * @since 2.0.0
 */
if ( ! class_exists( '\WP2FA\Authenticator\Open_SSL' ) ) {

	/**
	 * Responsible for SSL operations
	 */
	class Open_SSL {

		const CIPHER_METHOD     = 'aes-256-ctr';
		const BLOCK_BYTE_SIZE   = 16;
		const DIGEST_ALGORITHM  = 'SHA256';
		const SECRET_KEY_PREFIX = 'lsc_';

		/**
		 * Marks a value in the authenticated format.
		 *
		 * Values written before it are plain base64 - which never contains a
		 * colon - and are still read as they always were.
		 *
		 * @since 4.2.0
		 */
		const AUTHENTICATED_PREFIX = 'v2:';

		/**
		 * Internal cache for the OpenSSL functions, which only the formats before v2 need.
		 *
		 * @var bool|null
		 *
		 * @since 4.2.0
		 */
		private static $openssl_enabled = null;

		/**
		 * Internal caches for the two secretbox implementations.
		 *
		 * @var bool|null
		 *
		 * @since 4.2.0
		 */
		private static $native_secretbox = null;

		/**
		 * The sodium_compat one.
		 *
		 * @var bool|null
		 *
		 * @since 4.2.0
		 */
		private static $compat_secretbox = null;

		/**
		 * Secretbox sizes - fixed by the algorithm, so not taken from constants a host may lack.
		 *
		 * @since 4.2.0
		 */
		const SECRETBOX_NONCE_BYTES = 24;
		const SECRETBOX_MAC_BYTES   = 16;

		/**
		 * Internal cache var for the PHP ssl functions availability
		 *
		 * @var mixed|boolean
		 *
		 * @since 2.0.0
		 */
		private static $ssl_enabled = null;

		/**
		 * Encrypts given text
		 *
		 * @param string $text - Text to be encrypted.
		 *
		 * @return string
		 *
		 * @throws \RuntimeException - If encryption fails.
		 *
		 * @since 2.0.0
		 */
		public static function encrypt( string $text ): string {
			/*
			 * Always authenticated: XSalsa20-Poly1305 (secretbox), natively or
			 * through the sodium_compat library WordPress ships. AES-CTR without
			 * a tag let a changed payload decrypt to changed data, and a host
			 * without OpenSSL got the text back as it was and stored it. Neither
			 * is written any more; both are still read.
			 */
			$nonce = \random_bytes( self::SECRETBOX_NONCE_BYTES );

			return self::AUTHENTICATED_PREFIX . \base64_encode( $nonce . self::secretbox_seal( $text, $nonce ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		}
		/**
		 * Splits a Base64 payload into the IV and ciphertext the cipher expects.
		 *
		 * @param string $text - Base64-encoded IV followed by ciphertext.
		 *
		 * @return array|null Two elements, IV then ciphertext; null if $text cannot
		 *                    be one of ours.
		 *
		 * @since 4.2.0
		 */
		private static function split_payload( string $text ) {
			/*
			 * Strict, so a value that is not Base64 at all is refused outright rather
			 * than being silently salvaged. Non-strict decoding skips the characters
			 * it does not recognise, which turns arbitrary input into a short run of
			 * bytes that looks superficially like a payload.
			 */
			$decoded = \base64_decode( $text, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

			if ( false === $decoded ) {
				return null;
			}

			$ivlen = \openssl_cipher_iv_length( self::CIPHER_METHOD );

			if ( false === $ivlen ) {
				return null;
			}

			/*
			 * Anything shorter than the IV cannot be one of ours, and handing the
			 * short slice to openssl_decrypt() made it complain — "IV passed is only
			 * N bytes long" — which was reachable from an unauthenticated request,
			 * since the Out of Band login handler passes ?code= straight through.
			 *
			 * Exactly the IV length is allowed: that is what encrypt('') produces,
			 * and it still decrypts to an empty string as it always has.
			 */
			if ( \strlen( $decoded ) < $ivlen ) {
				return null;
			}

			return array( \substr( $decoded, 0, $ivlen ), \substr( $decoded, $ivlen ) );
		}

		/**
		 * Decrypts crypt text
		 *
		 * @param string $text - Encrypted text to be decrypted.
		 *
		 * @return string
		 *
		 * @throws \RuntimeException - If decryption fails.
		 *
		 * @since 2.0.0
		 */
		public static function decrypt( string $text ): string {
			if ( 0 === \strpos( $text, self::AUTHENTICATED_PREFIX ) ) {
				return self::decrypt_authenticated( (string) \substr( $text, \strlen( self::AUTHENTICATED_PREFIX ) ) );
			}

			if ( self::openssl_available() ) {
				$payload = self::split_payload( $text );

				if ( null === $payload ) {
					// Not a payload this class produced; nothing to decrypt.
					return '';
				}

				list( $iv, $ciphertext_raw ) = $payload;

				$decrypted_text = \openssl_decrypt( $ciphertext_raw, self::CIPHER_METHOD, self::legacy_key(), OPENSSL_RAW_DATA, $iv );

				if ( false === $decrypted_text ) {
					throw new \RuntimeException( 'Decryption failed.' );
				}

				return $decrypted_text;
			}

			if ( self::sodium_available() ) {
				// Encrypted the old way, and this host can no longer read that.
				throw new \RuntimeException( 'Decryption failed: OpenSSL is needed to read this value.' );
			}

			return $text;
		}

		/**
		 * Opens a value written in the authenticated format.
		 *
		 * @param string $encoded - The value without its prefix.
		 *
		 * @return string
		 *
		 * @throws \RuntimeException - If the value is malformed or was changed.
		 *
		 * @since 4.2.0
		 */
		private static function decrypt_authenticated( string $encoded ): string {
			$raw = \base64_decode( $encoded, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

			if ( false === $raw || \strlen( $raw ) < self::SECRETBOX_NONCE_BYTES + self::SECRETBOX_MAC_BYTES ) {
				throw new \RuntimeException( 'Decryption failed.' );
			}

			$plain = self::secretbox_open( \substr( $raw, self::SECRETBOX_NONCE_BYTES ), \substr( $raw, 0, self::SECRETBOX_NONCE_BYTES ) );

			if ( false === $plain ) {
				// Changed, truncated, or written with another key: never returned as data.
				throw new \RuntimeException( 'Decryption failed: the value does not authenticate.' );
			}

			return $plain;
		}

		/**
		 * Seals a value with secretbox.
		 *
		 * @param string $text  - The value.
		 * @param string $nonce - A fresh nonce.
		 *
		 * @return string
		 *
		 * @throws \RuntimeException - If no secretbox implementation can be had at all.
		 *
		 * @since 4.2.0
		 */
		private static function secretbox_seal( string $text, string $nonce ): string {
			if ( self::native_secretbox() ) {
				return \sodium_crypto_secretbox( $text, $nonce, self::authenticated_key() );
			}

			if ( self::compat_secretbox() ) {
				return \ParagonIE_Sodium_Compat::crypto_secretbox( $text, $nonce, self::authenticated_key() );
			}

			// Only a WordPress missing its own bundled library gets here. Refuse rather than store the secret readable.
			throw new \RuntimeException( 'Encryption failed: no authenticated encryption is available.' );
		}

		/**
		 * Opens a secretbox; false when it does not authenticate.
		 *
		 * The library WordPress ships and the native extension produce the same
		 * bytes, so either opens what the other sealed.
		 *
		 * @param string $box   - The sealed value.
		 * @param string $nonce - Its nonce.
		 *
		 * @return string|false
		 *
		 * @throws \RuntimeException - If no secretbox implementation can be had at all.
		 *
		 * @since 4.2.0
		 */
		private static function secretbox_open( string $box, string $nonce ) {
			try {
				if ( self::native_secretbox() ) {
					return \sodium_crypto_secretbox_open( $box, $nonce, self::authenticated_key() );
				}

				if ( self::compat_secretbox() ) {
					return \ParagonIE_Sodium_Compat::crypto_secretbox_open( $box, $nonce, self::authenticated_key() );
				}
			} catch ( \Throwable $e ) {
				return false;
			}

			throw new \RuntimeException( 'Decryption failed: no authenticated encryption is available.' );
		}

		/**
		 * The key the formats before v2 used.
		 *
		 * Unchanged, so everything already stored still opens with it.
		 *
		 * @return string
		 *
		 * @since 4.2.0
		 */
		private static function legacy_key(): string {
			return \hash( 'sha256', (string) \base64_decode( wp_salt() ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		}

		/**
		 * The v2 key: from the same secret as before, so changing format changes no setup.
		 *
		 * @return string
		 *
		 * @since 4.2.0
		 */
		private static function authenticated_key(): string {
			return \hash_hmac( 'sha256', 'wp-2fa authenticated encryption v2', self::legacy_key(), true );
		}

		/**
		 * Whether sodium's secretbox is there - natively or as WordPress's sodium_compat.
		 *
		 * @return bool
		 *
		 * @since 4.2.0
		 */
		private static function sodium_available(): bool {
			return self::native_secretbox() || self::compat_secretbox();
		}

		/**
		 * Whether the native secretbox functions can be used.
		 *
		 * @return bool
		 *
		 * @since 4.2.0
		 */
		private static function native_secretbox(): bool {
			if ( null === self::$native_secretbox ) {
				self::$native_secretbox = \function_exists( 'sodium_crypto_secretbox' ) && \function_exists( 'sodium_crypto_secretbox_open' );
			}

			return self::$native_secretbox;
		}

		/**
		 * Whether WordPress's bundled sodium_compat library can be used - loading it if need be.
		 *
		 * WordPress loads it only when sodium_crypto_box() is missing, so a host
		 * that disables just the secretbox functions got neither and fell back to
		 * the old formats. The library is in every supported WordPress; it runs
		 * in pure PHP where the native functions are unavailable.
		 *
		 * @return bool
		 *
		 * @since 4.2.0
		 */
		private static function compat_secretbox(): bool {
			if ( null === self::$compat_secretbox ) {
				if ( ! \class_exists( 'ParagonIE_Sodium_Compat', false ) && \defined( 'ABSPATH' ) && \defined( 'WPINC' ) ) {
					$autoload = ABSPATH . WPINC . '/sodium_compat/autoload.php';
					if ( \is_readable( $autoload ) ) {
						require_once $autoload;
					}
				}

				self::$compat_secretbox = \class_exists( 'ParagonIE_Sodium_Compat' ) && \method_exists( 'ParagonIE_Sodium_Compat', 'crypto_secretbox' );
			}

			return self::$compat_secretbox;
		}

		/**
		 * Whether the OpenSSL functions the formats before v2 need are there.
		 *
		 * @return bool
		 *
		 * @since 4.2.0
		 */
		private static function openssl_available(): bool {
			if ( null === self::$openssl_enabled ) {
				self::$openssl_enabled = \function_exists( 'openssl_encrypt' ) && \function_exists( 'openssl_decrypt' ) && \function_exists( 'openssl_cipher_iv_length' );
			}

			return self::$openssl_enabled;
		}

		/**
		 * Decrypts crypt text
		 *
		 * @param string $text - Encrypted text to be decrypted.
		 *
		 * @return string
		 *
		 * @throws \RuntimeException - If legacy decryption fails.
		 *
		 * @since 2.0.0
		 */
		public static function decrypt_legacy( string $text ): string {
			if ( self::openssl_available() ) {
				$payload = self::split_payload( $text );

				if ( null === $payload ) {
					// Not a payload this class produced; nothing to decrypt.
					return '';
				}

				list( $iv, $ciphertext_raw ) = $payload;

				$key = \openssl_digest( \base64_decode( WP2FA::get_secret_key() ), self::DIGEST_ALGORITHM, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
				$decrypted_text = \openssl_decrypt( $ciphertext_raw, self::CIPHER_METHOD, $key, OPENSSL_RAW_DATA, $iv );

				if ( false === $decrypted_text ) {
					throw new \RuntimeException( 'Decryption failed.' );
				}

				$text = $decrypted_text;
			}

			return $text;
		}

		/**
		 * Decrypts old wps_ secret strings
		 *
		 * @param string $text - The encrypted string.
		 *
		 * @return string
		 *
		 * @throws \RuntimeException - If decryption fails.
		 *
		 * @since 2.3.0
		 */
		public static function decrypt_wps( string $text ): string {
			if ( self::openssl_available() ) {
				$payload = self::split_payload( $text );

				if ( null === $payload ) {
					// Not a payload this class produced; nothing to decrypt.
					return '';
				}

				list( $iv, $ciphertext_raw ) = $payload;

				$key = \openssl_digest( \base64_decode( \wp_salt() ), self::DIGEST_ALGORITHM, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
				$decrypted_text = \openssl_decrypt( $ciphertext_raw, self::CIPHER_METHOD, $key, OPENSSL_RAW_DATA, $iv );

				if ( false === $decrypted_text ) {
					throw new \RuntimeException( 'Decryption failed.' );
				}

				$text = $decrypted_text;
			}

			return $text;
		}

		/**
		 * Generates random bytes by given size
		 *
		 * @param integer $octets - Number of octets for use for random generator.
		 *
		 * @return string
		 *
		 * @since 2.0.0
		 */
		public static function secure_random( int $octets = 0 ): string {
			if ( 0 === $octets ) {
				$octets = self::BLOCK_BYTE_SIZE;
			}

			return \random_bytes( $octets );
		}

		/**
		 * Checks the open ssl methods existence
		 *
		 * @return boolean
		 *
		 * @since 2.0.0
		 */
		public static function is_ssl_available(): bool {
			/*
			 * Whether secrets can be encrypted at all - which callers use to decide
			 * whether to. Sodium is always there through WordPress, so in practice
			 * this is always true now, and nothing is stored as plain text for want
			 * of OpenSSL.
			 */
			if ( null === self::$ssl_enabled ) {
				self::$ssl_enabled = self::sodium_available();
			}

			return self::$ssl_enabled;
		}
	}
}
