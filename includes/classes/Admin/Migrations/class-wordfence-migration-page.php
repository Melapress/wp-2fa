<?php
/**
 * Admin screen for the Wordfence Login Security migration.
 *
 * @package    wp2fa
 * @subpackage admin
 * @copyright  2026 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

declare(strict_types=1);

namespace WP2FA\Admin\Migrations;

use WP2FA\Admin\Helpers\WP_Helper;
use WP2FA\Utils\Settings_Utils;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

if ( ! class_exists( '\WP2FA\Admin\Migrations\Wordfence_Migration_Page' ) ) {

	/**
	 * The menu entry, settings screen and prompts for migrating away from Wordfence.
	 *
	 * Everything here is conditional on Wordfence Login Security actually being present. On a
	 * site that has never had it, none of this registers, so the plugin gains no menu entry,
	 * no notice and no extra queries.
	 *
	 * @since 4.1.0
	 */
	class Wordfence_Migration_Page {

		/**
		 * Page and menu slug.
		 *
		 * @var string
		 */
		public const MENU_SLUG = 'wp-2fa-wordfence-migration';

		/**
		 * Set once the administrator has answered the prompt, either way.
		 *
		 * @var string
		 */
		public const PROMPT_ANSWERED = 'wordfence_migration_prompt_answered';

		/**
		 * Set once they have acknowledged that every user has moved across.
		 *
		 * @var string
		 */
		public const COMPLETION_ACKNOWLEDGED = 'wordfence_migration_acknowledged';

		/**
		 * Registers the screen, but only where there is something to migrate.
		 *
		 * @return void
		 *
		 * @since 4.1.0
		 */
		public static function init() {
			if ( ! Wordfence_Login_Security::is_available() ) {
				return;
			}

			\add_action( WP_2FA_PREFIX . 'after_admin_menu_created', array( __CLASS__, 'add_menu_item' ), 20, 2 );
			\add_action( 'admin_notices', array( __CLASS__, 'render_prompt' ) );
			\add_action( 'network_admin_notices', array( __CLASS__, 'render_prompt' ) );
			\add_action( 'wp_ajax_wp2fa_wfls_answer_prompt', array( __CLASS__, 'ajax_answer_prompt' ) );
			\add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_styles' ) );
		}

		/**
		 * Loads the settings screens' stylesheet on this screen, which is drawn with the same parts.
		 *
		 * @return void
		 *
		 * @since 4.2.0
		 */
		public static function enqueue_styles() {
			$page = isset( $_GET['page'] ) ? \sanitize_text_field( \wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			if ( self::MENU_SLUG !== $page ) {
				return;
			}

			\wp_enqueue_style(
				'wp_2fa_admin_settings',
				WP_2FA_URL . 'includes/assets/css/settings.css',
				array(),
				WP_2FA_VERSION
			);
		}

		/**
		 * Whether the administrator still has to say yes or no.
		 *
		 * @return bool
		 *
		 * @since 4.1.0
		 */
		public static function is_prompt_pending(): bool {
			return ! Settings_Utils::get_option( self::PROMPT_ANSWERED, false );
		}

		/**
		 * Whether every Wordfence user has moved and nobody has dismissed the news yet.
		 *
		 * @return bool
		 *
		 * @since 4.1.0
		 */
		public static function is_completion_unacknowledged(): bool {
			return Wordfence_Login_Security::is_complete()
				&& ! Settings_Utils::get_option( self::COMPLETION_ACKNOWLEDGED, false );
		}

		/**
		 * Whether the menu entry should still be there at all.
		 *
		 * Once the administrator has confirmed that everyone has moved, the screen has served
		 * its purpose and the entry goes away rather than sitting there for good.
		 *
		 * @return bool
		 *
		 * @since 4.1.0
		 */
		public static function should_show_menu(): bool {
			return ! Settings_Utils::get_option( self::COMPLETION_ACKNOWLEDGED, false );
		}

		/**
		 * The count bubble to append to a menu title, when the migrator wants attention.
		 *
		 * The same bubble core uses for pending updates, so it reads as "there is something
		 * here for you" without needing to be explained. Shown on the top level entry as well
		 * as the submenu one: the prompt appears on screens all over the admin, and a marker
		 * only on a submenu is invisible until somebody has already opened the menu.
		 *
		 * @return string Empty when there is nothing to draw attention to.
		 *
		 * @since 4.2.0
		 */
		public static function menu_bubble(): string {
			/*
			 * Asked first, because the top level menu calls this on every site — not only the
			 * ones with something to migrate. The submenu is registered from init(), which
			 * already returns early without Wordfence; nothing else does that check for us,
			 * and the bubble would otherwise sit on the menu of every install for good.
			 */
			if ( ! Wordfence_Login_Security::is_available() ) {
				return '';
			}

			if ( ! self::should_show_menu() ) {
				return '';
			}

			if ( ! self::is_prompt_pending() && ! self::is_completion_unacknowledged() ) {
				return '';
			}

			return ' <span class="update-plugins count-1"><span class="update-count">1</span></span>';
		}

		/**
		 * Adds the submenu entry, with a count bubble when it wants attention.
		 *
		 * @param string $top_menu_slug - The plugin's top level menu.
		 * @param bool   $is_network    - Whether this is the network admin menu.
		 *
		 * @return void
		 *
		 * @since 4.1.0
		 */
		public static function add_menu_item( $top_menu_slug = '', $is_network = false ) {
			if ( ! self::should_show_menu() ) {
				return;
			}

			$title = \esc_html__( 'Wordfence Login Security Migrator', 'wp-2fa' );

			/*
			 * The same bubble core uses for pending updates, so it reads as "there is something
			 * here for you" without needing to be explained. It is shown while the prompt is
			 * unanswered, and again when the migration finishes and nobody has seen that yet.
			 */
			$title .= self::menu_bubble();

			\add_submenu_page(
				$top_menu_slug,
				\esc_html__( 'Wordfence Login Security Migrator', 'wp-2fa' ),
				$title,
				'manage_options',
				self::MENU_SLUG,
				array( __CLASS__, 'render' ),
				90
			);
		}

		/**
		 * The wording used by the wizard slide, the prompt and the screen itself.
		 *
		 * Kept in one place so the three surfaces cannot drift apart, and so translators see
		 * each paragraph once.
		 *
		 * @return string[]
		 *
		 * @since 4.1.0
		 */
		public static function get_offer_copy(): array {
			return array(
				\esc_html__( 'You are currently using the Wordfence Login Security plugin for two-factor authentication (2FA). This plugin has been discontinued.', 'wp-2fa' ),
				\esc_html__( 'WP 2FA includes a built-in migration tool that can automatically migrate your users to WP 2FA. The migration is seamless and requires no action from your users. The next time they log in, their 2FA configuration will be migrated automatically.', 'wp-2fa' ),
				\esc_html__( 'The migration process runs automatically in the background whenever a user logs in. Once all users have been migrated, WP 2FA will notify you.', 'wp-2fa' ),
			);
		}

		/**
		 * The setup wizard's Wordfence slide.
		 *
		 * Rendered as radios rather than buttons so the wizard's existing Finish handler picks
		 * the answer up with everything else it saves; the wizard has no per-slide submit.
		 *
		 * @return void
		 *
		 * @since 4.1.0
		 */
		public static function render_wizard_step() {
			?>
			<h2><?php \esc_html_e( 'Wordfence Login Security has been discontinued', 'wp-2fa' ); ?></h2>

			<?php foreach ( self::get_offer_copy() as $paragraph ) { ?>
				<p><?php echo \esc_html( $paragraph ); ?></p>
			<?php } ?>

			<p><strong><?php \esc_html_e( 'Would you like to enable automatic migration?', 'wp-2fa' ); ?></strong></p>

			<?php
			/*
			 * The wizard's own radio component, not a bare input.
			 *
			 * A plain radio here looked permanently unselected: the admin styling strips the
			 * native control and draws the selected dot itself, and that drawing only happens
			 * inside .radio-option. Outside it the dot fell back to white on white, so the
			 * default answer was invisible and clicking appeared to do nothing — while the
			 * choice was in fact being made all along.
			 */
			?>
			<div class="form-group settings-row">
				<div class="settings-control">
					<label class="radio-option">
						<input type="radio" name="wp2fa_wfls_migrate" id="wp2fa-wfls-migrate-yes" value="yes" checked="checked" />
						<span><?php \esc_html_e( 'Yes', 'wp-2fa' ); ?></span>
					</label>
					<label class="radio-option">
						<input type="radio" name="wp2fa_wfls_migrate" id="wp2fa-wfls-migrate-no" value="no" />
						<span><?php \esc_html_e( 'No', 'wp-2fa' ); ?></span>
					</label>
				</div>
			</div>
			<?php
		}

		/**
		 * Records the wizard slide's answer.
		 *
		 * @param array $posted - The raw request the wizard saved with.
		 *
		 * @return void
		 *
		 * @since 4.1.0
		 */
		public static function save_wizard_step( array $posted ) {
			if ( ! Wordfence_Login_Security::is_available() || ! isset( $posted['wp2fa_wfls_migrate'] ) ) {
				return;
			}

			$answer = \sanitize_text_field( (string) $posted['wp2fa_wfls_migrate'] );

			Wordfence_Login_Security::set_enabled( 'yes' === $answer );
			Settings_Utils::update_option( self::PROMPT_ANSWERED, true );
		}

		/**
		 * Shows the offer to administrators who never saw the wizard slide.
		 *
		 * @return void
		 *
		 * @since 4.1.0
		 */
		public static function render_prompt() {
			if ( ! \current_user_can( 'manage_options' ) || ! self::is_prompt_pending() ) {
				return;
			}

			/*
			 * Confined to WP 2FA's own screens. The count bubble on the menu is what makes this
			 * discoverable from anywhere in the admin; repeating the notice on every unrelated
			 * screen would be noise. The migrator's own page is excluded too, since it puts the
			 * same question inline.
			 */
			$screen = \get_current_screen();

			if ( ! $screen || ! \in_array( $screen->id, WP_Helper::PLUGIN_PAGES, true ) ) {
				return;
			}

			$nonce = \wp_create_nonce( 'wp2fa_wfls_answer_prompt' );
			?>
			<?php
			/*
			 * Carries the same classes as the plugin's other admin notice, so it inherits the
			 * same border, spacing and type rather than rendering as a bare core notice beside
			 * it. The pairing of the buttons matches too: one primary, one secondary.
			 */
			?>
			<div class="notice notice-info wp-2fa-nag wp-2fa-admin-notice wp2fa-wfls-prompt" id="wp2fa-wfls-prompt">
				<h3><?php \esc_html_e( 'Migrate your users from Wordfence Login Security', 'wp-2fa' ); ?></h3>
				<?php foreach ( self::get_offer_copy() as $paragraph ) { ?>
					<p><?php echo \esc_html( $paragraph ); ?></p>
				<?php } ?>
				<p>
					<strong><?php \esc_html_e( 'Would you like to enable automatic migration?', 'wp-2fa' ); ?></strong>
				</p>
				<p>
					<button type="button" class="button button-primary" data-wp2fa-wfls-answer="yes"><?php \esc_html_e( 'Yes', 'wp-2fa' ); ?></button>
					<button type="button" class="button button-secondary" data-wp2fa-wfls-answer="no"><?php \esc_html_e( 'No', 'wp-2fa' ); ?></button>
				</p>
			</div>
			<script>
			( function () {
				var notice = document.getElementById( 'wp2fa-wfls-prompt' );

				if ( ! notice ) {
					return;
				}

				notice.addEventListener( 'click', function ( event ) {
					var button = event.target.closest( '[data-wp2fa-wfls-answer]' );

					if ( ! button ) {
						return;
					}

					var body = new FormData();
					body.append( 'action', 'wp2fa_wfls_answer_prompt' );
					body.append( 'nonce', <?php echo \wp_json_encode( $nonce ); ?> );
					body.append( 'answer', button.getAttribute( 'data-wp2fa-wfls-answer' ) );

					button.disabled = true;

					window.fetch( <?php echo \wp_json_encode( \admin_url( 'admin-ajax.php' ) ); ?>, {
						method: 'POST',
						credentials: 'same-origin',
						body: body
					} ).then( function ( response ) {
						if ( ! response.ok ) {
							throw new Error( 'Request failed' );
						}

						return response.json();
					} ).then( function ( payload ) {
						if ( ! payload || ! payload.success ) {
							throw new Error( 'Request failed' );
						}

						notice.parentNode.removeChild( notice );
					} ).catch( function () {
						button.disabled = false;
					} );
				} );
			}() );
			</script>
			<?php
		}

		/**
		 * Records the answer to the prompt.
		 *
		 * @return void
		 *
		 * @since 4.1.0
		 */
		public static function ajax_answer_prompt() {
			$nonce = isset( $_POST['nonce'] ) ? \sanitize_text_field( \wp_unslash( $_POST['nonce'] ) ) : '';

			if ( ! \current_user_can( 'manage_options' ) || ! \wp_verify_nonce( $nonce, 'wp2fa_wfls_answer_prompt' ) ) {
				\wp_send_json_error( \esc_html__( 'Nonce verification failed.', 'wp-2fa' ), 403 );
			}

			$answer = isset( $_POST['answer'] ) ? \sanitize_text_field( \wp_unslash( $_POST['answer'] ) ) : 'no';

			Wordfence_Login_Security::set_enabled( 'yes' === $answer );
			Settings_Utils::update_option( self::PROMPT_ANSWERED, true );

			\wp_send_json_success();
		}

		/**
		 * Handles the screen's own form submissions.
		 *
		 * @return string A message to show, or '' when there is nothing to report.
		 *
		 * @since 4.1.0
		 */
		private static function handle_post(): string {
			if ( 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? \strtoupper( \sanitize_text_field( \wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '' ) ) {
				return '';
			}

			$nonce = isset( $_POST['wp2fa_wfls_nonce'] ) ? \sanitize_text_field( \wp_unslash( $_POST['wp2fa_wfls_nonce'] ) ) : '';

			if ( ! \wp_verify_nonce( $nonce, 'wp2fa_wfls_settings' ) ) {
				return '';
			}

			if ( isset( $_POST['wp2fa_wfls_acknowledge'] ) ) {
				Settings_Utils::update_option( self::COMPLETION_ACKNOWLEDGED, true );

				return \esc_html__( 'Thanks — the migrator has been put away.', 'wp-2fa' );
			}

			Wordfence_Login_Security::set_enabled( isset( $_POST['wp2fa_wfls_enabled'] ) );
			Settings_Utils::update_option( self::PROMPT_ANSWERED, true );

			return \esc_html__( 'Settings saved.', 'wp-2fa' );
		}

		/**
		 * Renders the migrator screen.
		 *
		 * @return void
		 *
		 * @since 4.1.0
		 */
		public static function render() {
			if ( ! \current_user_can( 'manage_options' ) ) {
				\wp_die( \esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-2fa' ) );
			}

			$message   = self::handle_post();
			$complete  = Wordfence_Login_Security::is_complete();
			$migrated  = Wordfence_Login_Security::migrated_user_count();
			$pending   = Wordfence_Login_Security::pending_user_count();
			$is_on     = Wordfence_Login_Security::is_enabled();
			?>
			<div class="wp-2fa-settings-wrapper wp-2fa-policies-new wp2fa-wfls-wrap">
				<form method="post">
					<?php \wp_nonce_field( 'wp2fa_wfls_settings', 'wp2fa_wfls_nonce' ); ?>

					<div class="main-settings-new">
						<div class="wrap main-left">
							<h2><?php \esc_html_e( 'Wordfence Login Security Migrator', 'wp-2fa' ); ?></h2>

							<?php if ( $complete ) { ?>
								<div class="settings-card wp2fa-wfls-complete">
									<p><strong><?php \esc_html_e( 'All users have been migrated from Wordfence Login Security to WP 2FA.', 'wp-2fa' ); ?></strong></p>
									<p>
										<button type="submit" name="wp2fa_wfls_acknowledge" value="1" class="button button-primary">
											<?php \esc_html_e( 'OK', 'wp-2fa' ); ?>
										</button>
									</p>
								</div>
							<?php } else { ?>
								<div class="settings-card wp2fa-wfls-help-card">
									<div class="notice-tip wp2fa-wfls-help" role="note">
										<span class="notice-tip-icon" aria-hidden="true">i</span>
										<p class="notice-tip-body"><?php echo \esc_html( \implode( ' ', self::get_offer_copy() ) ); ?></p>
									</div>
								</div>
							<?php } ?>

							<div class="settings-card wp2fa-wfls-setting-card">
								<h3 class="section-title"><?php \esc_html_e( 'Automatically migrate 2FA configuration', 'wp-2fa' ); ?></h3>
								<div class="form-group settings-row">
									<label class="toggle-switch" for="wp2fa_wfls_enabled">
										<input type="checkbox" name="wp2fa_wfls_enabled" id="wp2fa_wfls_enabled" value="1" <?php \checked( $is_on ); ?> />
										<span class="toggle-slider"></span>
										<span class="toggle-label"><?php \esc_html_e( 'Automatically migrate users from Wordfence Login Security to WP 2FA', 'wp-2fa' ); ?></span>
									</label>
									<p class="description-settings-card">
										<?php \esc_html_e( 'Each user is migrated the next time they log in. Their existing authenticator app keeps working — they are not asked to set 2FA up again.', 'wp-2fa' ); ?>
									</p>
								</div>
							</div>

							<div class="settings-card wp2fa-wfls-status-card">
								<h3 class="section-title"><?php \esc_html_e( '2FA migration status', 'wp-2fa' ); ?></h3>
								<div class="stat-cards-row wp2fa-wfls-status">
									<div class="stat-card">
										<span class="stat-value"><?php echo \esc_html( (string) $migrated ); ?></span>
										<span class="stat-label"><?php \esc_html_e( 'Users migrated to WP 2FA', 'wp-2fa' ); ?></span>
									</div>
									<div class="stat-card">
										<span class="stat-value"><?php echo \esc_html( (string) $pending ); ?></span>
										<span class="stat-label"><?php \esc_html_e( 'Users still using Wordfence Login Security', 'wp-2fa' ); ?></span>
									</div>
								</div>
							</div>
						</div>

						<?php include \WP_2FA_PATH . '/includes/classes/Admin/Settings/templates/sidebar.php'; ?>
					</div>

					<div class="save-footer">
						<?php \submit_button( \esc_html__( 'Save settings', 'wp-2fa' ), 'primary', 'submit', false ); ?>
						<?php if ( '' !== $message ) { ?>
							<span class="wp2fa-save-notice wp2fa-save-notice--success"><?php echo \esc_html( $message ); ?></span>
						<?php } ?>
					</div>
				</form>
			</div>
			<?php
		}
	}
}
