<?php
/**
 * White labeling landing template.
 *
 * Lists the sub-sections available under White labeling:
 * - Emails & SMS Templates
 * - Customize 2FA code page
 * - Customize 2FA setup wizard
 *
 * @package wp-2fa
 *
 * @since 4.0.0
 */

use WP2FA\Admin\Settings_Builder;
use WP2FA\Admin\SettingsPages\Settings_Page_White_Labeling_New;
use WP2FA\Licensing\Licensing_Factory;

?>
<div class="general-settings-main-wrapper">
	<?php

	Settings_Builder::build_option(
		array(
			'title' => \esc_html__( 'White labeling', 'wp-2fa' ),
			'id'    => 'white-labeling-tab',
			'type'  => 'tab-title',
		)
	);

	Settings_Builder::build_option(
		array(
			'text' => \wp_sprintf(
				// translators: 1. Link to documentation, 2. Link to support.
				\esc_html__( 'Use the settings below to configure several 2FA settings on your website. If you have any questions, %1$s or %2$s.', 'wp-2fa' ),
				\wp_sprintf( '<a href="%s" target="_blank">%s</a>', 'https://melapress.com/support/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=white_labeling_doc', \esc_html__( 'visit documentation', 'wp-2fa' ) ),
				\wp_sprintf( '<a href="%s" target="_blank">%s</a>', 'https://melapress.com/support/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=white_labeling_support', \esc_html__( 'contact support', 'wp-2fa' ) )
			),
			'id'   => 'white-labeling-tab',
			'type' => 'description',
		)
	);
	?>

	<div class="settings-card">
		<div class="settings-group">
			<?php
			$white_labeling_tabs = Settings_Page_White_Labeling_New::collect_tabs();

			Settings_Builder::build_option(
				array(
					'values' => $white_labeling_tabs,
					'id'     => 'white-labeling-wrap-list',
					'type'   => 'list',
					'class'  => 'settings-list',
				)
			);

			$is_enterprise = Licensing_Factory::has_active_valid_license() && ( Licensing_Factory::provider_call( 'is_plan_or_trial__premium_only', 'business', true ) || Licensing_Factory::provider_call( 'is_plan_or_trial__premium_only', 'ent', true ) || Licensing_Factory::provider_call( 'is_plan_or_trial__premium_only', 'enterprise', true ) );
			if ( ! $is_enterprise ) :
				$enterprise_badge = Settings_Builder::get_enterprise_badge_html();
				$locked_items  = array(
					\esc_html__( 'Customize 2FA setup wizard', 'wp-2fa' )                    => 'wl-setup-wizard',
					\esc_html__( 'Customize 2FA user prompts and notifications', 'wp-2fa' ) => 'wl-prompts',
					\esc_html__( 'Customize 2FA user profile page area', 'wp-2fa' )         => 'wl-profile',
				);
			?>
			<ul class="wp-2fa-list-options settings-list wp2fa-premium-settings-list">
				<?php foreach ( $locked_items as $item_title => $item_context ) : ?>
				<li class="wp2fa-premium-settings-item">
					<?php echo '' !== $item_context ? Settings_Builder::get_enterprise_badge_html( $item_context ) : $enterprise_badge; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<span class="option-value"><?php echo \esc_html( $item_title ); ?></span>
				</li>
				<?php endforeach; ?>
			</ul>
			<?php endif; ?>
		</div>
	</div>
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
