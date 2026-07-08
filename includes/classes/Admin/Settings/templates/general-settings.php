<?php
/**
 * Settings Template
 *
 * @package wp-2fa
 */

use WP2FA\Admin\Settings_Builder;
use WP2FA\Admin\Settings_Page;
use WP2FA\Admin\SettingsPages\Settings_Page_New;
use WP2FA\Licensing\Licensing_Factory;

?>
<div class="general-settings-main-wrapper">
	<?php

	Settings_Builder::build_option(
		array(
			'title' => \esc_html__( 'Settings', 'wp-2fa' ),
			'id'    => 'general-settings-tab',
			'type'  => 'tab-title',
		)
	);

	Settings_Builder::build_option(
		array(
			'text' => \wp_sprintf(
				// translators: 1. Link to documentation, 2. Link to support.
				\esc_html__( 'Use the settings below to configure several 2FA settings on your website. If you have any questions, %1$s or %2$s.', 'wp-2fa' ),
				\wp_sprintf( '<a href="%s" target="_blank">%s</a>', 'https://melapress.com/support/kb/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=settings_page_doc', \esc_html__( 'visit documentation', 'wp-2fa' ) ),
				\wp_sprintf( '<a href="%s" target="_blank">%s</a>', 'https://melapress.com/support/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=general_settings_support', \esc_html__( 'contact support', 'wp-2fa' ) )
			),
			'id'   => 'general-settings-tab',
			'type' => 'description',
		)
	);
	?>

	<div class="settings-card">
		<div class="settings-group">
			<div class="group-title" style="display:none">
				<?php
				Settings_Builder::build_option(
					array(
						'text' => \esc_html__( 'General Settings', 'wp-2fa' ),
						'id'   => 'general-settings-group-title',
						'type' => 'simple-text',
					)
				);
				?>
			</div>
			<?php
				$general_settings = \apply_filters( WP_2FA_PREFIX . 'general_settings_group', Settings_Page_New::register_general_settings( array() ) );

				Settings_Builder::build_option(
					array(
						'values' => $general_settings,
						'id'     => 'general-settings-wrap-list',
						'type'   => 'list',
						'class'  => 'settings-list',
					)
				);
				?>
		</div>

		<?php if ( ! Settings_Page::is_new_interface_enabled() ) : ?>
		<div class="settings-group">
			<div class="group-title">
				<?php
				Settings_Builder::build_option(
					array(
						'text' => \esc_html__( 'White Labelling', 'wp-2fa' ),
						'id'   => 'white-labelling-group-title',
						'type' => 'simple-text',
					)
				);
				?>
			</div>
			<?php
				$white_labelling_settings = \apply_filters( WP_2FA_PREFIX . 'white_labelling_settings_group', Settings_Page_New::register_white_labelling_settings( array() ) );

				Settings_Builder::build_option(
					array(
						'values' => $white_labelling_settings,
						'id'     => 'white-labelling-wrap-list',
						'type'   => 'list',
						'class'  => 'settings-list',
					)
				);
				?>
		</div>
		<?php endif; ?>
	</div>

	<?php if ( ! Licensing_Factory::has_active_valid_license() ) :
		$panel_items = array(
			array(
				'title'   => \esc_html__( '2FA provider integrations', 'wp-2fa' ),
				'context' => 'provider-integrations',
			),
			array(
				'title'   => \esc_html__( 'Plugin integrations', 'wp-2fa' ),
				'context' => 'plugin-integrations',
			),
			array(
				'title'   => \esc_html__( 'Export / import', 'wp-2fa' ),
				'context' => 'export-import',
			),
		);

		$panel_banner_title  = \esc_html__( 'Unlock all settings in Premium', 'wp-2fa' );
		$panel_banner_desc   = \esc_html__( 'Enjoy unlimited flexibility and control with advanced settings', 'wp-2fa' );
		$panel_banner_cta    = \esc_html__( 'Upgrade to Premium', 'wp-2fa' );
		$panel_banner_url    = 'https://melapress.com/wordpress-2fa/pricing/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=free-settings-page';
		$panel_visible_count = 3;
		$panel_id            = 'wp2fa-locked-panel-settings-new';
		$panel_banner_badge_context = 'settings-banner';

		include \WP_2FA_PATH . 'includes/classes/Admin/Settings/templates/partials/premium-locked-panel.php';
	endif; ?>
</div>
<script>
	// handle navigation when clicking a settings list item
	// extract span id and show corresponding '-wrap' section

	document.addEventListener('DOMContentLoaded', function() {
		var container = document.querySelector('.general-settings-main-wrapper');
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
					if ( backButtons ) {
						backButtons.forEach( function(backButton) {
						backButton.addEventListener('click', function(e) {
							e.preventDefault();
							container.style.display = 'block';
							var items = container.querySelectorAll('.settings-group li');
							items.forEach(function(li) {
								var span = li.querySelector('span');
								if (span && span.id) {
									var wrapId = span.id + '-wrap';
									var wrap = document.getElementById(wrapId);
									if (wrap) {
										wrap.style.display = 'none';
									}
								}
							});
						});
						} );
					}
				}
				container.style.display = 'none';
			});
		});


	});

	function toggleProvider(element) {
		const item = element.closest('.provider-item');
		item.classList.toggle('open');
	}

</script>

