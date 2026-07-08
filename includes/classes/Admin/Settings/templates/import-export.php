<?php
/**
 * Import/Export Settings Template
 *
 * @package wp-2fa
 */

use WP2FA\WP2FA;
use WP2FA\Admin\Settings_Builder;
use WP2FA\Admin\Helpers\Email_Templates;

$ie_nonce              = \wp_create_nonce( 'wp-2fa-export-settings' );
$ie_key                = 'wp-2fa';
$ie_current_policy     = WP2FA::get_wp2fa_setting( 'enforcement-policy' );
$ie_show_site_specific = ( 'certain-roles-only' === $ie_current_policy || 'certain-users-only' === $ie_current_policy );
$ie_show_custom_email  = ( 'use-custom-email' === Email_Templates::get_wp2fa_email_templates( 'email_from_setting' ) );

?>
	<div class="settings-page" id="import-export-wrap">

		<?php
		Settings_Builder::build_option(
			array(
				'parent'       => \esc_html__( 'Settings', 'wp-2fa' ),
				'type'         => 'breadcrumb',
				'custom_class' => 'back-policies-settings-main-wrapper',
				'default'      => \esc_html__( 'Export / import', 'wp-2fa' ),
			)
		);

		Settings_Builder::build_option(
			array(
				'title' => \esc_html__( 'Export / import', 'wp-2fa' ),
				'id'    => 'import-export-tab',
				'type'  => 'tab-title',
			)
		);

		Settings_Builder::build_option(
			array(
				'text'  => \wp_sprintf(
				// translators: 1. Link to documentation.
					\esc_html__( 'Export the plugin\'s settings to a file, or import settings from a previously exported file. Use this to back up your configuration or copy the same settings to another website. %1$s.', 'wp-2fa' ),
					\wp_sprintf( '<a href="%s" target="_blank">%s</a>', 'https://melapress.com/support/kb/wp-2fa-plugin-getting-started/?#utm_source=plugin&utm_medium=wp2fa&utm_campaign=guide_getting_started_wp2fa', \esc_html__( 'Learn more', 'wp-2fa' ) )
				),
				'class' => 'description-settings-card',
				'id'    => 'import-export-tab',
				'type'  => 'description',
			)
		);

		?>

		<div class="settings-card">

			<!-- Export Section -->
			<div class="import-export-section">
				<div class="section-content">
					<h3 class="section-heading"><?php \esc_html_e( 'Export', 'wp-2fa' ); ?></h3>
					<p class="section-description">
						<?php \esc_html_e( 'Once the settings are exported a download will automatically start.', 'wp-2fa' ); ?><br>
						<?php \esc_html_e( 'The settings are exported to a JSON file.', 'wp-2fa' ); ?>
					</p>

					<?php if ( $ie_show_site_specific ) : ?>
					<div class="export-option-row">
						<label for="ie-export-site-specific">
							<input type="checkbox" id="ie-export-site-specific" value="site-specific">
							<?php \esc_html_e( 'Also export the site specific settings as enforced users and roles (the policy will be set to Do not enforce if left unchecked)', 'wp-2fa' ); ?>
						</label>
					</div>
					<?php endif; ?>

					<?php if ( $ie_show_custom_email ) : ?>
					<div class="export-option-row">
						<label for="export-custom-from-email">
							<input type="checkbox" id="export-custom-from-email" name="export-custom-from-email" value="site-specific">
							<?php \esc_html_e( 'Add the custom from email to the export', 'wp-2fa' ); ?>
						</label>
					</div>
					<?php endif; ?>
				</div>
				<div class="section-actions">
					<button type="button" class="button button-primary" id="export-settings-btn">
						<?php \esc_html_e( 'Export', 'wp-2fa' ); ?>
					</button>
				</div>

			</div>

			<hr class="import-export-divider">

			<!-- Import Section -->
			<div class="import-export-section">
				<div class="section-content">
					<h3 class="section-heading"><?php \esc_html_e( 'Import', 'wp-2fa' ); ?></h3>
					<p class="section-description">
						<?php \esc_html_e( 'Once you choose a JSON settings file, it is validated before import and any problems are flagged.', 'wp-2fa' ); ?>
					</p>
					<span id="file-name-display" class="file-name-display"></span>
				</div>
				<div class="section-actions">
					<input type="file" id="import-settings-file" accept=".json" style="display: none;">
					<button type="button" class="button button-secondary" id="select-file-btn">
						<?php \esc_html_e( 'Select file', 'wp-2fa' ); ?>
					</button>
					<button type="button" class="button button-primary" id="import-settings-btn" disabled>
						<?php \esc_html_e( 'Validate and import', 'wp-2fa' ); ?>
					</button>
				</div>

			</div>

		</div>

		<!-- Import Validation Modal -->
		<div id="ie-import-modal">
			<div class="ie-modal-content">
				<span class="ie-modal-close">&times;</span>
				<h3 id="ie-modal-title"></h3>
				<ul id="ie-settings-output"></ul>
				<div id="ie-modal-actions"></div>
			</div>
		</div>

	</div>

	<script>
	(function() {
		var ieKey   = <?php echo \wp_json_encode( $ie_key ); ?>;
		var ieNonce = <?php echo \wp_json_encode( $ie_nonce ); ?>;
		var ieI18n  = {
			checking:       <?php echo \wp_json_encode( \esc_html__( 'Checking import contents', 'wp-2fa' ) ); ?>,
			checksPassed:   <?php echo \wp_json_encode( \esc_html__( 'Ready to import', 'wp-2fa' ) ); ?>,
			checksFailed:   <?php echo \wp_json_encode( \esc_html__( 'Issues found', 'wp-2fa' ) ); ?>,
			importing:      <?php echo \wp_json_encode( \esc_html__( 'Importing settings', 'wp-2fa' ) ); ?>,
			imported:       <?php echo \wp_json_encode( \esc_html__( 'Settings imported', 'wp-2fa' ) ); ?>,
			importError:    <?php echo \wp_json_encode( \esc_html__( 'This setting could not be imported. Please review the setting value and try again.', 'wp-2fa' ) ); ?>,
			wrongFormat:    <?php echo \wp_json_encode( \esc_html__( 'Please upload a valid JSON file.', 'wp-2fa' ) ); ?>,
			cancel:         <?php echo \wp_json_encode( \esc_html__( 'Cancel', 'wp-2fa' ) ); ?>,
			ready:          <?php echo \wp_json_encode( \esc_html__( 'The settings file has been tested and the configuration is ready to be imported. Would you like to proceed?', 'wp-2fa' ) ); ?>,
			proceeded:      <?php echo \wp_json_encode( \esc_html__( 'The configuration has been successfully imported. Click OK to close this window', 'wp-2fa' ) ); ?>,
			proceed:        <?php echo \wp_json_encode( \esc_html__( 'Proceed', 'wp-2fa' ) ); ?>,
			ok:             <?php echo \wp_json_encode( \esc_html__( 'OK', 'wp-2fa' ) ); ?>,
			oldVersionTitle: <?php echo \wp_json_encode( \esc_html__( 'Incompatible import file detected', 'wp-2fa' ) ); ?>,
			oldVersion:     <?php echo \wp_json_encode( \wp_kses(
				__( 'We detected that this file was exported from WP 2FA version 3.x. This format is no longer supported in version 4.0 and above and cannot be imported directly.<br><br>To resolve this, generate a fresh export from a site already running WP 2FA 4.0 or later and use that file here.<br><br><a href="https://melapress.com/support/submit-ticket/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=settings-exportimport-submit-ticket" target="_blank">Contact support</a> if you have any questions.', 'wp-2fa' ),
				array( 'br' => array(), 'a' => array( 'href' => array(), 'target' => array() ) )
			) ); ?>
		};

		document.addEventListener('DOMContentLoaded', function() {
			var selectFileBtn  = document.getElementById('select-file-btn');
			var fileInput      = document.getElementById('import-settings-file');
			var importBtn      = document.getElementById('import-settings-btn');
			var fileNameDisplay = document.getElementById('file-name-display');
			var exportBtn      = document.getElementById('export-settings-btn');
			var modal          = document.getElementById('ie-import-modal');
			var modalTitle     = document.getElementById('ie-modal-title');
			var settingsOutput = document.getElementById('ie-settings-output');
			var modalActions   = document.getElementById('ie-modal-actions');
			var modalClose     = document.querySelector('.ie-modal-close');

			// Select file button.
			if (selectFileBtn) {
				selectFileBtn.addEventListener('click', function() {
					fileInput.click();
				});
			}

			// File selection.
			if (fileInput) {
				fileInput.addEventListener('change', function(e) {
					var file = e.target.files[0];
					if (file) {
						fileNameDisplay.textContent = file.name;
						importBtn.disabled = false;
					} else {
						fileNameDisplay.textContent = '';
						importBtn.disabled = true;
					}
				});
			}

			// Close modal.
			if (modalClose) {
				modalClose.addEventListener('click', function() {
					modal.style.display = 'none';
				});
			}

			// Export settings.
			if (exportBtn) {
				exportBtn.addEventListener('click', function() {
					var customEmail  = false;
					var siteSpecific = false;
					var cbEmail = document.getElementById('export-custom-from-email');
					var cbSite  = document.getElementById('ie-export-site-specific');

					if (cbEmail && cbEmail.checked) {
						customEmail = true;
					}
					if (cbSite && cbSite.checked) {
						siteSpecific = true;
					}

					var formData = new FormData();
					formData.append('action', ieKey + '_export_settings');
					formData.append('nonce', ieNonce);
					formData.append('export-custom-from-email', customEmail);
					formData.append('export-site-specific', siteSpecific);

					fetch(ajaxurl, { method: 'POST', body: formData })
						.then(function(r) { return r.json(); })
						.then(function(result) {
							if (result.data) {
								var json = JSON.stringify(result.data);
								var blob = new Blob([json], { type: 'application/json' });
								var link = document.createElement('a');
								link.href = URL.createObjectURL(blob);
								link.download = ieKey + '_settings.json';
								link.click();
								URL.revokeObjectURL(link.href);
							}
						});
				});
			}

			// Validate & Import.
			if (importBtn) {
				importBtn.addEventListener('click', function() {
					if (!fileInput.files || !fileInput.files[0]) {
						return;
					}

					var ext = fileInput.value.split('.').pop().toLowerCase();
					if (ext !== 'json') {
						alert(ieI18n.wrongFormat);
						return;
					}

					buildFileInfo('false');
				});
			}

			/**
			 * Read the JSON file and start checking/importing each setting.
			 */
			function buildFileInfo(doImport) {
				// Reset modal actions.
				if (doImport === 'false') {
					settingsOutput.innerHTML = '';
					modalActions.innerHTML =
						'<button type="button" class="button button-secondary ie-modal-close-btn">' + ieI18n.cancel + '</button>' +
						' <button type="button" class="button button-primary" id="ie-proceed-btn">' + ieI18n.proceed + '</button>';
					modalActions.classList.add('ie-disabled');
				} else {
					modalActions.classList.add('ie-disabled');
				}

				var reader = new FileReader();
				reader.readAsText(fileInput.files[0]);

				reader.onload = function() {
					var parsed = JSON.parse(reader.result);
					var options = Array.isArray(parsed) ? parsed : JSON.parse(parsed);

					// Check if the export file contains a version identifier.
					var hasVersion = options.some(function(opt) {
						return opt && opt.option_name === 'wp_2fa_export_version';
					});

					if (!hasVersion) {
						modal.style.display = 'block';
						modalTitle.textContent = ieI18n.oldVersionTitle;
						settingsOutput.innerHTML = '';
						modalActions.innerHTML = '<p class="ie-ready-text">' + ieI18n.oldVersion + '</p>';
						modalActions.classList.remove('ie-disabled');
						return;
					}

					for (var i = 0; i < options.length; i++) {
						if (options[i] === '' || !options[i]) {
							continue;
						}
						var optName  = options[i].option_name;
						var optValue = options[i].option_value;

						// Skip the version metadata entry.
						if (optName === 'wp_2fa_export_version') {
							continue;
						}

						var label    = optName.replace(ieKey + '_', '').replace(/_/g, ' ').replace(/-/g, ' ');

						if (doImport === 'false') {
							var li = document.createElement('li');
							li.setAttribute('data-ie-option', optName);
							li.innerHTML = '<div class="ie-option-label">' + label + '</div>';
							settingsOutput.appendChild(li);
						}

						checkSettingPreImport(optName, optValue, doImport);
					}
				};

				// Bind action buttons after they're rendered.
				setTimeout(function() {
					var closeBtn = modalActions.querySelector('.ie-modal-close-btn');
					if (closeBtn) {
						closeBtn.addEventListener('click', function() {
							modal.style.display = 'none';
						});
					}
					var proceedBtn = document.getElementById('ie-proceed-btn');
					if (proceedBtn) {
						proceedBtn.addEventListener('click', function() {
							buildFileInfo('true');
						});
					}
				}, 50);
			}

			/**
			 * AJAX-check (or import) a single setting.
			 */
			function checkSettingPreImport(optName, optValue, doImport) {
				var encodedValue = btoa(unescape(encodeURIComponent(optValue)));

				modal.style.display = 'block';

				if (doImport === 'true') {
					modalTitle.textContent = ieI18n.importing;
					// Mark existing status icons as "complete" so we can count new ones.
					var oldIcons = settingsOutput.querySelectorAll('[data-ie-option] .ie-status');
					for (var s = 0; s < oldIcons.length; s++) {
						oldIcons[s].classList.add('ie-complete');
					}
				} else {
					modalTitle.textContent = ieI18n.checking;
				}

				var formData = new FormData();
				formData.append('action', ieKey + '_check_setting_pre_import');
				formData.append('setting_name', optName);
				formData.append('setting_value_b64', encodedValue);
				formData.append('process_import', doImport);
				formData.append('nonce', ieNonce);

				fetch(ajaxurl, { method: 'POST', body: formData })
					.then(function(r) { return r.json(); })
					.then(function(result) {
						var li = settingsOutput.querySelector('[data-ie-option="' + optName + '"]');
						if (!li) {
							return;
						}

						if (result.success) {
							if (doImport === 'true' && result.data && result.data.import_confirmation) {
								li.insertAdjacentHTML('beforeend', '<span class="ie-status ie-success">' + result.data.import_confirmation + '</span>');
							} else {
								li.insertAdjacentHTML('beforeend', '<span class="ie-status ie-success dashicons dashicons-yes-alt"></span>');
							}
						} else {
							var reason = result.data && result.data.failure_reason ? result.data.failure_reason : ieI18n.importError;
							li.insertAdjacentHTML('beforeend',
								'<span class="ie-status ie-error">' +
								' <span class="dashicons dashicons-info" aria-hidden="true"></span>' +
								' <span class="ie-error-message">' + reason + '</span>' +
								'</span>'
							);
						}

						// Check if all settings have been processed.
						var totalOptions = settingsOutput.querySelectorAll('[data-ie-option]').length;
						var doneOptions  = settingsOutput.querySelectorAll('[data-ie-option] .ie-status:not(.ie-complete)').length;

						if (totalOptions === doneOptions) {
							if (doImport === 'true') {
								modalTitle.textContent = ieI18n.imported;
								modalActions.innerHTML =
									'<p class="ie-ready-text">' + ieI18n.proceeded + '</p>' +
									'<button type="button" class="button button-secondary ie-modal-close-btn">' + ieI18n.ok + '</button>';
								modalActions.classList.remove('ie-disabled');
								var closeBtn = modalActions.querySelector('.ie-modal-close-btn');
								if (closeBtn) {
									closeBtn.addEventListener('click', function() {
										modal.style.display = 'none';
									});
								}
							} else {
								var errorCount = settingsOutput.querySelectorAll('.ie-error').length;
								if (errorCount) {
									modalTitle.textContent = ieI18n.checksFailed;
								} else {
									modalTitle.textContent = ieI18n.checksPassed;
								}

								var proceedLabel = errorCount ? 'Proceed and skip invalid settings' : ieI18n.proceed;
								modalActions.innerHTML =
									'<p class="ie-ready-text">' + ieI18n.ready + '</p>' +
									'<button type="button" class="button button-secondary ie-modal-close-btn">' + ieI18n.cancel + '</button>' +
									' <button type="button" class="button button-primary" id="ie-proceed-btn">' + proceedLabel + '</button>';
								modalActions.classList.remove('ie-disabled');

								var closeBtn2 = modalActions.querySelector('.ie-modal-close-btn');
								if (closeBtn2) {
									closeBtn2.addEventListener('click', function() {
										modal.style.display = 'none';
									});
								}
								var proceedBtn2 = document.getElementById('ie-proceed-btn');
								if (proceedBtn2) {
									proceedBtn2.addEventListener('click', function() {
										buildFileInfo('true');
									});
								}
							}
						}
					});
			}

		});
	})();
	</script>

	<style>
	.import-export-section {
		margin-bottom: 30px;
		display: flex;
		gap: 30px;
		align-items: flex-start;
	}

	.import-export-section:last-child {
		margin-bottom: 0;
	}

	.section-actions {
		display: flex;
		flex-direction: column;
		gap: 10px;
		min-width: 140px;
		flex-shrink: 0;
	}

	.section-content {
		flex: 1;
	}

	.section-heading {
		margin: 0 0 12px 0;
		font-size: 16px;
		font-weight: 600;
		color: #333;
	}

	.section-description {
		margin: 0 0 12px 0;
		font-size: 14px;
		color: #666;
		line-height: 1.5;
	}

	.export-option-row {
		margin: 8px 0;
		font-size: 14px;
	}

	.export-option-row label {
		display: flex;
		align-items: flex-start;
		gap: 6px;
		cursor: pointer;
		color: #444;
		line-height: 1.4;
	}

	.export-option-row input[type="checkbox"] {
		margin-top: 3px;
		flex-shrink: 0;
	}

	.file-name-display {
		display: block;
		font-size: 14px;
		color: #666;
		padding: 8px 12px;
		border: 1px solid #ddd;
		border-radius: 4px;
		background: #f9f9f9;
		margin-top: 8px;
		min-height: 20px;
	}

	.import-export-divider {
		margin: 30px 0;
		border: none;
		border-top: 1px solid #e5e5e5;
	}

	#import-export-wrap .button {
		cursor: pointer;
		font-size: 14px;
		padding: 8px 16px;
		border-radius: 4px;
		white-space: nowrap;
	}

	#import-export-wrap .button-primary:hover:not(:disabled) {
		background-color: #005a87;
	}

	#import-export-wrap #import-settings-btn {
		margin-bottom: 20px;
	}

	#import-export-wrap .button:disabled {
		opacity: 0.5;
		cursor: not-allowed;
	}

	#select-file-btn {
		border: 1px dashed;
	}

	/* Import Modal */
	#ie-import-modal {
		display: none;
		position: fixed;
		z-index: 9999;
		left: 0;
		top: 0;
		width: 100%;
		height: 100%;
		overflow: auto;
		background-color: rgba(0, 0, 0, 0.4);
	}

	.ie-modal-content {
		background-color: #fefefe;
		margin: 5% auto;
		padding: 24px;
		border: 1px solid #888;
		border-radius: 6px;
		width: 80%;
		max-width: 800px;
		position: relative;
	}

	.ie-modal-close {
		color: #aaa;
		position: absolute;
		right: 16px;
		top: 12px;
		font-size: 28px;
		font-weight: bold;
		cursor: pointer;
	}

	.ie-modal-close:hover {
		color: #333;
	}

	#ie-modal-title {
		font-size: 20px;
		margin: 0 0 16px;
		padding-right: 30px;
	}

	#ie-settings-output {
		list-style: none;
		margin: 0 0 16px;
		padding: 0;
	}

	[data-ie-option] {
		line-height: 28px;
		padding: 2px 0;
		display: flex;
		align-items: flex-start;
		flex-wrap: nowrap;
		gap: 10px;
	}

	.ie-option-label {
		display: inline-block;
		min-width: 285px;
		flex: 0 0 285px;
		font-size: 14px;
		font-weight: 500;
		text-transform: capitalize;
	}

	.ie-status {
		margin-left: 0;
		font-size: 14px;
		display: inline-flex;
		align-items: center;
		gap: 6px;
		flex: 1 1 auto;
	}

	.ie-success {
		color: green;
	}

	.ie-error {
		color: red;
		align-items: flex-start;
		line-height: 1.4;
	}

	.ie-error .ie-error-message {
		font-size: 14px;
		display: inline;
	}

	.ie-error .dashicons {
		flex: 0 0 auto;
		margin-top: 2px;
	}

	#ie-modal-actions {
		margin-top: 16px;
	}

	#ie-modal-actions.ie-disabled {
		opacity: 0.5;
		pointer-events: none;
	}

	.ie-ready-text {
		margin: 0 0 12px;
		font-size: 14px;
	}

	#ie-modal-actions .button {
		margin-right: 8px;
	}
	</style>
