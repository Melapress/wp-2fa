<?php
/**
 * About Us page template.
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

/**
 * Define plugin cards.
 *
 * Each card has:
 * - 'name'        => Display name
 * - 'description' => Short tagline
 * - 'slug'        => Plugin slug (plugin-directory/main-file.php format)
 * - 'logo'        => URL to logo image (or empty string for placeholder)
 * - 'wp_url'      => WordPress.org plugin slug for install URL
 */
$melapress_plugins = array(
	array(
		'name'        => __( 'WP Activity Log', 'wp-2fa' ),
		'description' => __( 'The #1 activity log plugin for WordPress websites', 'wp-2fa' ),
		'slug'        => 'wp-security-audit-log/wp-security-audit-log.php',
		'logo'        => WP_2FA_URL . 'dist/images/wp-2fa-white-icon20x28.svg',
		'wp_url'      => 'wp-security-audit-log',
	),
	array(
		'name'        => __( 'WP 2FA', 'wp-2fa' ),
		'description' => __( 'Two-factor authentication plugin for WordPress websites', 'wp-2fa' ),
		'slug'        => 'wp-2fa/wp-2fa.php',
		'logo'        => '',
		'wp_url'      => 'wp-2fa',
	),
	array(
		'name'        => __( 'WP Login Security', 'wp-2fa' ),
		'description' => __( 'Improve account security on your WordPress site', 'wp-2fa' ),
		'slug'        => 'melapress-login-security/melapress-login-security.php',
		'logo'        => '',
		'wp_url'      => 'melapress-login-security',
	),
);

/**
 * Determine button state for a given plugin.
 *
 * @param string $plugin_slug Plugin slug (dir/file.php).
 * @param string $wp_url      WordPress.org slug for install link.
 *
 * @return array{label:string,url:string,class:string}|null Null when plugin is active.
 */
function wp2fa_get_plugin_card_button( string $plugin_slug, string $wp_url ) {
	if ( is_plugin_active( $plugin_slug ) ) {
		return null; // Already active – no button.
	}

	$all_plugins = get_plugins();

	if ( isset( $all_plugins[ $plugin_slug ] ) ) {
		// Installed but not activated.
		return array(
			'label' => __( 'Activate', 'wp-2fa' ),
			'url'   => wp_nonce_url(
				self_admin_url( 'plugins.php?action=activate&plugin=' . urlencode( $plugin_slug ) ),
				'activate-plugin_' . $plugin_slug
			),
			'class' => 'mp-about-btn-outline',
		);
	}

	// Not installed.
	return array(
		'label' => __( 'Install', 'wp-2fa' ),
		'url'   => wp_nonce_url(
			self_admin_url( 'update.php?action=install-plugin&plugin=' . urlencode( $wp_url ) ),
			'install-plugin_' . $wp_url
		),
		'class' => 'mp-about-btn-filled',
	);
}
?>

<style>
/* ── About Us page ─────────────────────────────────────── */
/* .mp-about-wrap {
	max-width: 980px;
	margin: 30px auto;
	font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
} */

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
	padding: 40px 36px;
}
.mp-about-hero-text h2 {
	margin: 0 0 14px;
	font-size: 22px;
	font-weight: 600;
	color: #1d2327;
}
.mp-about-hero-text p {
	margin: 0;
	font-size: 14px;
	line-height: 1.6;
	color: #3c434a;
}
.mp-about-hero-logo {
	flex: 1;
	display: flex;
	align-items: center;
	justify-content: center;
	background: #e8e8e8;
	padding: 40px 30px;
}
.mp-about-hero-logo img {
	max-width: 260px;
	width: 100%;
	height: auto;
}

/* Plugin cards grid */
.mp-about-cards {
	display: grid;
	grid-template-columns: repeat(3, 1fr);
	gap: 24px;
}
.mp-about-card {
	background: #fff;
	border: 1px solid #dcdcde;
	border-radius: 8px;
	padding: 28px 24px;
	display: flex;
	flex-direction: column;
	justify-content: space-between;
}
.mp-about-card-logo {
	width: 48px;
	height: 48px;
	margin-bottom: 16px;
	display: flex;
	align-items: center;
	justify-content: center;
}
.mp-about-card-logo img {
	max-width: 48px;
	max-height: 48px;
}
.mp-about-card-logo-placeholder {
	width: 48px;
	height: 48px;
	background: #dcdcde;
	border-radius: 6px;
}
.mp-about-card h3 {
	margin: 0 0 6px;
	font-size: 16px;
	font-weight: 600;
	color: #1d2327;
}
.mp-about-card p {
	margin: 0 0 18px;
	font-size: 13px;
	line-height: 1.5;
	color: #646970;
}

/* ── Responsive ──────────────────────────────────────── */
@media screen and (max-width: 960px) {
	.mp-about-cards {
		grid-template-columns: repeat(2, 1fr);
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
@media screen and (max-width: 600px) {
	.mp-about-cards {
		grid-template-columns: 1fr;
	}
	.mp-about-wrap {
		margin: 16px;
	}
}
</style>

<div class="wrap">
<hr class="wp-header-end" />
<div class="main-settings-new">
	<div class="mp-about-wrap">
		<!-- Hero section -->
		<div class="mp-about-hero">
			<div class="mp-about-hero-text">
				<h2><?php \esc_html_e( 'We are Melapress', 'wp-2fa' ); ?></h2>
				<p>
					<?php
					\esc_html_e(
						'Melapress is an eclectic team of WordPress wizards dedicated to developing exceptional management and security plugins. We develop innovative plugins that simplify WordPress security and user management for websites of all sizes.',
						'wp-2fa'
					);
					?>
				</p>
			</div>
			<div class="mp-about-hero-logo">
				<img
					src="<?php echo esc_url( WP_2FA_URL . 'dist/images/melapress-logo-horiz.svg' ); ?>"
					alt="<?php \esc_attr_e( 'Melapress', 'wp-2fa' ); ?>"
				/>
			</div>
		</div>

		<!-- Plugin cards -->
		<div class="mp-about-cards">
			<?php foreach ( $melapress_plugins as $plugin ) { ?>
				<?php $button = wp2fa_get_plugin_card_button( $plugin['slug'], $plugin['wp_url'] ); ?>
				<div class="mp-about-card">
					<div>
						<div class="mp-about-card-logo">
							<?php if ( ! empty( $plugin['logo'] ) ) { ?>
								<img
									src="<?php echo \esc_url( $plugin['logo'] ); ?>"
									alt="<?php echo \esc_attr( $plugin['name'] ); ?>"
								/>
							<?php } else { ?>
								<div class="mp-about-card-logo-placeholder"></div>
							<?php } ?>
						</div>
						<h3><?php echo \esc_html( $plugin['name'] ); ?></h3>
						<p><?php echo \esc_html( $plugin['description'] ); ?></p>
					</div>
					<?php if ( null !== $button ) { ?>
						<a
							href="<?php echo \esc_url( $button['url'] ); ?>"
							class="<?php echo \esc_attr( $button['class'] ); ?>"
						>
							<?php echo \esc_html( $button['label'] ); ?>
						</a>
					<?php } ?>
				</div>
			<?php } ?>
		</div>
	</div>

	<?php include WP_2FA_PATH . 'includes/classes/Admin/Settings/templates/sidebar.php'; ?>
</div>
</div>
