<?php
/**
 * Policies – Per-role policy sub-page template.
 *
 * Includes a master "enable role-based policies" toggle, then reuses the
 * shared _policy-fields.php partial so every setting is defined only once.
 *
 * Expects $role_slug and $role_name to be set before include.
 *
 * @package wp-2fa
 *
 * @since 3.1.2
 */

use WP2FA\Utils\Settings_Utils;
use WP2FA\Admin\Settings_Builder;

if ( ! isset( $role_slug, $role_name ) ) {
	return;
}

// Is role-based policy enabled for this role?
$role_enabled = ( 'enable_role' === Settings_Utils::get_setting_role( $role_slug, 'enable_role_based_policies' ) );

// Shared partial variables.
$policy_role = $role_slug;
$id_suffix   = '-' . \esc_attr( $role_slug );
$name_prefix = WP_2FA_POLICY_SETTINGS_NAME . '[' . \esc_attr( $role_slug ) . ']';

?>
<div class="settings-page" id="role-<?php echo \esc_attr( $role_slug ); ?>-wrap">

	<?php
	/*
	 * Hidden sentinel field — always submitted (never inside the toggle-target
	 * container that disables its children).  Lets the save handler distinguish
	 * "role template was on the page but toggle was unchecked" from "role
	 * template was not rendered at all".
	 */
	?>
	<input type="hidden"
		name="<?php echo \esc_attr( $name_prefix ); ?>[_rendered]"
		value="1" />

	<?php
	Settings_Builder::build_option(
		array(
			'parent'       => \esc_html__( '2FA policies', 'wp-2fa' ),
			'type'         => 'breadcrumb',
			'custom_class' => 'back-policies-settings-main-wrapper',
			'default'      => $role_name,
		)
	);

	Settings_Builder::build_option(
		array(
			'title' => \wp_sprintf(
						/* translators: %s: role display name */
				\esc_html__( 'Customize policies for %s', 'wp-2fa' ),
				\esc_html( $role_name )
			),
			'id'    => 'role-' . $role_slug . '-tab',
			'type'  => 'tab-title',
		)
	);
	?>

	<!-- Master switch: enable role-specific policies -->
	<!-- <div class="settings-card"> -->
		<!-- <div class="form-group settings-row"> -->
			<div style="margin-top:30px">
			<?php
			Settings_Builder::build_option(
				array(
					'id'          => 'enable-role-policies-' . $role_slug,
					'type'        => 'toggle-checkbox',
					'option_name' => $name_prefix . '[enable_role_based_policies]',
					// 'not_bool'    => true,
					// 'not_id'    => true,
					'value'       => 'enable_role',
					'default'     => $role_enabled,
					'text'        => '<strong>' . \wp_sprintf(
						/* translators: %s: role display name */
						\esc_html__( 'Enable custom policies for this role', 'wp-2fa' ),
						\esc_html( $role_name )
					) . '</strong>',
					'data_attrs'  => array( 'toggle-target' => '#role-' . \esc_attr( $role_slug ) . '-fields' ),
				)
			);
			?>
			<!-- </div>
			<div class="form-group settings-row"> -->
			<?php
			// Settings_Builder::build_option(
			// array(
			// 'text'  => \wp_sprintf(
			// * translators: %s: role display name */
			// \esc_html__( 'When enabled, the below settings override the sitewide policies for users with the %s role.', 'wp-2fa' ),
			// '<strong>' . \esc_html( $role_name ) . '</strong>'
			// ),
			// 'class' => 'description-settings-card',
			// 'id'    => 'role-' . $role_slug . '-desc',
			// 'type'  => 'description',
			// )
			// );
			?>
		<!-- </div> -->
	<!-- </div> -->
	</div>

	<!-- Role-specific policy fields (shown/hidden by toggle) -->
	<div id="role-<?php echo \esc_attr( $role_slug ); ?>-fields" class="role-policy-fields"<?php echo $role_enabled ? '' : ' style="display:none;"'; ?>>
		<?php
		// Include shared policy fields partial.
		require __DIR__ . '/_policy-fields.php';
		?>
	</div>

</div>
