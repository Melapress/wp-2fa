<?php
/**
 * Policies Settings – main landing template.
 *
 * Renders the two groups (Global and Customize Per User Role) as clickable
 * lists that navigate into the individual policy sub-pages.
 *
 * @package wp-2fa
 */

use WP2FA\Admin\Settings_Builder;
use WP2FA\Admin\Helpers\WP_Helper;
use WP2FA\Licensing\Licensing_Factory;
use WP2FA\Admin\SettingsPages\Settings_Page_Policies_New;

?>
<div class="policies-settings-main-wrapper">
	<?php

	Settings_Builder::build_option(
		array(
			'title' => \esc_html__( '2FA policies', 'wp-2fa' ),
			'id'    => 'policies-settings-tab',
			'type'  => 'tab-title',
		)
	);

	Settings_Builder::build_option(
		array(
			'text' => \wp_sprintf(
				// translators: 1. Link to documentation, 2. Link to support.
				\esc_html__( 'Use the settings below to configure the properties of the two-factor authentication on your website and how users use it. If you have any questions, %1$s or %2$s.', 'wp-2fa' ),
				\wp_sprintf( '<a href="%s" target="_blank">%s</a>', 'https://melapress.com/support/kb/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=general_settings_doc', \esc_html__( 'visit documentation', 'wp-2fa' ) ),
				\wp_sprintf( '<a href="%s" target="_blank">%s</a>', 'https://melapress.com/support/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=policies_settings_support', \esc_html__( 'contact support', 'wp-2fa' ) )
			),
			'id'   => 'policies-settings-tab',
			'type' => 'description',
		)
	);
	?>

	<div class="settings-card">
		<div class="settings-group">
			<div class="group-title">
				<?php
				Settings_Builder::build_option(
					array(
						'text' => \esc_html__( 'Global', 'wp-2fa' ),
						'id'   => 'global-policies-group-title',
						'type' => 'simple-text',
					)
				);
				?>
			</div>
			<?php
			$global_policies = Settings_Page_Policies_New::get_global_policy_tabs();

			Settings_Builder::build_option(
				array(
					'values' => $global_policies,
					'id'     => 'global-policies-wrap-list',
					'type'   => 'list',
					'class'  => 'settings-list',
				)
			);
			?>
		</div>

		<?php
			?>
	</div>
<?php
			$role_policies = Settings_Page_Policies_New::get_role_policy_tabs();

			// Build items array for the reusable locked panel component.
			$panel_items = array();
			foreach ( $role_policies as $role_tab ) {
				$panel_items[] = array(
					'title'   => $role_tab['title'],
				);
			}

			$panel_banner_title  = \esc_html__( 'Set custom policies for each role in Premium', 'wp-2fa' );
			$panel_banner_desc   = \esc_html__( 'Different teams, different rules. Set per-role 2FA requirements', 'wp-2fa' );
			$panel_banner_cta    = \esc_html__( 'Upgrade to Premium', 'wp-2fa' );
			$panel_banner_url    = 'https://melapress.com/wordpress-2fa/pricing/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=per-role-policies';
			$panel_visible_count = 3;
			$panel_lock_icons_disabled    = true;
			$panel_banner_badge_context   = 'policies-banner';

			include \WP_2FA_PATH . 'includes/classes/Admin/Settings/templates/partials/premium-locked-panel.php';
		?>
</div>
<script>
	document.addEventListener('DOMContentLoaded', function() {
		var container = document.querySelector('.policies-settings-main-wrapper');
		if (!container) {
			return;
		}

		var items = container.querySelectorAll('.settings-group li');
		items.forEach(function(li) {
			li.addEventListener('click', function() {
				var span = li.querySelector('span');
				if (!span || !span.id) {
					return;
				}
				var targetId = span.id + '-wrap';
				var target = document.getElementById(targetId);
				if (target) {
					target.style.display = 'block';

					var backButtons = target.querySelectorAll('.back-click');
					if (backButtons) {
						backButtons.forEach(function(backButton) {
							backButton.addEventListener('click', function(e) {
								e.preventDefault();
								container.style.display = 'block';
								var allItems = container.querySelectorAll('.settings-group li');
								allItems.forEach(function(innerLi) {
									var innerSpan = innerLi.querySelector('span');
									if (innerSpan && innerSpan.id) {
										var wrapId = innerSpan.id + '-wrap';
										var wrap = document.getElementById(wrapId);
										if (wrap) {
											wrap.style.display = 'none';
										}
									}
								});
							});
						});
					}
				}
				container.style.display = 'none';
			});
		});
	});
</script>
