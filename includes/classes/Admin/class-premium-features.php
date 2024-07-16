<?php
/**
 * Premium features rendering class.
 *
 * @package    wp2fa
 * @subpackage admin
 *
 * @copyright  2024 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 *
 * @see       https://wordpress.org/plugins/wp-2fa/
 * @since      2.0.0
 */

declare(strict_types=1);

namespace WP2FA\Admin;

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
			$banner  = '<div id="wp-2fa-side-banner">';
			$banner .= '<img src="' . \esc_url( WP_2FA_URL . 'dist/images/wizard-logo.png' ) . '">';
			$banner .= '<p>' . \esc_html__( 'Upgrade to Premium & benefit:', 'wp-2fa' ) . '</p>';
			$banner .= '<ul><li><span class="dashicons dashicons-yes-alt"></span>' . \esc_html__( 'Login with 2FA via SMS, push notification or with a simple mouse click', 'wp-2fa' ) . '</li>';
			$banner .= '<li><span class="dashicons dashicons-yes-alt"></span>' . \esc_html__( 'Add & manage trusted devices ("Remember this device" option)', 'wp-2fa' ) . '</li>';
			$banner .= '<li><span class="dashicons dashicons-yes-alt"></span> ' . \esc_html__( 'Add alternative 2FA methods ensuring no user is ever locked out', 'wp-2fa' ) . '</li>';
			$banner .= '<li><span class="dashicons dashicons-yes-alt"></span> ' . \esc_html__( 'One-click 2FA integration with WooCommerce', 'wp-2fa' ) . '</li>';
			$banner .= '<li><span class="dashicons dashicons-yes-alt"></span> ' . \esc_html__( 'Completely whitelabel the 2FA user experience including the 2FA code page, email & wizards text', 'wp-2fa' ) . '</li>';
			$banner .= '<li><span class="dashicons dashicons-yes-alt"></span> ' . \esc_html__( 'Configure different 2FA policies for different user roles', 'wp-2fa' ) . '</li>';
			$banner .= '<li><span class="dashicons dashicons-yes-alt"></span> ' . \esc_html__( 'Many other features', 'wp-2fa' ) . '</li>';
			$banner .= '<li><span class="dashicons dashicons-yes-alt"></span> ' . \esc_html__( 'No Ads!', 'wp-2fa' ) . '</li></ul>';
			$banner .= '<a href="https://melapress.com/wordpress-2fa/pricing/?utm_source=plugin&utm_medium=link&utm_campaign=wp2fa" class="button button-primary" target="_blank">' . \esc_html__( 'Upgrade to Premium', 'wp-2fa' ) . '</a>';
			$banner .= '</div>';

			echo $banner; // phpcs:ignore
		}

		/**
		 * Handles rendering the content.
		 *
		 * @return void
		 *
		 * @since 2.8.0
		 */
		public static function render() {
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
						<a href="<?php echo \esc_url( 'https://melapress.com/wordpress-2fa/pricing/?utm_source=plugin&utm_medium=link&utm_campaign=wp2fa' ); ?>" target="_blank" rel="noopener"><?php \esc_html_e( 'Upgrade to Premium', 'wp-2fa' ); ?></a>
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
						<a href="<?php echo \esc_url( 'https://melapress.com/wordpress-2fa/pricing/?utm_source=plugin&utm_medium=link&utm_campaign=wp2fa' ); ?>" target="_blank" rel="noopener"><?php \esc_html_e( 'Upgrade to Premium', 'wp-2fa' ); ?></a>
					</div>		
				</div>

				<div>
					<p>
					<?php
					$text = sprintf(
						/* translators: 1: Link to our site 2: Link to our contact page */
						\esc_html__( 'Visit the WP 2FA %1$s for more information or %2$s  with any questions you might have. We look forward to hearing from you.', 'wp-2fa' ),
						'<a target="_blank" href="' . \esc_url( 'https://melapress.com/wordpress-2fa/?&utm_source=plugin&utm_medium=link&utm_campaign=wp2fa' ) . '">' . \esc_html__( 'plugin website', 'wp-2fa' ) . '</a>',
						'<a target="_blank" href="' . \esc_url( 'https://melapress.com/contact/?&utm_source=plugin&utm_medium=link&utm_campaign=wp2fa' ) . '">' . \esc_html__( 'contact us', 'wp-2fa' ) . '</a>'
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
