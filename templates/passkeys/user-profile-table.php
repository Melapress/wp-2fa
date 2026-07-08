<?php
/**
 * Passkeys User Profile Table Template
 *
 * Variables expected in scope (set before include):
 *   $section_title             string  Section heading text.
 *   $section_description_html  string  Pre-escaped description HTML.
 *   $public_key_credentials    array   Array of passkey data arrays.
 *   $add_button                bool    Whether to show the "Add a Passkey" button.
 *   $user                      WP_User The user whose profile is displayed.
 *   $date_format               string  WordPress date format.
 *   $time_format               string  WordPress time format.
 *
 * @package wp2fa
 * @since   4.0.0
 */

defined( 'ABSPATH' ) || exit;

use WP2FA\Methods\Passkeys;
use WP2FA\WP2FA;
?>
<tr><td colspan="2" style="padding: 0;">
	<div class="wp-2fa-admin">
		<h2 class="wp-2fa-admin--heading">
			<?php echo \esc_html( $section_title ); ?>
		</h2>
		<p class="description">
			<?php echo \wp_kses_post( $section_description_html ); ?>
		</p>

		<?php if ( empty( $public_key_credentials ) ) : ?>
			<p class="wp-2fa-passkey-empty-message">
				<strong><?php \esc_html_e( 'No Passkeys yet!', 'wp-2fa' ); ?></strong> <?php \esc_html_e( 'Create your first Passkey by clicking the button below.', 'wp-2fa' ); ?>
			</p>
		<?php endif; ?>

		<?php if ( $add_button && \wp_get_current_user()->ID === $user->ID ) : ?>
			<?php $passkey_btn_class = empty( WP2FA::get_wp2fa_white_label_setting( 'enable_wizard_styling' ) ) ? 'button button-primary' : 'wp2fa-profile__btn wp2fa-profile__btn--primary'; ?>
			<a href="#" class="<?php echo \esc_attr( $passkey_btn_class ); ?>" data-open-configure-2fa-wizard-passkey><?php \esc_html_e( 'Add a Passkey', 'wp-2fa' ); ?></a>
		<?php endif; ?>

		<?php if ( ! empty( $public_key_credentials ) ) : ?>
		<table class="wp-list-table wp-2fa-passkey-list-table widefat fixed striped table-view-list" id="wp-2fa-passkey-table">
			<thead>
				<tr>
					<th class="manage-column column-name column-primary sortable asc" scope="col" data-sort-type="text">
						<a href="#">
							<span><?php \esc_html_e( 'Name', 'wp-2fa' ); ?></span>
							<span class="sorting-indicators">
								<span class="sorting-indicator asc" aria-hidden="true"></span>
								<span class="sorting-indicator desc" aria-hidden="true"></span>
							</span>
						</a>
					</th>
					<th class="manage-column column-status sortable asc" scope="col" data-sort-type="text">
						<a href="#">
							<span><?php \esc_html_e( 'Status', 'wp-2fa' ); ?></span>
							<span class="sorting-indicators">
								<span class="sorting-indicator asc" aria-hidden="true"></span>
								<span class="sorting-indicator desc" aria-hidden="true"></span>
							</span>
						</a>
					</th>
					<th class="manage-column column-created-date sortable asc" scope="col" data-sort-type="numeric">
						<a href="#">
							<span><?php \esc_html_e( 'Created', 'wp-2fa' ); ?></span>
							<span class="sorting-indicators">
								<span class="sorting-indicator asc" aria-hidden="true"></span>
								<span class="sorting-indicator desc" aria-hidden="true"></span>
							</span>
						</a>
					</th>
					<th class="manage-column column-last-used-date sortable asc" scope="col" data-sort-type="numeric">
						<a href="#">
							<span><?php \esc_html_e( 'Last used', 'wp-2fa' ); ?></span>
							<span class="sorting-indicators">
								<span class="sorting-indicator asc" aria-hidden="true"></span>
								<span class="sorting-indicator desc" aria-hidden="true"></span>
							</span>
						</a>
					</th>
					<th class="manage-column column-action" scope="col"><?php \esc_html_e( 'Actions', 'wp-2fa' ); ?></th>
				</tr>
			</thead>
			<tfoot>
				<tr>
					<th class="manage-column column-name column-primary sortable asc" scope="col" data-sort-type="text">
						<a href="#">
							<span><?php \esc_html_e( 'Name', 'wp-2fa' ); ?></span>
							<span class="sorting-indicators">
								<span class="sorting-indicator asc" aria-hidden="true"></span>
								<span class="sorting-indicator desc" aria-hidden="true"></span>
							</span>
						</a>
					</th>
					<th class="manage-column column-status sortable asc" scope="col" data-sort-type="text">
						<a href="#">
							<span><?php \esc_html_e( 'Status', 'wp-2fa' ); ?></span>
							<span class="sorting-indicators">
								<span class="sorting-indicator asc" aria-hidden="true"></span>
								<span class="sorting-indicator desc" aria-hidden="true"></span>
							</span>
						</a>
					</th>
					<th class="manage-column column-created-date sortable asc" scope="col" data-sort-type="numeric">
						<a href="#">
							<span><?php \esc_html_e( 'Created', 'wp-2fa' ); ?></span>
							<span class="sorting-indicators">
								<span class="sorting-indicator asc" aria-hidden="true"></span>
								<span class="sorting-indicator desc" aria-hidden="true"></span>
							</span>
						</a>
					</th>
					<th class="manage-column column-last-used-date sortable asc" scope="col" data-sort-type="numeric">
						<a href="#">
							<span><?php \esc_html_e( 'Last used', 'wp-2fa' ); ?></span>
							<span class="sorting-indicators">
								<span class="sorting-indicator asc" aria-hidden="true"></span>
								<span class="sorting-indicator desc" aria-hidden="true"></span>
							</span>
						</a>
					</th>
					<th class="manage-column column-action" scope="col"><?php \esc_html_e( 'Actions', 'wp-2fa' ); ?></th>
				</tr>
			</tfoot>
			<tbody>

				<?php
				foreach ( $public_key_credentials as $public_key_credential ) :
					$extra_data  = $public_key_credential;
					$fingerprint = \ParagonIE_Sodium_Core_Base64_UrlSafe::encodeUnpadded( $extra_data['credential_id'] );

					// Determine status text and CSS class.
					if ( ! empty( $extra_data['revoked'] ) ) {
						$status_text  = \esc_html__( 'Revoked', 'wp-2fa' );
						$status_class = 'wp-2fa-passkey-status--revoked';
					} elseif ( $extra_data['enabled'] ) {
						$status_text  = \esc_html__( 'Enabled', 'wp-2fa' );
						$status_class = 'wp-2fa-passkey-status--enabled';
					} else {
						$status_text  = \esc_html__( 'Disabled', 'wp-2fa' );
						$status_class = 'wp-2fa-passkey-status--disabled';
					}

					// Enable/Disable button text.
					$btn_text = $extra_data['enabled']
						? \esc_html__( 'Disable', 'wp-2fa' )
						: \esc_html__( 'Enable', 'wp-2fa' );

					// Date values.
					$created_timestamp  = (int) ( $extra_data['created'] ?? 0 );
					$last_used_timestamp = (int) ( $extra_data['last_used'] ?? 0 );
					?>
					<tr>
						<td data-field="name"
							data-id="<?php echo \esc_attr( $fingerprint ); ?>"
							data-label="<?php echo \esc_attr__( 'Name', 'wp-2fa' ); ?>"
							data-sort-value="<?php echo \esc_attr( $extra_data['name'] ?? '' ); ?>">
							<?php echo \esc_html( $extra_data['name'] ?? '' ); ?>
						</td>
						<td data-label="<?php echo \esc_attr__( 'Status', 'wp-2fa' ); ?>"
							data-sort-value="<?php echo \esc_attr( $status_text ); ?>">
							<span class="wp-2fa-passkey-status <?php echo \esc_attr( $status_class ); ?>">
								<?php echo \esc_html( $status_text ); ?>
							</span>
						</td>
						<td data-label="<?php echo \esc_attr__( 'Created', 'wp-2fa' ); ?>"
							data-sort-value="<?php echo \esc_attr( $created_timestamp ); ?>">
							<?php
							if ( $created_timestamp > 0 ) {
								$event_datetime_utc = \gmdate( 'Y-m-d H:i:s', $created_timestamp );
								$event_date         = \get_date_from_gmt( $event_datetime_utc, $date_format );
								$event_time         = \get_date_from_gmt( $event_datetime_utc, $time_format );
								echo \esc_html( $event_date );
								echo '<br>';
								echo \esc_html( $event_time );
							}
							?>
						</td>
						<td data-label="<?php echo \esc_attr__( 'Last used', 'wp-2fa' ); ?>"
							data-sort-value="<?php echo \esc_attr( $last_used_timestamp ); ?>">
							<?php
							if ( empty( $extra_data['last_used'] ) ) {
								\esc_html_e( 'Not used yet', 'wp-2fa' );
							} else {
								$event_datetime_utc = \gmdate( 'Y-m-d H:i:s', $last_used_timestamp );
								$event_date         = \get_date_from_gmt( $event_datetime_utc, $date_format );
								$event_time         = \get_date_from_gmt( $event_datetime_utc, $time_format );
								echo \esc_html( $event_date );
								echo '<br>';
								echo \esc_html( $event_time );
							}
							?>
						</td>
						<td data-label="<?php echo \esc_attr__( 'Actions', 'wp-2fa' ); ?>">
							<?php
							$is_owner = \wp_get_current_user()->ID === $user->ID;
							$is_admin = \current_user_can( 'manage_options' );

							// Enable/Disable: visible to owner and administrators.
							if ( $is_owner || $is_admin ) {
								printf(
									'<button type="button" data-id="%1$s" name="%2$s" class="button-link disable enable_styling" aria-label="%3$s" data-nonce="%4$s" data-userid="%5$s">%6$s</button>',
									\esc_attr( $fingerprint ),
									\esc_attr( $extra_data['name'] ?? '' ),
									/* translators: %1$s: the action (Enable/Disable), %2$s: the passkey name. */
									\esc_attr( sprintf( __( '%1$s %2$s', 'wp-2fa' ), $btn_text, $extra_data['name'] ?? '' ) ),
									\esc_attr( \wp_create_nonce( 'wp2fa-user-passkey-enable' ) ),
									\esc_attr( (string) $user->ID ),
									$btn_text // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								);
							}

							// Revoke: visible only to the passkey owner.
							if ( $is_owner ) {
								printf(
									'<button type="button" data-id="%1$s" name="%2$s" class="button-link delete enable_styling" aria-label="%3$s" data-nonce="%4$s" data-userid="%5$s">%6$s</button>',
									\esc_attr( $fingerprint ),
									\esc_attr( $extra_data['name'] ?? '' ),
									/* translators: %s: the passkey's given name. */
									\esc_attr( sprintf( __( 'Revoke %s', 'wp-2fa' ), $extra_data['name'] ?? '' ) ),
									\esc_attr( \wp_create_nonce( 'wp2fa-user-passkey-revoke' ) ),
									\esc_attr( (string) $user->ID ),
									\esc_html__( 'Revoke', 'wp-2fa' )
								);
							}
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php endif; ?>
	</div>
</td></tr>
