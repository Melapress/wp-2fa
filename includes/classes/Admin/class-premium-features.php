<?php
/**
 * Premium features rendering class.
 *
 * @package    wp2fa
 * @subpackage admin
 *
 * @copyright  2026 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 *
 * @see       https://wordpress.org/plugins/wp-2fa/
 * @since      2.0.0
 */

declare(strict_types=1);

namespace WP2FA\Admin;

use WP2FA\Licensing\Licensing_Factory;

/*
 * Premium_Features class for the premium features show
 *
 * @since 2.4.0
 */
if ( ! class_exists( '\WP2FA\Admin\Premium_Features' ) ) {
	/**
	 * Handles contact the features page and content.
	 */
	class Premium_Features {
		public const TOP_MENU_SLUG = 'wp-2fa-premium-features';

		/**
		 * Create admin menu entry and settings page.
		 *
		 * @return void
		 *
		 * @since 2.8.0
		 */
		public static function add_extra_menu_item() {
			\add_submenu_page(
				Settings_Page::TOP_MENU_SLUG,
				\esc_html__( 'Premium Features', 'wp-2fa' ),
				\esc_html__( 'Premium Features ➤', 'wp-2fa' ),
				'manage_options',
				self::TOP_MENU_SLUG,
				array( __CLASS__, 'render' ),
				100
			);
		}

		/**
		 * Adds an upgrade banner to settings pages.
		 *
		 * @return void
		 *
		 * @since 2.8.0
		 */
		public static function add_settings_banner() {
			?>
			<style>
				#wp-2fa-side-banner.mp-sidebar-upgrade {
					border: 2px solid #2271b1;
					border-radius: 8px;
					padding: 28px 24px;
					text-align: start;
					background: #fff;
					max-width: 320px;
					margin-bottom: 20px;
				}
				#wp-2fa-side-banner .mp-sidebar-upgrade-header {
					display: flex;
					align-items: center;
					gap: 10px;
					margin-bottom: 16px;
				}
				#wp-2fa-side-banner .mp-sidebar-upgrade-logo {
					width: 36px;
					height: auto;
				}
				#wp-2fa-side-banner .mp-sidebar-upgrade-title {
					font-size: 18px;
					font-weight: 700;
					color: #1d2327;
				}
				#wp-2fa-side-banner .mp-sidebar-upgrade-tagline {
					margin: 0 0 16px;
					font-size: 14px;
					line-height: 1.5;
					color: #1d2327;
				}
				#wp-2fa-side-banner .mp-sidebar-upgrade-features {
					list-style: none;
					margin: 0 0 24px;
					padding: 0;
				}
				#wp-2fa-side-banner .mp-sidebar-upgrade-features li {
					position: relative;
					padding-inline-start: 28px;
					margin-bottom: 12px;
					font-size: 14px;
					line-height: 1.5;
					color: #3c434a;
				}
				#wp-2fa-side-banner .mp-sidebar-upgrade-features li::before {
					content: '';
					position: absolute;
					inset-inline-start: 0;
					top: 2px;
					width: 18px;
					height: 18px;
					background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%232271b1' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='20 6 9 17 4 12'/%3E%3C/svg%3E");
					background-size: contain;
					background-repeat: no-repeat;
				}
				#wp-2fa-side-banner .mp-sidebar-upgrade-btn {
					display: block;
					width: 100%;
					padding: 12px 24px;
					margin-bottom: 20px;
					font-size: 15px;
					font-weight: 600;
					text-align: center;
					text-decoration: none;
					color: #fff;
					background: #2271b1;
					border: none;
					border-radius: 6px;
					cursor: pointer;
					transition: background 0.2s;
					box-sizing: border-box;
				}
				#wp-2fa-side-banner .mp-sidebar-upgrade-btn:hover,
				#wp-2fa-side-banner .mp-sidebar-upgrade-btn:focus {
					background: #135e96;
					color: #fff;
					text-decoration: none;
				}
				#wp-2fa-side-banner .mp-sidebar-upgrade-guarantee-title {
					margin: 0 0 4px;
					font-size: 14px;
					text-align: center;
					color: #1d2327;
				}
				#wp-2fa-side-banner .mp-sidebar-upgrade-guarantee {
					margin: 0;
					font-size: 13px;
					text-align: center;
					color: #646970;
				}
			</style>
			<div id="wp-2fa-side-banner" class="mp-sidebar-card mp-sidebar-upgrade">
				<div class="mp-sidebar-upgrade-header">
					<img src="<?php echo \esc_url( WP_2FA_URL . 'dist/images/wp-2fa-color_opt.png' ); ?>" alt="WP 2FA" class="mp-sidebar-upgrade-logo">
					<span class="mp-sidebar-upgrade-title"><?php \esc_html_e( 'WP2FA Premium', 'wp-2fa' ); ?></span>
				</div>

				<p class="mp-sidebar-upgrade-tagline"><strong><?php \esc_html_e( 'Stronger authentication. Smoother logins.', 'wp-2fa' ); ?></strong></p>

				<ul class="mp-sidebar-upgrade-features">
					<li><?php \esc_html_e( 'Passkeys, SMS, one-click login links, & more advanced authentication methods', 'wp-2fa' ); ?></li>
					<li><?php \esc_html_e( 'Faster logins with trusted devices', 'wp-2fa' ); ?></li>
					<li><?php \esc_html_e( 'Email-based 2FA with zero setup', 'wp-2fa' ); ?></li>
					<li><?php \esc_html_e( 'WooCommerce integration', 'wp-2fa' ); ?></li>
					<li><?php \esc_html_e( 'Fully branded and white-label 2FA', 'wp-2fa' ); ?></li>
					<li><?php \esc_html_e( 'Flexible policies for different user roles', 'wp-2fa' ); ?></li>
					<li><?php \esc_html_e( 'Backup methods to prevent lockouts', 'wp-2fa' ); ?></li>
				</ul>

				<a href="https://melapress.com/wordpress-2fa/pricing/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=upgrade_to_premium_banner" target="_blank" rel="noopener noreferrer" class="mp-sidebar-upgrade-btn"><?php \esc_html_e( 'Buy now', 'wp-2fa' ); ?></a>

				<p class="mp-sidebar-upgrade-guarantee-title"><strong><?php \esc_html_e( "Stronger security that your users won't push back on", 'wp-2fa' ); ?></strong></p>
				<p class="mp-sidebar-upgrade-guarantee"><?php \esc_html_e( '30-day money back guarantee', 'wp-2fa' ); ?></p>
			</div>
			<?php
		}

		/**
		 * Handles rendering the content.
		 *
		 * @return void
		 *
		 * @since 2.8.0
		 */
		public static function render() {
			if ( ! Licensing_Factory::has_active_valid_license() ) {
				?>
				<style>
				.wp2fa-reports-teaser-wrap {
					max-width: 100%;
					margin-top: 20px;
					margin-right: 20px;
				}
				.wp2fa-reports-teaser-card {
					display: grid;
					grid-template-columns: 1fr 1fr;
					gap: 36px;
					background: #fff;
					border: 1px solid #dcdcde;
					border-radius: 8px;
					padding: 28px;
				}
				.wp2fa-reports-teaser-title-wrap {
					display: flex;
					align-items: center;
					gap: 14px;
					margin-bottom: 18px;
				}
				.wp2fa-reports-teaser-icon {
					width: 52px;
					height: 52px;
					border-radius: 8px;
					object-fit: contain;
				}
				.wp2fa-reports-teaser-title {
					margin: 0;
					font-size: 22px;
					line-height: 1.22;
					font-weight: 700;
					color: #3c434a;
				}
				.wp2fa-reports-teaser-content {
					font-size: 14px;
				}
				.wp2fa-reports-teaser-description {
					margin: 0 0 16px;
					font-size: 14px;
					line-height: 1.45;
					font-weight: 400;
					color: #50575e;
				}
				.wp2fa-reports-teaser-list {
					list-style: none;
					margin: 0 0 24px;
					padding: 0;
					display: flex;
					flex-direction: column;
					gap: 12px;
				}
				.wp2fa-reports-teaser-list li {
					display: flex;
					align-items: flex-start;
					gap: 10px;
				}
				.wp2fa-reports-teaser-list-icon {
					width: 32px;
					height: 32px;
					border-radius: 50%;
					background: #2271b1;
					flex-shrink: 0;
					margin-top: 2px;
					position: relative;
				}
				.wp2fa-reports-teaser-list-icon::before {
					content: '';
					position: absolute;
					top: 50%;
					left: 50%;
					width: 17px;
					height: 17px;
					transform: translate(-50%, -50%);
					background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='white'%3E%3Cpath d='M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z'/%3E%3C/svg%3E");
					background-repeat: no-repeat;
					background-size: contain;
				}
				.wp2fa-reports-teaser-point-description {
					display: block;
					margin-top: 2px;
					font-size: 14px;
					line-height: 1.34;
					color: #646970;
				}
				.wp2fa-reports-teaser-cta {
					display: inline-block;
					padding: 9px 18px;
					border-radius: 7px;
					background: #2271b1;
					border: 1px solid #2271b1;
					color: #fff;
					font-size: 17px;
					font-weight: 500;
					text-decoration: none;
				}
				.wp2fa-reports-teaser-cta:hover,
				.wp2fa-reports-teaser-cta:focus {
					background: #135e96;
					border-color: #135e96;
					color: #fff;
				}
				.wp2fa-reports-teaser-preview {
					background: #efeff0;
					border-radius: 10px;
					padding: 16px;
					overflow: hidden;
				}
				.wp2fa-reports-teaser-preview img {
					width: 100%;
					height: auto;
					display: block;
					border-radius: 8px;
					opacity: 0.94;
					margin-left: 60px;
				}

				@media screen and (max-width: 980px) {
					.wp2fa-reports-teaser-card {
						grid-template-columns: 1fr;
					}
					.wp2fa-reports-teaser-preview img {
						margin-left: 0;
					}
				}
				</style>

				<div class="wp2fa-reports-teaser-wrap">
					<div class="wp2fa-reports-teaser-card">
						<div class="wp2fa-reports-teaser-content">
							<div class="wp2fa-reports-teaser-title-wrap">
								<svg class="wp2fa-reports-teaser-icon" xmlns="http://www.w3.org/2000/svg" width="33" height="49" viewBox="0 0 33 49" fill="none" aria-hidden="true" focusable="false">
									<path d="M10.7607 16.2846L18.7944 8.17949L26.9017 0H13.414H0V8.17949V16.2846L2.65332 13.6077L5.38035 10.8564L8.03367 13.6077L10.7607 16.2846Z" fill="#99FFFF"/>
									<path d="M32.2094 48.9278V37.997V27.1406L21.5224 37.997L10.7617 48.7791L21.5224 48.8534L32.2094 48.9278Z" fill="#3E6BFF"/>
									<path d="M10.7607 27.1406L8.03367 24.3893L5.38035 21.7124L2.65332 18.9611L0 16.2841V27.1406V37.997L2.65332 35.2457L5.38035 32.5688L8.03367 35.2457L10.7607 37.997L18.7944 29.8175L26.9017 21.7124L29.555 24.3893L32.2084 27.1406V16.2841V5.42773L21.5214 16.2841L10.7607 27.1406Z" fill="#40D3F0"/>
								</svg>
								<h1 class="wp2fa-reports-teaser-title"><?php echo \esc_html__( 'Upgrade to Premium and get more users protected', 'wp-2fa' ); ?></h1>
							</div>

							<p class="wp2fa-reports-teaser-description"><?php echo \esc_html__( 'WP 2FA Premium helps you roll out two-factor authentication faster, improve user adoption, and give users more flexible ways to securely access their accounts.', 'wp-2fa' ); ?></p>
							<p class="wp2fa-reports-teaser-description"><?php echo \esc_html__( 'Whether you manage a business website, WooCommerce store, membership platform, or agency website, WP 2FA Premium gives you the tools you need to implement and enforce 2FA with minimal friction.', 'wp-2fa' ); ?></p>
							<p class="wp2fa-reports-teaser-description"><?php echo \esc_html__( 'Upgrade today and unlock features such as:', 'wp-2fa' ); ?></p>

							<ul class="wp2fa-reports-teaser-list">
								<li>
									<span class="wp2fa-reports-teaser-list-icon" aria-hidden="true"></span>
									<span class="wp2fa-reports-teaser-point-description"><?php echo \esc_html__( 'Roll out 2FA faster by automatically enrolling users with zero-setup email 2FA', 'wp-2fa' ); ?></span>
								</li>
								<li>
									<span class="wp2fa-reports-teaser-list-icon" aria-hidden="true"></span>
									<span class="wp2fa-reports-teaser-point-description"><?php echo \esc_html__( 'Increase user adoption with frictionless authentication options such as passkeys, trusted devices, and one-click login', 'wp-2fa' ); ?></span>
								</li>
								<li>
									<span class="wp2fa-reports-teaser-list-icon" aria-hidden="true"></span>
									<span class="wp2fa-reports-teaser-point-description"><?php echo \esc_html__( 'Give every user a secure authentication method that works for them, including passkeys, SMS, security keys, email verification, and authenticator apps', 'wp-2fa' ); ?></span>
								</li>
								<li>
									<span class="wp2fa-reports-teaser-list-icon" aria-hidden="true"></span>
									<span class="wp2fa-reports-teaser-point-description"><?php echo \esc_html__( 'Reduce support requests and account recovery issues with backup authentication methods and password reset protection', 'wp-2fa' ); ?></span>
								</li>
								<li>
									<span class="wp2fa-reports-teaser-list-icon" aria-hidden="true"></span>
									<span class="wp2fa-reports-teaser-point-description"><?php echo \esc_html__( 'Apply different security requirements to different users with flexible 2FA policies per user/role', 'wp-2fa' ); ?></span>
								</li>
								<li>
									<span class="wp2fa-reports-teaser-list-icon" aria-hidden="true"></span>
									<span class="wp2fa-reports-teaser-point-description"><?php echo \esc_html__( 'WooCommerce integration for a seamless customer experience', 'wp-2fa' ); ?></span>
								</li>
								<li>
									<span class="wp2fa-reports-teaser-list-icon" aria-hidden="true"></span>
									<span class="wp2fa-reports-teaser-point-description"><?php echo \esc_html__( '2FA usage reports and statistics to track adoption across your website', 'wp-2fa' ); ?></span>
								</li>
							</ul>

							<a class="wp2fa-reports-teaser-cta" href="<?php echo \esc_url( 'https://melapress.com/wordpress-2fa/features/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=free-premium-features-page' ); ?>" target="_blank" rel="noopener noreferrer"><?php echo \esc_html__( 'View all features', 'wp-2fa' ); ?></a>
						</div>

						<div class="wp2fa-reports-teaser-preview" aria-hidden="true">
							<img src="<?php echo \esc_url( WP_2FA_URL . 'includes/assets/images/reports-teaser-preview.png' ); ?>" alt="<?php echo \esc_attr__( 'Reports page preview', 'wp-2fa' ); ?>" />
						</div>
					</div>
				</div>
				<?php

				return;
			}

			?>
			<style>
				.features-wrap {
					background: #fff;
					padding: 25px 30px;
					margin-top: 25px;
				}

				.features-wrap h2 {
					font-size: 28px;
					margin-bottom: 30px;
				}

				.features-wrap p {
					font-size: 16px;
						line-height: 28px;
				}

				.feature-list {
					margin-bottom: 20px;
				}

				.feature-list li {
					margin-bottom: 10px;
					font-size: 15px;
				}

				.feature-list li .dashicons {
					color: #3E6BFF;
				}

				.premium-cta {
					margin: 25px 0 15px;
					text-align: center;
				}

				.premium-cta a:not(.inverse), .table-link {
					background-color: #3E6BFF;
					color: #fff;
					padding: 15px 26px;
					border-radius: 30px;
					font-size: 16px;
					white-space: nowrap;
					text-decoration: none;
					font-weight: 700;
					display: inline-block;
					margin-right: 15px;
					border: 2px solid #3E6BFF;
				}

				.premium-cta a:hover, .table-link:hover, .premium-cta a.inverse, .table-link.inverse {
					color: #3E6BFF;
					background-color: #fff;
				}

				.premium-cta a.inverse {
					font-weight: 700;
					text-decoration: none;
					font-size: 16px;
				}

				.content-block {
					margin-bottom: 26px;
					border-bottom: 1px solid #eee;
					padding-bottom: 15px;
				}

				.feature-table tr td {
					text-align: center;
					min-width: 200px
				}
				.feature-table tr td:first-of-type {
					text-align: left;
					font-weight: 500;
				}
				.feature-table td p {
					margin-top: 0;
				}
				.row-head span {
					font-size: 17px;
					font-weight: 700;
				}
				.feature-table .dashicons {
					color: #3E6BFF;
				}
				.feature-table .dashicons-no {
					color: red;
				}
				.table-link {
					font-size: 14px;
					padding: 9px;
					width: 193px;
					margin-top: 10px;
				}
				.pull-up {
					position: relative;
					top: -23px;
				}

				.wp2fa-logo {
					max-width: 130px;
				}

				.logo-wrap {
					float: left;
					margin-right: 30px;
				}
			</style>

			<div class="wrap help-wrap features-wrap wp-2fa-settings-wrapper">
				<div class="page-head">
					<h2><?php \esc_html_e( 'Upgrade to Premium and benefit more!', 'wp-2fa' ); ?></h2>
				</div>
				<div class="content-block">
					<div class="logo-wrap">
						<img class="wp2fa-logo" src="<?php echo WP_2FA_URL; // phpcs:ignore?>dist/images/wp-2fa-color_opt.png" alt="">
					</div>
					<div>
						<p><?php \esc_html_e( 'WP 2FA is your trusted gatekeeper, keeping your website, users, customers, team members, and anyone who accesses your website, including you, secure and better protected than ever before.', 'wp-2fa' ); ?></p>
						<p><?php \esc_html_e( 'Upgrade to WP 2FA Premium to add more secure authentication options and automate more, encouraging all your website users to utilize 2FA to its fullest extent and give your users more flexibility by allowing them to work from anywhere without compromising on security.', 'wp-2fa' ); ?></p>
					</div>
				</div>
				<div class="content-block">
					<p><strong><?php \esc_html_e( 'Upgrade to Premium and start benefiting from value-added features such as:', 'wp-2fa' ); ?></strong></p>
					<ul class="feature-list">
						<li><span class="dashicons dashicons-saved"></span> <?php \esc_html_e( 'More 2FA methods, including SMS, push notifications & one-click login', 'wp-2fa' ); ?></li>
						<li><span class="dashicons dashicons-saved"></span> <?php \esc_html_e( 'Trusted devices: Allow users to add trusted devices so they do not have to manually enter the 2FA code each time they log in', 'wp-2fa' ); ?></li>
						<li><span class="dashicons dashicons-saved"></span> <?php \esc_html_e( 'White labeling features: Gain increased trust by extending your business’ branding and tone of voice to all 2FA pages, wizards & emails', 'wp-2fa' ); ?></li>
						<li><span class="dashicons dashicons-saved"></span> <?php \esc_html_e( 'Refer to the features matrix below for a detailed list of all the premium features', 'wp-2fa' ); ?></li>
					</ul>
					<div class="premium-cta">
						<a href="<?php echo \esc_url( 'https://melapress.com/wordpress-2fa/pricing/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=upgrade_to_premium_banner_2' ); ?>" target="_blank" rel="noopener"><?php \esc_html_e( 'Upgrade to Premium', 'wp-2fa' ); ?></a>
					</div>		
				</div>
				<div class="content-block">
					<p><strong><?php \esc_html_e( 'WP 2FA plugin features', 'wp-2fa' ); ?></strong></p>
					<p><?php \esc_html_e( 'Take advantage of these benefits and many others, with prices starting from as little as $29 for 5 users per year. ', 'wp-2fa' ); ?></p>
					<table class="c21 feature-table">
						<tbody>
							<tr class="c2">
								<td class="c6" colspan="1" rowspan="1">
									<p class="c10 c4"><span class="c5"></span></p>
								</td>
								<td class="c8 row-head" colspan="1" rowspan="1">
									<p class="c7"><span class="c5"><?php \esc_html_e( 'Premium', 'wp-2fa' ); ?></span></p>
								</td>
								<td class="c12 row-head" colspan="1" rowspan="1">
									<p class="c7"><span class="c5"><?php \esc_html_e( 'Free', 'wp-2fa' ); ?></span></p>
								</td>
							</tr>
							<tr class="c2">
								<td class="c6" colspan="1" rowspan="1">
									<p class="c10"><span class="c5"><?php \esc_html_e( 'Support', 'wp-2fa' ); ?></span></p>
								</td>
								<td class="c8" colspan="1" rowspan="1">
									<p class="c7"><span class="c5"><?php \esc_html_e( '1-to-1 emails, forums', 'wp-2fa' ); ?></span></p>
								</td>
								<td class="c12" colspan="1" rowspan="1">
									<p class="c7"><span class="c5"><?php \esc_html_e( 'forums', 'wp-2fa' ); ?></span></p>
								</td>
							</tr>
							<tr class="c2">
								<td class="c6" colspan="1" rowspan="1">
									<p class="c10"><span class="c5"><?php \esc_html_e( 'Out of the box support for e-commerce, membership & third party plugins (no code required)', 'wp-2fa' ); ?></span></p>
								</td>
								<td class="c8" colspan="1" rowspan="1">
									<p class="c7"><span class="c5"><span class="dashicons dashicons-saved"></span></span></p>
								</td>
								<td class="c12" colspan="1" rowspan="1">
									<p class="c7"><span class="c5"><span class="dashicons dashicons-saved"></span></span></p>
								</td>
							</tr>
							<tr class="c2">
								<td class="c6" colspan="1" rowspan="1">
									<p class="c10"><span class="c5"><?php \esc_html_e( '2FA code via mobile app', 'wp-2fa' ); ?></span></p>
								</td>
								<td class="c8" colspan="1" rowspan="1">
									<p class="c7"><span class="c5"><span class="dashicons dashicons-saved"></span></span></p>
								</td>
								<td class="c12" colspan="1" rowspan="1">
									<p class="c7"><span class="c5"><span class="dashicons dashicons-saved"></span></span></p>
								</td>
							</tr>
							<tr class="c2">
								<td class="c6" colspan="1" rowspan="1">
									<p class="c10"><span class="c5"><?php \esc_html_e( '2FA code over email', 'wp-2fa' ); ?></span></p>
								</td>
								<td class="c8" colspan="1" rowspan="1">
									<p class="c7"><span class="c5"><span class="dashicons dashicons-saved"></span></span></p>
								</td>
								<td class="c12" colspan="1" rowspan="1">
									<p class="c7"><span class="c5"><span class="dashicons dashicons-saved"></span></span></p>
								</td>
							</tr>
							<tr class="c2">
								<td class="c6" colspan="1" rowspan="1">
									<p class="c10"><span class="c5"><?php \esc_html_e( '2FA login with hardware key (YubiKey)', 'wp-2fa' ); ?></span></p>
								</td>
								<td class="c8" colspan="1" rowspan="1">
									<p class="c7"><span class="c5"><span class="dashicons dashicons-saved"></span></span></p>
								</td>
								<td class="c12" colspan="1" rowspan="1">
									<p class="c7"><span class="c5"><span class="dashicons dashicons-no"></span></span></p>
								</td>
							</tr>
							<tr class="c2">
								<td class="c6" colspan="1" rowspan="1">
									<p class="c10"><span class="c5"><?php \esc_html_e( '2FA login with push notification (Authy)', 'wp-2fa' ); ?></span></p>
								</td>
								<td class="c8" colspan="1" rowspan="1">
									<p class="c7"><span class="c5"><span class="dashicons dashicons-saved"></span></span></p>
								</td>
								<td class="c12" colspan="1" rowspan="1">
									<p class="c7"><span class="c5"><span class="dashicons dashicons-no"></span></span></p>
								</td>
							</tr>
							<tr class="c2">
								<td class="c6" colspan="1" rowspan="1">
									<p class="c10"><span class="c5"><?php \esc_html_e( '2FA Login with SMS (with Twilio or Clickatell)', 'wp-2fa' ); ?></span></p>
								</td>
								<td class="c8" colspan="1" rowspan="1">
									<p class="c7"><span class="c5"><span class="dashicons dashicons-saved"></span></span></p>
								</td>
								<td class="c12" colspan="1" rowspan="1">
									<p class="c7"><span class="c5"><span class="dashicons dashicons-no"></span></span></p>
								</td>
							</tr>
							<tr class="c2">
								<td class="c6" colspan="1" rowspan="1">
									<p class="c10"><span class="c5"><?php \esc_html_e( 'Enable 2FA instantly with email – enroll all users automatically, no setup required.', 'wp-2fa' ); ?></span></p>
								</td>
								<td class="c8" colspan="1" rowspan="1">
									<p class="c7"><span class="c5"><span class="dashicons dashicons-saved"></span></span></p>
								</td>
								<td class="c12" colspan="1" rowspan="1">
									<p class="c7"><span class="c5"><span class="dashicons dashicons-no"></span></span></p>
								</td>
							</tr>
							<tr class="c2">
								<td class="c6" colspan="1" rowspan="1">
									<p class="c10"><span class="c5"><?php \esc_html_e( 'One-click 2FA login (via link in email)', 'wp-2fa' ); ?></span></p>
								</td>
								<td class="c8" colspan="1" rowspan="1">
									<p class="c7"><span class="c5"><span class="dashicons dashicons-saved"></span></span></p>
								</td>
								<td class="c12" colspan="1" rowspan="1">
									<p class="c7"><span class="c5"><span class="dashicons dashicons-no"></span></span></p>
								</td>
							</tr>
							<tr class="c2">
								<td class="c6" colspan="1" rowspan="1">
									<p class="c10"><span class="c5"><?php \esc_html_e( 'Allow next login without 2FA', 'wp-2fa' ); ?></span></p>
								</td>
								<td class="c8" colspan="1" rowspan="1">
									<p class="c7"><span class="c5"><span class="dashicons dashicons-saved"></span></span></p>
								</td>
								<td class="c12" colspan="1" rowspan="1">
									<p class="c7"><span class="c5"><span class="dashicons dashicons-no"></span></span></p>
								</td>
							</tr>
							<tr class="c2">
								<td class="c6" colspan="1" rowspan="1">
									<p class="c10"><span class="c5"><?php \esc_html_e( 'Different 2FA policies per user role', 'wp-2fa' ); ?></span></p>
								</td>
								<td class="c8" colspan="1" rowspan="1">
									<p class="c7"><span class="c5"><span class="dashicons dashicons-saved"></span></span></p>
								</td>
								<td class="c12" colspan="1" rowspan="1">
									<p class="c7"><span class="c5"><span class="dashicons dashicons-no"></span></span></p>
								</td>
							</tr>
							<tr class="c2">
								<td class="c6" colspan="1" rowspan="1">
									<p class="c10"><span class="c5"><?php \esc_html_e( 'Trusted devices (remember devices)', 'wp-2fa' ); ?></span></p>
								</td>
								<td class="c8" colspan="1" rowspan="1">
									<p class="c7"><span class="c5"><span class="dashicons dashicons-saved"></span></span></p>
								</td>
								<td class="c12" colspan="1" rowspan="1">
									<p class="c7"><span class="c5"><span class="dashicons dashicons-no"></span></span></p>
								</td>
							</tr>
							<tr class="c2">
								<td class="c6" colspan="1" rowspan="1">
									<p class="c10"><span class="c5"><?php \esc_html_e( 'Alternative 2FA methods', 'wp-2fa' ); ?></span></p>
								</td>
								<td class="c8" colspan="1" rowspan="1">
									<p class="c7"><span class="c5"><span class="dashicons dashicons-saved"></span></span></p>
								</td>
								<td class="c12" colspan="1" rowspan="1">
									<p class="c7"><span class="c5"><?php \esc_html_e( 'Backup codes only', 'wp-2fa' ); ?></span></p>
								</td>
							</tr>
							<tr class="c2">
								<td class="c6" colspan="1" rowspan="1">
									<p class="c10"><span class="c5"><?php \esc_html_e( 'White labeling (logo, wizards, email, colours, fonts & custom CSS)', 'wp-2fa' ); ?></span></p>
								</td>
								<td class="c8" colspan="1" rowspan="1">
									<p class="c7"><span class="c5"><span class="dashicons dashicons-saved"></span></span></p>
								</td>
								<td class="c12" colspan="1" rowspan="1">
									<p class="c7"><span class="c5"><span class="dashicons dashicons-no"></span></span></p>
								</td>
							</tr>
							<tr class="c2">
								<td class="c6" colspan="1" rowspan="1">
									<p class="c10"><span class="c5"><?php \esc_html_e( 'One-click 2FA integration in WooCommerce user page', 'wp-2fa' ); ?></span></p>
								</td>
								<td class="c8" colspan="1" rowspan="1">
									<p class="c7"><span class="c5"><span class="dashicons dashicons-saved"></span></span></p>
								</td>
								<td class="c12" colspan="1" rowspan="1">
									<p class="c7"><span class="c5"><span class="dashicons dashicons-no"></span></span></p>
								</td>
							</tr>
							<tr class="c2">
								<td class="c6" colspan="1" rowspan="1">
									<p class="c10"><span class="c5"><?php \esc_html_e( 'Reports & Statistics', 'wp-2fa' ); ?></span></p>
								</td>
								<td class="c8" colspan="1" rowspan="1">
									<p class="c7"><span class="c5"><span class="dashicons dashicons-saved"></span></span></p>
								</td>
								<td class="c12" colspan="1" rowspan="1">
									<p class="c7"><span class="c5"><span class="dashicons dashicons-no"></span></span></p>
								</td>
							</tr>
							<tr class="c2">
								<td class="c6" colspan="1" rowspan="1">
									<p class="c10"><span class="c5"><?php \esc_html_e( 'Configurable 2FA code expiration time', 'wp-2fa' ); ?></span></p>
								</td>
								<td class="c8" colspan="1" rowspan="1">
									<p class="c7"><span class="c5"><span class="dashicons dashicons-saved"></span></span></p>
								</td>
								<td class="c12" colspan="1" rowspan="1">
									<p class="c7"><span class="c5"><span class="dashicons dashicons-no"></span></span></p>
								</td>
							</tr>
							<tr class="c2">
								<td class="c6" colspan="1" rowspan="1">
									<p class="c10"><span class="c5"><?php \esc_html_e( 'Sortable users\' 2FA status', 'wp-2fa' ); ?></span></p>
								</td>
								<td class="c8" colspan="1" rowspan="1">
									<p class="c7"><span class="c5"><span class="dashicons dashicons-saved"></span></span></p>
								</td>
								<td class="c12" colspan="1" rowspan="1">
									<p class="c7"><span class="c5"><span class="dashicons dashicons-no"></span></span></p>
								</td>
							</tr>
							<tr class="c2">
								<td class="c6" colspan="1" rowspan="1">
									<p class="c10"><span class="c5"><?php \esc_html_e( 'Export/import plugin settings', 'wp-2fa' ); ?></span></p>
								</td>
								<td class="c8" colspan="1" rowspan="1">
									<p class="c7"><span class="c5"><span class="dashicons dashicons-saved"></span></span></p>
								</td>
								<td class="c12" colspan="1" rowspan="1">
									<p class="c7"><span class="c5"><span class="dashicons dashicons-no"></span></span></p>
								</td>
							</tr>
							<tr class="c2">
								<td class="c6" colspan="1" rowspan="1">
									<p class="c10"><span class="c5"><?php \esc_html_e( 'No Ads!', 'wp-2fa' ); ?></span></p>
								</td>
								<td class="c8" colspan="1" rowspan="1">
									<p class="c7"><span class="c5"><span class="dashicons dashicons-saved"></span></span></p>
								</td>
								<td class="c12" colspan="1" rowspan="1">
									<p class="c7"><span class="c5"><span class="dashicons dashicons-no"></span></span></p>
								</td>
							</tr>
						</tbody>
					</table>

					<div class="premium-cta">
						<a href="<?php echo \esc_url( 'https://melapress.com/wordpress-2fa/pricing/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=upgrade_to_premium_banner_3' ); ?>" target="_blank" rel="noopener"><?php \esc_html_e( 'Upgrade to Premium', 'wp-2fa' ); ?></a>
					</div>		
				</div>

				<div>
					<p>
					<?php
					$text = sprintf(
						/* translators: 1: Link to our site 2: Link to our contact page */
						\esc_html__( 'Visit the WP 2FA %1$s for more information or %2$s  with any questions you might have. We look forward to hearing from you.', 'wp-2fa' ),
						'<a target="_blank" href="' . \esc_url( 'https://melapress.com/wordpress-2fa/?&utm_source=plugin&utm_medium=wp2fa&utm_campaign=melapress_plugin_website' ) . '">' . \esc_html__( 'plugin website', 'wp-2fa' ) . '</a>',
						'<a target="_blank" href="' . \esc_url( 'https://melapress.com/contact/?&utm_source=plugin&utm_medium=wp2fa&utm_campaign=melapress_contact_us' ) . '">' . \esc_html__( 'contact us', 'wp-2fa' ) . '</a>'
					);

				echo $text; // phpcs:ignore -- Visit the WP 2FA plugin website for more information or contact us with any questions you might have. We look forward to hearing from you.
					?>
					</p>
				</div>
			</div>		
			<?php
		}

		/**
		 * Add "_blank" attr to pricing link to ensure it opens in new tab.
		 *
		 * @return void
		 *
		 * @since 2.8.0
		 */
		public static function pricing_new_tab_js() {
			?>
		<script type="text/javascript">
			jQuery( document ).ready( function() {
				jQuery( '.wp-2fa.pricing' ).parent().attr( 'target', '_blank' );
			});
		</script>
			<?php
		}
	}
}
