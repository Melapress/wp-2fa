<?php
/**
 * Profile section: Currently configured summary.
 *
 * Shows the primary method and secondary (backup) methods.
 *
 * @var array $data Template data from the renderer.
 *
 * @package wp2fa
 * @since   4.0.0
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wp2fa-profile__summary">
	<div class="wp2fa-profile__summary-row">
		<span class="wp2fa-profile__summary-label"><?php echo \esc_html( \WP2FA\WP2FA::get_wp2fa_white_label_setting( 'user-profile-primary-method-label', true ) ); ?></span>
		<span class="wp2fa-profile__summary-value">
			<?php echo \esc_html( $data['primary_label'] ); ?>
			<?php if ( ! $data['has_enabled_methods'] && (int) $data['user_id'] === (int) \get_current_user_id() ) : ?>
				<a href="#" class="wp2fa-profile__configure-now-link" data-wp2fa-open-wizard data-user-id="<?php echo \esc_attr( (string) $data['user_id'] ); ?>"><?php \esc_html_e( 'Configure Now.', 'wp-2fa' ); ?></a>
			<?php endif; ?>
		</span>
	</div>
	<div class="wp2fa-profile__summary-row">
		<span class="wp2fa-profile__summary-label"><?php echo \esc_html( \WP2FA\WP2FA::get_wp2fa_white_label_setting( 'user-profile-secondary-method-label', true ) ); ?></span>
		<span class="wp2fa-profile__summary-value">
			<?php if ( $data['has_enabled_methods'] ) : ?>
				<?php echo \esc_html( $data['backup_methods_label'] ); ?>
			<?php else : ?>
				<?php \esc_html_e( 'No enabled secondary methods', 'wp-2fa' ); ?>
			<?php endif; ?>
		</span>
	</div>
</div>
