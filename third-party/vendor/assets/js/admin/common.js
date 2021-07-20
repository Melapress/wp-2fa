/* global wp2faData, wp2faWizardData MicroModal */
/* eslint-disable require-jsdoc, no-mixed-spaces-and-tabs, camelcase, prefer-destructuring, no-var, no-unused-vars, no-redeclare,  no-inner-declarations, no-console */

jQuery( document ).ready( function() {
	MicroModal.init();

	/****************************
	 Common front/admin JS.
	 ****************************/
	function updateStepTitles() {
		if ( jQuery( '[data-step-title]' ).length ) {
			jQuery( '.step-title-wrapper' ).remove();
			jQuery( '.wp2fa-setup-content' ).prepend( '<div class="step-title-wrapper"></div>' );
			var counter = 1;
			jQuery( '[data-step-title]:not(.hidden)' ).each( function() {
				var stepLabel = jQuery( this ).attr( 'data-step-title' );
				if ( jQuery( this ).hasClass( 'active' ) ) {
					jQuery( '.step-title-wrapper' ).append( `<span class="step-title active-step-title"><span>${counter}</span> ${stepLabel}</span>` );
				} else {
					jQuery( '.step-title-wrapper' ).append( `<span class="step-title"><span>${counter}</span> ${stepLabel}</span>` );
				}
				counter++;
			} );
		}
	}

	updateStepTitles();

	jQuery( 'body' ).on( 'click', '.step-title', function( e ) {
		var currentLabel = jQuery( this ).text().substr( 2 );
		jQuery( '[data-step-title]:not(.hidden)' ).each( function() {
			var currentStep = jQuery( this );
			jQuery( '[data-step-title]' ).removeClass( 'active' );
			jQuery( '.step-title' ).removeClass( 'active-step-title' );
			var stepLabel = jQuery( this ).attr( 'data-step-title' );
			jQuery( `[data-step-title="${currentLabel}"]` ).addClass( 'active' );
		} );
		updateStepTitles();
	} );

	jQuery( '[data-unhide-when-checked]' ).each( function() {
		if ( jQuery( this ).is( ':checked' ) ) {
			const thingToShow = jQuery( this ).attr( 'data-unhide-when-checked' );
			jQuery( thingToShow ).show( 0 );
		}
	} );

	jQuery( 'body' ).on( 'click', '[for="all-users"], [for="certain-roles-only"]', function( e ) {
		jQuery( '.step-setting-wrapper.hidden' ).removeClass( 'hidden' ).addClass( 'un-hidden' );
		updateStepTitles();
	} );

	jQuery( 'body' ).on( 'click', '[for="do-not-enforce"]', function( e ) {
		jQuery( '.step-setting-wrapper.un-hidden' ).removeClass( 'un-hidden' ).addClass( 'hidden' );
		updateStepTitles();
	} );

	jQuery( 'body' ).on( 'click', '.modal__btn' , function( e ) {
		e.preventDefault();
	} );

	// Stop modal from closing by pressing enter.
	jQuery( 'body' ).on( 'keypress', '.wp2fa-modal', function( event ) {
		var keycode = ( event.keyCode ? event.keyCode : event.which );
		if ( '13' == keycode ) {
			return false;
		}
	} );

	jQuery( document ).on( 'click', '[data-open-configure-2fa-wizard]', function( event ) {
		event.preventDefault();
		jQuery( '.verification-response span' ).remove();
		jQuery( '#configure-2fa .wizard-step.active, #configure-2fa .step-setting-wrapper.active' ).removeClass( 'active' );
		jQuery( '#configure-2fa .wizard-step:first-of-type, #configure-2fa .step-setting-wrapper:first-of-type' ).addClass( 'active' );
		jQuery( '.modal__content input:not([type="radio"]):not([type="hidden"])' ).val( '' );
		MicroModal.show( 'configure-2fa' );
		if ( jQuery( 'input#basic' ).is( ':visible' ) ) {
			jQuery( 'input#basic' ).click();
		} else {
			jQuery( 'input#geek' ).click();
		}
		jQuery( '[name="wp_2fa_enabled_methods"]' ).change();

		if ( 1 === jQuery( '.wizard-step.active .option-pill' ).length ) {
			jQuery( '.modal__btn.button.button-primary.2fa-choose-method' ).click();
		}
	} );

	jQuery( document ).on( 'click', '.step-setting-wrapper.active .option-pill input[type="checkbox"]', function ( e ) {
		if ( 'backup-codes' !== this.id && !jQuery( this ).hasClass( 'disabled' ) ) {
			if ( true !== jQuery( '#geek' ).prop( 'checked' ) && true !== jQuery( '#basic' ).prop( 'checked' ) ) {
				jQuery( '#backup-codes' ).addClass( 'disabled' );
				jQuery( 'label[for=\'backup-codes\']' ).addClass( 'disabled' );
				window.backupCodes = jQuery( '#backup-codes' ).prop( 'checked' );
				jQuery( '#backup-codes' ).prop( 'checked',false );
				if ( jQuery( '[name="next_step_setting"]' ).length ) {
					jQuery( '[name="next_step_setting"]' ).addClass( 'disabled' ).attr( 'name', 'next_step_setting_disabled' );
				}
			} else {
				
				jQuery( '#backup-codes' ).removeClass( 'disabled' );
				jQuery( 'label[for=\'backup-codes\']' ).removeClass( 'disabled' );
				if ( 'undefined' !== window.backupCodes ) {
					jQuery( '#backup-codes' ).prop( 'checked', window.backupCodes );
				}
				if ( jQuery( '[name="next_step_setting_disabled"]' ).length ) {
					jQuery( '[name="next_step_setting_disabled"]' ).removeClass( 'disabled' ).attr( 'name', 'next_step_setting' );
				}
			}
		} else {
			if ( !jQuery( this ).hasClass( 'disabled' ) ) {
				window.backupCodes = jQuery( '#backup-codes' ).prop( 'checked' );
			} else{
				jQuery( '#backup-codes' ).prop( 'checked', false );
			}
		}
	} );

	jQuery( document ).on( 'click', '#2fa-method-select input[type="checkbox"]', function ( e ) {
		if ( 'backup-codes' !== this.id && !jQuery( this ).hasClass( 'disabled' ) ) {
			if ( true !== jQuery( '#totp' ).prop( 'checked' ) && true !== jQuery( '#hotp' ).prop( 'checked' ) ) {
				jQuery( '#backup-codes' ).addClass( 'disabled' );
				jQuery( 'label[for=\'backup-codes\']' ).addClass( 'disabled' );
				window.backupCodes = jQuery( '#backup-codes' ).prop( 'checked' );
				jQuery( '#backup-codes' ).prop( 'checked',false );
			} else {
				jQuery( '#backup-codes' ).removeClass( 'disabled' );
				jQuery( 'label[for=\'backup-codes\']' ).removeClass( 'disabled' );
				if ( 'undefined' !== window.backupCodes ) {
					jQuery( '#backup-codes' ).prop( 'checked', window.backupCodes );
				}
			}
		} else {
			if ( !jQuery( this ).hasClass( 'disabled' ) ) {
				window.backupCodes = jQuery( '#backup-codes' ).prop( 'checked' );
			} else{
				jQuery( '#backup-codes' ).prop( 'checked', false );
			}
		}
	} );

	jQuery( document ).on( 'click', '[data-close-2fa-modal]', function( e ) {
		e.preventDefault();
		var modalToClose = `#${  jQuery( this ).closest( '.wp2fa-modal' ).attr( 'id' )}`;
		jQuery( modalToClose ).removeClass( 'is-open' ).attr( 'aria-hidden', 'true' );
	} );

	jQuery( document ).on( 'click', '[data-close-2fa-modal-and-refresh]', function( e ) {
		e.preventDefault();
		var modalToClose = `#${  jQuery( this ).closest( '.wp2fa-modal' ).attr( 'id' )}`;
		jQuery( modalToClose ).removeClass( 'is-open' ).attr( 'aria-hidden', 'true' );
		removeShowParam();
	} );

	jQuery( document ).on( 'click', '[data-validate-authcode-ajax]', function( e ) {
		e.preventDefault();
		const thisButton = jQuery( this );
		const actionToRun = 'validate_authcode_via_ajax';
		const nonceValue = jQuery( this ).attr( 'data-nonce' );
		var values = {};
		jQuery.each( jQuery( '.wp-2fa-user-profile-form :input, .wp2fa-modal :input' ).serializeArray(), function( i, field ) {
			values[field.name] = field.value;
		} );
		const currentPageURL = window.location.href;
		const form = values;
		jQuery.ajax( {
			type: 'POST',
			dataType: 'json',
			url: wp2faData.ajaxURL,
			data: {
				action: actionToRun,
				form: values,
				_wpnonce: nonceValue,
			},
			complete: function( data ) {
				if ( false === data.responseJSON.success ) {
					jQuery( thisButton ).parent().find( '.verification-response' ).html( `<span style="color:red">${data.responseJSON.data['error']}</span>` );
				}
				if ( true === data.responseJSON.success ) {
					const currentSubStep = jQuery( this ).parent().parent().find( '.active' ).not( '.step-setting-wrapper' );
					const nextSubStep = jQuery( '#2fa-wizard-config-backup-codes' );
					jQuery( this ).parent().parent().find( '.active' ).not( '.step-setting-wrapper' ).removeClass( 'active' );
					jQuery( '.wizard-step.active' ).removeClass( 'active' );
					jQuery( nextSubStep ).addClass( 'active' );

					jQuery( document ).on( 'click', '[name="save_step"], [data-close-2fa-modal]', function() {
						if ( 'redirectToUrl' in wp2faWizardData &&
								'' != jQuery.trim( wp2faWizardData.redirectToUrl ) ) {
							window.location.replace( wp2faWizardData.redirectToUrl );
						} else {
							removeShowParam();
						}
					} );
				}
			}
		}, );
	}
	);

	jQuery( 'body' ).on( 'click', '.contains-hidden-inputs input[type="radio"]', function( e ) {
		if ( jQuery( this ).hasClass( 'js-nested' ) ) {
			return;
		}

		jQuery( this ).closest( '.contains-hidden-inputs' ).find( '.hidden' ).hide( 200 );
		if ( jQuery( this ).is( '[data-unhide-when-checked]' ) ) {
			const thingToShow = jQuery( this ).attr( 'data-unhide-when-checked' );
			if ( jQuery( this ).is( ':checked' ) ) {
				jQuery( thingToShow ).slideDown( 200 );
			}
		}
	} );


	jQuery( document ).on( 'click', '.dismiss-user-configure-nag', function() {
		const thisNotice = jQuery( this ).closest( '.notice' );
		jQuery.ajax( {
			url: wp2faData.ajaxURL,
			data: {
				action: 'dismiss_nag'
			},
			complete: function() {
				jQuery( thisNotice ).slideUp();
			},
		} );
	} );

	jQuery( document ).on( 'click', '.dismiss-user-reconfigure-nag', function() {
		const thisNotice = jQuery( this ).closest( '.notice' );
		jQuery.ajax( {
			url: wp2faData.ajaxURL,
			data: {
				action: 'wp2fa_dismiss_reconfigure_nag'
			},
			complete: function( data ) {
				jQuery( thisNotice ).slideUp();
			},
		} );
	} );

	jQuery( document ).on( 'click', '[data-trigger-account-unlock]', function() {
		const nonce = jQuery( this ).attr( 'data-nonce' );
		const account = jQuery( this ).attr( 'data-account-to-unlock' );
		jQuery.ajax( {
			url: wp2faData.ajaxURL,
			data: {
				action: 'unlock_account',
				user_id: account,
				wp_2fa_nonce: nonce
			}
		} );
	} );

	jQuery( document ).on( 'click', '.remove-2fa', function( e ) {
		e.preventDefault();
	} );

	jQuery( document ).on( 'click', '.modal__close', function( e ) {
		e.preventDefault();

		if ( jQuery( this ).parent().find( '#notify-users' ).length ) {
			MicroModal.show( 'notify-users' );
			jQuery( '.button-confirm' ).blur();
		}
	} );

	jQuery( document ).on( 'click', '.button-confirm', function ( e ) {
		e.preventDefault();
		MicroModal.close( 'configure-2fa' );
		MicroModal.close( 'notify-users' );
	} );

	jQuery( document ).on( 'click', '.button-decline', function ( e ) {
		e.preventDefault();
	} );

	jQuery( document ).on( 'click', '#close-settings', function ( e ) {
		e.preventDefault();
		MicroModal.close( 'notify-admin-settings-page' );
		window.location.replace( jQuery( this ).data( 'redirect-url' ) );
	} );

	jQuery( document ).on( 'click', '.first-time-wizard', function ( e ) {
		e.preventDefault();
		MicroModal.show( 'notify-admin-settings-page' );
	} );

	jQuery( document ).on( 'click', '[data-trigger-remove-2fa]', function() {
		const nonce = jQuery( this ).attr( 'data-nonce' );
		const account = jQuery( this ).attr( 'data-user-id' );
		jQuery.ajax( {
			url: wp2faData.ajaxURL,
			data: {
				action: 'remove_user_2fa',
				user_id: account,
				wp_2fa_nonce: nonce
			},
			complete: function( data ) {
				location.reload();
			},
		} );
	} );

	jQuery( document ).on( 'click', '[data-submit-2fa-form]', function( e ) {
		jQuery( '#submit' ).click();
	} );

	jQuery( document ).on( 'click', '[data-trigger-setup-email]', function( e ) {
		const actionToRun = 'send_authentication_setup_email';
		if ( jQuery( '#custom-email-address' ).val() ) {
			var emailAddress = jQuery( '#custom-email-address' ).val();
		} else {
			var emailAddress = jQuery( '#use_wp_email' ).val();
		}
		if ( jQuery( this ).hasClass( 'resend-email-code' ) ) {
			var updateBtnText = true;
			var originalBtnText = jQuery( this ).text();
		}
		const userID = jQuery( this ).attr( 'data-user-id' );
		const nonce = jQuery( this ).attr( 'data-nonce' );
		const thisBtn = jQuery( this );
		jQuery.ajax( {
			type: 'POST',
			dataType: 'json',
			url: wp2faData.ajaxURL,
			data: {
				action: actionToRun,
				email_address: emailAddress,
				user_id: userID,
				nonce: nonce
			},
			complete: function( data ) {
				// Nothing to see here.
			},
			success: function( data ) {
				if ( updateBtnText ) {
					jQuery( thisBtn ).find( 'span' ).fadeTo( 100, 0, function() {
						jQuery( thisBtn ).find( 'span' ).delay( 100 );
						jQuery( thisBtn ).find( 'span' ).text( wp2faWizardData.codeReSentText );
						jQuery( thisBtn ).find( 'span' ).fadeTo( 100, 1 );
					} );
					setTimeout( function() {
						jQuery( thisBtn ).find( 'span' ).fadeTo( 100, 0, function() {
							jQuery( thisBtn ).find( 'span' ).delay( 100 );
							jQuery( thisBtn ).find( 'span' ).text( originalBtnText );
							jQuery( thisBtn ).find( 'span' ).fadeTo( 100, 1 );
						} );
					}, 2500 );
				}
			}
		} );
	} );

	jQuery( 'body' ).on( 'click', '.button[name="next_step_setting"]', function( e ) {
		e.preventDefault;
		const currentSubStep = jQuery( this ).closest( '.step-setting-wrapper.active' );
		const nextSubStep = jQuery( currentSubStep ).nextAll('div:not(.hidden)').filter(':first');
		jQuery( currentSubStep ).removeClass( 'active' );
		jQuery( nextSubStep ).addClass( 'active' );
		updateStepTitles();
	} );

	jQuery( document ).on( 'change', '[name="wp_2fa_enabled_methods"]', function( event ) {
		var step = jQuery( '[name="wp_2fa_enabled_methods"]:checked' ).val();
		jQuery( '.2fa-choose-method[data-next-step]' ).attr( 'data-next-step', `2fa-wizard-${step}` );
	} );

	jQuery( 'body' ).on( 'click', '.button[data-name="next_step_setting_modal_wizard"]', function( e ) {
		e.preventDefault;
		var nextStep = jQuery( this ).attr( 'data-next-step' );
		if ( nextStep ) {
			const currentSubStep = jQuery( this ).parent().parent().find( '.active' ).not( '.step-setting-wrapper' );
			const nextSubStep = jQuery( `#${nextStep}` );
			jQuery( this ).parent().parent().find( '.active' ).not( '.step-setting-wrapper' ).removeClass( 'active' );
			jQuery( '.wizard-step.active' ).removeClass( 'active' );
			jQuery( nextSubStep ).addClass( 'active' );
		} else {
			const currentSubStep = jQuery( this ).parent().parent().find( '.active' ).not( '.step-setting-wrapper' );
			const nextSubStep = jQuery( currentSubStep ).next();
			jQuery( '.wizard-step.active' ).removeClass( 'active' );
			jQuery( nextSubStep ).addClass( 'active' );
		}
	} );

	jQuery( 'body' ).on( 'click', '.button[data-trigger-generate-backup-codes]', function( e ) {
		e.preventDefault();
		const actionToRun = 'run_ajax_generate_json';
		const nonceValue = jQuery( this ).attr( 'data-nonce' );
		const userID = jQuery( this ).attr( 'data-user-id' );
		jQuery.ajax( {
			type: 'POST',
			dataType: 'json',
			url: wp2faData.ajaxURL,
			data: {
				action: actionToRun,
				_wpnonce: nonceValue,
				user_id: userID
			},
			complete: function( data ) {
				jQuery( '#backup-codes-wrapper' ).slideUp( 0 );
				jQuery( '.wp2fa-modal.is-open #backup-codes-wrapper, .wp2fa-setup-content #backup-codes-wrapper' ).empty();

				var codes = jQuery.parseJSON( data.responseText );
				var codes = codes.data['codes'];
				jQuery.each( codes, function( index, value ) {
					jQuery( '.wp2fa-modal.is-open #backup-codes-wrapper, .wp2fa-setup-content #backup-codes-wrapper' ).append( `${value} </br>` );
				} );
				jQuery( '#backup-codes-wrapper' ).slideDown( 500 );
				jQuery( '.close-wizard-link' ).text( wp2faWizardData.readyText ).fadeIn( 50 );
			}
		}, );
	}
	);

	jQuery( 'body' ).on( 'click', '.button[data-trigger-reset-key]', function( e ) {
		e.preventDefault();
		if ( jQuery( '.qr-code-wrapper' ).length ) {
			jQuery( '.qr-code-wrapper' ).addClass( 'regenerating' );
		}
		var doReload = jQuery( this ).attr( 'data-trigger-reset-key' );
		const thisButton = jQuery( this );
		const actionToRun = 'regenerate_authentication_key';
		const nonceValue = jQuery( this ).attr( 'data-nonce' );
		const userID = jQuery( this ).attr( 'data-user-id' );
		jQuery.ajax( {
			type: 'POST',
			dataType: 'json',
			url: wp2faData.ajaxURL,
			data: {
				action: actionToRun,
				_wpnonce: nonceValue,
				user_id: userID
			},
			complete: function( data ) {
				if ( jQuery( '.change-2fa-confirm.hidden' ).length ) {
					jQuery( '.change-2fa-confirm.hidden' ).trigger( 'click' );
				}

				// Update modal fields with new value.
				if ( jQuery( '.app-key' ).length ) {
					jQuery( '#wp-2fa-totp-qrcode' ).attr( 'src', data.responseJSON.data['qr'] );
					jQuery( '.app-key' ).text( data.responseJSON.data['key'] );
					jQuery( '[name="wp-2fa-totp-key"]' ).val( data.responseJSON.data['key'] );
					// Purposefully delayed for a cleaner feel.
					setTimeout( function() {
						jQuery( '.qr-code-wrapper' ).removeClass( 'regenerating' );
					}, 500 );

				}
			}
		}, );
	}
	);

	jQuery( 'body' ).on( 'click', '.button[data-trigger-backup-code-download]', function( e ) {
		e.preventDefault();
		const userName = jQuery( this ).attr( 'data-user' );
		const websiteURL = jQuery( this ).attr( 'data-website-url' );
		const preamble = `${wp2faWizardData.codesPreamble} ${userName} on the website ${websiteURL}:\n\n`;
		var codesWrapper = jQuery( '.active #backup-codes-wrapper' ).text().split( ' ' ).join( '\n' );
		download( 'backup_codes.txt', preamble + codesWrapper );
	}
	);

	jQuery( 'body' ).on( 'click', '.button[data-trigger-print]', function( e ) {
		e.preventDefault();
		const userName = jQuery( this ).attr( 'data-user-id' );
		const websiteURL = jQuery( this ).attr( 'data-website-url' );
		const preamble = `${wp2faWizardData.codesPreamble} ${userName} on the website ${websiteURL}:\n\n`;
		const divToPrint = jQuery( '.active #backup-codes-wrapper' )[ 0 ];
		const newWin = window.open( '', 'Print-Window' );
		newWin.document.open();
		newWin.document.write( `<html><body onload="window.print()">${preamble}</br></br>${divToPrint.innerHTML}</body></html>` );
		newWin.document.close();
		setTimeout( function() {
			newWin.close();
		}, 10 );
	}
	);

	// https://stackoverflow.com/questions/3665115/how-to-create-a-file-in-memory-for-user-to-download-but-not-through-server
	function download( filename, text ) {
		const element = document.createElement( 'a' );
		element.setAttribute( 'href', `data:text/plain;charset=utf-8,${encodeURIComponent( text )}` );
		element.setAttribute( 'download', filename );
		element.style.display = 'none';
		document.body.appendChild( element );
		element.click();
		document.body.removeChild( element );
	}

	jQuery( document ).on( 'click', '#custom-email-address', function() {
		jQuery( '#use_custom_email' ).prop( 'checked', true );
	} );

	jQuery( document ).on( 'click', '[data-check-on-click]', function() {
		const thingToCheck = jQuery( this ).attr( 'data-check-on-click' );
		jQuery( thingToCheck ).prop( 'checked', true );
	} );

	jQuery( document ).on( 'click', '[data-trigger-submit-form]', function( e ) {
		e.preventDefault();
		const thingToSubmit = jQuery( this ).attr( 'data-trigger-submit-form' );
		jQuery( '.change-2fa-confirm' ).trigger( 'click' );
	} );

	jQuery( document ).on( 'click', '[data-reload]', function ( e ) {
		removeShowParam();
	});

	function removeShowParam() {
		let url = new URL( location.href );
		let params = new URLSearchParams( url.search );
		params.delete('show'); 
		location.replace( `${location.pathname}?${params}` );
	}

	jQuery( '[name="wp_2fa_settings[enforcement-policy]"]' ).on( "input", function() {
		if ( jQuery( 'input[name="wp_2fa_settings[enforcement-policy]"]:checked' ).val() == 'all-users' ) {
			jQuery( '[data-step-title="Exclude users"]' ).removeClass( 'hidden' );
			updateStepTitles();
		} else {
			jQuery( '[data-step-title="Exclude users"]' ).addClass( 'hidden' );
			updateStepTitles();
		}
	});
} );
