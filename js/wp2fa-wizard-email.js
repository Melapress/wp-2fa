/**
 * WP 2FA - Email (HOTP) Method Module
 *
 * Registers the Email method with the wizard via wp.hooks.
 * Renders the email address selection and verification steps.
 *
 * @package wp2fa
 * @since   4.0.0
 *
 * Hooks this module uses:
 *   - wp2fa_wizard_methods       (filter)  — registers Email as an available method
 *   - wp2fa_wizard_render_method (action)  — renders Email-specific configuration UI
 */
( function () {
	'use strict';

	if ( ! window.wp || ! window.wp.hooks ) {
		return;
	}

	var hooks = wp.hooks;
	var METHOD_ID = 'email';

	/* -------------------------------------------------------
	 * Register method in the selection list
	 * ------------------------------------------------------- */
	hooks.addFilter( 'wp2fa_wizard_methods', 'wp2fa/email', function ( methods, context ) {
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
				: 'One-time code via email (HOTP)',
			description: wp2faWizardData.methodDescriptions && wp2faWizardData.methodDescriptions[ METHOD_ID ]
				? wp2faWizardData.methodDescriptions[ METHOD_ID ]
				: '',
			order: ( wp2faWizardData.methodOrders && typeof wp2faWizardData.methodOrders[ METHOD_ID ] !== 'undefined' )
				? wp2faWizardData.methodOrders[ METHOD_ID ]
				: 2
		} );

		return methods;
	} );

	/* -------------------------------------------------------
	 * Render Email configuration
	 * ------------------------------------------------------- */
	hooks.addAction( 'wp2fa_wizard_render_method', 'wp2fa/email', function ( methodId, container, context ) {
		if ( methodId !== METHOD_ID ) {
			return;
		}

		var emailData = wp2faWizardData.emailData || {};

		renderEmailSetup();

		function renderEmailSetup() {
			container.innerHTML = '';

			/* Title */
			var titleEl = document.getElementById( 'wp2fa-wizard-title' );
			if ( titleEl ) {
				titleEl.innerHTML = hooks.applyFilters(
					'wp2fa_wizard_step_title',
					wp2faWizardData.i18n.settingUpEmail || 'Setting up HOTP (one-time code via email)',
					'configure-email-setup'
				);
			}

			/* Build notice HTML */
			var noticeHtml = '';
			var whitelistNotice = wp2faWizardData.i18n.emailWhitelistNotice || '';
			var showNotice = !! emailData.fromEmail && !! whitelistNotice;
			if ( showNotice ) {
				var safeFromEmail = emailData.fromEmail.replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' ).replace( /"/g, '&quot;' );
				noticeHtml = whitelistNotice.replace( '{from_email}', safeFromEmail );
			}

			/* Render body via template */
			var setupTmpl = wp.template( 'wp2fa-email-setup' );
			container.innerHTML = setupTmpl( {
				description: wp2faWizardData.i18n.emailSetupIntro || '',
				userEmail: emailData.userEmail || '',
				useMyEmailLabel: wp2faWizardData.i18n.useMyEmail || 'Use my user email',
				allowCustomEmail: !! emailData.allowCustomEmail,
				useAnotherLabel: wp2faWizardData.i18n.useAnotherEmail || 'Use another email address',
				emailPlaceholder: wp2faWizardData.i18n.emailPlaceholder || 'email@example.com',
				emailHelpText: wp2faWizardData.i18n.emailHelpText || '',
				showNotice: showNotice,
				noticeHtml: noticeHtml
			} );

			/* Pre-populate nominated/custom email if the user had one configured */
			if ( emailData.nominatedEmail && emailData.allowCustomEmail ) {
				var customRadio = container.querySelector( '#wp2fa-email-use-custom' );
				var wpRadio     = container.querySelector( '#wp2fa-email-use-wp' );
				var customInput = container.querySelector( '#wp2fa-email-custom-address' );
				if ( customRadio && wpRadio && customInput ) {
					wpRadio.checked    = false;
					customRadio.checked = true;
					customInput.value   = emailData.nominatedEmail;
				}
			}

			/* Footer via template */
			var footer = document.getElementById( 'wp2fa-wizard-footer' );
			var setupFooterTmpl = wp.template( 'wp2fa-email-setup-footer' );
			footer.innerHTML = setupFooterTmpl( {
				goBackLabel: wp2faWizardData.i18n.goBack || 'Go back',
				readyLabel: wp2faWizardData.i18n.imReady || "I'm Ready"
			} );

			footer.querySelector( '#wp2fa-email-btn-goback' ).addEventListener( 'click', function ( e ) {
				e.preventDefault();
				hooks.doAction( 'wp2fa_wizard_before_back', 'configure-email-setup', 'select-method' );
				window.wp2faWizard.goToStep( 'select-method' );
				hooks.doAction( 'wp2fa_wizard_after_back', 'select-method' );
			} );

			var readyBtn = footer.querySelector( '#wp2fa-email-btn-ready' );
			readyBtn.addEventListener( 'click', function () {
				/* Determine selected email */
				var selectedRadio = container.querySelector( 'input[name="wp2fa_wizard_email_address"]:checked' );
				var emailAddress = '';

				if ( selectedRadio && selectedRadio.value === 'use_custom_email' ) {
					var customEmailInput = container.querySelector( '#wp2fa-email-custom-address' );
					emailAddress = customEmailInput ? customEmailInput.value.trim() : '';
					if ( ! emailAddress || ! window.wp2faWizard.utils.isValidEmail( emailAddress ) ) {
						customEmailInput.focus();
						return;
					}
				} else if ( selectedRadio ) {
					emailAddress = ''; //selectedRadio.value;
				}

				/* Send setup email via AJAX */
				readyBtn.disabled = true;
				var spinner = window.wp2faWizard.utils.addSpinner( readyBtn );

				var formData = new FormData();
				formData.append( 'action', 'send_authentication_setup_email' );
				formData.append( 'email_address', emailAddress );
				formData.append( 'nonce', wp2faWizardData.sendEmailNonce );

				fetch( wp2faWizardData.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					body: formData
				} )
					.then( function ( res ) { return res.json(); } )
					.then( function ( data ) {
						readyBtn.disabled = false;
						window.wp2faWizard.utils.removeSpinner( spinner );
						if ( data.success ) {
							renderEmailVerify( emailAddress );
						} else {
							window.wp2faWizard.utils.showInlineError( container, window.wp2faWizard.utils.extractAjaxError( data, wp2faWizardData.i18n.emailSendError || 'Failed to send setup email.' ) );
						}
					} )
					.catch( function () {
						readyBtn.disabled = false;
						window.wp2faWizard.utils.removeSpinner( spinner );
					} );
			} );
		}

		function renderEmailVerify( emailAddress ) {
			container.innerHTML = '';

			/* Title */
			var titleEl = document.getElementById( 'wp2fa-wizard-title' );
			if ( titleEl ) {
				titleEl.innerHTML = hooks.applyFilters(
					'wp2fa_wizard_step_title',
					wp2faWizardData.i18n.verifyConfig || 'Verify configuration',
					'configure-email-verify'
				);
			}

			/* Render body via template */
			var verifyTmpl = wp.template( 'wp2fa-email-verify' );
			container.innerHTML = verifyTmpl( {
				description: wp2faWizardData.i18n.emailVerifyIntro || 'Please enter the one-time code sent to your email address to complete setup.',
				codeLabel: wp2faWizardData.i18n.verificationCode || 'Verification Code:',
				placeholder: '000000',
				resendLabel: wp2faWizardData.i18n.resendCode || 'Send me another code'
			} );

			var input = container.querySelector( '#wp2fa-email-authcode' );
			var response = container.querySelector( '#wp2fa-email-verification-response' );

			input.addEventListener( 'input', function () {
				this.value = this.value.replace( /\s/g, '' );
			} );

			/* Resend button */
			var resendBtn = container.querySelector( '#wp2fa-email-resend-code' );
			resendBtn.addEventListener( 'click', function () {
				resendBtn.disabled = true;

				var formData = new FormData();
				formData.append( 'action', 'send_authentication_setup_email' );
				formData.append( 'email_address', emailAddress );
				formData.append( 'nonce', wp2faWizardData.sendEmailNonce );

				fetch( wp2faWizardData.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					body: formData
				} )
					.then( function ( res ) { return res.json(); } )
					.then( function ( data ) {
						resendBtn.disabled = false;
						if ( data.success ) {
							response.className = 'wp2fa-wizard-verification-response wp2fa-response-success';
							response.textContent = wp2faWizardData.i18n.codeSent || 'A new code has been sent.';
							setTimeout( function () {
								response.textContent = '';
								response.className = 'wp2fa-wizard-verification-response';
							}, 3000 );
						}
					} )
					.catch( function () {
						resendBtn.disabled = false;
					} );
			} );

			/* Footer via template */
			var footer = document.getElementById( 'wp2fa-wizard-footer' );
			var verifyFooterTmpl = wp.template( 'wp2fa-email-verify-footer' );
			footer.innerHTML = verifyFooterTmpl( {
				cancelLabel: wp2faWizardData.i18n.cancel || 'Cancel',
				validateLabel: wp2faWizardData.i18n.validateSave || 'Validate & Save'
			} );

			footer.querySelector( '#wp2fa-email-verify-btn-cancel' ).addEventListener( 'click', function () {
				window.wp2faWizard.close();
			} );

			var validateBtn = footer.querySelector( '#wp2fa-email-btn-validate' );
			validateBtn.addEventListener( 'click', function () {
				var code = input.value.trim();
				if ( ! code ) {
					window.wp2faWizard.utils.showResponse( response, 'error', wp2faWizardData.i18n.enterCode || 'Please enter the verification code.' );
					return;
				}

				validateBtn.disabled = true;
				var spinner = window.wp2faWizard.utils.addSpinner( validateBtn );

				hooks.doAction( 'wp2fa_wizard_validate_method', METHOD_ID, container );

				var formData = new FormData();
				formData.append( 'action', 'validate_authcode_via_ajax' );
				formData.append( 'form[wp-2fa-email-authcode]', code );
				if ( emailAddress ) {
					formData.append( 'form[custom-email-address]', emailAddress );
				}
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
							window.wp2faWizard.utils.showResponse( response, 'success', wp2faWizardData.i18n.emailSuccess || 'Email 2FA configured successfully!' );
							hooks.doAction( 'wp2fa_wizard_method_validated', METHOD_ID );
						} else {
							var errMsg = window.wp2faWizard.utils.extractAjaxError( data, wp2faWizardData.i18n.invalidCode || 'Invalid code. Please try again.' );
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
