/**
 * WP 2FA - Backup Codes Method Module
 *
 * Registers backup codes as a secondary/backup method with the wizard via wp.hooks.
 * After a primary method is validated, the wizard core calls the backup methods flow.
 * This module renders a "generate backup codes" step with download/copy/print options.
 *
 * @package wp2fa
 * @since   4.0.0
 *
 * Hooks this module uses:
 *   - wp2fa_wizard_backup_methods       (filter) — registers backup codes as available
 *   - wp2fa_wizard_render_backup_method (action) — renders backup codes generation UI
 */
( function () {
	'use strict';

	if ( ! window.wp || ! window.wp.hooks ) {
		return;
	}

	var hooks = wp.hooks;
	var METHOD_ID = 'backup_codes';

	/* -------------------------------------------------------
	 * Register as a backup method
	 * ------------------------------------------------------- */
	hooks.addFilter( 'wp2fa_wizard_backup_methods', 'wp2fa/backup-codes', function ( methods, context ) {
		if ( typeof wp2faWizardData === 'undefined' ) {
			return methods;
		}
		var backupMethods = wp2faWizardData.backupMethods || {};
		if ( ! backupMethods[ METHOD_ID ] ) {
			return methods;
		}

		methods.push( {
			id: METHOD_ID,
			name: wp2faWizardData.backupMethodLabels && wp2faWizardData.backupMethodLabels[ METHOD_ID ]
				? wp2faWizardData.backupMethodLabels[ METHOD_ID ]
				: 'Backup Codes',
			description: '',
			order: 1
		} );

		return methods;
	} );

	/* -------------------------------------------------------
	 * Render backup codes generation step
	 * ------------------------------------------------------- */
	hooks.addAction( 'wp2fa_wizard_render_backup_method', 'wp2fa/backup-codes', function ( methodId, container, context ) {
		if ( methodId !== METHOD_ID ) {
			return;
		}

		var backupData = wp2faWizardData.backupCodesData || {};

		renderGenerateStep();

		function renderGenerateStep() {
			container.innerHTML = '';

			/* Title — hide when user already selected this from a multi-method list */
			var titleEl = document.getElementById( 'wp2fa-wizard-title' );
			if ( titleEl ) {
				if ( Object.keys( wp2faWizardData.backupMethods || {} ).length > 1 ) {
					titleEl.innerHTML = '';
				} else {
					titleEl.innerHTML = hooks.applyFilters(
						'wp2fa_wizard_step_title',
						wp2faWizardData.i18n.backupCodesTitle || '',
						'backup-method-backup_codes'
					);
				}
			}

			/* Render body via template */
			var generateTmpl = wp.template( 'wp2fa-backup-codes-generate' );
			container.innerHTML = generateTmpl( {
				intro: wp2faWizardData.i18n.backupCodesIntro || 'Backup codes let you log in when you can\'t access your primary 2FA method. Each code can only be used once. We recommend generating and safely storing your backup codes now.',
				generateLabel: wp2faWizardData.i18n.generateBackupCodes || 'Generate list of backup codes'
			} );

			/* Generate button event */
			var generateBtn = container.querySelector( '#wp2fa-backup-codes-generate-btn' );
			generateBtn.addEventListener( 'click', function () {
				generateBtn.disabled = true;
				var spinner = window.wp2faWizard.utils.addSpinner( generateBtn );

				var formData = new FormData();
				formData.append( 'action', 'wp2fa_run_ajax_generate_json' );
				formData.append( 'nonce', backupData.nonce || '' );

				fetch( wp2faWizardData.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					body: formData
				} )
					.then( function ( res ) { return res.json(); } )
					.then( function ( data ) {
						generateBtn.disabled = false;
						window.wp2faWizard.utils.removeSpinner( spinner );
						if ( data.success && data.data && data.data.codes ) {
							renderCodesDisplay( data.data.codes );
						} else {
							var errEl = document.createElement( 'div' );
							errEl.className = 'wp2fa-wizard-verification-response wp2fa-response-error';
							errEl.textContent = wp2faWizardData.i18n.backupCodesError || 'Failed to generate backup codes.';
							container.appendChild( errEl );
							setTimeout( function () {
								errEl.textContent = '';
								errEl.className = 'wp2fa-wizard-verification-response';
							}, 3000 );
						}
					} )
					.catch( function () {
						generateBtn.disabled = false;
						window.wp2faWizard.utils.removeSpinner( spinner );
					} );
			} );
		}

		function renderCodesDisplay( codes ) {
			container.innerHTML = '';

			/* Title */
			var titleEl = document.getElementById( 'wp2fa-wizard-title' );
			if ( titleEl ) {
				titleEl.innerHTML = hooks.applyFilters(
					'wp2fa_wizard_step_title',
					wp2faWizardData.i18n.yourBackupCodes || 'Your backup codes',
					'backup-method-backup_codes-display'
				);
			}

			var codesLines = [];
			for ( var ci = 0; ci < codes.length; ci++ ) {
				codesLines.push( ( ci + 1 ) + ': ' + codes[ ci ] );
			}
			var codesText = codesLines.join( '\n' );

			/* Render body via template */
			var displayTmpl = wp.template( 'wp2fa-backup-codes-display' );
			container.innerHTML = displayTmpl( {
				intro: wp2faWizardData.i18n.backupCodesGenerated || 'Here are your backup codes. Store them somewhere safe. Each code can only be used once.',
				codesText: codesText,
				codesCount: Math.min( codes.length, 10 ),
				showCopyBtn: !! ( navigator.clipboard || document.queryCommandSupported( 'copy' ) ),
				copyLabel: wp2faWizardData.i18n.copyBackupCodes || 'Copy',
				downloadLabel: wp2faWizardData.i18n.downloadBackupCodes || 'Download',
				printLabel: wp2faWizardData.i18n.printBackupCodes || 'Print',
				emailLabel: wp2faWizardData.i18n.sendBackupCodesEmail || 'Send via email',
				emailNonce: backupData.emailNonce || ''
			} );

			var textarea = container.querySelector( '#wp2fa-backup-codes-textarea' );

			/* Copy button event */
			var copyBtn = container.querySelector( '#wp2fa-backup-codes-copy-btn' );
			if ( copyBtn ) {
				copyBtn.addEventListener( 'click', function () {
					if ( navigator.clipboard && window.isSecureContext ) {
						navigator.clipboard.writeText( codesText );
					} else {
						textarea.select();
						document.execCommand( 'copy' );
					}
					copyBtn.textContent = wp2faWizardData.i18n.copied || 'Copied!';
					setTimeout( function () {
						copyBtn.textContent = wp2faWizardData.i18n.copyBackupCodes || 'Copy';
					}, 2000 );
				} );
			}

			/* Download button event */
			container.querySelector( '#wp2fa-backup-codes-download-btn' ).addEventListener( 'click', function () {
				var siteUrl = window.location.hostname || 'site';
				var blob = new Blob( [ 'Backup codes for ' + siteUrl + '\n\n' + codesText + '\n' ], { type: 'text/plain' } );
				var url = URL.createObjectURL( blob );
				var a = document.createElement( 'a' );
				a.href = url;
				a.download = 'wp-2fa-backup-codes.txt';
				document.body.appendChild( a );
				a.click();
				document.body.removeChild( a );
				URL.revokeObjectURL( url );
			} );

			/* Print button event */
			container.querySelector( '#wp2fa-backup-codes-print-btn' ).addEventListener( 'click', function () {
				var printWindow = window.open( '', '_blank' );
				if ( printWindow ) {
					var safeText = codesText.replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' ).replace( /"/g, '&quot;' );
					printWindow.document.write( '<html><head><title>Backup Codes</title></head><body>' );
					printWindow.document.write( '<h1>Backup Codes</h1>' );
					printWindow.document.write( '<pre>' + safeText + '</pre>' );
					printWindow.document.write( '</body></html>' );
					printWindow.document.close();
					printWindow.print();
				}
			} );

			/* Email button event */
			var emailBtn = container.querySelector( '#wp2fa-backup-codes-email-btn' );
			if ( emailBtn ) {
				emailBtn.addEventListener( 'click', function () {
					sendCodesViaEmail( emailBtn, codesText );
				} );
			}

			/* Footer via template */
			var footer = document.getElementById( 'wp2fa-wizard-footer' );
			var displayFooterTmpl = wp.template( 'wp2fa-backup-codes-display-footer' );
			footer.innerHTML = displayFooterTmpl( {
				closeLabel: wp2faWizardData.i18n.imReadyClose || "I'm ready, close the wizard"
			} );

			footer.querySelector( '#wp2fa-backup-codes-close-btn' ).addEventListener( 'click', function () {
				window.wp2faWizard.nextBackupMethod();
			} );
		}

		/**
		 * Send codes via email.
		 *
		 * @param {HTMLElement} emailBtn The email button element.
		 * @param {string}      codesText The formatted codes text to send.
		 */
		function sendCodesViaEmail( emailBtn, codesText ) {
			var nonce = emailBtn.getAttribute( 'data-nonce' );
			var ajaxUrl = wp2faWizardData.ajaxUrl;

			if ( ! nonce ) {
				showStatus( 'Invalid request. Please try again.', true );
				return;
			}

			emailBtn.disabled = true;
			var originalText = emailBtn.textContent;

			var formData = new FormData();
			formData.append( 'action', 'send_backup_codes_email' );
			formData.append( '_wpnonce', nonce );
			formData.append( 'codes', JSON.stringify( codesText ) );

			fetch( ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: formData
			} )
				.then( function ( res ) { return res.json(); } )
				.then( function ( data ) {
					emailBtn.disabled = false;
					if ( data.success ) {
						showStatus( wp2faWizardData.i18n.backupCodesEmailSent || 'Codes sent to your email.', false );
					} else {
						showStatus( wp2faWizardData.i18n.backupCodesEmailError || 'Failed to send email.', true );
					}
				} )
				.catch( function () {
					emailBtn.disabled = false;
					showStatus( wp2faWizardData.i18n.networkError || 'Network error. Please try again.', true );
				} );
		}

		/**
		 * Show status message below the buttons.
		 *
		 * @param {string}  message The status message.
		 * @param {boolean} isError Whether this is an error message.
		 */
		function showStatus( message, isError ) {
			var el = container.querySelector( '#wp2fa-backup-codes-status' );
			if ( el ) {
				el.textContent = message;
				el.style.display = '';
				el.style.color = isError ? '#d63638' : '#00a32a';
				el.style.marginTop = '10px';
			}
		}
	} );
}() );
