<?php
/**
 * Docs and Support page template.
 *
 * @package    wp2fa
 * @subpackage admin
 * @copyright  2026 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 * @since      4.1.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP2FA\Admin\Helpers\WP_Helper;
?>

<style>
/* ── Docs and Support page ─────────────────────────────── */
/* .mp-support-wrap {
	max-width: 1200px;
	margin: 30px auto;
	font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
} */

/* Main layout: content + sidebar */
.mp-support-layout {
	display: grid;
	/* grid-template-columns: 1fr 320px; */
	gap: 28px;
	margin-bottom: 24px;
}

/* Content area (left) */
/* .mp-support-content {
	background: #fff;
	border: 1px solid #dcdcde;
	border-radius: 8px;
	padding: 28px 24px;
}
.mp-support-content h2 {
	margin: 0 0 14px;
	font-size: 22px;
	font-weight: 600;
	color: #1d2327;
}
.mp-support-content h3 {
	margin: 18px 0 10px;
	font-size: 15px;
	font-weight: 600;
	color: #1d2327;
}
.mp-support-content ul {
	margin: 0 0 16px;
	padding: 0;
	list-style: none;
}
.mp-support-content ul li {
	margin-bottom: 6px;
}
.mp-support-content ul li a {
	color: #2271b1;
	text-decoration: none;
	font-size: 14px;
}
.mp-support-content ul li a:hover {
	color: #135e96;
	text-decoration: underline;
}
.mp-support-content .mp-video-embed {
	margin-top: 16px;
}
.mp-support-content .mp-video-embed iframe {
	width: 100%;
	max-width: 560px;
	height: 315px;
	border: none;
	border-radius: 6px;
} */

/* Two-column cards row */
.mp-support-cards {
	display: grid;
	grid-template-columns: repeat(2, 1fr);
	gap: 24px;
	margin-bottom: 24px;
}
.mp-support-card {
	background: #fff;
	border: 1px solid #dcdcde;
	border-radius: 8px;
	padding: 28px 24px;
	display: flex;
	flex-direction: column;
	justify-content: space-between;
}
.mp-support-card h3 {
	margin: 0 0 10px;
	font-size: 16px;
	font-weight: 600;
	color: #1d2327;
}
.mp-support-card p {
	margin: 0 0 18px;
	font-size: 13px;
	line-height: 1.5;
	color: #646970;
}
.mp-support-card-buttons {
	display: flex;
	gap: 10px;
	flex-wrap: wrap;
}

/* System info togglable card */
.mp-support-sysinfo {
	background: #fff;
	border: 1px solid #dcdcde;
	border-radius: 8px;
	overflow: hidden;
}
.mp-support-sysinfo-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 20px 24px;
	cursor: pointer;
	user-select: none;
}
.mp-support-sysinfo-header:hover {
	background: #f9f9f9;
}
.mp-support-sysinfo-header h3 {
	margin: 0;
	font-size: 16px;
	font-weight: 600;
	color: #1d2327;
}
.mp-support-sysinfo-toggle {
	width: 24px;
	height: 24px;
	transition: transform 0.2s ease;
	display: flex;
	align-items: center;
	justify-content: center;
}
.mp-support-sysinfo-toggle .dashicons {
	font-size: 20px;
	width: 20px;
	height: 20px;
	color: #646970;
}
.mp-support-sysinfo.open .mp-support-sysinfo-toggle {
	transform: rotate(180deg);
}
.mp-support-sysinfo-body {
	display: none;
	padding: 0 24px 24px;
}
.mp-support-sysinfo.open .mp-support-sysinfo-body {
	display: block;
}
#system-info-textarea {
	width: 100%;
	height: 300px;
	font-family: monospace;
	font-size: 12px;
	padding: 12px;
	border: 1px solid #dcdcde;
	border-radius: 4px;
	background: #f6f7f7;
	resize: vertical;
	box-sizing: border-box;
}

/* Hero / intro section */
.mp-about-hero {
	display: flex;
	gap: 0;
	background: #f0f0f0;
	border-radius: 8px;
	overflow: hidden;
	margin-bottom: 24px;
	background: #fff;
	border: 1px solid #dcdcde;
	border-radius: 8px;
	padding: 28px 24px;
}
.mp-about-hero-text {
	flex: 1;
	padding: 0 2px;
}
.mp-about-hero-text h2 {
	margin: 0 0 14px;
	font-size: 22px;
	font-weight: 600;
	color: #1d2327;
}
.mp-about-hero-text h2.left-title {
	padding: 2px 0px 40px 0;
}
.mp-about-hero-text p {
	margin: 0;
	font-size: 14px;
	line-height: 1.6;
	color: #3c434a;
}
.mp-about-hero-video {
	flex: 1;
	display: flex;
	align-items: center;
	/* justify-content: center;
	background: #e8e8e8;
	padding: 40px 30px; */
	border: 1px solid #dcdcde;
	border-radius: 8px;
}
.mp-about-hero-video iframe {
	width: 100%;
	height: 100%;
	border: 1px solid #dcdcde;
	border-radius: 22px;
}


/* ── Responsive ──────────────────────────────────────── */
@media screen and (max-width: 960px) {
	.mp-support-layout {
		grid-template-columns: 1fr;
	}
}
@media screen and (max-width: 782px) {
	.mp-support-cards {
		grid-template-columns: 1fr;
	}
}
@media screen and (max-width: 600px) {
	.mp-support-wrap {
		margin: 16px;
	}
}
@media screen and (max-width: 782px) {
	.mp-about-hero {
		flex-direction: column;
	}
	.mp-about-hero-text,
	.mp-about-hero-logo {
		padding: 28px 24px;
	}
}
</style>


<div class="main-settings-new">
	<div class="wrap main-left">

			<div class="mp-about-hero">
				<div class="mp-about-hero-text">
					<h2 class="left-title"><?php \esc_html_e( 'Docs and Support', 'wp-2fa' ); ?></h2>

					<h3><?php \esc_html_e( 'Getting started', 'wp-2fa' ); ?></h3>
					<ul>
						<li>
							<a href="<?php echo \esc_url( 'https://melapress.com/support/kb/wp-2fa-plugin-getting-started/?&utm_source=plugin&utm_medium=wp2fa&utm_campaign=getting_started_help' ); ?>" target="_blank">
								<?php \esc_html_e( 'Getting started with WP 2FA', 'wp-2fa' ); ?>
							</a>
						</li>
						<li>
							<a href="<?php echo \esc_url( 'https://melapress.com/support/kb/wp-2fa-configure-2fa-policies-enforce/?&utm_source=plugin&utm_medium=wp2fa&utm_campaign=configure_policies_help' ); ?>" target="_blank">
								<?php \esc_html_e( 'Configuring 2FA policies & making 2FA mandatory', 'wp-2fa' ); ?>
							</a>
						</li>
						<li>
							<a href="<?php echo \esc_url( 'https://melapress.com/support/kb/wp-2fa-configure-2fa-front-end-page-wordpress/?&utm_source=plugin&utm_medium=wp2fa&utm_campaign=no_dashboard_page_help' ); ?>" target="_blank">
								<?php \esc_html_e( 'Allowing users to configure 2FA from a website page (no dashboard access)', 'wp-2fa' ); ?>
							</a>
						</li>
					</ul>
				</div>
			<div class="mp-about-hero-video">
				<iframe src="https://www.youtube.com/embed/vRlX_NNGeFo" title="<?php \esc_attr_e( 'Getting started with WP 2FA', 'wp-2fa' ); ?>" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
			</div>
		</div>

		<!-- Two-column cards: Plugin documentation + Plugin Support -->
		<div class="mp-support-cards">

			<!-- Plugin documentation -->
			<div class="mp-support-card">
				<div>
					<h3><?php \esc_html_e( 'Plugin documentation', 'wp-2fa' ); ?></h3>
					<p><?php \esc_html_e( 'For more technical information about the WP 2FA plugin please visit the plugin\'s knowledge base.', 'wp-2fa' ); ?></p>
				</div>
				<div class="mp-support-card-buttons">
					<a href="<?php echo \esc_url( 'https://melapress.com/support/kb/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=technical_help' ); ?>" class="mp-about-btn-filled" target="_blank">
						<?php \esc_html_e( 'Go to knowledge base', 'wp-2fa' ); ?>
					</a>
				</div>
			</div>

			<!-- Plugin Support -->
			<div class="mp-support-card">
				<div>
					<h3><?php \esc_html_e( 'Plugin Support', 'wp-2fa' ); ?></h3>
					<p><?php \esc_html_e( 'Do you need assistance with the plugin? Have you noticed or encountered an issue while using WP 2FA, or do you just want to report something to us?', 'wp-2fa' ); ?></p>
				</div>
				<div class="mp-support-card-buttons">
					<a href="<?php echo \esc_url( 'https://melapress.com/support/submit-ticket/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=suppor_ticket' ); ?>" class="mp-about-btn-filled" target="_blank">
						<?php \esc_html_e( 'Open Support ticket', 'wp-2fa' ); ?>
					</a>
					<a href="<?php echo \esc_url( 'https://melapress.com/contact/?utm_source=plugin&utm_medium=link&utm_campaign=contact_us' ); ?>" class="mp-about-btn-outline" target="_blank">
						<?php \esc_html_e( 'Contact us', 'wp-2fa' ); ?>
					</a>
				</div>
			</div>

		</div>

		<!-- System info toggleable card -->
		<div class="mp-support-sysinfo open" id="mp-sysinfo-toggle">
			<div class="mp-support-sysinfo-header" onclick="document.getElementById('mp-sysinfo-toggle').classList.toggle('open');">
				<h3><?php \esc_html_e( 'System info', 'wp-2fa' ); ?></h3>
				<div class="mp-support-sysinfo-toggle">
					<span class="dashicons dashicons-arrow-down-alt2"></span>
				</div>
			</div>
			<div class="mp-support-sysinfo-body">
				<form method="post" dir="ltr">
					<textarea readonly="readonly" onclick="this.focus(); this.select()" id="system-info-textarea" name="wsal-sysinfo"><?php echo \esc_textarea( \WP2FA\Admin\Help_Contact_Us::get_sysinfo() ); ?></textarea>
					<p class="submit">
						<input type="hidden" name="ppmwp-action" value="download_sysinfo" />
						<button type="button" class="mp-about-btn-filled" id="wp2fa-download-sysinfo"><?php \esc_html_e( 'Download System Info File', 'wp-2fa' ); ?></button>
					</p>
				</form>
				<script>
					function wp2faDownloadSysinfo(filename, text) {
						var element = document.createElement('a');
						element.setAttribute('href', 'data:text/plain;charset=utf-8,' + encodeURIComponent(text));
						element.setAttribute('download', filename);
						element.style.display = 'none';
						document.body.appendChild(element);
						element.click();
						document.body.removeChild(element);
					}
					jQuery( document ).ready( function() {
						jQuery( '#wp2fa-download-sysinfo' ).on( 'click', function( event ) {
							event.preventDefault();
							wp2faDownloadSysinfo( 'wp2fa-system-info.txt', jQuery( '#system-info-textarea' ).val() );
						} );
					} );
				</script>
			</div>
		</div>

	</div>

	<?php include WP_2FA_PATH . 'includes/classes/Admin/Settings/templates/sidebar.php'; ?>
</div>

