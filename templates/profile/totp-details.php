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
		<?php
		/*
		 * The QR code is a data: URI. esc_url() strips that scheme - it is not
		 * one WordPress allows in links - and left an empty src: the broken image
		 * of #3747, back again once the escaping was restored. It is checked to
		 * be exactly what TOTP::get_qr_code() makes, and escaped as an attribute.
		 */
		$wp2fa_qr_src = is_string( $totp['qr_code_url'] ?? null ) && 1 === preg_match( '#^data:image/svg\+xml;base64,[A-Za-z0-9+/]+={0,2}$#', $totp['qr_code_url'] ) ? $totp['qr_code_url'] : '';
		?>
		<?php if ( '' !== $wp2fa_qr_src ) : ?>
			<img class="wp2fa-profile__totp-qr"
				src="<?php echo \esc_attr( $wp2fa_qr_src ); ?>"
				alt="<?php \esc_attr_e( 'TOTP QR Code', 'wp-2fa' ); ?>" />
		<?php else : ?>
			<div class="wp2fa-profile__totp-qr-unavailable">
				<span class="wp2fa-profile__totp-qr-unavailable-icon" aria-hidden="true">&#9888;</span>
				<p><?php \esc_html_e( 'The QR code image cannot be generated on this server. Enter the key below into your authenticator app by hand instead.', 'wp-2fa' ); ?></p>
			</div>
		<?php endif; ?>
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
