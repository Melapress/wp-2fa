<?php
/**
 * Policies – Sitewide 2FA policies sub-page template.
 *
 * Thin wrapper that sets the shared partial context for sitewide policies
 * (no role) and includes _policy-fields.php for the actual form fields.
 *
 * @package wp-2fa
 */

use WP2FA\Admin\Settings_Builder;

// Shared partial variables: sitewide = no role.
$policy_role = '';
$id_suffix   = '';
$name_prefix = WP_2FA_POLICY_SETTINGS_NAME;

?>
<div class="settings-page" id="sitewide-policies-wrap">

	<?php
	Settings_Builder::build_option(
		array(
			'parent'       => \esc_html__( '2FA policies', 'wp-2fa' ),
			'type'         => 'breadcrumb',
			'custom_class' => 'back-policies-settings-main-wrapper',
			'default'      => \esc_html__( 'Sitewide 2FA policies', 'wp-2fa' ),
		)
	);

	Settings_Builder::build_option(
		array(
			'title' => \esc_html__( 'Sitewide 2FA policies', 'wp-2fa' ),
			'id'    => 'sitewide-policies-tab',
			'type'  => 'tab-title',
		)
	);

	// Include shared policy fields partial.
	require __DIR__ . '/_policy-fields.php';
	?>

</div>
