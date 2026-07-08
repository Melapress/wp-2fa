/**
 * WP 2FA - TOTP Method Module
 *
 * Registers the TOTP (authenticator app) method with the wizard via wp.hooks.
 * Renders the QR code setup and verification steps.
 *
 * @package wp2fa
 * @since   4.0.0
 *
 * Hooks this module uses:
 *   - wp2fa_wizard_methods      (filter)  — registers TOTP as an available method
 *   - wp2fa_wizard_render_method (action) — renders TOTP-specific configuration UI
 */
( function () {
	'use strict';

	if ( ! window.wp || ! window.wp.hooks ) {
		return;
	}

	var hooks = wp.hooks;
	var METHOD_ID = 'totp';

	/* -------------------------------------------------------
	 * Register method in the selection list
	 * ------------------------------------------------------- */
	hooks.addFilter( 'wp2fa_wizard_methods', 'wp2fa/totp', function ( methods, context ) {
		/* Only add if server-side says TOTP is enabled for this user */
		if ( typeof wp2faWizardData === 'undefined' ) {
			return methods;
		}
		var enabledMethods = wp2faWizardData.enabledMethods || {};
		if ( ! enabledMethods[ METHOD_ID ] ) {
			return methods;
		}

		methods.push( {
			id: METHOD_ID,
			name: wp2faWizardData.methodLabels && wp2faWizardData.methodLabels[ METHOD_ID ]
				? wp2faWizardData.methodLabels[ METHOD_ID ]
				: 'One-time code via 2FA App (TOTP)',
			// description: wp2faWizardData.methodDescriptions && wp2faWizardData.methodDescriptions[ METHOD_ID ]
			// 	? wp2faWizardData.methodDescriptions[ METHOD_ID ]
			// 	: 'Refer to the guide on how to set up 2FA apps for more information on how to setup these apps and which apps are supported.',
			order: ( wp2faWizardData.methodOrders && typeof wp2faWizardData.methodOrders[ METHOD_ID ] !== 'undefined' )
				? wp2faWizardData.methodOrders[ METHOD_ID ]
				: 1
		} );

		return methods;
	} );

	/* -------------------------------------------------------
	 * Render TOTP configuration
	 * ------------------------------------------------------- */
	hooks.addAction( 'wp2fa_wizard_render_method', 'wp2fa/totp', function ( methodId, container, context ) {
		if ( methodId !== METHOD_ID ) {
			return;
		}

		var totpData = wp2faWizardData.totpData || {};

		renderSetupStep();

		function renderSetupStep() {
			container.innerHTML = '';

			/* Title update */
			var titleEl = document.getElementById( 'wp2fa-wizard-title' );
			if ( titleEl ) {
				titleEl.innerHTML = hooks.applyFilters(
					'wp2fa_wizard_step_title',
					wp2faWizardData.i18n.settingUpTotp || 'Setting up TOTP (one-time code via app)',
					'configure-totp-setup'
				);
			}

			/* Render body via template */
			var setupTmpl = wp.template( 'wp2fa-totp-setup' );
			container.innerHTML = setupTmpl( {
				qrCodeUrl: totpData.qrCodeUrl || '',
				totpKey: totpData.totpKey || '',
				step1: wp2faWizardData.i18n.totpStep1 || 'Download and start the application of your choice',
				step2: wp2faWizardData.i18n.totpStep2 || 'From within the application scan the QR code provided on the left. Otherwise, enter the following code manually in the application:',
				step3: wp2faWizardData.i18n.totpStep3 || 'Click the "I\'m ready" button below when you complete the application setup process to proceed with the wizard.',
				copyKeyLabel: wp2faWizardData.i18n.copyKey || 'Copy key'
			} );

			/* Copy button event */
			var copyBtn = container.querySelector( '#wp2fa-totp-key-copy' );
			var keyInput = container.querySelector( '#wp2fa-totp-key-input' );
			if ( copyBtn && keyInput ) {
				copyBtn.addEventListener( 'click', function () {
					if ( navigator.clipboard && window.isSecureContext ) {
						navigator.clipboard.writeText( keyInput.value );
					} else {
						keyInput.select();
						document.execCommand( 'copy' );
					}

					// Show copied tooltip.
					var tooltip = copyBtn.querySelector( '.wp2fa-copy-tooltip' );
					if ( ! tooltip ) {
						tooltip = document.createElement( 'span' );
						tooltip.className = 'wp2fa-copy-tooltip';
						copyBtn.style.position = 'relative';
						copyBtn.appendChild( tooltip );
					}
					tooltip.textContent = wp2faWizardData.i18n.copied || 'Copied!';
					tooltip.classList.add( 'visible' );
					setTimeout( function () {
						tooltip.classList.remove( 'visible' );
					}, 1500 );
				} );
			}

			/* Footer via template */
			var footer = document.getElementById( 'wp2fa-wizard-footer' );
			var setupFooterTmpl = wp.template( 'wp2fa-totp-setup-footer' );
			footer.innerHTML = setupFooterTmpl( {
				goBackLabel: wp2faWizardData.i18n.goBack || 'Go back',
				continueLabel: wp2faWizardData.i18n.continueBtn || 'Continue'
			} );

			footer.querySelector( '#wp2fa-totp-btn-goback' ).addEventListener( 'click', function ( e ) {
				e.preventDefault();
				hooks.doAction( 'wp2fa_wizard_before_back', 'configure-totp-setup', 'select-method' );
				window.wp2faWizard.goToStep( 'select-method' );
				hooks.doAction( 'wp2fa_wizard_after_back', 'select-method' );
			} );

			footer.querySelector( '#wp2fa-totp-btn-ready' ).addEventListener( 'click', function () {
				renderVerifyStep();
			} );
		}

		function renderVerifyStep() {
			container.innerHTML = '';

			/* Title */
			var titleEl = document.getElementById( 'wp2fa-wizard-title' );
			if ( titleEl ) {
				titleEl.innerHTML = hooks.applyFilters(
					'wp2fa_wizard_step_title',
					wp2faWizardData.i18n.verifyConfig || 'Verify configuration',
					'configure-totp-verify'
				);
			}

			/* Render body via template */
			var verifyTmpl = wp.template( 'wp2fa-totp-verify' );
			container.innerHTML = verifyTmpl( {
				description: wp2faWizardData.i18n.totpVerifyIntro || 'Please type in the one-time code from your authenticator app to finalize setup.',
				codeLabel: wp2faWizardData.i18n.verificationCode || 'Verification Code:',
				placeholder: '000000'
			} );

			var input = container.querySelector( '#wp2fa-totp-authcode' );
			var response = container.querySelector( '#wp2fa-totp-verification-response' );

			input.addEventListener( 'input', function () {
				this.value = this.value.replace( /\s/g, '' );
			} );

			/* Footer via template */
			var footer = document.getElementById( 'wp2fa-wizard-footer' );
			var verifyFooterTmpl = wp.template( 'wp2fa-totp-verify-footer' );
			footer.innerHTML = verifyFooterTmpl( {
				goBackLabel: wp2faWizardData.i18n.goBack || 'Go back',
				validateLabel: wp2faWizardData.i18n.validateSave || 'Validate & Save'
			} );

			footer.querySelector( '#wp2fa-totp-verify-btn-back' ).addEventListener( 'click', function () {
				renderSetupStep();
			} );

			var validateBtn = footer.querySelector( '#wp2fa-totp-btn-validate' );
			validateBtn.addEventListener( 'click', function () {
				var code = input.value.trim();
				if ( ! code ) {
					window.wp2faWizard.utils.showResponse( response, 'error', wp2faWizardData.i18n.enterCode || 'Please enter the verification code.' );
					return;
				}

				if ( ! /^\d{6}$/.test( code ) ) {
					window.wp2faWizard.utils.showResponse( response, 'error', wp2faWizardData.i18n.invalidCodeFormat || 'Invalid code. Please enter the correct OTP code generated by your Authenticator app.' );
					return;
				}

				validateBtn.disabled = true;
				var spinner = window.wp2faWizard.utils.addSpinner( validateBtn );

				hooks.doAction( 'wp2fa_wizard_validate_method', METHOD_ID, container );

				var formData = new FormData();
				formData.append( 'action', 'validate_authcode_via_ajax' );
				formData.append( 'form[wp-2fa-totp-authcode]', code );
				formData.append( 'form[wp-2fa-totp-key]', totpData.totpKey || '' );
				formData.append( '_wpnonce', context.nonce );

				fetch( wp2faWizardData.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					body: formData
				} )
					.then( function ( res ) { return res.json(); } )
					.then( function ( data ) {
						validateBtn.disabled = false;
						window.wp2faWizard.utils.removeSpinner( spinner );

						if ( data.success ) {
							window.wp2faWizard.utils.showResponse( response, 'success', wp2faWizardData.i18n.totpSuccess || 'TOTP configured successfully!' );
							hooks.doAction( 'wp2fa_wizard_method_validated', METHOD_ID );
						} else {
							var errMsg = window.wp2faWizard.utils.extractAjaxError( data, wp2faWizardData.i18n.invalidCode || 'Invalid or expired OTP code. Please try again.' );
							window.wp2faWizard.utils.showResponse( response, 'error', errMsg );
						}
					} )
					.catch( function () {
						validateBtn.disabled = false;
						window.wp2faWizard.utils.removeSpinner( spinner );
						window.wp2faWizard.utils.showResponse( response, 'error', wp2faWizardData.i18n.networkError || 'Network error. Please try again.' );
					} );
			} );

			input.focus();
		}

	} );
}() );
