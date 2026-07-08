<?php
/**
 * Settings Policies New – new-design policies page implemented as a static class.
 *
 * @package    wp2fa
 * @subpackage settings-pages
 */

namespace WP2FA\Admin\SettingsPages;

use WP2FA\WP2FA;
use WP2FA\Admin\Settings_Page;
use WP2FA\Utils\Settings_Utils;
use WP2FA\Admin\Helpers\WP_Helper;
use WP2FA\Extensions\RoleSettings\Role_Settings_Controller;

if ( ! class_exists( '\WP2FA\Admin\SettingsPages\Settings_Page_Policies_New' ) ) {
	/**
	 * Class Settings_Page_Policies_New
	 *
	 * @package WP2FA\Admin\SettingsPages
	 */
	class Settings_Page_Policies_New {

		/**
		 * Page slug constant.
		 *
		 * @var string
		 */
		public const PAGE_SLUG = 'wp-2fa-policies-new';

		/**
		 * Initialize hooks for this policies page.
		 *
		 * @since 3.1.1.2
		 */
		public static function init() {

			// Register AJAX handler for saving.
			\add_action( 'wp_ajax_wp2fa_save_policies_new', array( __CLASS__, 'ajax_save' ) );

			// Register AJAX handler for multi-select search.
			\add_action( 'wp_ajax_wp2fa_search_policy_items', array( __CLASS__, 'ajax_search_items' ) );

			\add_action(
				'admin_enqueue_scripts',
				function ( $hook ) {
					$allowed_hooks = array(
						'wp-2fa_page_' . self::PAGE_SLUG,
						'toplevel_page_wp-2fa-policies',
						'wp-2fa_page_wp-2fa-policies',
						'wp-2fa_page_wp-2fa-settings-new',
					);

					if ( ! in_array( $hook, $allowed_hooks, true ) ) {
						return;
					}

					// Only load the new settings CSS on pages that actually use the new interface.
					if ( ! Settings_Page::is_new_interface_enabled() && 'wp-2fa_page_' . self::PAGE_SLUG !== $hook ) {
						return;
					}

					// Enqueue the new-design settings CSS with its own handle
					// so it does not override the old admin-style.css.
					\wp_enqueue_style(
						'wp_2fa_settings_new_css',
						WP_2FA_URL . 'includes/assets/css/settings.css',
						array(),
						WP_2FA_VERSION
					);

					// Dialog assets.
					\WP2FA\Core\register_dialog_assets();
					\wp_enqueue_script( 'wp2fa-dialog' );
					\wp_enqueue_style( 'wp2fa-dialog' );

					// Save-policies JS (vanilla, no jQuery).
					\wp_enqueue_script(
						'wp_2fa_save_policies_new',
						WP_2FA_URL . 'includes/assets/js/save-policies-new.js',
						array( 'wp2fa-dialog' ),
						WP_2FA_VERSION,
						true
					);

					\wp_localize_script(
						'wp_2fa_save_policies_new',
						'wp2faSavePoliciesNew',
						array(
							'ajaxUrl'          => \admin_url( 'admin-ajax.php' ),
							'nonce'            => \wp_create_nonce( 'wp2fa_save_policies_new_nonce' ),
							'action'           => 'wp2fa_save_policies_new',
							'saveText'         => \esc_html__( 'Save settings', 'wp-2fa' ),
							'savingText'       => \esc_html__( 'Saving…', 'wp-2fa' ),
							'successText'      => \esc_html__( 'Settings saved.', 'wp-2fa' ),
							'errorText'        => \esc_html__( 'An error occurred. Please try again.', 'wp-2fa' ),
							'errorsModalTitle' => \esc_html__( 'Settings saved with warnings', 'wp-2fa' ),
							'errorsModalClose' => \esc_html__( 'OK, I understand', 'wp-2fa' ),
							'searchAction'     => 'wp2fa_search_policy_items',
							// Keep intentionally empty to render the new-page dialog without a title.
							'newPageTitle'     => '',
							'newPageClose'     => \esc_html__( 'OK', 'wp-2fa' ),
						)
					);

					\wp_enqueue_script(
						'wp_2fa_settings_new',
						WP_2FA_URL . 'includes/assets/js/settings-design-logic.js',
						array(),
						WP_2FA_VERSION,
						true
					);

					// Hash-state JS — preserves section / sub-tab state
					// in the URL fragment so a refresh or shared link
					// returns to the same view.
					\wp_enqueue_script(
						'wp_2fa_policies_hash_state',
						WP_2FA_URL . 'includes/assets/js/policies-hash-state.js',
						array(),
						WP_2FA_VERSION,
						true
					);

					/**
					 * Fires after the core policies-new scripts are enqueued.
					 *
					 * Extensions can hook here to enqueue additional JS/CSS
					 * assets on the policies page.
					 *
					 * @param string $hook The current admin page hook suffix.
					 *
					 * @since 3.2.0
					 */
					\do_action( WP_2FA_PREFIX . 'policies_new_enqueue_scripts', $hook );

					// General "exclude yourself" prompt when enforcement = all-users + no grace period.
					$current_user = \wp_get_current_user();
					if ( $current_user && $current_user->exists() ) {
						\wp_enqueue_script(
							'wp_2fa_exclude_self_prompt',
							WP_2FA_URL . 'includes/assets/js/exclude-self-prompt.js',
							array( 'wp_2fa_save_policies_new' ),
							WP_2FA_VERSION,
							true
						);

						\wp_localize_script(
							'wp_2fa_exclude_self_prompt',
							'wp2faExcludeSelfPrompt',
							array(
								'currentUserLogin' => \esc_js( $current_user->user_login ),
								'modalTitle'       => \esc_html__( 'Exclude yourself?', 'wp-2fa' ),
								'modalBody'        => \esc_html__( 'You are about to enforce 2FA on all users, including yourself, however you have not yet configured your own 2FA method. What would you like to do?', 'wp-2fa' )
									/*. '<br><br>'
									. \esc_html__( "If you don't want to risk being locked out, you can exclude yourself from the 2FA policies.", 'wp-2fa' )*/,
								'excludeBtnText'   => \esc_html__( 'Exclude myself from 2FA policies', 'wp-2fa' ),
								'continueBtnText'  => \esc_html__( 'Continue anyway', 'wp-2fa' ),
								//'noteText'         => \esc_html__( "Note: Don't forget to save your settings for any changes to take effect.", 'wp-2fa' ),
							)
						);
					}
				}
			);
		}

		/**
		 * Render the policies screen.
		 */
		public static function render() {
			if ( ! \current_user_can( 'manage_options' ) ) {
				return;
			}

			$main_user = ! empty( WP2FA::get_wp2fa_setting( '2fa_settings_last_updated_by' ) ) ? (int) WP2FA::get_wp2fa_setting( '2fa_settings_last_updated_by' ) : \get_current_user_id();
			if ( ! empty( WP2FA::get_wp2fa_general_setting( 'limit_access' ) ) && $main_user !== \get_current_user_id() ) {
				echo \esc_html__( 'These settings have been disabled by your site administrator, please contact them for further assistance.', 'wp-2fa' );
				return;
			}
			?>
			<div class="wp-2fa-settings-wrapper wp-2fa-policies-new">
				<div class="page-header">
					<!-- <h2><?php \esc_html_e( '2FA policies New', 'wp-2fa' ); ?></h2> -->
				</div>

				<div class="main-settings-new">
					<div class="wrap main-left">
						<?php
						foreach ( self::collect_policy_tabs() as $tab_id => $tab ) {
							?>
							<div id="wp-2fa-policies-tab-<?php echo \esc_attr( $tab_id ); ?>" class="tabs-wrap">
								<?php
								$template_file = isset( $tab['template'] ) ? $tab['template'] : $tab_id;
								$template_path = \WP_2FA_PATH . '/includes/classes/Admin/Settings/templates/policies/' . $template_file . '.php';
								if ( file_exists( $template_path ) ) {
									if ( isset( $tab['role_slug'], $tab['role_name'] ) ) {
										$role_slug = $tab['role_slug'];
										$role_name = $tab['role_name'];
										include $template_path;
									} else {
										include $template_path;
									}
								}
								?>
							</div>
							<?php
						}
						?>
					</div>

					<?php include WP_2FA_PATH . 'includes/classes/Admin/Settings/templates/sidebar.php'; ?>
				</div>

				<div class="save-footer">
					<button type="button" class="button button-primary button-large js-save-policies-new"><?php \esc_html_e( 'Save settings', 'wp-2fa' ); ?></button>
				</div>
			</div>
			<?php
		}

		/**
		 * AJAX handler for saving policies from this page.
		 *
		 * @since 3.1.1.2
		 */
		public static function ajax_save() {
			// 1. Nonce check.
			$nonce = isset( $_POST['nonce'] ) ? \sanitize_text_field( \wp_unslash( $_POST['nonce'] ) ) : '';
			if ( ! \wp_verify_nonce( $nonce, 'wp2fa_save_policies_new_nonce' ) ) {
				\wp_send_json_error(
					array( 'message' => \esc_html__( 'Security check failed. Please refresh the page and try again.', 'wp-2fa' ) ),
					403
				);
			}

			// 2. Capability check.
			if ( ! \current_user_can( 'manage_options' ) ) {
				\wp_send_json_error(
					array( 'message' => \esc_html__( 'You do not have permission to perform this action.', 'wp-2fa' ) ),
					403
				);
			}

			// 3. Collect and sanitize the policy fields.
			if ( isset( $_POST[ WP_2FA_POLICY_SETTINGS_NAME ] ) && is_array( $_POST[ WP_2FA_POLICY_SETTINGS_NAME ] ) ) {
				$raw      = \wp_unslash( $_POST[ WP_2FA_POLICY_SETTINGS_NAME ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$existing = Settings_Utils::get_option( WP_2FA_POLICY_SETTINGS_NAME, array() );

				// Sanitize excluded_users (comma-separated text input or array).
				if ( isset( $raw['excluded_users'] ) ) {
					if ( is_array( $raw['excluded_users'] ) ) {
						$users = array_map( 'sanitize_text_field', array_map( 'trim', $raw['excluded_users'] ) );
					} else {
						$users = array_map( 'sanitize_text_field', array_map( 'trim', explode( ',', $raw['excluded_users'] ) ) );
					}
					$raw['excluded_users'] = array_values( array_filter( $users ) );
				}

				// Sanitize excluded_roles (comma-separated text input or array).
				if ( isset( $raw['excluded_roles'] ) ) {
					if ( is_array( $raw['excluded_roles'] ) ) {
						$roles = array_map( 'sanitize_text_field', array_map( 'trim', $raw['excluded_roles'] ) );
					} else {
						$roles = array_map( 'sanitize_text_field', array_map( 'trim', explode( ',', $raw['excluded_roles'] ) ) );
					}
					$raw['excluded_roles'] = array_values( array_filter( $roles ) );
				}

				// Sanitize enforced_users (comma-separated text input or array).
				if ( isset( $raw['enforced_users'] ) ) {
					if ( is_array( $raw['enforced_users'] ) ) {
						$e_users = array_map( 'sanitize_text_field', array_map( 'trim', $raw['enforced_users'] ) );
					} else {
						$e_users = array_map( 'sanitize_text_field', array_map( 'trim', explode( ',', $raw['enforced_users'] ) ) );
					}
					$raw['enforced_users'] = array_values( array_filter( $e_users ) );
				}

				// Sanitize enforced_roles (comma-separated text input or array).
				if ( isset( $raw['enforced_roles'] ) ) {
					if ( is_array( $raw['enforced_roles'] ) ) {
						$e_roles = array_map( 'sanitize_text_field', array_map( 'trim', $raw['enforced_roles'] ) );
					} else {
						$e_roles = array_map( 'sanitize_text_field', array_map( 'trim', explode( ',', $raw['enforced_roles'] ) ) );
					}
					$raw['enforced_roles'] = array_values( array_filter( $e_roles ) );
				}

				// Sanitize included_sites (comma-separated text input or array).
				if ( isset( $raw['included_sites'] ) ) {
					if ( is_array( $raw['included_sites'] ) ) {
						$sites = array_map( 'sanitize_text_field', array_map( 'trim', $raw['included_sites'] ) );
					} else {
						$sites = array_map( 'sanitize_text_field', array_map( 'trim', explode( ',', $raw['included_sites'] ) ) );
					}
					$raw['included_sites'] = array_values( array_filter( $sites ) );
				}

				// Sanitize excluded_sites (comma-separated text input or array).
				if ( isset( $raw['excluded_sites'] ) ) {
					if ( is_array( $raw['excluded_sites'] ) ) {
						$ex_sites = array_map( 'sanitize_text_field', array_map( 'trim', $raw['excluded_sites'] ) );
					} else {
						$ex_sites = array_map( 'sanitize_text_field', array_map( 'trim', explode( ',', $raw['excluded_sites'] ) ) );
					}
					$raw['excluded_sites'] = array_values( array_filter( $ex_sites ) );
				}

				// Prevent Role_Settings_Controller::validate_and_sanitize() from
				// running inside the filter chain. The old validate_and_sanitize()
				// fires 'filter_output_content' which iterates all roles and wipes
				// any role whose key is absent from $raw – but the new form does
				// not embed per-role data in the global policy payload. Role
				// settings are handled separately via the 'policies_new_ajax_save'
				// action that fires below.
				if ( class_exists( Role_Settings_Controller::class ) ) {
					\remove_filter( WP_2FA_PREFIX . 'filter_output_content', array( Role_Settings_Controller::class, 'validate_and_sanitize' ), 10 );
				}

				$validated = Settings_Page_Policies::validate_and_sanitize( $raw );

				// Merge validated output on top of existing settings, but ONLY
				// for keys actually submitted by the form. validate_and_sanitize()
				// fills in defaults (false, empty arrays, etc.) for every known
				// setting even when it is absent from the input. A naive
				// array_merge($existing, $validated) would overwrite real values
				// with those phantom defaults for settings the new form does not
				// manage (e.g. enable_destroy_session, limit_access).
				if ( \is_array( $validated ) && \is_array( $existing ) ) {
					$submitted_keys = \array_keys( $raw );

					// Checkbox fields are not submitted when unchecked, but
					// the form declares which checkbox keys it manages via a
					// hidden _managed_checkboxes field. Treat those as
					// submitted so their validated value (false) is used.
					if ( ! empty( $raw['_managed_checkboxes'] ) ) {
						$managed = array_map( 'trim', explode( ',', \sanitize_text_field( $raw['_managed_checkboxes'] ) ) );
						$submitted_keys = \array_merge( $submitted_keys, $managed );
						$submitted_keys = \array_unique( $submitted_keys );
					}

					// Map of derived keys → the submitted keys they originate from.
					$derived_from = array(
						'grace-period-expiry-time' => array( 'grace-period', 'grace-period-denominator' ),
						'custom-user-page-id'      => array( 'create-custom-user-page', 'custom-user-page-url' ),
					);

					$merged = $existing;
					foreach ( $validated as $key => $value ) {
						if ( \in_array( $key, $submitted_keys, true ) ) {
							// Key was directly present in the form submission.
							$merged[ $key ] = $value;
						} elseif ( isset( $derived_from[ $key ] ) && \array_intersect( $derived_from[ $key ], $submitted_keys ) ) {
							// Key is computed from submitted form fields.
							$merged[ $key ] = $value;
						} elseif ( ! \array_key_exists( $key, $existing ) ) {
							// Completely new key — include it.
							$merged[ $key ] = $value;
						}
						// Otherwise: key was NOT submitted and already exists
						// in DB → keep the existing value (skip the default).
					}
				} else {
					$merged = \is_array( $validated ) ? $validated : $existing;
				}

				WP2FA::update_plugin_settings( $merged, false, WP_2FA_POLICY_SETTINGS_NAME );
			}

			// 3b. Handle white-label settings submitted alongside the policy form.
			if ( isset( $_POST[ WP_2FA_WHITE_LABEL_SETTINGS_NAME ] ) && is_array( $_POST[ WP_2FA_WHITE_LABEL_SETTINGS_NAME ] ) ) {
				$wl_raw      = \wp_unslash( $_POST[ WP_2FA_WHITE_LABEL_SETTINGS_NAME ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$wl_existing = Settings_Utils::get_option( WP_2FA_WHITE_LABEL_SETTINGS_NAME, array() );

				if ( isset( $wl_raw['hide_page_generated_by'] ) && '' !== trim( (string) $wl_raw['hide_page_generated_by'] ) ) {
					$wl_existing['hide_page_generated_by'] = 'hide_page_generated_by';
				} else {
					$wl_existing['hide_page_generated_by'] = false;
				}

				WP2FA::update_plugin_settings( $wl_existing, false, WP_2FA_WHITE_LABEL_SETTINGS_NAME );
			}

			// 4. Allow extensions to hook in.
			\do_action( WP_2FA_PREFIX . 'policies_new_ajax_save', \wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			// 5. Check if a new custom FE page was created during this save.
			$new_page_dialog_body = null;
			if ( \get_transient( WP_2FA_PREFIX . 'new_custom_page_created' ) ) {
				\delete_transient( WP_2FA_PREFIX . 'new_custom_page_created' );
				$page_slug = WP2FA::get_wp2fa_setting( 'custom-user-page-url' );
				$page      = $page_slug ? \get_page_by_path( $page_slug ) : null;
				if ( $page ) {
					$page_url = \get_permalink( $page->ID );
					if ( $page_url ) {
						$new_page_dialog_body = \wp_sprintf(
							'<h3>%1$s</h3><h4><a href="%2$s" target="_blank">%2$s</a></h4><p>%3$s</p><p>%4$s</p>',
							\esc_html__( 'The plugin created the 2FA settings page with the URL:', 'wp-2fa' ),
							\esc_url( $page_url ),
							\esc_html__( 'You can edit this page using the page editor, like you do with all other pages.', 'wp-2fa' ),
							\esc_html__( 'Use the {2fa_settings_page_url} html tag in the email templates to include the URL of the 2FA configuration page when notifying the users to configure two-factor authentication.', 'wp-2fa' )
						);
					}
				}
			}

			// 6. Collect any validation errors.
			global $wp_settings_errors;
			if ( ! empty( $wp_settings_errors ) ) {
				$errors = array();
				foreach ( $wp_settings_errors as $error ) {
					$errors[] = array(
						'code'    => isset( $error['code'] ) ? \sanitize_key( $error['code'] ) : '',
						'message' => isset( $error['message'] ) ? \wp_strip_all_tags( $error['message'] ) : '',
						'type'    => isset( $error['type'] ) ? \sanitize_key( $error['type'] ) : 'error',
					);
				}
				$response = array(
					'message' => \esc_html__( 'Settings saved with warnings.', 'wp-2fa' ),
					'errors'  => $errors,
				);
				if ( $new_page_dialog_body ) {
					$response['newPageDialogBody'] = $new_page_dialog_body;
				}
				\wp_send_json_success( $response );
			}

			$response = array( 'message' => \esc_html__( 'Settings saved.', 'wp-2fa' ) );
			if ( $new_page_dialog_body ) {
				$response['message'] = '';
				$response['newPageDialogBody'] = $new_page_dialog_body;
			}
			\wp_send_json_success( $response );
		}

		/**
		 * AJAX handler for searching users and roles (multi-select autocomplete).
		 *
		 * Expects GET parameters:
		 *   - nonce (string) Verified against wp2fa_save_policies_new_nonce.
		 *   - type  (string) 'users' or 'roles'.
		 *   - term  (string) Search query (minimum 2 characters).
		 *
		 * Returns JSON array of { id, text } objects.
		 *
		 * @since 3.1.1.2
		 */
		public static function ajax_search_items() {
			$nonce = isset( $_GET['nonce'] ) ? \sanitize_text_field( \wp_unslash( $_GET['nonce'] ) ) : '';
			if ( ! \wp_verify_nonce( $nonce, 'wp2fa_save_policies_new_nonce' ) ) {
				\wp_send_json_error(
					array( 'message' => \esc_html__( 'Security check failed.', 'wp-2fa' ) ),
					403
				);
			}

			if ( ! \current_user_can( 'manage_options' ) ) {
				\wp_send_json_error(
					array( 'message' => \esc_html__( 'Permission denied.', 'wp-2fa' ) ),
					403
				);
			}

			$type = isset( $_GET['type'] ) ? \sanitize_text_field( \wp_unslash( $_GET['type'] ) ) : '';
			$term = isset( $_GET['term'] ) ? \sanitize_text_field( \wp_unslash( $_GET['term'] ) ) : '';

			$allowed_types = array( 'users', 'roles', 'sites' );
			if ( ! in_array( $type, $allowed_types, true ) ) {
				\wp_send_json_error(
					array( 'message' => \esc_html__( 'Invalid search type.', 'wp-2fa' ) ),
					400
				);
			}

			if ( strlen( $term ) < 2 ) {
				\wp_send_json_success( array() );
			}

			$results = array();

			if ( 'users' === $type ) {
				$args = array(
					'search'         => '*' . $term . '*',
					'search_columns' => array( 'user_login', 'display_name' ),
					'number'         => 20,
					'fields'         => array( 'ID', 'user_login' ),
				);

				if ( WP_Helper::is_multisite() ) {
					$args['blog_id'] = 0;
				}

				$users = \get_users( $args );

				foreach ( $users as $user ) {
					$results[] = array(
						'id'   => $user->user_login,
						'text' => $user->user_login,
					);
				}
			} elseif ( 'roles' === $type ) {
				foreach ( WP_Helper::get_roles_wp() as $role_slug => $role_name ) {
					if ( false !== stripos( $role_name, $term ) || false !== stripos( $role_slug, $term ) ) {
						$results[] = array(
							'id'   => strtolower( $role_slug ),
							'text' => $role_name,
						);
					}
				}
			} elseif ( 'sites' === $type && WP_Helper::is_multisite() ) {
				foreach ( WP_Helper::get_multi_sites() as $site ) {
					if ( false !== stripos( $site->blogname, $term ) || false !== stripos( $site->domain, $term ) || (string) $site->blog_id === $term ) {
						$results[] = array(
							'id'   => (string) $site->blog_id,
							'text' => $site->blogname,
						);
					}
				}
			}

			\wp_send_json_success( $results );
		}

		/**
		 * Get the global policy tabs displayed on the landing page.
		 *
		 * @return array
		 */
		public static function get_global_policy_tabs(): array {
			return array(
				'enforcement-exclusions' => array(
					'id'    => 'enforcement-exclusions',
					'title' => \esc_html__( 'Enforcement and exclusions', 'wp-2fa' ),
				),
				'sitewide-policies'      => array(
					'id'    => 'sitewide-policies',
					'title' => \esc_html__( 'Sitewide 2FA policies', 'wp-2fa' ),
				),
			);
		}

		/**
		 * Get the per-role policy tabs.
		 *
		 * @return array
		 */
		public static function get_role_policy_tabs(): array {
			$excluded_roles = (array) WP2FA::get_wp2fa_setting( 'excluded_roles' );

			$helper_roles = WP_Helper::get_roles();
			$roles        = array_flip( array_diff( $helper_roles, $excluded_roles ) );

			$tabs  = array();
			// $roles = WP_Helper::get_roles_wp();

			foreach ( $roles as $role_key => $role_name ) {
				$slug = \sanitize_title( $role_key );
				$tab  = array(
					'id'        => 'role-' . $slug,
					'title'     => $role_name,
					'role_slug' => $slug,
					'role_name' => $role_name,
				);

				// Mark roles that have a separate (custom) policy enabled.
				if ( 'enable_role' === Settings_Utils::get_setting_role( $slug, 'enable_role_based_policies' ) ) {
					$tab['badge'] = \esc_html__( 'Custom', 'wp-2fa' );
				}

				$tabs[ 'role-' . $slug ] = $tab;
			}

			return $tabs;
		}

		/**
		 * Collect all policy tabs (landing + sub-pages) for rendering.
		 *
		 * @return array
		 */
		private static function collect_policy_tabs(): array {
			$tabs = array(
				'policies-settings' => array(
					'id'    => 'policies-settings',
					'title' => \esc_html__( '2FA policies', 'wp-2fa' ),
				),
			);

			// Global sub-pages.
			foreach ( self::get_global_policy_tabs() as $key => $tab ) {
				$tabs[ $key ] = $tab;
			}

			// Per-role sub-pages – all use the same template file.
			foreach ( self::get_role_policy_tabs() as $key => $tab ) {
				$tab['template'] = 'role-policy';
				$tabs[ $key ]    = $tab;
			}

			return $tabs;
		}
	}
}
