<?php
/**
 * Profile section: 2FA Setup card.
 *
 * Contains buttons for configuring/changing 2FA and optionally removing it.
 *
 * @var array $data Template data from the renderer.
 *
 * @package wp2fa
 * @since   4.0.0
 */

defined( 'ABSPATH' ) || exit;
$setup = $data['setup_card_data'];
?>
<div class="wp2fa-profile__card" id="wp2fa-setup-card">
	<h4 class="wp2fa-profile__card-title"><?php \esc_html_e( '2FA Setup', 'wp-2fa' ); ?></h4>
	<div class="wp2fa-profile__btn-group">
		<?php if ( $setup['show_change_btn'] ) : ?>
			<button type="button"
				class="wp2fa-profile__btn wp2fa-profile__btn--primary"
				data-wp2fa-open-wizard
				data-user-id="<?php echo \esc_attr( (string) $data['user_id'] ); ?>">
				<?php echo \esc_html( $setup['change_label'] ); ?>
			</button>
		<?php endif; ?>

		<?php if ( $setup['show_configure_btn'] ) : ?>
			<button type="button"
				class="wp2fa-profile__btn wp2fa-profile__btn--primary"
				data-wp2fa-open-wizard
				data-user-id="<?php echo \esc_attr( (string) $data['user_id'] ); ?>">
				<?php echo \esc_html( $setup['configure_label'] ); ?>
			</button>
		<?php endif; ?>

		<?php if ( $setup['show_remove_btn'] ) : ?>
			<button type="button"
				class="wp2fa-profile__btn wp2fa-profile__btn--secondary"
				data-wp2fa-confirm="remove-2fa">
				<?php \esc_html_e( 'Remove 2FA', 'wp-2fa' ); ?>
			</button>
		<?php endif; ?>
	</div>
</div>
