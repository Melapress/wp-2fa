<?php
/**
 * Settings Template
 *
 * @package wp-2fa
 */

use WP2FA\Admin\Settings_Page;
use WP2FA\Admin\Settings_Builder;
use WP2FA\Admin\Helpers\SMS_Templates;
use WP2FA\Licensing\Licensing_Factory;
use WP2FA\Admin\Helpers\Email_Templates;

?>
	<div class="settings-page" id="email-settings-wrap">

				<?php
				Settings_Builder::build_option(
					array(
						'parent'       => \esc_html__( 'White labeling', 'wp-2fa' ),
						'type'         => 'breadcrumb',
						'custom_class' => 'back-policies-settings-main-wrapper',
						'default'      => \esc_html__( 'Customize emails & SMS templates', 'wp-2fa' ),
					)
				);
				Settings_Builder::build_option(
					array(
						'title' => \esc_html__( 'Customize emails & SMS templates', 'wp-2fa' ),
						'id'    => 'email-settings-tab',
						'type'  => 'tab-title',
					)
				);

				Settings_Builder::build_option(
					array(
						'text'  => \wp_sprintf(
						// translators: 1. Link to documentation, 2. Link to support.
							\esc_html__( 'Customize the Emails & SMS Templates that are sent to users. %1$s.', 'wp-2fa' ),
							\wp_sprintf( '<a href="%s" target="_blank">%s</a>', 'https://melapress.com/support/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=email_settings_doc', \esc_html__( 'Learn more', 'wp-2fa' ) )
						),
						'class' => 'description-settings-card',
						'id'    => 'email-settings-tab',
						'type'  => 'description',
					)
				);

				$vals = array(
					array(
						'id'    => 'settings',
						'title' => \esc_html__( 'Email settings', 'wp-2fa' ),
					),
				);

				// @free:start
				$vals[] = array(
					'id'              => 'templates',
					'title'           => \esc_html__( 'Email templates', 'wp-2fa' ),
					'locked'          => true,
					'premium_context' => 'wp-2fa-white-labeling:email-templates',
				);
				$vals[] = array(
					'id'              => 'sms',
					'title'           => \esc_html__( 'SMS templates', 'wp-2fa' ),
					'locked'          => true,
					'premium_context' => 'wp-2fa-white-labeling:sms-templates',
				);
				// @free:end


				Settings_Builder::build_option(
					array(
						'class'       => 'description-settings-card',
						'id'          => 'email-settings-tab',
						'type'        => 'tabbed-navigation',
						'option_name' => 'email-settings-tabs',
						'values'      => $vals,
					)
				);
				?>

			
		<!-- ══════════════════════════════════════════════════════
			TAB 1 — Email settings
		══════════════════════════════════════════════════════════ -->
		<div class="tab-panel tab-panel-settings">

			<div class="settings-card">
				<?php

				Settings_Builder::build_option(
					array(
						'title' => \esc_html__( 'Email settings', 'wp-2fa' ),
						'id'    => 'general-settings-tab',
						'type'  => 'section-title',
					)
				);

				Settings_Builder::build_option(
					array(
						'text'  => \wp_sprintf(
						// translators: 1. Link to documentation, 2. Link to support.
							\esc_html__( 'Configure how the plugin sends 2FA emails, including the "from" name and address and a delivery test. %1$s', 'wp-2fa' ),
							\wp_sprintf( '<a href="%s" target="_blank">%s</a>', 'https://melapress.com/support/kb/troubleshoot-2fa-email-delivery/?#utm_source=plugin&utm_medium=wp2fa&utm_campaign=guide_troubleshoot_2fa_email_delivery', \esc_html__( 'Learn more', 'wp-2fa' ) )
						),
						'class' => 'description-settings-card',
						'id'    => 'general-settings-tab',
						'type'  => 'description',
					)
				);

				Settings_Builder::build_option(
					array(
						'title' => \esc_html__( 'Which email address should the plugin use as a from address?', 'wp-2fa' ),
						'id'    => 'general-settings-tab',
						'type'  => 'section-sub-title',
					)
				);

				Settings_Builder::build_option(
					array(
						'text'  => '',
						'class' => 'description-settings-card',
						'id'    => 'general-settings-tab',
						'type'  => 'description',
					)
				);
				?>
				<div class="form-group settings-row">
					<?php
					Settings_Builder::build_option(
						array(
							'text' => \esc_html__( 'From email & name', 'wp-2fa' ),
							'id'   => 'email-from-label',
							'type' => 'settings-label',
						)
					);

					Settings_Builder::build_option(
						array(
							'id'          => 'email_from_setting',
							'type'        => 'radio',
							'option_name' => 'email_from_setting',
							'default'     => Email_Templates::get_wp2fa_email_templates( 'email_from_setting' ) ?: 'use-defaults',
							'options'     => array(
								'use-defaults'     => \wp_sprintf(
									/* translators: %s: default from email address */
									\esc_html__( 'Use the email address %s', 'wp-2fa' ),
									'<strong>' . \sanitize_email( Settings_Page::get_default_email_address() ) . '</strong>'
								),
								'use-custom-email' => \esc_html__( 'Use another email address', 'wp-2fa' ),
							),
							'toggle'      => array(
								'use-defaults'     => '',
								'use-custom-email' => '#custom-from-email-wrap',
							),
						)
					);
					?>
				</div>

				<div id="custom-from-email-wrap" class="email_from_setting-options">
					<?php
					Settings_Builder::build_option(
						array(
							'text'  => \esc_html__( 'A \'From email\' address with a domain different than that of your website domain name, or with a domain that the hosting does not relay might cause the notification emails to be blocked, marked as spam, or not delivered at all. If you are not 100% sure about this change, consult with your web host.', 'wp-2fa' ),
							'class' => 'description-settings-card',
							'id'    => 'custom-from-email-desc',
							'type'  => 'description',
						)
					);
					?>
					<div class="form-group settings-row">
						<?php
						Settings_Builder::build_option(
							array(
								'text' => \esc_html__( 'Email Address:', 'wp-2fa' ),
								'id'   => 'custom-from-email-address-label',
								'type' => 'settings-label',
							)
						);
						?>
						<div class="settings-control">
							<?php
							Settings_Builder::build_option(
								array(
									'id'          => 'custom_from_email_address',
									'type'        => 'text',
									'placeholder' => \esc_html__( 'Enter email address', 'wp-2fa' ),
									'class'       => 'form-input',
									'option_name' => 'custom_from_email_address',
									'default'     => Email_Templates::get_wp2fa_email_templates( 'custom_from_email_address' ),
								)
							);
							?>
						</div>
					</div>
					<div class="form-group settings-row">
						<?php
						Settings_Builder::build_option(
							array(
								'text' => \esc_html__( 'Display Name:', 'wp-2fa' ),
								'id'   => 'custom-from-display-name-label',
								'type' => 'settings-label',
							)
						);
						?>
						<div class="settings-control">
							<?php
							Settings_Builder::build_option(
								array(
									'id'          => 'custom_from_display_name',
									'type'        => 'text',
									'placeholder' => \esc_html__( 'Enter display name', 'wp-2fa' ),
									'class'       => 'form-input',
									'option_name' => 'custom_from_display_name',
									'default'     => Email_Templates::get_wp2fa_email_templates( 'custom_from_display_name' ),
								)
							);
							?>
						</div>
					</div>
				</div>
				<?php

				Settings_Builder::build_option(
					array(
						'text' => \esc_html__( 'The "From email" address should match your website domain. If the "from address" does not match your website domain, the emails may be blocked or marked as spam. If you are not sure about this please consult with your website administrator / developer or contact us for more information.', 'wp-2fa' ),
						'id'   => 'general-settings-tab',
						'type' => 'tip',
					)
				);
				?>

			</div><!-- /.settings-card -->

			<!-- Email delivery test -->
			<div class="settings-card delivery-test-card">
				<?php

				Settings_Builder::build_option(
					array(
						'title' => \esc_html__( 'Email delivery test', 'wp-2fa' ),
						'id'    => 'general-settings-tab',
						'type'  => 'section-sub-title',
					)
				);

				Settings_Builder::build_option(
					array(
						'text'  => \wp_sprintf(
						// translators: 1. Link to documentation, 2. Link to support.
							\esc_html__( 'Send a test email to confirm your site can deliver 2FA emails. If it fails, common causes are an unauthorized "from" address, an SMTP misconfiguration, or your host blocking outgoing mail. %1$s.', 'wp-2fa' ),
							\wp_sprintf( '<a href="%s" target="_blank">%s</a>', 'https://melapress.com/support/kb/troubleshoot-2fa-email-delivery/?#utm_source=plugin&utm_medium=wp2fa&utm_campaign=guide_troubleshoot_2fa_email_delivery', \esc_html__( 'Learn more', 'wp-2fa' ) )
						),
						'class' => 'description-settings-card',
						'id'    => 'general-settings-tab',
						'type'  => 'description',
					)
				);
				Settings_Builder::build_option(
					array(
						'default'    => \esc_html__( 'Test email delivery', 'wp-2fa' ),
						'type'       => 'button',
						'class'      => 'btn-outline',
						'data_attrs' => array(
							'nonce'           => \esc_attr( \wp_create_nonce( 'wp-2fa-email' ) ),
							'check-email-2fa' => 'true',
						),
					)
				);
				?>
				
			</div>

		</div><!-- /.tab-panel-settings -->

	</div>
