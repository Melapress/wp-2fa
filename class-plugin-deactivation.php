<?php
/**
 * Plugin deactivation class.
 *
 * Adds a form to request the reason for deactivation.
 *
 * @package WP2FA
 */

namespace WP2FA_Deactivation_Feedback_Server;

if ( ! class_exists( '\WP2FA_Deactivation_Feedback_Server\Plugin_Deactivation' ) ) {

	/**
	 * Plugin deactivation class.
	 */
	class Plugin_Deactivation {

		/**
		 * The plugin slug.
		 *
		 * This is used to identify and catch the deactivation trigger.
		 *
		 * @var string
		 */
		const PLUGIN_SLUG = 'wp-2fa';

		/**
		 * The remote API URL to send the deactivation reason to.
		 *
		 * @var string
		 */
		const REMOTE_URL = 'https://proxytron.melapress.com';

		/**
		 * Get the plugin slug suffix for premium version.
		 *
		 * @return string
		 */
		public static function plugin_slug() {
			$premium_version_slug = 'wp-2fa-premium/wp-2fa.php';
			if ( \is_plugin_active( $premium_version_slug ) ) {
				if ( ! function_exists( 'get_plugin_data' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}
				$plugin_file  = WP_PLUGIN_DIR . '/' . $premium_version_slug;
				$plugin_data  = array();
				if ( file_exists( $plugin_file ) ) {
					$plugin_data = \get_plugin_data( $plugin_file, false, false );
				}
				$plugin_slug    = isset( $plugin_data['slug'] ) ? $plugin_data['slug'] : sanitize_title( $plugin_data['Name'] );

				return $plugin_slug;
			}

			return self::PLUGIN_SLUG;
		}

		/**
		 * Constructor.
		 */
		public function __construct() {
			\add_action( 'admin_footer', array( $this, 'maybe_add_script' ) );
		}

		/**
		 * Maybe add the script to the admin footer.
		 *
		 * @return void
		 */
		public function maybe_add_script() {
			// Check if we're in the plugins page.
			if ( ! \function_exists( 'get_current_screen' ) || ! \get_current_screen() || ( 'plugins' !== \get_current_screen()->id && 'plugins-network' !== \get_current_screen()->id ) ) {
				return;
			}
			$this->the_popover();
			$this->the_inline_script();
			$this->the_inline_style();
		}

		/**
		 * The popover.
		 *
		 * @return void
		 */
		protected function the_popover() {
			$reasons = array(
				array(
					'id'                   => 'unexpected-behavior',
					'label'                => \__( 'The plugin isn\'t working, caused issues, or has a bug', 'wp-2fa' ),
					'feedback_placeholder' => \__( 'Can you briefly describe the issue?', 'wp-2fa' ),
					'feedback_type'        => 'textarea',
				),
				// [
				// 'id'                   => 'wrong-feature',
				// 'label'                => \__( "It's not what I was looking for", 'wp-2fa' ),
				// 'feedback_placeholder' => \__( 'What were you looking for?', 'wp-2fa' ),
				// 'feedback_type'        => 'textarea',
				// ],
				// [
				// 'id'                   => 'not-working',
				// 'label'                => \__( 'The plugin is not working', 'wp-2fa' ),
				// 'feedback_placeholder' => \__( 'Kindly share what didn\'t work so we can fix it for future users...', 'wp-2fa' ),
				// 'feedback_type'        => 'textarea',
				// ],
				array(
					'id'                   => 'found-better-plugin',
					'label'                => \__( 'I found a better alternative', 'wp-2fa' ),
					'feedback_placeholder' => \__( 'Which plugin did you switch to?', 'wp-2fa' ),
					'feedback_type'        => 'text',
				),
				array(
					'id'                   => 'missing-feature',
					'label'                => \__( 'The plugin is missing a specific feature', 'wp-2fa' ),
					'feedback_placeholder' => \__( 'What feature were you looking for?', 'wp-2fa' ),
					'feedback_type'        => 'textarea',
				),
				array(
					'id'                   => 'hard-to-understand',
					'label'                => \__( 'The plugin is too hard to set up or understand', 'wp-2fa' ),
					'feedback_placeholder' => \__( 'Can you tell us a bit more about this?', 'wp-2fa' ),
					'feedback_type'        => 'text',
				),
				array(
					'id'                   => 'temporary-deactivation',
					'label'                => \__( 'This is a temporary deactivation', 'wp-2fa' ),
					'feedback_type'        => false,
					'feedback_placeholder' => false,
				),
			);

			// Randomize the order of the reasons.
			\shuffle( $reasons );

			// Add the "other" reason at the end.
			$reasons[] = array(
				'id'                   => 'other',
				'label'                => \__( 'Other', 'wp-2fa' ),
				'feedback_placeholder' => false,
				'feedback_type'        => false,
			);

			?>
		<div id="<?php echo \esc_attr( self::plugin_slug() ); ?>-popover" popover>
			<div style="text-align: center; margin-bottom: 1.5rem;">
			<img src="<?php echo \esc_url( WP_2FA_URL . 'dist/images/wizard-logo.png' ); ?>" alt="<?php echo \esc_attr( 'Plugin Logo', 'wp-2fa' ); ?>" style="width: 50px; margin-bottom: 1rem;"> </div>
			<h1><?php \esc_html_e( "We're sorry to see you go", 'wp-2fa' ); ?></h1>
			<p><?php \esc_html_e( 'If you have a moment, please let us know why you are deactivating this plugin:', 'wp-2fa' ); ?></p>
			<form>
				<?php foreach ( $reasons as $reason ) : ?>
					<div class="reason-wrapper" data-reason="<?php echo \esc_attr( $reason['id'] ); ?>">
						<span class="radio-wrapper">
							<input
								id="deactivate-plugin-reason-<?php echo \esc_attr( $reason['id'] ); ?>"
								type="radio"
								name="reason"
								value="<?php echo \esc_attr( $reason['id'] ); ?>"
							>
							<label for="deactivate-plugin-reason-<?php echo \esc_attr( $reason['id'] ); ?>">
								<?php echo \esc_html( $reason['label'] ); ?>
							</label>
						</span>
						<?php if ( $reason['feedback_type'] ) : ?>
							<div class="feedback-wrapper">
								<?php if ( 'textarea' === $reason['feedback_type'] ) : ?>
									<textarea
										id="deactivate-plugin-reason-<?php echo \esc_attr( $reason['id'] ); ?>-feedback"
										name="feedback"
										placeholder="<?php echo \esc_attr( $reason['feedback_placeholder'] ); ?>"
									></textarea>
								<?php else : ?>
									<input
										id="deactivate-plugin-reason-<?php echo \esc_attr( $reason['id'] ); ?>-feedback"
										type="text"
										name="feedback"
										placeholder="<?php echo \esc_attr( $reason['feedback_placeholder'] ); ?>"
									>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>

				<div class="actions">
					<button type="button" class="submit"><?php \esc_html_e( 'Submit & Deactivate', 'wp-2fa' ); ?></button>
					<button type="button" class="dismiss"><?php \esc_html_e( 'Skip & Deactivate', 'wp-2fa' ); ?></button>
				</div>
			</form>
		</div>
			<?php
		}

		/**
		 * The inline script.
		 *
		 * @return void
		 */
		protected function the_inline_script() {
			?>
			<script>
				(function() {
					/*
					 * One request, one answer: the parsed JSON of a completed 2xx response, or
					 * null for anything else - an HTTP error, unreadable JSON, a network error,
					 * an abort or a timeout. This used to call back on every readyState change,
					 * so the feedback went out with a nonce that had not arrived yet, more than
					 * once, and the page navigated away from intermediate states. And it never
					 * gave up on a server that did not answer.
					 */
					const deactivatePluginFeedbackAjaxRequest = ( { url, data, timeout } ) => new Promise( ( resolve ) => {
						let settled = false;
						const settle = ( value ) => {
							if ( ! settled ) {
								settled = true;
								resolve( value );
							}
						};
						const http = new XMLHttpRequest();
						http.open( 'POST', url, true );
						http.timeout = timeout || 8000;
						http.onload = () => {
							if ( http.status < 200 || http.status >= 300 ) {
								settle( null );
								return;
							}
							try {
								settle( JSON.parse( http.responseText ) );
							} catch ( e ) {
								settle( null );
							}
						};
						http.onerror = http.ontimeout = http.onabort = () => settle( null );
						const dataForm = new FormData();
						for ( let [ key, value ] of Object.entries( data ) ) {
							dataForm.append( key, value );
						}
						http.send( dataForm );
					} );

					// Add an event listener to the deactivate button.
					/*
					 * Found by the plugin's row, which WordPress marks with the plugin file.
					 * The link's own id is built from whatever slug WordPress has for the
					 * plugin - from an update source when there is one - which need not be
					 * the slug this class works out, and with the premium build it was not:
					 * the link was never found, so the feedback form never opened at all.
					 */
					const deactivateButton = document.querySelector( 'tr[data-plugin="<?php echo \esc_attr( \plugin_basename( __DIR__ . '/wp-2fa.php' ) ); ?>"] span.deactivate a' )
						|| document.getElementById( 'deactivate-<?php echo \esc_attr( self::plugin_slug() ); ?>' );
					
					const deactivationPopover = document.getElementById( '<?php echo \esc_attr( self::plugin_slug() ); ?>-popover' );
					if ( deactivateButton && deactivationPopover ) {
						deactivateButton.addEventListener( 'click', function( e ) {
							e.preventDefault();
							deactivationPopover.showPopover();
						} );

						// Show/hide the feedback fields based on the selected reason.
						deactivationPopover.querySelectorAll( '.reason-wrapper' ).forEach( function( reasonWrapper ) {
							reasonWrapper.addEventListener( 'click', function( changeEvent ) {
								const radio = reasonWrapper.querySelector( 'input[type="radio"]' );
								if ( radio ) {
									radio.checked = true;
								}
								const feedbackWrapper = reasonWrapper.querySelector( '.feedback-wrapper' );
								// Hide any existing feedback fields.
								deactivationPopover.querySelectorAll( '.feedback-wrapper' ).forEach( function( feedbackWrapper ) {
									feedbackWrapper.style.display = 'none';
								} );
								if ( feedbackWrapper ) {
									reasonWrapper.querySelector( '.feedback-wrapper' ).style.display = 'block';
								}
							} );
						} );

						/*
						 * Deactivation is what the user asked for; feedback is optional. Whatever
						 * the feedback server does - answers, fails, never answers - the page goes
						 * on to deactivate, once.
						 */
						let leaving = false;
						const deactivate = () => {
							if ( leaving ) {
								return;
							}
							leaving = true;
							window.location.href = deactivateButton.href;
						};

						// Handle clicking on the dismiss button.
						deactivationPopover.querySelector( 'button.dismiss' ).addEventListener( 'click', function( dismissEvent ) {
							dismissEvent.preventDefault();
							deactivate();
						} );

						// Handle clicking on the submit button.
						let submitted = false;
						deactivationPopover.querySelector( 'button.submit' ).addEventListener( 'click', async function( submitEvent ) {
							submitEvent.preventDefault();
							if ( submitted ) {
								return;
							}
							submitted = true;

							const requestData = {
								action: 'plugin_deactivation',
								plugin: '<?php echo \esc_attr( self::plugin_slug() ); ?>',
								site: '<?php echo \esc_attr( get_site_url() ); ?>',
							};
							const formData = new FormData( deactivationPopover.querySelector( 'form' ) );
							requestData.reason = formData.get( 'reason' );
							const feedbackEl = document.getElementById( `deactivate-plugin-reason-${requestData.reason}-feedback` );
							requestData.feedback = feedbackEl ? feedbackEl.value : '';

							deactivationPopover.hidePopover();

							// A last resort, in case a request outlives its own timeout.
							setTimeout( deactivate, 20000 );

							// Get a nonce from the remote server; send the feedback only with a real one.
							const nonceResponse = await deactivatePluginFeedbackAjaxRequest( {
								url: '<?php echo \esc_url( self::REMOTE_URL ); ?>/?rest_route=/deactivation-feedback-server/v1/get-nonce',
								data: requestData,
							} );

							if ( nonceResponse && 'string' === typeof nonceResponse.nonce && '' !== nonceResponse.nonce ) {
								requestData.nonce = nonceResponse.nonce;
								await deactivatePluginFeedbackAjaxRequest( {
									url: '<?php echo \esc_url( self::REMOTE_URL ); ?>/?rest_route=/deactivation-feedback-server/v1/submit-feedback',
									data: requestData,
								} );
							}

							deactivate();
						} );
					}
				})();
			</script>
			<?php
		}

		/**
		 * The inline style.
		 *
		 * @return void
		 */
		protected function the_inline_style() {
			?>
			<style>
				#<?php echo \esc_attr( self::plugin_slug() ); ?>-popover {
					border: 1px solid #ccc;
					padding: 2rem;
					border-radius: 8px;

					form {
						display: flex;
						flex-direction: column;
						gap: 1rem;

						.reason-wrapper {
							display: flex;
							gap: 1rem;
							flex-direction: column;

							.feedback-wrapper {
								display: none;

								textarea, input {
									width: 100%;
									border: 1px solid #ccc;
									border-radius: 4px;
									padding: 0.5rem;
								}
							}
						}

						.actions {
							display: flex;
							gap: 1rem;
						}

						button {
							padding: 0.5rem 1rem;
							border-radius: 4px;
							border: 1px solid #ccc;
							background-color: #40D3F0;
							cursor: pointer;
							color: #fff;
							margin: 0;

							&.dismiss {
								background-color: #fff;
								cursor: pointer;
								color: #000;
							}
						}
					}
				}
			</style>
			<?php
		}
	}
}
