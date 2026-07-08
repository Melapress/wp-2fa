<?php
/**
 * Profile section: Backup 2FA Method card.
 *
 * Contains buttons for generating backup codes and managing backup methods.
 *
 * @var array $data Template data from the renderer.
 *
 * @package wp2fa
 * @since   4.0.0
 */

defined( 'ABSPATH' ) || exit;
$backup = $data['backup_card_data'];
?>
<div class="wp2fa-profile__card" id="wp2fa-backup-card">
	<h4 class="wp2fa-profile__card-title"><?php \esc_html_e( 'Backup 2FA Method', 'wp-2fa' ); ?></h4>
	<div class="wp2fa-profile__btn-group">
		<?php if ( $backup['show_generate_codes'] ) { ?>
			<button type="button"
				class="wp2fa-profile__btn wp2fa-profile__btn--primary"
				data-wp2fa-generate-backup-codes
				data-nonce="<?php echo \esc_attr( $backup['backup_codes_nonce'] ); ?>"
				data-user-id="<?php echo \esc_attr( (string) $data['user_id'] ); ?>">
				<?php \esc_html_e( 'Generate list of backup codes', 'wp-2fa' ); ?>
			</button>
			<?php if ( $backup['codes_remaining'] > 0 ) { ?>
				<p class="wp2fa-profile__status-text">
					<?php
					printf(
						/* translators: %d: number of remaining backup codes */
						\esc_html__( '%d unused backup codes remaining.', 'wp-2fa' ),
						(int) $backup['codes_remaining']
					);
					?>
				</p>
			<?php } elseif ( 0 === $backup['codes_remaining'] ) { ?>
				<p class="wp2fa-profile__status-text">
					<?php echo \wp_kses_post( \WP2FA\WP2FA::get_wp2fa_white_label_setting( 'backup_codes_learn_more', true ) ); ?>
				</p>
			<?php } ?>
		<?php } ?>

		<?php
		/**
		 * Action: wp_2fa_profile_backup_card_buttons
		 *
		 * Allows extensions to add their own backup method buttons
		 * (e.g., "Remove backup email method").
		 *
		 * @param array    $backup Backup card data.
		 * @param \WP_User $user   The target user object.
		 *
		 * @since 4.0.0
		 */
		\do_action( WP_2FA_PREFIX . 'profile_backup_card_buttons', $backup, $data['user'] );
		?>

		<?php
		// Render any extra backup buttons from filter.
		if ( ! empty( $backup['extra_buttons'] ) ) {
			echo \wp_kses_post( $backup['extra_buttons'] );
		}
		?>
	</div>
</div>
