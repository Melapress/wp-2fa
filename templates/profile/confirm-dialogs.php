<?php
/**
 * Profile section: Confirmation dialogs.
 *
 * Inline modal dialogs for destructive actions (remove 2FA, temp remove).
 *
 * @var array $data Template data from the renderer.
 *
 * @package wp2fa
 * @since   4.0.0
 */

defined( 'ABSPATH' ) || exit;
?>
<?php if ( ! empty( $data['setup_card_data']['show_remove_btn'] ) ) : ?>
<div class="wp2fa-profile__confirm-overlay" id="wp2fa-confirm-remove-2fa" role="dialog" aria-modal="true" aria-labelledby="wp2fa-confirm-remove-title">
	<div class="wp2fa-profile__confirm-dialog">
		<div class="wp2fa-profile__confirm-icon">
			<svg width="56" height="56" viewBox="0 0 56 56" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
				<circle cx="28" cy="28" r="28" fill="#FFF3E0"/>
				<path d="M28 18l11 20H17l11-20z" stroke="#8B6914" stroke-width="2" stroke-linejoin="round" fill="none"/>
				<line x1="28" y1="26" x2="28" y2="32" stroke="#8B6914" stroke-width="2" stroke-linecap="round"/>
				<circle cx="28" cy="35" r="1" fill="#8B6914"/>
			</svg>
		</div>
		<h3 id="wp2fa-confirm-remove-title"><?php \esc_html_e( 'Remove 2FA?', 'wp-2fa' ); ?></h3>
		<p><?php \esc_html_e( 'Are you sure you want to remove Two Factor Authentication and lower the security of your account?', 'wp-2fa' ); ?></p>
		<div class="wp2fa-profile__confirm-buttons wp2fa-profile__confirm-buttons--stacked">
			<a href="#"
				class="wp2fa-profile__btn wp2fa-profile__btn--primary"
				data-wp2fa-remove-2fa
				data-user-id="<?php echo \esc_attr( (string) $data['user_id'] ); ?>"
				data-nonce="<?php echo \esc_attr( \wp_create_nonce( 'wp-2fa-remove-user-2fa-nonce' ) ); ?>">
				<?php \esc_html_e( 'Yes, Remove', 'wp-2fa' ); ?>
			</a>
			<a href="#" class="wp2fa-profile__confirm-cancel-link" data-wp2fa-confirm-close>
				<?php \esc_html_e( 'No, Cancel', 'wp-2fa' ); ?>
			</a>
		</div>
	</div>
</div>
<?php endif; ?>

<?php if ( ! empty( $data['admin_actions_data']['temp_remove_url'] ) ) : ?>
<div class="wp2fa-profile__confirm-overlay" id="wp2fa-confirm-temp-remove" role="dialog" aria-modal="true" aria-labelledby="wp2fa-confirm-temp-title">
	<div class="wp2fa-profile__confirm-dialog">
		<h3 id="wp2fa-confirm-temp-title"><?php \esc_html_e( 'Confirm temporary 2FA removal of the user', 'wp-2fa' ); ?></h3>
		<p><?php \esc_html_e( 'Are you sure you want to enable temporary login without 2FA for the user?', 'wp-2fa' ); ?></p>
		<div class="wp2fa-profile__confirm-buttons">
			<a href="<?php echo \esc_url( $data['admin_actions_data']['temp_remove_url'] ); ?>"
				class="wp2fa-profile__btn wp2fa-profile__btn--primary"
				id="wp2fa-confirm-temp-yes">
				<?php \esc_html_e( 'OK', 'wp-2fa' ); ?>
			</a>
			<button type="button" class="wp2fa-profile__btn wp2fa-profile__btn--secondary" data-wp2fa-confirm-close>
				<?php \esc_html_e( 'Cancel', 'wp-2fa' ); ?>
			</button>
		</div>
	</div>
</div>
<?php endif; ?>
