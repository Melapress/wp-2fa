<?php
/**
 * Profile section: TOTP QR code details.
 *
 * Expandable section showing the TOTP QR code and manual key.
 *
 * @var array $data Template data from the renderer.
 *
 * @package wp2fa
 * @since   4.0.0
 */

defined( 'ABSPATH' ) || exit;
$totp = $data['totp_data'];
?>
<div class="wp2fa-profile__totp-details">
	<button type="button"
		class="wp2fa-profile__totp-toggle"
		data-wp2fa-toggle-totp
		aria-expanded="false"
		aria-controls="wp2fa-totp-qr-content">
		&#9654; <?php \esc_html_e( 'Show QR code', 'wp-2fa' ); ?>
	</button>
	<div class="wp2fa-profile__totp-content" id="wp2fa-totp-qr-content">
		<img class="wp2fa-profile__totp-qr"
			src="<?php echo ( $totp['qr_code_url'] ); ?>"
			alt="<?php \esc_attr_e( 'TOTP QR Code', 'wp-2fa' ); ?>" />
		<div class="wp2fa-profile__totp-key-wrap">
			<input type="text"
				class="wp2fa-profile__totp-key-input"
				id="wp2fa-totp-key"
				readonly
				value="<?php echo \esc_attr( $totp['totp_key'] ); ?>" />
			<?php if ( \is_ssl() ) : ?>
				<button type="button"
					class="wp2fa-profile__copy-btn"
					data-wp2fa-copy="#wp2fa-totp-key"><?php \esc_html_e( 'COPY', 'wp-2fa' ); ?></button>
			<?php endif; ?>
		</div>
	</div>
</div>
