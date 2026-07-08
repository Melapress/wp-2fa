<?php
/**
 * Policies – Enforcement and exclusions sub-page template.
 *
 * @package wp-2fa
 */

use WP2FA\WP2FA;
use WP2FA\Admin\Settings_Builder;
use WP2FA\Admin\Helpers\WP_Helper;

$enforcement_policy = WP2FA::get_wp2fa_setting( 'enforcement-policy' );
$excluded_users     = array_filter( (array) WP2FA::get_wp2fa_setting( 'excluded_users' ) );
$excluded_roles     = array_filter( (array) WP2FA::get_wp2fa_setting( 'excluded_roles' ) );
$enforced_users     = array_filter( (array) WP2FA::get_wp2fa_setting( 'enforced_users' ) );
$enforced_roles     = array_filter( (array) WP2FA::get_wp2fa_setting( 'enforced_roles' ) );
$all_roles          = WP_Helper::get_roles_wp();

// Build items arrays with id/text pairs for the multi-select components.
$excluded_users_items = array_map(
	function ( $user ) {
		return array(
			'id'   => $user,
			'text' => $user,
		);
	},
	$excluded_users
);

$excluded_roles_items = array_map(
	function ( $role ) use ( $all_roles ) {
		return array(
			'id'   => $role,
			'text' => isset( $all_roles[ $role ] ) ? $all_roles[ $role ] : ucfirst( $role ),
		);
	},
	$excluded_roles
);

$enforced_users_items = array_map(
	function ( $user ) {
		return array(
			'id'   => $user,
			'text' => $user,
		);
	},
	$enforced_users
);

$enforced_roles_items = array_map(
	function ( $role ) use ( $all_roles ) {
		return array(
			'id'   => $role,
			'text' => isset( $all_roles[ $role ] ) ? $all_roles[ $role ] : ucfirst( $role ),
		);
	},
	$enforced_roles
);

// Build radio options – add multisite-specific choices when applicable.
$radio_options = array(
	'all-users' => \esc_html__( 'All users', 'wp-2fa' ),
);

$toggle_map = array(
	'all-users' => '',
);

if ( WP_Helper::is_multisite() ) {
	$radio_options['superadmins-only']            = \esc_html__( 'Only super admins', 'wp-2fa' );
	$radio_options['superadmins-siteadmins-only'] = \esc_html__( 'Only super admins and site admins', 'wp-2fa' );
	$toggle_map['superadmins-only']               = '';
	$toggle_map['superadmins-siteadmins-only']    = '';
}

$radio_options['certain-roles-only'] = \esc_html__( 'Only for specific users and roles', 'wp-2fa' );
$toggle_map['certain-roles-only']    = '#enforcement-specific-inputs';

if ( WP_Helper::is_multisite() ) {
	$radio_options['enforce-on-multisite'] = \esc_html__( 'These sub-sites', 'wp-2fa' );
	$toggle_map['enforce-on-multisite']    = '#enforcement-sites-inputs';
}

$radio_options['do-not-enforce'] = \esc_html__( 'Do not enforce on any users', 'wp-2fa' );
$toggle_map['do-not-enforce']    = '';

?>
<div class="settings-page" id="enforcement-exclusions-wrap">
	<?php
		Settings_Builder::build_option(
			array(
				'parent'       => \esc_html__( '2FA policies', 'wp-2fa' ),
				'type'         => 'breadcrumb',
				'custom_class' => 'back-policies-settings-main-wrapper',
				'default'      => \esc_html__( 'Enforcement and exclusions', 'wp-2fa' ),
			)
		);

		Settings_Builder::build_option(
			array(
				'title' => \esc_html__( 'Enforcement and exclusions', 'wp-2fa' ),
				'id'    => 'enforcement-exclusions-tab',
				'type'  => 'tab-title',
			)
		);
		?>

	<!-- 2FA Enforcement section -->
	<div class="settings-card">
		<?php
		Settings_Builder::build_option(
			array(
				'title' => \esc_html__( '2FA Enforcement', 'wp-2fa' ),
				'id'    => 'enforcement-section',
				'type'  => 'section-title',
			)
		);

		Settings_Builder::build_option(
			array(
				'text'  => sprintf(
					/* translators: %s: link to 2FA policies page */
					\esc_html__( 'When you enforce 2FA, users will be prompted to set it up at their next login. You can exclude specific roles or users below. To customize the setup grace period, visit the %s.', 'wp-2fa' ),
					'<a href="' . \esc_url( \admin_url( 'admin.php?page=wp-2fa-policies' ) ) . '#section=sitewide-policies&tab=user-access" class="wp2fa-policies-link">' . \esc_html__( '2FA policies page', 'wp-2fa' ) . '</a>'
				),
				'class' => 'description-settings-card',
				'id'    => 'enforcement-section-desc',
				'type'  => 'description',
			)
		);
		?>
		<script>
		document.addEventListener('click', function(e) {
			var link = e.target.closest('.wp2fa-policies-link');
			if (link) {
				e.preventDefault();
				window.location.href = link.getAttribute('href');
				window.location.reload();
			}
		});
		</script>
		<?php
		?>

		<div class="form-group settings-row">
			<?php
			Settings_Builder::build_option(
				array(
					'text' => \esc_html__( 'Enforce 2FA on', 'wp-2fa' ),
					'id'   => 'enforcement-policy-label',
					'type' => 'settings-label',
				)
			);

			Settings_Builder::build_option(
				array(
					'id'          => 'enforcement-policy',
					'type'        => 'radio',
					'option_name' => WP_2FA_POLICY_SETTINGS_NAME . '[enforcement-policy]',
					'default'     => $enforcement_policy,
					'options'     => $radio_options,
					'toggle'      => $toggle_map,
				)
			);
			?>

		</div>

			<!-- Enforced users / roles — shown only when "certain-roles-only" is selected -->
			<div id="enforcement-specific-inputs" class="enforcement-policy-options" style="display:none; padding-bottom: 20px;">
				<div class="form-group settings-row" style="margin-top:12px;">
					<?php
					Settings_Builder::build_option(
						array(
							'text' => \esc_html__( 'Users', 'wp-2fa' ),
							'id'   => 'enforced-users-label',
							'type' => 'settings-label',
						)
					);

					Settings_Builder::build_option(
						array(
							'id'          => 'enforced-users',
							'type'        => 'multi-select-ajax',
							'option_name' => WP_2FA_POLICY_SETTINGS_NAME . '[enforced_users]',
							'default'     => implode( ',', $enforced_users ),
							'placeholder' => \esc_attr__( 'Type to search users…', 'wp-2fa' ),
							'items'       => $enforced_users_items,
							'data_attrs'  => array(
								'search-type' => 'users',
								'min-chars'   => '2',
							),
						)
					);
					?>
				</div>

				<div class="form-group settings-row">
					<?php
					Settings_Builder::build_option(
						array(
							'text' => \esc_html__( 'Roles', 'wp-2fa' ),
							'id'   => 'enforced-roles-label',
							'type' => 'settings-label',
						)
					);

					Settings_Builder::build_option(
						array(
							'id'          => 'enforced-roles',
							'type'        => 'multi-select-ajax',
							'option_name' => WP_2FA_POLICY_SETTINGS_NAME . '[enforced_roles]',
							'default'     => implode( ',', $enforced_roles ),
							'placeholder' => \esc_attr__( 'Type to search roles…', 'wp-2fa' ),
							'items'       => $enforced_roles_items,
							'data_attrs'  => array(
								'search-type' => 'roles',
								'min-chars'   => '2',
							),
						)
					);
					?>
				</div>
			</div>

			<?php if ( WP_Helper::is_multisite() ) : ?>
			<!-- Sub-sites selection — shown only when "enforce-on-multisite" is selected -->
			<div id="enforcement-sites-inputs" class="enforcement-policy-options" style="display:none; padding-bottom: 20px;">
				<div class="form-group settings-row" style="margin-top:12px;">
					<?php
					$included_sites       = array_filter( (array) WP2FA::get_wp2fa_setting( 'included_sites' ) );
					$included_sites_items = array();
					foreach ( WP_Helper::get_multi_sites() as $site ) {
						if ( in_array( $site->blog_id, $included_sites, true ) || in_array( (string) $site->blog_id, $included_sites, true ) ) {
							$included_sites_items[] = array(
								'id'   => $site->blog_id,
								'text' => $site->blogname,
							);
						}
					}

					Settings_Builder::build_option(
						array(
							'text' => \esc_html__( 'Sites', 'wp-2fa' ),
							'id'   => 'enforced-sites-label',
							'type' => 'settings-label',
						)
					);

					Settings_Builder::build_option(
						array(
							'id'          => 'enforced-sites',
							'type'        => 'multi-select-ajax',
							'option_name' => WP_2FA_POLICY_SETTINGS_NAME . '[included_sites]',
							'default'     => implode( ',', $included_sites ),
							'placeholder' => \esc_attr__( 'Type to search sites…', 'wp-2fa' ),
							'items'       => $included_sites_items,
							'data_attrs'  => array(
								'search-type' => 'sites',
								'min-chars'   => '2',
							),
						)
					);
					?>
				</div>
			</div>
			<?php endif; ?>


	</div>

	<!-- 2FA Exclusions section -->
	<div class="settings-card">
		<?php
		Settings_Builder::build_option(
			array(
				'title' => \esc_html__( '2FA Exclusions', 'wp-2fa' ),
				'id'    => 'exclusions-section',
				'type'  => 'section-title',
			)
		);

		Settings_Builder::build_option(
			array(
				'text'  => \esc_html__( 'If you are enforcing 2FA on all users but for some reason you would like to exclude individual user(s) or users with a specific role, you can exclude them below.', 'wp-2fa' ),
				'class' => 'description-settings-card',
				'id'    => 'exclusions-section-desc',
				'type'  => 'description',
			)
		);
		?>

		<div class="form-group settings-row">
			<?php
			Settings_Builder::build_option(
				array(
					'text' => \esc_html__( 'Exclude the following users', 'wp-2fa' ),
					'id'   => 'excluded-users-label',
					'type' => 'settings-label',
				)
			);

			Settings_Builder::build_option(
				array(
					'id'          => 'excluded-users',
					'type'        => 'multi-select-ajax',
					'option_name' => WP_2FA_POLICY_SETTINGS_NAME . '[excluded_users]',
					'default'     => implode( ',', $excluded_users ),
					'placeholder' => \esc_attr__( 'Type to search users…', 'wp-2fa' ),
					'items'       => $excluded_users_items,
					'data_attrs'  => array(
						'search-type' => 'users',
						'min-chars'   => '2',
					),
				)
			);
			?>
		</div>

		<div class="form-group settings-row">
			<?php
			Settings_Builder::build_option(
				array(
					'text' => \esc_html__( 'Exclude the following roles', 'wp-2fa' ),
					'id'   => 'excluded-roles-label',
					'type' => 'settings-label',
				)
			);

			Settings_Builder::build_option(
				array(
					'id'          => 'excluded-roles',
					'type'        => 'multi-select-ajax',
					'option_name' => WP_2FA_POLICY_SETTINGS_NAME . '[excluded_roles]',
					'default'     => implode( ',', $excluded_roles ),
					'placeholder' => \esc_attr__( 'Type to search roles…', 'wp-2fa' ),
					'items'       => $excluded_roles_items,
					'data_attrs'  => array(
						'search-type' => 'roles',
						'min-chars'   => '2',
					),
				)
			);
			?>
		</div>
	</div>

	<?php if ( WP_Helper::is_multisite() ) : ?>
	<!-- Exclude Sites section (multisite only) -->
	<div class="settings-card">
		<?php
		Settings_Builder::build_option(
			array(
				'title' => \esc_html__( 'Exclude Sites', 'wp-2fa' ),
				'id'    => 'exclude-sites-section',
				'type'  => 'section-title',
			)
		);

		Settings_Builder::build_option(
			array(
				'text'  => \esc_html__( 'If you are enforcing 2FA on all users but for some reason you do not want to enforce it on a specific sub site, specify the sub site name below:', 'wp-2fa' ),
				'class' => 'description-settings-card',
				'id'    => 'exclude-sites-section-desc',
				'type'  => 'description',
			)
		);

		$excluded_sites       = array_filter( (array) WP2FA::get_wp2fa_setting( 'excluded_sites' ) );
		$excluded_sites_items = array();
		foreach ( WP_Helper::get_multi_sites() as $site ) {
			if ( in_array( $site->blog_id, $excluded_sites, true ) || in_array( (string) $site->blog_id, $excluded_sites, true ) ) {
				$excluded_sites_items[] = array(
					'id'   => $site->blog_id,
					'text' => $site->blogname,
				);
			}
		}
		?>

		<div class="form-group settings-row">
			<?php
			Settings_Builder::build_option(
				array(
					'text' => \esc_html__( 'Exclude the following sites', 'wp-2fa' ),
					'id'   => 'excluded-sites-label',
					'type' => 'settings-label',
				)
			);

			Settings_Builder::build_option(
				array(
					'id'          => 'excluded-sites',
					'type'        => 'multi-select-ajax',
					'option_name' => WP_2FA_POLICY_SETTINGS_NAME . '[excluded_sites]',
					'default'     => implode( ',', $excluded_sites ),
					'placeholder' => \esc_attr__( 'Type to search sites…', 'wp-2fa' ),
					'items'       => $excluded_sites_items,
					'data_attrs'  => array(
						'search-type' => 'sites',
						'min-chars'   => '2',
					),
				)
			);
			?>
		</div>
	</div>
	<?php endif; ?>
</div>
