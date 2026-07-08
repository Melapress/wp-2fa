<?php
/**
 * Profile section: Main wrapper template.
 *
 * Renders the entire "Two-factor authentication settings" profile section.
 * All variables are extracted from the $data array passed by the renderer.
 *
 * @var string   $preamble_title        Section heading.
 * @var string   $preamble_desc         Section description.
 * @var bool     $show_preamble         Whether to show the heading/description.
 * @var bool     $is_excluded           Whether the user is excluded from 2FA.
 * @var string   $excluded_content      Extra content for excluded users (from filter).
 * @var bool     $has_enabled_methods   Whether the user has an enabled primary 2FA method.
 * @var string   $primary_label         Translated label for the primary method.
 * @var string   $backup_methods_label  Comma-separated backup method names.
 * @var bool     $show_enabled          Whether to show the "Currently configured" summary.
 * @var bool     $show_setup_card       Whether to show the 2FA Setup card.
 * @var bool     $show_backup_card      Whether to show the Backup 2FA Method card.
 * @var bool     $show_admin_actions    Whether to show admin-only actions.
 * @var array    $setup_card_data       Data for the setup card template.
 * @var array    $backup_card_data      Data for the backup card template.
 * @var array    $admin_actions_data    Data for admin actions template.
 * @var array    $totp_data             TOTP-specific data (QR code, key).
 * @var string   $extra_content         Additional content from filters.
 *
 * @package wp2fa
 * @since   4.0.0
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wp2fa-profile <?php echo \esc_attr( $data['styling_class'] ?? 'default_styling' ); ?>" id="wp2fa-profile-section" data-user-id="<?php echo \esc_attr( (string) $data['user_id'] ); ?>">

	<?php if ( $data['show_preamble'] ) : ?>
		<h2 class="wp2fa-profile__title"><?php echo \esc_html( strip_tags( $data['preamble_title'] ) ); ?></h2>
		<?php if ( ! empty( $data['preamble_desc'] ) ) : ?>
			<p class="wp2fa-profile__desc"><?php echo \wp_kses_post( $data['preamble_desc'] ); ?></p>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( $data['is_excluded'] ) : ?>
		<?php if ( ! empty( $data['excluded_content'] ) ) : ?>
			<div class="wp2fa-profile__notice">
				<?php echo \wp_kses_post( $data['excluded_content'] ); ?>
			</div>
		<?php endif; ?>

		<?php
		// Still render extra content (e.g. passkeys) for excluded users if applicable.
		if ( ! empty( $data['extra_content'] ) ) :
			echo \wp_kses_post( $data['extra_content'] );
		endif;
		?>
	</div>
		<?php return; ?>
	<?php endif; ?>

	<?php
	// Currently configured summary.
	if ( $data['show_enabled'] ) :
		include __DIR__ . '/summary.php';
	endif;
	?>

	<?php
	// TOTP QR code section (only for users viewing own profile with TOTP configured).
	if ( ! empty( $data['totp_data'] ) && $data['totp_data']['show_qr'] ) :
		include __DIR__ . '/totp-details.php';
	endif;
	?>

	<?php if ( $data['show_setup_card'] || $data['show_backup_card'] ) : ?>
		<h3 class="wp2fa-profile__section-title"><?php \esc_html_e( '2FA Configuration', 'wp-2fa' ); ?></h3>
		<div class="wp2fa-profile__cards">
			<?php
			if ( $data['show_setup_card'] ) :
				include __DIR__ . '/card-setup.php';
			endif;

			if ( $data['show_backup_card'] ) :
				include __DIR__ . '/card-backup.php';
			endif;
			?>
		</div>
	<?php endif; ?>

	<?php
	// Admin-only actions (reset, temp remove, unlock).
	if ( $data['show_admin_actions'] ) :
		include __DIR__ . '/admin-actions.php';
	endif;
	?>

	<?php
	// Extra content from filters.
	if ( ! empty( $data['extra_content'] ) ) :
		echo \wp_kses_post( $data['extra_content'] );
	endif;
	?>

	<?php
	// Confirmation dialogs.
	include __DIR__ . '/confirm-dialogs.php';
	?>

	<?php
	// Backup codes generated dialog.
	if ( ! empty( $data['backup_card_data']['show_generate_codes'] ) ) :
		include __DIR__ . '/backup-codes-dialog.php';
	endif;
	?>
</div>
