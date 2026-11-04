/**
 * Credential settings for premium integrations, shared by current and legacy screens.
 * Secrets and nonces are sent only in POST bodies. No jQuery dependency.
 *
 * @package wp2fa
 */
( function () {
	'use strict';

	var providers = {
		twilio: {
			global: 'wp2faTwilio', check: 'check_twilio_credentials', store: 'store_twilio_api_key',
			fields: { twilio_sid: 'wp-2fa-twilio-sid', twilio_auth: 'wp-2fa-twilio-auth', twilio_id: 'wp-2fa-twilio-id' },
			panel: 'wizard-sid-key', setup: 'data-sid-setup-wizard'
		},
		authy: {
			global: 'wp2faAuthy', check: 'check_authy_api_key', store: 'store_authy_api_key',
			fields: { authy_key: 'wp-2fa-authy-api' }, panel: 'wizard-api-key', setup: 'data-api-setup-wizard'
		},
		clickatell: {
			global: 'wp2faClickatell', check: 'check_clickatell_credentials', store: 'store_clickatell_api_key',
			fields: { clickatell_sid: 'wp-2fa-clickatell-sid' }, panel: 'wizard-clickatell-sid-key', setup: 'data-clickatell-sid-setup-wizard'
		},
		yubico: {
			global: 'wp2faYubico', check: 'check_yubico_credentials', store: 'store_yubico_api_key',
			fields: { yubico_sid: 'wp-2fa-yubico-id', yubico_secret: 'wp-2fa-yubico-secret-key', yubico_api: 'wp-2fa-yubico-api-endpoint' },
			panel: 'wizard-yubico-id-key', setup: 'data-yubico-id-setup-wizard'
		}
	};

	function visible( element, show ) {
		if ( element ) {
			element.classList.toggle( 'hidden', ! show );
			element.style.display = show ? '' : 'none';
		}
	}

	function message( payload ) {
		if ( payload && typeof payload.data === 'string' ) {
			return payload.data;
		}
		if ( payload && Array.isArray( payload.data ) && payload.data[ 0 ] ) {
			return payload.data[ 0 ].message || wp2faIntegrationSettings.errorText;
		}
		return wp2faIntegrationSettings.errorText;
	}

	function dialog( config, text ) {
		var strings = window[ config.global ] || {};
		if ( window.wp2faDialog ) {
			window.wp2faDialog.alert( { title: strings.dialogTitle || '', message: text } );
		} else {
			window.alert( text );
		}
	}

	async function post( action, fields, nonce ) {
		var body = new FormData();
		body.append( 'action', action );
		body.append( '_wpnonce', nonce );
		Object.keys( fields ).forEach( function ( key ) { body.append( key, fields[ key ] ); } );
		var response = await fetch( wp2faIntegrationSettings.ajaxUrl, {
			method: 'POST', credentials: 'same-origin', body: body
		} );
		var result = await response.json();
		if ( ! response.ok || ! result.success ) {
			throw new Error( message( result ) );
		}
		return result;
	}

	function updateStatus( name, config, suffix, configured ) {
		var checkbox = document.getElementById( name + suffix );
		if ( checkbox ) {
			checkbox.checked = configured;
			checkbox.classList.toggle( 'disabled', ! configured );
			if ( configured ) {
				checkbox.removeAttribute( config.setup );
			} else {
				checkbox.setAttribute( config.setup, '' );
			}
			checkbox.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		}
		visible( document.getElementById( config.panel + suffix ), false );
		visible( document.getElementById( name + '-links' + suffix ), configured );
		document.querySelectorAll( '.' + name + '-sub-wrapping' ).forEach( function ( el ) {
			el.classList.toggle( 'disabled', ! configured );
		} );
		document.querySelectorAll( '[data-check-' + name + '-2fa]' ).forEach( function ( button ) {
			var item = button.closest( '.wp2fa-sortable-item' );
			if ( ! item ) { return; }
			if ( configured ) { item.removeAttribute( 'data-needs-setup' ); }
			else { item.setAttribute( 'data-needs-setup', 'true' ); }
			var dot = item.querySelector( '.wp2fa-integration-status-dot' );
			if ( dot ) {
				dot.classList.toggle( 'wp2fa-status-not-configured', ! configured );
				dot.classList.toggle( 'wp2fa-status-configured', configured );
				if ( configured ) { dot.title = wp2faIntegrationSettings.configured; }
			}
			visible( item.querySelector( '.wp2fa-integration-setup-notice' ), ! configured );
		} );
		if ( configured ) {
			document.querySelectorAll( '[for="all-users"], [for="certain-roles-only"], #backup-codes' + suffix + ', label[for="backup-codes' + suffix + '"], [name="next_step_setting"]' ).forEach( function ( el ) {
				el.classList.remove( 'disabled' );
				el.disabled = false;
			} );
		}
	}

	document.addEventListener( 'click', async function ( event ) {
		for ( var name of Object.keys( providers ) ) {
			var config = providers[ name ];
			var button = event.target.closest( '[data-check-' + name + '-2fa], [data-api-remove-' + name + '], [' + config.setup + ']' );
			if ( ! button ) { continue; }
			event.preventDefault();
			if ( button.disabled ) { return; }
			var role = button.getAttribute( 'data-role' ) || '';
			var suffix = role === 'global' ? '' : role;
			if ( button.hasAttribute( config.setup ) ) {
				visible( document.getElementById( config.panel + ( role && role !== 'global' && role.charAt( 0 ) !== '-' ? '-' + role : suffix ) ), true );
				return;
			}
			var nonce = button.getAttribute( 'data-nonce' ) || '';
			var fields = {};
			var inputs = [];
			Object.keys( config.fields ).forEach( function ( key ) {
				var id = config.fields[ key ];
				var input = document.getElementById( id + suffix ) || document.getElementById( id );
				fields[ key ] = input ? input.value : '';
				if ( input ) { inputs.push( input ); }
			} );
			button.disabled = true;
			try {
				if ( button.hasAttribute( 'data-api-remove-' + name ) ) {
					await post( 'remove_wp2fa_' + name + '_settings', {}, nonce );
					inputs.forEach( function ( input ) { input.value = ''; } );
					updateStatus( name, config, suffix, false );
				} else {
					await post( config.check, fields, nonce );
					// Authy's check does not save. Legacy policy cards also enable the method on save.
					if ( name === 'authy' || document.getElementById( name + suffix ) ) {
						var stored = Object.assign( {}, fields );
						if ( name === 'authy' ) { stored.authy_api_key = stored.authy_key; delete stored.authy_key; }
						await post( config.store, stored, nonce );
					}
					updateStatus( name, config, suffix, true );
					dialog( config, ( window[ config.global ] || {} ).keyCheckSuccessful || wp2faIntegrationSettings.configured );
				}
			} catch ( error ) {
				// Do not echo provider responses or exception details that might contain credentials.
				dialog( config, wp2faIntegrationSettings.errorText );
			} finally {
				button.disabled = false;
			}
			return;
		}
	} );
} )();
