<?php // phpcs:ignore

namespace WP2FA\Admin;

use \WP2FA\WP2FA as WP2FA;

/**
 * PremiumFeatures - Handles contact the features page and content.
 */
class PremiumFeatures {

	const TOP_MENU_SLUG = 'wp-2fa-premium-features';

	/**
	 * Create admin menu entry and settings page
	 */
	public function add_extra_menu_item() {
		add_submenu_page(
			'wp-2fa-policies',
			esc_html__( 'Premium Features', 'wp-2fa' ),
			esc_html__( 'Premium Features ➤', 'wp-2fa' ),
			'manage_options',
			self::TOP_MENU_SLUG,
			array( $this, 'render' ),
			100
		);
	}

	/**
	 * Handles rendering the content.
	 *
	 * @return void
	 */
	public function render() {
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

			.premium-cta a, .table-link {
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
				<h2><?php esc_html_e( 'Upgrade to Premium to benefit more!', 'wp-2fa' ); ?></h2>
			</div>
			<div class="content-block">
				<div class="logo-wrap">
					<img class="wp2fa-logo" src="<?php echo WP_2FA_URL; ?>dist/images/wp-2fa-color_opt.png" alt="">
				</div>
				<div>
					<p><?php esc_html_e( 'WP 2FA is your trusted gatekeeper, keeping your website, users, customers, team members, and you secure and better protected than ever before. We thank you for the continued trust you show in our plugin.', 'wp-2fa' ); ?></p>
					<p><?php esc_html_e( 'Upgrade to WP 2FA Premium to automate more, encouraging users to utilize 2FA to its fullest extent and give your users more flexibility by allowing them to work from pretty much anywhere without compromising on security.', 'wp-2fa' ); ?></p>
				</div>
			</div>
			<div class="content-block">
				<p><?php esc_html_e( 'Upgrade to Premium today to start benefiting from value-added features such as:', 'wp-2fa' ); ?></p>
				<ul class="feature-list">
					<li><span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Trusted devices: Give users the option to add trusted devices so they do not have to enter the 2FA code each time they log in', 'wp-2fa' ); ?></li>
					<li><span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'White labeling tools & features: Gain increased trust by extending your business\' branding and tone of voice to all 2FA pages', 'wp-2fa' ); ?></li>
					<li><span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Configure different policies for different user roles: While requiring everyone to use 2FA is generally a good idea, stricter policies for more sensitive accounts can help you keep everyone happy', 'wp-2fa' ); ?></li>
				</ul>
				<div class="premium-cta">
					<a href="<?php echo esc_url( 'https://wp2fa.io/pricing/?utm_source=plugin&utm_medium=referral&utm_campaign=WP2FA&utm_content=upgrade+page+upgrade' ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'UPGRADE NOW', 'wp-2fa' ); ?></a>
					<a class="inverse" href="<?php echo esc_url( 'https://wp2fa.io/get-wp-2fa-premium-trial/?utm_source=plugin&utm_medium=referral&utm_campaign=WP2FA' ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Get the Free 14-day trial', 'wp-2fa' ); ?></a>
				</div>			
			</div>

			<div class="content-block">
				<p><?php esc_html_e( 'Take advantage of these benefits and many others, with prices starting from as little as $59 for 5 users per year. Below is the complete list of Premium features:', 'wp-2fa' ); ?></p>
				<table class="c21 feature-table">
					<tbody>
						<tr class="c2">
							<td class="c6" colspan="1" rowspan="1">
								<p class="c10 c4"><span class="c5"></span></p>
							</td>
							<td class="c8 row-head" colspan="1" rowspan="1">
								<p class="c7"><span class="c5"><?php esc_html_e( 'Premium', 'wp-2fa' ); ?></span></p>
							</td>
							<td class="c12 row-head" colspan="1" rowspan="1">
								<p class="c7"><span class="c5"><?php esc_html_e( 'Free', 'wp-2fa' ); ?></span></p>
							</td>
						</tr>
						<tr class="c2">
							<td class="c6" colspan="1" rowspan="1">
								<p class="c10"><span class="c5"><?php esc_html_e( 'Support', 'wp-2fa' ); ?></span></p>
							</td>
							<td class="c8" colspan="1" rowspan="1">
								<p class="c7"><span class="c5"><?php esc_html_e( 'Email & forums', 'wp-2fa' ); ?></span></p>
							</td>
							<td class="c12" colspan="1" rowspan="1">
								<p class="c7"><span class="c5"><?php esc_html_e( 'Forums', 'wp-2fa' ); ?></span></p>
							</td>
						</tr>
						<tr class="c2">
							<td class="c6" colspan="1" rowspan="1">
								<p class="c10"><span class="c5"><?php esc_html_e( 'Install on unlimited websites', 'wp-2fa' ); ?></span></p>
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
								<p class="c10"><span class="c5"><?php esc_html_e( 'E-commerce, membership & other third party plugins support', 'wp-2fa' ); ?></span></p>
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
								<p class="c10"><span class="c5"><?php esc_html_e( '2FA code via mobile app', 'wp-2fa' ); ?></span></p>
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
								<p class="c10"><span class="c5"><?php esc_html_e( '2FA code over email', 'wp-2fa' ); ?></span></p>
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
								<p class="c10"><span class="c5"><?php esc_html_e( 'Backup codes', 'wp-2fa' ); ?></span></p>
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
								<p class="c10"><span class="c5"><?php esc_html_e( 'One-click 2FA login', 'wp-2fa' ); ?></span></p>
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
								<p class="c10"><span class="c5"><?php esc_html_e( 'Different 2FA policies per user role', 'wp-2fa' ); ?></span></p>
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
								<p class="c10"><span class="c5"><?php esc_html_e( 'Trusted devices (don\'t ask for 2FA code)', 'wp-2fa' ); ?></span></p>
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
								<p class="c10"><span class="c5"><?php esc_html_e( 'Secondary 2FA backup method', 'wp-2fa' ); ?></span></p>
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
								<p class="c10"><span class="c5"><?php esc_html_e( 'White labeling (logo, text, colors & fonts)', 'wp-2fa' ); ?></span></p>
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
								<p class="c10"><span class="c5"><?php esc_html_e( 'Reports & Statistics', 'wp-2fa' ); ?></span></p>
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
								<p class="c10"><span class="c5"><?php esc_html_e( 'Configurable 2FA code expiration time', 'wp-2fa' ); ?></span></p>
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
								<p class="c10"><span class="c5"><?php esc_html_e( 'Sortable users\' 2FA status', 'wp-2fa' ); ?></span></p>
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
					<a href="<?php echo esc_url( 'https://wp2fa.io/pricing/?utm_source=plugin&utm_medium=referral&utm_campaign=WP2FA&utm_content=upgrade+page+upgrade' ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'UPGRADE NOW', 'wp-2fa' ); ?></a>
					<a class="inverse" href="<?php echo esc_url( 'https://wp2fa.io/get-wp-2fa-premium-trial/?utm_source=plugin&utm_medium=referral&utm_campaign=WP2FA' ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Get the Free 14-day trial', 'wp-2fa' ); ?></a>
				</div>		
			</div>
					
			<div>
				<p><?php 
				$text = sprintf(
						esc_html__( 'For more information about the WP 2FA, please %1$s. If you have any questions about the plugin or would like to ask us anything, please %2$s', 'wp-2fa' ),
						'<a target="_blank" href="'. esc_url( 'https://wp2fa.io' ) . '">' . esc_html__( 'visit our website', 'wp-2fa' ) . '</a>',
						'<a target="_blank" href="'. esc_url(  'https://wp2fa.io/contact/') . '">' . esc_html__( 'our contact form', 'wp-2fa' ) . '</a>.'
				);

				echo $text;
				?></p>
			</div>
		</div>		
		<?php
	}
}
