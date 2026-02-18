<?php
/**
 * Responsible for the Passkeys extension plugin settings
 *
 * @package    wp2fa
 * @subpackage passkeys
 * @since 3.0.0
 * @copyright  2026 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

declare(strict_types=1);

namespace WP2FA\Passkeys;

use WP2FA\Methods\Passkeys;
use WP2FA\Admin\Helpers\User_Helper;
use WP2FA\Passkeys\Source_Repository;
use WP2FA\Passkeys\Helpers\Authenticators_Helper;
use WP2FA\WP2FA;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Passkeys user settings class
 */
if ( ! class_exists( '\WP2FA\Passkeys\Passkeys_User_Profile' ) ) {

	/**
	 * Responsible for setting different 2FA Passkeys settings
	 *
	 * @since 3.0.0
	 */
	class Passkeys_User_Profile {

		/**
		 * Initialize the Passkeys_User_Profile class
		 *
		 * @since 3.0.0
		 */
		public static function init() {
			\add_filter( WP_2FA_PREFIX . 'append_to_profile_form_content', array( __CLASS__, 'add_user_profile_form' ), 10, 2 );
		}

		/**
		 * Gives the ability to add more content to the profile page.
		 *
		 * @param string   $content - The parsed HTML of the form.
		 * @param \WP_User $user - The user object.
		 *
		 * @since 3.0.0
		 */
		public static function add_user_profile_form( $content, \WP_User $user ) {

			if ( ! Passkeys::is_enabled( User_Helper::get_user_role( $user ) ) ) {
				return $content;
			}

			$public_key_credentials = Source_Repository::find_all_for_user( $user );

			$add_button = true;

			if ( 'free' === WP2FA::get_plugin_version() && ! empty( $public_key_credentials ) && \count( $public_key_credentials ) >= 1 ) {
				$public_key_credentials = array( reset( $public_key_credentials ) );

				$add_button = false;
			}

			\ob_start();
			?>
			<tr><td colspan="2" style="padding: 0;">
				<div class="wp-2fa-admin">
					<h2 class="wp-2fa-admin--heading">
						<?php echo \esc_html( WP2FA::get_wp2fa_white_label_setting( 'passkeys-option-label', true ) ) . __( ' - by WP 2FA', 'wp-2fa' ); ?>
					</h2>
					<p class="description">
						<?php
						printf(
							wp_kses(
								__( 'Passkeys make logging in easier and safer. Instead of typing passwords, you can use your fingerprint, face, or device PIN to prove who you are. This reduces the risk of stolen passwords and makes authentication quick and seamless. For more information and instructions on how to use Passkeys, refer to the guide %s', 'wp-2fa' ),
								array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) )
							),
							'<a href="' . esc_url( 'https://melapress.com/support/kb/wp-2fa-activate-use-passkeys/?#utm_source=plugin&utm_medium=wp2fa&utm_campaign=passkeys_user' ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'How to activate and use passkeys in WordPress.', 'wp-2fa' ) . '</a>'
						);
						?>
					</p>
					<style>
						.wp-2fa-passkey-list-table th {
							padding: 8px 10px !important;
							/*display: table-cell !important; */
						}
						.wp-2fa-passkey-list-table{
							border: 1px solid #c3c4c7;
							box-shadow: 0 1px 1px rgba(0,0,0,.04);
						}
						.wp-2fa-passkey-list-table td {
							line-height: 1.3 !important;
							margin-bottom: 9px !important;
							padding: 15px 10px !important;
							line-height: 1.3 !important;
							vertical-align: middle !important;
						}
						@media screen and (max-width: 782px) {
							.wp-2fa-passkey-list-table thead {
								display: none !important;
							}
						
							.wp-2fa-passkey-list-table td::before {
								content: attr(data-label) !important;
								font-weight: bold !important;
								text-align: left !important;
								position: absolute !important;
								left: 10px !important;
								top: 50% !important;
								transform: translateY(-50%) !important;
								white-space: nowrap !important;
							}
							.wp-2fa-passkey-list-table td {
								display: block !important;
								width: 100% !important;
								text-align: right !important;
								position: relative !important;
								padding-left: 50% !important;
							}
						}
						td.editing {
							background-color: #f0f8ff;
						}
						.spinner {
							width: 14px;
							height: 14px;
							border: 2px solid #ccc;
							border-top-color: #007bff;
							border-radius: 50%;
							animation: spin 0.7s linear infinite;
							display: inline-block;
							vertical-align: middle;
						}
						@keyframes spin {
							to { transform: rotate(360deg); }
						}
						input.invalid {
							border: 2px solid #d9534f;
							background-color: #ffe6e6;
						}
						.cell-error {
							color: #d9534f;
							font-size: 11px;
							margin-top: 4px;
							display: block;
						}

						/* ✅ Hover effect for editable cells */
						/*td[data-field]:not(.editing):hover {
							background-color: #f8faff;
							cursor: pointer;
							position: relative;
						}*/
 
						/* ✅ Small tooltip that appears on hover */
						/*td[data-field]:not(.editing):hover::after {
							content: "<?php \esc_html_e( 'Click to edit', 'wp-2fa' ); ?>";
							position: absolute;
							bottom: 2px;
							right: 6px;
							font-size: 0.8em;
							color: #6c5e5e;
							font-style: italic;
						} */

					</style>
					<table class="wp-list-table wp-2fa-passkey-list-table widefat fixed striped table-view-list">
						<thead>
							<tr>
								<th class="manage-column column-name column-primary" scope="col"><?php \esc_html_e( 'Name', 'wp-2fa' ); ?></th>
								<th class="manage-column column-status" scope="col"><?php \esc_html_e( 'Status', 'wp-2fa' ); ?></th>
								<th class="manage-column column-created-date" scope="col">
								<?php
								\esc_html_e( 'Created Date', 'wp-2fa' );
								?>
								</th>
								<th class="manage-column column-last-used-date" scope="col">
								<?php
								\esc_html_e( 'Last Used', 'wp-2fa' );
								?>
								</th>
								<!-- <th class="manage-column column-type" scope="col"><?php esc_html_e( 'Type', 'wp-2fa' ); ?></th> -->
								<th class="manage-column column-action" scope="col"><?php \esc_html_e( 'Actions', 'wp-2fa' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							if ( empty( $public_key_credentials ) ) :
								?>
								<tr>
									<td colspan="5">
										<?php esc_html_e( 'No passkeys found.', 'wp-2fa' ); ?>
									</td>
								</tr>
								<?php
							endif;

							if ( ! class_exists( 'ParagonIE_Sodium_Core_Base64_UrlSafe', false ) ) {
								require_once ABSPATH . WPINC . '/sodium_compat/src/Core/Base64/UrlSafe.php';
								require_once ABSPATH . WPINC . '/sodium_compat/src/Core/Util.php';
							}

							foreach ( $public_key_credentials as $public_key_credential ) {
								$extra_data = $public_key_credential;

								if ( ! class_exists( 'ParagonIE_Sodium_Core_Base64_UrlSafe', false ) ) {
									require_once ABSPATH . WPINC . '/sodium_compat/src/Core/Base64/UrlSafe.php';
									require_once ABSPATH . WPINC . '/sodium_compat/src/Core/Util.php';
								}

								$fingerprint = \ParagonIE_Sodium_Core_Base64_UrlSafe::encodeUnpadded( $extra_data['credential_id'] );
								?>
								<tr>
									<td data-field="name" data-id="<?php echo \esc_attr( $fingerprint ); ?>" data-label="<?php echo esc_attr( __( 'Name', 'wp-2fa' ) ); ?>">
										<?php echo esc_html( $extra_data['name'] ?? '' ); ?>
									</td>
									<td data-label="<?php echo esc_attr( __( 'Status', 'wp-2fa' ) ); ?>">
										<?php
										$btn_text = \esc_html__( 'Disable', 'wp-2fa' );
										if ( $extra_data['enabled'] ) {
											\esc_html_e( 'Enabled', 'wp-2fa' );
										} else {
											$btn_text = \esc_html__( 'Enable', 'wp-2fa' );
											\esc_html_e( 'Disabled', 'wp-2fa' );
										}
										?>
									</td>
									<td data-label="<?php echo esc_attr( __( 'Created Date', 'wp-2fa' ) ); ?>">
										<?php
										$date_format = \get_option( 'date_format' );
										if ( ! $date_format ) {
											$date_format = 'F j, Y';
										}
										$time_format = \get_option( 'time_format' );
										if ( ! $time_format ) {
											$time_format = 'g:i a';
										}

										$event_datetime_utc = \gmdate( 'Y-m-d H:i:s', (int) $extra_data['created'] );
										$event_local        = \get_date_from_gmt( $event_datetime_utc, $date_format . ' ' . $time_format );
										echo \esc_html( $event_local );
										echo '<br>';
											echo \esc_html( Passkeys::get_datetime_from_now( (string) $extra_data['created'] ) );
										?>
									</td>
									<td data-label="<?php echo esc_attr( __( 'Last Used', 'wp-2fa' ) ); ?>">
										<?php
										if ( empty( $extra_data['last_used'] ) ) {
											\esc_html_e( 'Not used yet', 'wp-2fa' );
										} else {
											$event_datetime_utc = \gmdate( 'Y-m-d H:i:s', $extra_data['last_used'] );
											$event_local        = \get_date_from_gmt( $event_datetime_utc, $date_format . ' ' . $time_format );
											echo \esc_html( $event_local );
											echo '<br>';
											echo \esc_html( Passkeys::get_datetime_from_now( (string) $extra_data['last_used'] ) );
										}
										?>
									</td>
									<!-- <td>
										<?php echo esc_html( Authenticators_Helper::get_friendly_name( $extra_data['aaguid'] ) ); ?>
									</td> -->
									<td data-label="<?php echo esc_attr( __( 'Actions', 'wp-2fa' ) ); ?>">
										<?php
											printf(
												'<button type="button" data-id="%1$s" name="%2$s" id="%1$s" class="button delete enable_styling" aria-label="%3$s" data-nonce="%4$s" data-userid="%5$s">%6$s</button>',
												\esc_attr( $fingerprint ),
												\esc_attr( $extra_data['name'] ?? '' ),
												/* translators: %s: the passkey's given name. */
												\esc_attr( sprintf( __( 'Revoke %s' ), $extra_data['name'] ?? '' ) ),
												\esc_attr( \wp_create_nonce( 'wp2fa-user-passkey-revoke' ) ),
												\esc_attr( \get_current_user_id() ),
												\esc_html__( 'Revoke', 'wp-2fa' )
											);
										?>
										<?php
											printf(
												'<button type="button" data-id="%1$s" name="%2$s" id="%1$s" class="button disable enable_styling" aria-label="%3$s" data-nonce="%4$s" data-userid="%5$s">%6$s</button>',
												\esc_attr( $fingerprint ),
												\esc_attr( $extra_data['name'] ?? '' ),
												/* translators: %s: the passkey's given name. */
												\esc_attr( sprintf( __( '%1$s %2$s' ), $btn_text, $extra_data['name'] ?? '' ) ),
												\esc_attr( \wp_create_nonce( 'wp2fa-user-passkey-enable' ) ),
												\esc_attr( \get_current_user_id() ),
												$btn_text // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
											);
										?>
									</td>
								</tr>
							<?php } ?>
						</tbody>
					</table>
					<?php if ( $add_button && \wp_get_current_user()->ID === $user->ID ) { ?>
						<a href="#" class="button button-primary" style="margin-top: 10px;" data-open-configure-2fa-wizard-passkey><?php esc_html_e( 'Add a Passkey', 'wp-2fa' ); ?></a>
						
						
					<?php } ?>
				</div>

				<script>
					
					jQuery(document).on(
						'click',
						'[data-open-configure-2fa-wizard-passkey]',
						function (event) {
							jQuery(window).off('beforeunload')
							event.preventDefault();
							MicroModal.show( 'configure-2fa-passkeys' );
						}
					);
					jQuery(document).on(
						'click',
						'[data-close-2fa-modal]',
						function (e) {
						e.preventDefault();
						jQuery(window).off('beforeunload');
						var modalToClose = `#${ jQuery(this).closest('.wp2fa-modal').attr('id') }`;
						jQuery(modalToClose).removeClass('is-open').attr('aria-hidden', 'true');
		}
					  );
					
				</script>

				<div class="wp2fa-modal micromodal-slide" id="configure-2fa-passkeys" aria-hidden="true">
					<div class="modal__overlay" tabindex="-1">
						<div class="modal__container" role="dialog" aria-dialog aria-labelledby="modal-1-title">
							<header class="modal__header">
								<h2 class="modal__title" id="modal-1-title">
									<?php \esc_html_e( 'Set up Passkey Login', 'wp-2fa' ); ?>
								</h2>
								<button class="modal__close" aria-label="Close modal" data-micromodal-close></button>
							</header>
							<main class="modal__content" id="modal-1-content">
								<p style="margin-bottom: 15px;"><?php \esc_html_e( 'Passkeys replace traditional passwords with a quick and secure login method built right into your device. Depending on your setup, this could be a fingerprint, facial recognition, or another trusted device. Even if your device doesn’t support biometrics, you can still create and use a passkey.', 'wp-2fa' ); ?></p>
								
								<p style="margin-bottom: 15px;"><?php \esc_html_e( 'A Passkey is stored safely on your device. So it can’t be phished, leaked, or reused by hackers.', 'wp-2fa' ); ?></p>
								
								<p><b><?php \esc_html_e( 'Follow these steps to add a passkey:', 'wp-2fa' ); ?></b></p>
								<p>
								<ol style="padding-left: 25px;">
									<li style="padding: 0;">
									<?php
									\esc_html_e(
										'Click the "Add Passkey" button and a pop-up will appear.',
										'wp-2fa'
									);
									?>
										</li>
									<li style="padding: 0;"><?php \esc_html_e( 'Follow the instructions on the popup', 'wp-2fa' ); ?></li>
									<li style="padding: 0;"><?php \esc_html_e( 'Specify a name for your passkey and that is it!', 'wp-2fa' ); ?></li>
								</ol>
								</p>
								<p><button style="margin-top: 10px;" type="button" class="button button-secondary wp-2fa-register-new-usbpasskey hide-if-no-js enable_styling" aria-expanded="false" data-nonce="<?php echo \esc_attr( \wp_create_nonce( 'wp2fa_profile_register' ) ); ?>"><?php \esc_html_e( 'Add a USB security key', 'wp-2fa' ); ?></button><button style="margin-top: 10px;" type="button" class="button button-secondary wp-2fa-register-new-passkey hide-if-no-js enable_styling" aria-expanded="false" data-nonce="<?php echo \esc_attr( \wp_create_nonce( 'wp2fa_profile_register' ) ); ?>"><?php \esc_html_e( 'Add a Passkey', 'wp-2fa' ); ?></button><button style="margin-top: 10px;" type="button" class="button button-secondary hide-if-no-js enable_styling" data-close-2fa-modal><?php \esc_html_e( 'Exit', 'wp-2fa' ); ?></button></p>
								<div class="wp-register-passkey--message"></div>
							</main>
						</div>
					</div>
				</div>

				<div id="overlay">
					<div id="customPrompt">
						<p><?php \esc_html_e( 'Name your passkey:', 'wp-2fa' ); ?></p>
						<p class="description" style="font-weight: normal;"><?php \esc_html_e( 'Choose a name that helps you recognise which device this passkey belongs to. Only letters, numbers, spaces, underscores, and hyphens are allowed.', 'wp-2fa' ); ?></p>
						<input type="text" id="userInput" placeholder="<?php \esc_html_e( 'Letters, numbers, -, _, spaces', 'wp-2fa' ); ?>">
						<div id="error"></div>
						<button id="submitBtn"><?php \esc_html_e( 'Submit', 'wp-2fa' ); ?></button>
					</div>
				</div>
				<style>
					#overlay {
						display: none;
						position: fixed;
						inset: 0;
						background: rgba(0,0,0,0.5);
						justify-content: center;
						align-items: center;
						z-index: 9999;
					}

					#customPrompt {
						background: #fff;
						padding: 20px 25px;
						border-radius: 10px;
						width: 320px;
						box-shadow: 0 6px 20px rgba(0,0,0,0.25);
						text-align: center;
					}

					#customPrompt p {
						margin: 0 0 10px;
						font-weight: 600;
					}

					#customPrompt input {
						width: 100%;
						padding: 8px;
						border: 1px solid #ccc;
						border-radius: 6px;
						font-size: 16px;
						margin-top: 8px;
					}

					#customPrompt button {
						margin-top: 12px;
						padding: 8px 16px;
						background: #007bff;
						color: white;
						border: none;
						border-radius: 6px;
						cursor: pointer;
						font-size: 15px;
					}

					#customPrompt button:hover {
						background: #0056b3;
					}

					#error {
						color: red;
						margin-top: 8px;
						font-size: 14px;
					}
				</style>
			</td></tr>
			<?php

			$output = ob_get_contents();
			ob_end_clean();

			return $content . $output;
		}
	}
}
