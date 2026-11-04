/** Legacy provider enrollment uses the same vanilla phone picker and POST transport. @package wp2fa */
( function () {
	'use strict';
	if ( window.wp2faLegacyProvidersLoaded ) { return; }
	window.wp2faLegacyProvidersLoaded = true;
	var providers = {
		twilio: { input: 'wp-2fa-phone-twilio', ready: 'twilio_tel', register: 'register_user_twilio', error: 'error-msg-twilio', global: 'wp2faTwilio' },
		authy: { input: 'wp-2fa-phone', ready: 'authy_tel', register: 'register_user', error: 'error-msg', global: 'wp2faAuthy' },
		clickatell: { input: 'wp-2fa-phone-clickatell', ready: 'clickatell_tel', register: 'register_user_clickatell', error: 'error-msg-clickatell', global: 'wp2faClickatell' },
		yubico: { input: 'wp-2fa-yubico-otp', ready: 'yubico_otp_submit', register: 'register_user_yubico', error: 'error-msg-yubico', global: 'wp2faYubico' }
	};
	Object.keys( providers ).forEach( function ( name ) {
		var config = providers[ name ];
		var phoneInstance = null;
		var phoneInput = null;
		var completed = false;

		function getPhone() {
			var input = document.getElementById( config.input );
			if ( name !== 'yubico' && input && input !== phoneInput && window.intlTelInputVanilla ) {
				phoneInput = input;
				phoneInstance = window.intlTelInputVanilla( input, {
					initialCountry: 'auto', geoIpLookup: window.intlTelInputVanilla.geoIpLookup,
					placeholderNumberType: 'MOBILE'
				} );
			}
			return phoneInstance;
		}

		function showError( text ) {
			var error = document.querySelector( '.' + name + '-step-setting-wrapper.active .verification-response-' + name )
				|| document.getElementById( config.error );
			if ( error ) { error.textContent = text; error.classList.remove( 'hidden' ); }
		}

		async function post( action, fields, nonce ) {
			var body = new FormData();
			body.append( 'action', action );
			body.append( '_wpnonce', nonce );
			Object.keys( fields ).forEach( function ( key ) { body.append( key, fields[ key ] ); } );
			var url = ( window.wp2faWizardData || {} ).ajaxURL || ( window.wp2faIntegrationSettings || {} ).ajaxUrl || window.ajaxurl;
			var response = await fetch( url, { method: 'POST', credentials: 'same-origin', body: body } );
			var result = await response.json();
			if ( ! response.ok || ! result.success ) { throw new Error( 'Request failed' ); }
		}

		function nextStep() {
			var current = document.querySelector( '.' + name + '-step-setting-wrapper.active' );
			if ( ! current ) { return; }
			var next = current.nextElementSibling;
			while ( next && ! next.classList.contains( name + '-step-setting-wrapper' ) ) { next = next.nextElementSibling; }
			if ( next ) {
				current.classList.remove( 'active' ); current.classList.add( 'hidden' );
				next.classList.add( 'active' ); next.classList.remove( 'hidden' );
				var input = next.querySelector( 'input[type="text"], input[type="password"]' );
				if ( input ) { input.focus(); }
			}
		}

		function finish() {
			completed = true;
			var next = document.getElementById( 'wp-2fa-wizard-backup-methods' )
				|| document.getElementById( 'wp-2fa-wizard-email-backup-selected' )
				|| document.getElementById( 'wp-2fa-wizard-config-backup-codes' );
			if ( ! next ) { return; }
			document.querySelectorAll( '.wizard-step.active' ).forEach( function ( step ) { step.classList.remove( 'active' ); } );
			next.classList.add( 'active' );
		}

		document.addEventListener( 'click', async function ( event ) {
			var button = event.target.closest( '#' + config.ready + ', [data-validate-' + name + '-ajax]' );
			if ( ! button ) { return; }
			event.preventDefault();
			if ( button.getAttribute( 'aria-busy' ) === 'true' ) { return; }
			var picker = getPhone();
			var strings = window[ config.global ] || {};
			var nonce = button.getAttribute( 'data-nonce' ) || '';
			var error = document.getElementById( config.error );
			if ( error ) { error.classList.add( 'hidden' ); }
			button.setAttribute( 'aria-busy', 'true' );
			try {
				var phone = picker ? picker.getNumber() : ( document.getElementById( config.input ) || {} ).value || '';
				if ( button.id === config.ready ) {
					if ( ! phone.trim() || ( picker && ! picker.isValidNumber() ) ) {
						showError( ( strings.errorMap || [] )[ picker ? picker.getValidationError() : 0 ] || ( window.wp2faIntegrationSettings || {} ).errorText );
						return;
					}
					var fields = name === 'yubico' ? { otp: phone.trim() } : { phone: phone };
					if ( name === 'authy' ) {
						var country = picker ? picker.getSelectedCountryData().dialCode : '';
						fields.country_code = country;
						fields.phone = country ? phone.replace( '+' + country, '' ) : phone;
					}
					await post( config.register, fields, nonce );
					nextStep();
				} else {
					var token = ( document.getElementById( name + '-token' ) || {} ).value || '';
					if ( ! token.trim() || ! ( name === 'yubico' ? /^[a-zA-Z\s]+$/ : /^[0-9\s]+$/ ).test( token ) ) {
						showError( ( strings.errorMap || [] )[ 0 ] );
						return;
					}
					var verification = { token: token, method: !! document.querySelector( 'button[data-close-2fa-modal]' ) };
					if ( name === 'yubico' ) { verification.otp = phone.trim(); }
					else if ( name !== 'authy' ) { verification.phone = phone; }
					await post( 'check_' + name + '_token', verification, nonce );
					finish();
				}
			} catch ( error ) {
				showError( ( window.wp2faIntegrationSettings || {} ).errorText );
			} finally {
				button.removeAttribute( 'aria-busy' );
			}
		} );

		document.addEventListener( 'click', function ( event ) {
			if ( ! event.target.closest( '[data-close-2fa-modal]' ) ) { return; }
			var steps = document.querySelectorAll( '.' + name + '-step-setting-wrapper' );
			steps.forEach( function ( step, index ) {
				step.classList.toggle( 'active', index === 0 );
				step.classList.toggle( 'hidden', index !== 0 );
			} );
		} );
		document.addEventListener( 'click', async function ( event ) {
			if ( ! completed ) { return; }
			if ( event.target.closest( '#select-backup-method' ) ) {
				event.preventDefault();
				var selected = document.querySelector( 'input[name="backup_method_select"]:checked' );
				var next = selected && document.getElementById( selected.getAttribute( 'data-step' ) );
				if ( next ) {
					document.querySelectorAll( '.wizard-step.active' ).forEach( function ( step ) { step.classList.remove( 'active' ); } );
					next.classList.add( 'active' );
				}
			}
			// Closing the modal is already handled by the shared profile script.
			if ( ! event.target.closest( '[name="save_step"]' ) ) { return; }
			event.preventDefault();
			var data = window.wp2faWizardData || {};
			if ( 'reLogin' in data && String( data.reLoginEnabled ) === String( data.reLogin ).trim() ) {
				try {
					await post( 'custom_ajax_logout', {}, data.nonce || '' );
					window.location.replace( data.loginUrl || '/wp-login.php' );
				} catch ( error ) { showError( ( window.wp2faIntegrationSettings || {} ).errorText ); }
			} else if ( data.redirectToUrl ) {
				window.location.replace( data.redirectToUrl );
			} else if ( window.removeShowParam ) {
				window.removeShowParam();
			}
		} );
		if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', getPhone ); }
		else { getPhone(); }
	} );
} )();
