<?php
/**
 * Profile section: Admin-only actions.
 *
 * Shown when an admin views another user's profile.
 * Reset 2FA, temporary removal, unlock account.
 *
 * @var array $data Template data from the renderer.
 *
 * @package wp2fa
 * @since   4.0.0
 */

defined( 'ABSPATH' ) || exit;
$admin = $data['admin_actions_data'];
?>
<div class="wp2fa-profile__admin-actions">
	<h4 class="wp2fa-profile__admin-title"><?php \esc_html_e( 'Admin Actions', 'wp-2fa' ); ?></h4>

	<?php if ( ! empty( $admin['description'] ) ) : ?>
		<p class="wp2fa-profile__admin-desc"><?php echo \esc_html( $admin['description'] ); ?></p>
	<?php endif; ?>

	<?php if ( ! empty( $admin['reset_url'] ) ) : ?>
		<a href="<?php echo \esc_url( $admin['reset_url'] ); ?>" class="wp2fa-profile__btn wp2fa-profile__btn--primary">
			<?php \esc_html_e( 'Reset 2FA configuration', 'wp-2fa' ); ?>
		</a>
	<?php endif; ?>

	<?php if ( ! empty( $admin['temp_remove_url'] ) ) : ?>
		<button type="button"
			class="wp2fa-profile__btn wp2fa-profile__btn--secondary"
			data-wp2fa-confirm="temp-remove"
			data-href="<?php echo \esc_url( $admin['temp_remove_url'] ); ?>">
			<?php \esc_html_e( 'Remove 2FA temporary', 'wp-2fa' ); ?>
		</button>
	<?php endif; ?>

	<?php if ( ! empty( $admin['unlock_url'] ) ) : ?>
		<a href="<?php echo \esc_url( $admin['unlock_url'] ); ?>" class="wp2fa-profile__btn wp2fa-profile__btn--secondary">
			<?php \esc_html_e( 'Unlock user and reset the grace period', 'wp-2fa' ); ?>
		</a>
	<?php endif; ?>
</div>
