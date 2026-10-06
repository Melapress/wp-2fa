<?php
/**
 * EDD Network Licensing.
 *
 * Handles multisite-specific licensing logic: activating/deactivating
 * licenses for all subsites in a network, tracking per-site activation
 * status, and managing the subsite lifecycle (new site / deleted site).
 *
 * Each subsite in the network consumes one activation slot from the
 * EDD license. The network admin manages the license from the network
 * admin dashboard, and all subsites are activated/deactivated as a batch.
 *
 * All plugin-specific values are sourced from Licensing_Factory constants
 * — no direct coupling to plugin globals.
 *
 * @since      2.4.0
 * @package    wp2fa
 * @subpackage Licensing
 * @copyright  2026 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

declare(strict_types=1);

namespace WP2FA\Licensing;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP2FA\Utils\Debugging;
use WP2FA\Admin\Helpers\User_Helper;
use WP2FA\Admin\Helpers\Methods_Helper;

if ( ! class_exists( '\WP2FA\Licensing\EDD_Network_Licensing' ) ) {

	/**
	 * Handles multisite network licensing operations.
	 *
	 * @since 2.4.0
	 */
	class EDD_Network_Licensing {

		/**
		 * Network option for storing per-site activation data.
		 *
		 * References the canonical constant in Licensing_Factory.
		 *
		 * @var string
		 */
		const NETWORK_ACTIVATIONS_OPTION = Licensing_Factory::NETWORK_ACTIVATIONS_OPTION;

		/**
		 * Transient name for tracking batch activation progress.
		 *
		 * References the canonical constant in Licensing_Factory.
		 *
		 * @var string
		 */
		const PROGRESS_TRANSIENT = Licensing_Factory::NETWORK_PROGRESS_TRANSIENT;

		/**
		 * Number of sites to process per batch.
		 *
		 * @var int
		 */
		const BATCH_SIZE = 5;

		/**
		 * Progress transient TTL in seconds (5 minutes).
		 *
		 * @var int
		 */
		const PROGRESS_TTL = 300;

		/**
		 * Network option flag for failed subsite activation (insufficient slots).
		 *
		 * References the canonical constant in Licensing_Factory.
		 *
		 * @var string
		 */
		const ACTIVATION_FAILED_FLAG = Licensing_Factory::NETWORK_ACTIVATION_FAILED_FLAG;

		/**
		 * Initialize the network licensing hooks.
		 *
		 * Registers hooks for the subsite lifecycle (creation/deletion)
		 * to automatically activate/deactivate licenses.
		 *
		 * @return void
		 *
		 * @since 2.4.0
		 */
		public static function init() {
			/**
			 * The site's options, including its home URL, only exist once core has
			 * initialized it (wp_initialize_site at priority 10). Hooking in after that
			 * lets every path build the site URL the same way, with get_home_url().
			 */
			\add_action( 'wp_initialize_site', array( __CLASS__, 'on_site_created' ), 20 );
			\add_action( 'wp_delete_site', array( __CLASS__, 'on_site_deleted' ) );

			// Soft free mode: subsites not covered by the license offer only free methods for new setup.
			\add_filter( WP_2FA_PREFIX . 'wizard_enabled_methods', array( __CLASS__, 'filter_wizard_methods' ), 10, 3 );
		}

		/**
		 * Handle new subsite creation.
		 *
		 * Automatically activates the license for the new subsite if
		 * a valid license exists and activation slots are available.
		 * If no slots are available, sets a persistent notice flag.
		 *
		 * @param \WP_Site $new_site - The new site object.
		 *
		 * @return void
		 *
		 * @since 2.4.0
		 */
		public static function on_site_created( $new_site ) {
			if ( ! EDD_Provider::has_active_valid_license() ) {
				return;
			}

			$license_key  = EDD_Provider::get_license_option( EDD_Provider::LICENSE_KEY_OPTION );
			$license_data = EDD_Provider::get_license_option( EDD_Provider::LICENSE_DATA_OPTION, array() );
			$item_id      = is_array( $license_data ) && isset( $license_data['item_id'] ) ? (int) $license_data['item_id'] : 0;

			if ( empty( $license_key ) || 0 === $item_id ) {
				return;
			}

			$site_url = self::get_site_url( (int) $new_site->blog_id );

			// Check if slots are available.
			$slots_available = self::get_available_slots( $license_key, $item_id );

			if ( false === $slots_available || $slots_available < 1 ) {
				// No slots available — the site stays unlicensed until a sync finds a free slot.
				self::update_activation_failed_flag();
				return;
			}

			// Activate the new subsite. Its reply must not replace the network's license state.
			$result      = EDD_Provider::try_activate_license( $license_key, $item_id, $site_url, false );
			$activations = self::get_network_activation_status();

			if ( true === $result ) {
				$activations[ $site_url ] = array(
					'status'       => 'active',
					'activated_at' => time(),
				);
			} else {
				$activations[ $site_url ] = array(
					'status' => 'failed',
					'error'  => is_array( $result ) && isset( $result['message'] ) ? $result['message'] : 'Unknown error',
				);
			}

			\update_site_option( self::NETWORK_ACTIVATIONS_OPTION, $activations );
			self::update_activation_failed_flag();

			// Refresh license data.
			EDD_Provider::check_license( $license_key );
		}

		/**
		 * Handle subsite deletion.
		 *
		 * Deactivates the license for the deleted subsite to free the
		 * activation slot, and removes it from the network activations data.
		 *
		 * @param \WP_Site $old_site - The deleted site object.
		 *
		 * @return void
		 *
		 * @since 2.4.0
		 */
		public static function on_site_deleted( $old_site ) {
			$license_key  = EDD_Provider::get_license_option( EDD_Provider::LICENSE_KEY_OPTION );
			$license_data = EDD_Provider::get_license_option( EDD_Provider::LICENSE_DATA_OPTION, array() );
			$item_id      = is_array( $license_data ) && isset( $license_data['item_id'] ) ? (int) $license_data['item_id'] : 0;

			if ( empty( $license_key ) || 0 === $item_id ) {
				return;
			}

			$activations = self::get_network_activation_status();

			/**
			 * The site's tables are gone, so its home URL can't be read any more. Use
			 * the URL it was activated under, found by domain and path, and fall back
			 * to building one from the site object.
			 */
			$site_url = self::find_activation_url( $activations, (string) $old_site->domain, (string) $old_site->path );

			if ( '' === $site_url ) {
				$scheme   = \is_ssl() ? 'https' : 'http';
				$site_url = \untrailingslashit( \esc_url( $scheme . '://' . $old_site->domain . $old_site->path ) );
			}

			// Deactivate this site's license on the store.
			EDD_Provider::try_deactivate_for_url( $license_key, $item_id, $site_url );

			// Remove from stored activations.
			if ( isset( $activations[ $site_url ] ) ) {
				unset( $activations[ $site_url ] );
				\update_site_option( self::NETWORK_ACTIVATIONS_OPTION, $activations );
			}

			// Keep the warning only while another site is still unlicensed.
			self::update_activation_failed_flag();

			// Refresh license data — switch to main site context first
			// because the deleted site's tables no longer exist.
			if ( ! empty( $license_key ) ) {
				\switch_to_blog( \get_main_site_id() );
				EDD_Provider::check_license( $license_key );
				\restore_current_blog();
			}
		}

		/**
		 * Activate the license for all subsites in the network.
		 *
		 * Pre-checks available activation slots against the number of subsites.
		 * If sufficient slots are available, activates each subsite individually
		 * in batches, tracking progress for frontend polling.
		 *
		 * @param string $license_key - The license key to activate.
		 *
		 * @return bool|array True on success, array with error info on failure.
		 *
		 * @since 2.4.0
		 */
		public static function activate_network_license( string $license_key ) {
			$sites      = self::get_all_site_urls();
			$site_count = count( $sites );

			// Step 1: Determine the correct item_id via sequential try.
			$item_id = self::resolve_item_id( $license_key );

			if ( \is_wp_error( $item_id ) ) {
				return array(
					'success' => false,
					'message' => \__( 'Could not reach the license server. Please try again.', 'wp-2fa' ),
					'code'    => 'store_unreachable',
				);
			}

			if ( 0 === $item_id ) {
				return array(
					'success' => false,
					'message' => \__( 'This license key is not valid for this product.', 'wp-2fa' ),
					'code'    => 'item_name_mismatch',
				);
			}

			// Step 2: Pre-check available slots.
			$slots_available = self::get_available_slots( $license_key, $item_id );

			if ( false === $slots_available ) {
				return array(
					'success' => false,
					'message' => \__( 'Unable to verify license status. Please try again.', 'wp-2fa' ),
					'code'    => 'check_failed',
				);
			}

			if ( $slots_available < $site_count ) {
				return array(
					'success' => false,
					'message' => sprintf(
						/* translators: 1: number of subsites, 2: available activations */
						\__( 'Your network has %1$d subsites but your license only has %2$d activations remaining. Please upgrade your license or reduce the number of subsites before activating.', 'wp-2fa' ),
						$site_count,
						$slots_available
					),
					'code'    => 'insufficient_slots',
				);
			}

			// Step 3: Store the license key and item_id before batch activation.
			EDD_Provider::update_license_option( EDD_Provider::LICENSE_KEY_OPTION, $license_key );

			// Step 4: Initialize progress tracking.
			self::set_progress( $site_count, 0, 'processing' );

			// Step 5: Batch activate all subsites.
			$activations = array();
			$errors      = array();
			$completed   = 0;

			$batches = array_chunk( $sites, self::BATCH_SIZE );

			foreach ( $batches as $batch ) {
				foreach ( $batch as $site_url ) {
					// Per-site replies must not replace the network's license state; check_license() refreshes it below.
					$result = EDD_Provider::try_activate_license( $license_key, $item_id, $site_url, false );

					if ( true === $result ) {
						$activations[ $site_url ] = array(
							'status'       => 'active',
							'activated_at' => time(),
						);
					} else {
						$error_msg                = is_array( $result ) && isset( $result['message'] ) ? $result['message'] : 'Unknown error';
						$activations[ $site_url ] = array(
							'status' => 'failed',
							'error'  => $error_msg,
						);
						$errors[]                 = $site_url . ': ' . $error_msg;
					}

					++$completed;
				}

				// Update progress after each batch.
				self::set_progress( $site_count, $completed, 'processing', $errors );
			}

			// Step 6: Store activation results, and show the warning only if a site is left unlicensed.
			\update_site_option( self::NETWORK_ACTIVATIONS_OPTION, $activations );
			self::update_activation_failed_flag();

			// Step 7: Determine overall success.
			$active_count = count(
				array_filter(
					$activations,
					function ( $a ) {
						return 'active' === $a['status'];
					}
				)
			);

			if ( $active_count > 0 ) {
				// At least some sites activated — mark license as valid.
				EDD_Provider::update_license_option( EDD_Provider::LICENSE_STATUS_OPTION, 'valid' );
				EDD_Provider::update_license_option( EDD_Provider::PREMIUM_OPTION, 'yes' );
				EDD_Provider::set_license_transient( EDD_Provider::PREMIUM_OPTION, 'yes', DAY_IN_SECONDS );
				EDD_Provider::delete_license_transient( EDD_Provider::LICENSE_CHECK_TRANSIENT );

				// Fetch fresh license data.
				EDD_Provider::check_license( $license_key );
			}

			// Step 8: Mark progress as complete.
			self::set_progress( $site_count, $completed, 'complete', $errors );

			if ( ! empty( $errors ) ) {
				return array(
					'success' => true,
					'message' => sprintf(
						/* translators: 1: activated count, 2: total count */
						\__( 'License activated on %1$d of %2$d sites. Some sites had errors.', 'wp-2fa' ),
						$active_count,
						$site_count
					),
					'partial' => true,
				);
			}

			return true;
		}

		/**
		 * Deactivate the license for all subsites in the network.
		 *
		 * Batch deactivates each subsite individually and clears all local data.
		 *
		 * @return bool True on success, false on failure.
		 *
		 * @since 2.4.0
		 */
		public static function deactivate_network_license(): bool {
			$license_key = EDD_Provider::get_license_option( EDD_Provider::LICENSE_KEY_OPTION );

			if ( empty( $license_key ) ) {
				return false;
			}

			$license_data = EDD_Provider::get_license_option( EDD_Provider::LICENSE_DATA_OPTION, array() );
			$item_id      = is_array( $license_data ) && isset( $license_data['item_id'] ) ? (int) $license_data['item_id'] : 0;
			$activations  = self::get_network_activation_status();

			// If no stored activations, use current sites.
			if ( empty( $activations ) ) {
				$sites = self::get_all_site_urls();
			} else {
				$sites = array_keys( $activations );
			}

			$site_count = count( $sites );

			// Initialize progress.
			self::set_progress( $site_count, 0, 'processing' );

			$completed = 0;
			$errors    = array();
			$batches   = array_chunk( $sites, self::BATCH_SIZE );

			foreach ( $batches as $batch ) {
				foreach ( $batch as $site_url ) {
					if ( $item_id > 0 ) {
						$result = EDD_Provider::try_deactivate_for_url( $license_key, $item_id, $site_url );

						if ( ! $result ) {
							$errors[] = $site_url;
						}
					}

					++$completed;
				}

				self::set_progress( $site_count, $completed, 'processing', $errors );
			}

			// Clear all local data regardless of individual results.
			\delete_site_option( self::NETWORK_ACTIVATIONS_OPTION );
			EDD_Provider::clear_local_license_data();

			// Mark progress as complete.
			self::set_progress( $site_count, $completed, 'complete', $errors );

			return true;
		}

		/**
		 * Sync the license for the network.
		 *
		 * Reconciles the current subsites against stored activations:
		 * - New subsites are activated (if slots available).
		 * - Removed subsites are deactivated.
		 * - Updates the network activation option with current state.
		 *
		 * @return bool True on success, false on failure.
		 *
		 * @since 2.4.0
		 */
		public static function sync_network_license(): bool {
			$license_key = EDD_Provider::get_license_option( EDD_Provider::LICENSE_KEY_OPTION );

			if ( empty( $license_key ) ) {
				return false;
			}

			$license_data = EDD_Provider::get_license_option( EDD_Provider::LICENSE_DATA_OPTION, array() );
			$item_id      = is_array( $license_data ) && isset( $license_data['item_id'] ) ? (int) $license_data['item_id'] : 0;

			// Stored data can lack the product ID after an error reply; look it up again rather than give up.
			if ( 0 === $item_id ) {
				$resolved_item_id = self::resolve_item_id( $license_key );
				$item_id          = \is_wp_error( $resolved_item_id ) ? 0 : $resolved_item_id;
			}

			if ( 0 === $item_id ) {
				return false;
			}

			$current_sites      = self::get_all_site_urls();
			$stored_activations = self::get_network_activation_status();
			$stored_urls        = array_keys( $stored_activations );

			// Sites to activate: not stored yet, or stored but not active (a previous attempt failed).
			$new_sites = array();
			foreach ( $current_sites as $site_url ) {
				if ( ! isset( $stored_activations[ $site_url ]['status'] ) || 'active' !== $stored_activations[ $site_url ]['status'] ) {
					$new_sites[] = $site_url;
				}
			}

			// Find removed subsites (in stored but not current).
			$removed_sites = array_diff( $stored_urls, $current_sites );

			// Deactivate removed subsites.
			foreach ( $removed_sites as $site_url ) {
				EDD_Provider::try_deactivate_for_url( $license_key, $item_id, $site_url );
				unset( $stored_activations[ $site_url ] );
			}

			// Activate new subsites (if slots available).
			if ( ! empty( $new_sites ) ) {
				$slots_available = self::get_available_slots( $license_key, $item_id );

				if ( false !== $slots_available && $slots_available > 0 ) {
					$sites_to_activate = array_slice( $new_sites, 0, $slots_available );

					foreach ( $sites_to_activate as $site_url ) {
						// Per-site replies must not replace the network's license state; check_license() refreshes it below.
						$result = EDD_Provider::try_activate_license( $license_key, $item_id, $site_url, false );

						if ( true === $result ) {
							$stored_activations[ $site_url ] = array(
								'status'       => 'active',
								'activated_at' => time(),
							);
						} else {
							$stored_activations[ $site_url ] = array(
								'status' => 'failed',
								'error'  => is_array( $result ) && isset( $result['message'] ) ? $result['message'] : 'Unknown error',
							);
						}
					}
				}
			}

			// Update stored data.
			\update_site_option( self::NETWORK_ACTIVATIONS_OPTION, $stored_activations );

			// Keep the warning while any site is still unlicensed, clear it once all are.
			self::update_activation_failed_flag();

			// Refresh license data from the store.
			EDD_Provider::delete_license_transient( EDD_Provider::LICENSE_CHECK_TRANSIENT );
			EDD_Provider::delete_license_transient( EDD_Provider::PREMIUM_OPTION );
			EDD_Provider::check_license( $license_key );

			return true;
		}

		/**
		 * Get the network activation status data.
		 *
		 * @return array - Array of per-site activation data.
		 *
		 * @since 2.4.0
		 */
		public static function get_network_activation_status(): array {
			$data = \get_site_option( self::NETWORK_ACTIVATIONS_OPTION, array() );

			if ( ! is_array( $data ) ) {
				return array();
			}

			return $data;
		}

		/**
		 * Get the batch activation progress.
		 *
		 * @return array - Progress data array.
		 *
		 * @since 2.4.0
		 */
		public static function get_activation_progress(): array {
			$progress = \get_site_transient( self::PROGRESS_TRANSIENT );

			if ( ! is_array( $progress ) ) {
				return array(
					'total'     => 0,
					'completed' => 0,
					'status'    => 'idle',
					'errors'    => array(),
				);
			}

			return $progress;
		}

		/**
		 * Get all site URLs in the network.
		 *
		 * @return array - Array of site URLs.
		 *
		 * @since 2.4.0
		 */
		private static function get_all_site_urls(): array {
			// This network's sites only: the licence is this network's, and another
			// network of the same installation has its own.
			$sites = \get_sites(
				array(
					'number'     => 0,
					'network_id' => \get_current_network_id(),
				)
			);
			$urls  = array();

			foreach ( $sites as $site ) {
				$urls[] = self::get_site_url( (int) $site->blog_id );
			}

			return $urls;
		}

		/**
		 * Get the URL a site is activated under on the store.
		 *
		 * Every path (site creation, sync, batch activation) uses this, so the same
		 * site always maps to the same activation key.
		 *
		 * @param int $blog_id - The site ID.
		 *
		 * @return string - The site's home URL without a trailing slash.
		 *
		 * @since 4.2.0
		 */
		private static function get_site_url( int $blog_id ): string {
			return \untrailingslashit( \get_home_url( $blog_id ) );
		}

		/**
		 * Find the stored activation URL of a site by its domain and path.
		 *
		 * Used when the site's own options can no longer be read, e.g. after it was deleted.
		 *
		 * @param array  $activations - The stored network activations, keyed by URL.
		 * @param string $domain - The site domain.
		 * @param string $path - The site path.
		 *
		 * @return string - The matching activation URL, or an empty string when none matches.
		 *
		 * @since 4.2.0
		 */
		private static function find_activation_url( array $activations, string $domain, string $path ): string {
			$wanted = strtolower( $domain ) . \untrailingslashit( $path );

			foreach ( array_keys( $activations ) as $site_url ) {
				$host      = (string) \wp_parse_url( (string) $site_url, PHP_URL_HOST );
				$site_path = (string) \wp_parse_url( (string) $site_url, PHP_URL_PATH );

				if ( strtolower( $host ) . \untrailingslashit( $site_path ) === $wanted ) {
					return (string) $site_url;
				}
			}

			return '';
		}

		/**
		 * Check whether any site in the network has no active license activation.
		 *
		 * @return bool - True if at least one site is not activated, false otherwise.
		 *
		 * @since 4.2.0
		 */
		public static function has_unlicensed_sites(): bool {
			$activations = self::get_network_activation_status();

			foreach ( self::get_all_site_urls() as $site_url ) {
				if ( ! isset( $activations[ $site_url ]['status'] ) || 'active' !== $activations[ $site_url ]['status'] ) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Work out which activated subsites the license still covers.
		 *
		 * After a downgrade EDD keeps every existing activation valid, so the store
		 * can't tell which sites are over the limit. When activations_left is
		 * negative, the newest activated subsites are marked as not covered; the
		 * main site is always covered. Local and staging activations that the store
		 * doesn't count never make activations_left negative, so they can't cause
		 * a site to lose coverage.
		 *
		 * @return void
		 *
		 * @since 4.2.0
		 */
		public static function recalculate_coverage(): void {
			$activations = self::get_network_activation_status();

			if ( empty( $activations ) ) {
				return;
			}

			$license_data = EDD_Provider::get_license_option( EDD_Provider::LICENSE_DATA_OPTION, array() );
			$over_by      = 0;

			if ( is_array( $license_data ) && isset( $license_data['activations_left'] ) && is_numeric( $license_data['activations_left'] ) && (int) $license_data['activations_left'] < 0 ) {
				$over_by = abs( (int) $license_data['activations_left'] );
			}

			$main_url = self::get_site_url( (int) \get_main_site_id() );

			// Active subsites, newest activation first; these lose coverage first.
			$active_subsites = array();
			foreach ( $activations as $site_url => $activation ) {
				if ( isset( $activation['status'] ) && 'active' === $activation['status'] && $site_url !== $main_url ) {
					$active_subsites[ $site_url ] = isset( $activation['activated_at'] ) ? (int) $activation['activated_at'] : 0;
				}
			}
			arsort( $active_subsites, SORT_NUMERIC );

			$uncovered = array_slice( array_keys( $active_subsites ), 0, $over_by );
			$changed   = false;

			foreach ( $activations as $site_url => $activation ) {
				$is_active = isset( $activation['status'] ) && 'active' === $activation['status'];
				$covered   = $is_active && ! in_array( $site_url, $uncovered, true );

				if ( ! isset( $activation['covered'] ) || $covered !== $activation['covered'] ) {
					$activations[ $site_url ]['covered'] = $covered;
					$changed                             = true;
				}
			}

			if ( $changed ) {
				\update_site_option( self::NETWORK_ACTIVATIONS_OPTION, $activations );
			}
		}

		/**
		 * Check whether the license covers a site, i.e. premium methods may be set up there.
		 *
		 * @param int $blog_id - The site ID.
		 *
		 * @return bool - True for the main site, single-site installs and covered subsites, false otherwise.
		 *
		 * @since 4.2.0
		 */
		public static function is_site_covered( int $blog_id ): bool {
			if ( ! \is_multisite() || (int) \get_main_site_id() === $blog_id ) {
				return true;
			}

			$activations = self::get_network_activation_status();
			$site_url    = self::get_site_url( $blog_id );

			if ( ! isset( $activations[ $site_url ]['status'] ) || 'active' !== $activations[ $site_url ]['status'] ) {
				return false;
			}

			// Entries stored before coverage existed count as covered until the next recalculation.
			if ( ! isset( $activations[ $site_url ]['covered'] ) ) {
				return true;
			}

			return (bool) $activations[ $site_url ]['covered'];
		}

		/**
		 * Get the URLs of the network sites the license does not cover.
		 *
		 * @return array - List of site URLs, empty when every site is covered.
		 *
		 * @since 4.2.0
		 */
		public static function get_uncovered_sites(): array {
			$uncovered = array();

			foreach ( \get_sites( array( 'number' => 0, 'network_id' => \get_current_network_id() ) ) as $site ) {
				if ( ! self::is_site_covered( (int) $site->blog_id ) ) {
					$uncovered[] = self::get_site_url( (int) $site->blog_id );
				}
			}

			return $uncovered;
		}

		/**
		 * Offer only free methods for new setup on a subsite the license doesn't cover.
		 *
		 * Soft free mode: premium extensions stay loaded, so users who already use a
		 * premium method keep logging in with it, and their own method stays
		 * selectable. Only setting up a new premium method is not offered.
		 *
		 * @param array    $methods - Method slug => enabled flag, as offered by the wizard.
		 * @param \WP_User $user - The user the wizard is shown for.
		 * @param string   $role - The user's role.
		 *
		 * @return array - The methods to offer.
		 *
		 * @since 4.2.0
		 */
		public static function filter_wizard_methods( $methods, $user, $role ) {
			if ( ! is_array( $methods ) || self::is_site_covered( (int) \get_current_blog_id() ) ) {
				return $methods;
			}

			$current_method = ( $user instanceof \WP_User ) ? (string) User_Helper::get_enabled_method_for_user( $user ) : '';

			foreach ( array_keys( $methods ) as $method ) {
				// Built-in methods (TOTP, email, passkeys…) are free; only premium extensions are not found here.
				$is_free = false !== Methods_Helper::get_method_by_provider_name( (string) $method );

				if ( ! $is_free && $method !== $current_method ) {
					unset( $methods[ $method ] );
				}
			}

			return $methods;
		}

		/**
		 * Set or clear the "subsite could not be activated" warning to match the network state.
		 *
		 * The warning stays while any site is unlicensed and is cleared only once
		 * every site has an active activation.
		 *
		 * @return void
		 *
		 * @since 4.2.0
		 */
		private static function update_activation_failed_flag(): void {
			if ( self::has_unlicensed_sites() ) {
				\update_site_option( self::ACTIVATION_FAILED_FLAG, true );
				return;
			}

			\delete_site_option( self::ACTIVATION_FAILED_FLAG );
		}

		/**
		 * Resolve the correct EDD item_id for the license key.
		 *
		 * Tries Premium first, then Enterprise. Uses check_license (not activate)
		 * to determine which product the key belongs to without consuming a slot.
		 *
		 * A connection or store failure is returned as a WP_Error, so it isn't
		 * reported to the user as "not valid for this product".
		 *
		 * @param string $license_key - The license key.
		 *
		 * @return int|\WP_Error - The resolved item_id, 0 if the key belongs to no product, or WP_Error if the store could not answer.
		 *
		 * @since 2.4.0
		 */
		private static function resolve_item_id( string $license_key ) {
			$item_ids    = array( EDD_Provider::ITEM_ID_PREMIUM, EDD_Provider::ITEM_ID_ENTERPRISE );
			$store_error = null;

			foreach ( $item_ids as $item_id ) {
				$api_params = array(
					'edd_action' => 'check_license',
					'license'    => $license_key,
					'item_id'    => $item_id,
					'url'        => \network_home_url(),
				);

				$response = \wp_remote_post(
					EDD_Provider::STORE_URL,
					array(
						'timeout'   => 15,
						'sslverify' => true,
						'body'      => $api_params,
					)
				);

				if ( \is_wp_error( $response ) ) {
					$store_error = $response->get_error_message();
					continue;
				}

				$response_code = (int) \wp_remote_retrieve_response_code( $response );

				if ( 200 !== $response_code ) {
					$store_error = 'HTTP ' . $response_code;
					continue;
				}

				$data = json_decode( \wp_remote_retrieve_body( $response ), true );

				if ( ! is_array( $data ) ) {
					$store_error = 'Response is not valid JSON';
					continue;
				}

				// Check if the license status indicates this item_id is correct.
				// EDD may return 'invalid_item_id' in the license field (not just the error field)
				// when the key doesn't belong to this product.
				$license_status = isset( $data['license'] ) ? $data['license'] : '';
				$valid_statuses = array( 'valid', 'expired', 'inactive', 'disabled', 'site_inactive' );

				if ( in_array( $license_status, $valid_statuses, true ) ) {
					// Store the license data from the check.
					EDD_Provider::update_license_option( EDD_Provider::LICENSE_DATA_OPTION, $data );
					return $item_id;
				}
			}

			// No product matched and at least one lookup failed, so the key can't be judged as a mismatch.
			if ( null !== $store_error ) {
				Debugging::log( 'EDD license: could not resolve the product for the license key: ' . $store_error );

				return new \WP_Error( 'store_unreachable', $store_error );
			}

			return 0;
		}

		/**
		 * Get the number of available activation slots for the license.
		 *
		 * @param string $license_key - The license key.
		 * @param int    $item_id     - The EDD product ID.
		 *
		 * @return int|false - Number of available slots, or false on failure.
		 *
		 * @since 2.4.0
		 */
		private static function get_available_slots( string $license_key, int $item_id ) {
			$api_params = array(
				'edd_action' => 'check_license',
				'license'    => $license_key,
				'item_id'    => $item_id,
				'url'        => \network_home_url(),
			);

			$response = \wp_remote_post(
				EDD_Provider::STORE_URL,
				array(
					'timeout'   => 15,
					'sslverify' => true,
					'body'      => $api_params,
				)
			);

			if ( \is_wp_error( $response ) ) {
				return false;
			}

			$data = json_decode( \wp_remote_retrieve_body( $response ), true );

			if ( ! is_array( $data ) ) {
				return false;
			}

			// Store fresh license data.
			EDD_Provider::update_license_option( EDD_Provider::LICENSE_DATA_OPTION, $data );

			if ( isset( $data['activations_left'] ) ) {
				// 'unlimited' means no limit.
				if ( 'unlimited' === $data['activations_left'] ) {
					return PHP_INT_MAX;
				}

				return (int) $data['activations_left'];
			}

			// If license_limit is 0, it means unlimited.
			if ( isset( $data['license_limit'] ) && 0 === (int) $data['license_limit'] ) {
				return PHP_INT_MAX;
			}

			return false;
		}

		/**
		 * Set the batch activation/deactivation progress.
		 *
		 * @param int    $total     - Total number of sites.
		 * @param int    $completed - Number completed so far.
		 * @param string $status    - Status: 'processing', 'complete', 'failed'.
		 * @param array  $errors    - Array of error messages.
		 *
		 * @return void
		 *
		 * @since 2.4.0
		 */
		private static function set_progress( int $total, int $completed, string $status, array $errors = array() ) {
			\set_site_transient(
				self::PROGRESS_TRANSIENT,
				array(
					'total'     => $total,
					'completed' => $completed,
					'status'    => $status,
					'errors'    => $errors,
				),
				self::PROGRESS_TTL
			);
		}
	}
}
