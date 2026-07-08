/**
 * Save Policies New
 *
 * Handles the AJAX submission of all policy settings found inside .wp-2fa-policies-new.
 * Attaches to every element carrying the class js-save-policies-new.
 *
 * No jQuery. No transpiler. Plain, readable vanilla JS.
 *
 * @package wp-2fa
 * @since   3.1.1.2
 */

( function () {
	'use strict';

	/**
	 * Collect all named form fields inside a container element.
	 *
	 * @param {HTMLElement} container
	 * @returns {Object}
	 */
	function collectFields( container ) {
		var data     = {};
		var elements = container.querySelectorAll( 'input, select, textarea' );

		elements.forEach( function ( el ) {
			if ( ! el.name || el.disabled ) {
				return;
			}
			if ( el.type === 'submit' || el.type === 'button' || el.type === 'image' ) {
				return;
			}

			var name  = el.name;
			var value;

			if ( el.type === 'checkbox' ) {
				if ( ! el.checked ) {
					return;
				}
				value = el.value !== '' ? el.value : '1';
			} else if ( el.type === 'radio' ) {
				if ( ! el.checked ) {
					return;
				}
				value = el.value;
			} else {
				value = el.value;
			}

			if ( Object.prototype.hasOwnProperty.call( data, name ) ) {
				if ( ! Array.isArray( data[ name ] ) ) {
					data[ name ] = [ data[ name ] ];
				}
				data[ name ].push( value );
			} else {
				data[ name ] = value;
			}
		} );

		return data;
	}

	/**
	 * Serialise a flat or array-valued object into x-www-form-urlencoded.
	 *
	 * @param {Object} obj
	 * @returns {string}
	 */
	function serialise( obj ) {
		var parts = [];
		for ( var key in obj ) {
			if ( ! Object.prototype.hasOwnProperty.call( obj, key ) ) {
				continue;
			}
			var val = obj[ key ];
			if ( Array.isArray( val ) ) {
				val.forEach( function ( v ) {
					parts.push( encodeURIComponent( key ) + '=' + encodeURIComponent( v ) );
				} );
			} else {
				parts.push( encodeURIComponent( key ) + '=' + encodeURIComponent( val ) );
			}
		}
		return parts.join( '&' );
	}

	/**
	 * Insert a temporary feedback notice immediately after the clicked button.
	 * Any previous notice is removed first.
	 *
	 * @param {HTMLElement} btn
	 * @param {string}      message
	 * @param {string}      type    'success' | 'error'
	 */
	function showNotice( btn, message, type ) {
		var existing = document.querySelector( '.wp2fa-save-notice' );
		if ( existing ) {
			existing.remove();
		}

		var notice       = document.createElement( 'span' );
		notice.className = 'wp2fa-save-notice wp2fa-save-notice--' + type;
		notice.textContent = message;
		notice.style.cssText =
			'margin-left:12px;font-weight:500;' +
			( type === 'error' ? 'color:#d63638;' : 'color:#00a32a;' );

		btn.parentNode.insertBefore( notice, btn.nextSibling );

		// Auto-dismiss after 3.5 s.
		setTimeout( function () {
			if ( notice.parentNode ) {
				notice.remove();
			}
		}, 3500 );
	}

	/**
	 * Show a brief toast notification.
	 *
	 * @param {string} message
	 * @param {string} type    'success' or 'error'
	 */
	function showToast( message, type ) {
		var existing = document.querySelector( '.wp2fa-toast' );
		if ( existing ) {
			existing.remove();
		}

		var toast = document.createElement( 'div' );
		toast.className = 'wp2fa-toast wp2fa-toast--' + type;
		toast.textContent = message;
		document.body.appendChild( toast );

		// Force reflow then add visible class for CSS transition.
		void toast.offsetWidth;
		toast.classList.add( 'wp2fa-toast--visible' );

		setTimeout( function () {
			toast.classList.remove( 'wp2fa-toast--visible' );
			setTimeout( function () {
				toast.remove();
			}, 400 );
		}, 3000 );
	}

	/**
	 * Display a blocking modal dialog listing all settings errors/warnings.
	 *
	 * @param {Array}  errors   Array of {code, message, type} objects.
	 * @param {string} summary  Optional top-level summary message.
	 */
	function showErrorModal( errors, summary ) {
		var cfg = window.wp2faSavePoliciesNew || {};
		var existing = document.getElementById( 'wp2fa-settings-error-modal' );
		if ( existing ) {
			existing.remove();
		}

		var overlay = document.createElement( 'div' );
		overlay.id = 'wp2fa-settings-error-modal';
		overlay.setAttribute( 'role', 'dialog' );
		overlay.setAttribute( 'aria-modal', 'true' );
		overlay.setAttribute( 'aria-labelledby', 'wp2fa-error-modal-title' );
		overlay.style.cssText =
			'position:fixed;top:0;left:0;width:100%;height:100%;' +
			'background:rgba(0,0,0,0.65);z-index:100000;' +
			'display:flex;align-items:center;justify-content:center;';

		var modal = document.createElement( 'div' );
		modal.style.cssText =
			'background:#fff;border-radius:4px;padding:28px 32px 24px;' +
			'max-width:540px;width:90%;max-height:80vh;overflow-y:auto;' +
			'box-shadow:0 8px 32px rgba(0,0,0,0.28);position:relative;';

		var title = document.createElement( 'h2' );
		title.id = 'wp2fa-error-modal-title';
		title.textContent = cfg.errorsModalTitle || 'Settings saved with warnings';
		title.style.cssText = 'margin:0 0 8px;font-size:16px;font-weight:600;color:#1d2327;';
		modal.appendChild( title );

		if ( summary ) {
			var summaryEl = document.createElement( 'p' );
			summaryEl.textContent = summary;
			summaryEl.style.cssText = 'margin:0 0 16px;color:#50575e;font-size:13px;';
			modal.appendChild( summaryEl );
		}

		var list = document.createElement( 'ul' );
		list.style.cssText = 'margin:12px 0 20px;padding:0;list-style:none;';

		errors.forEach( function ( err ) {
			var isError = err.type === 'error';
			var li = document.createElement( 'li' );
			li.style.cssText =
				'display:flex;align-items:flex-start;gap:8px;' +
				'padding:10px 12px;margin-bottom:6px;border-radius:3px;' +
				'border-left:4px solid ' + ( isError ? '#d63638' : '#dba617' ) + ';' +
				'background:' + ( isError ? '#fef7f7' : '#fffbf0' ) + ';';

			var icon = document.createElement( 'span' );
			icon.setAttribute( 'aria-hidden', 'true' );
			icon.style.cssText =
				'flex-shrink:0;font-size:16px;line-height:1.4;' +
				'color:' + ( isError ? '#d63638' : '#dba617' ) + ';';
			icon.textContent = isError ? '\u26A0' : '\u2022';

			var text = document.createElement( 'span' );
			text.style.cssText = 'font-size:13px;line-height:1.5;color:#1d2327;';
			text.textContent = err.message;

			li.appendChild( icon );
			li.appendChild( text );
			list.appendChild( li );
		} );
		modal.appendChild( list );

		var closeBtn = document.createElement( 'button' );
		closeBtn.type = 'button';
		closeBtn.className = 'button button-primary';
		closeBtn.textContent = cfg.errorsModalClose || 'OK, I understand';
		closeBtn.style.cssText = 'display:block;margin-top:4px;';
		closeBtn.addEventListener( 'click', function () { overlay.remove(); } );
		modal.appendChild( closeBtn );

		overlay.addEventListener( 'click', function ( e ) {
			if ( e.target === overlay ) { overlay.remove(); }
		} );

		var escHandler = function ( e ) {
			if ( e.key === 'Escape' || e.keyCode === 27 ) {
				overlay.remove();
				document.removeEventListener( 'keydown', escHandler );
			}
		};
		document.addEventListener( 'keydown', escHandler );

		overlay.appendChild( modal );
		document.body.appendChild( overlay );
		closeBtn.focus();
	}

	/**
	 * Handle the save action.
	 */
	function handleSave() {
		var cfg = window.wp2faSavePoliciesNew;
		if ( ! cfg ) {
			return;
		}

		var container = document.querySelector( '.wp-2fa-policies-new' );
		if ( ! container ) {
			return;
		}

		var buttons = document.querySelectorAll( '.js-save-policies-new' );
		buttons.forEach( function ( btn ) {
			btn.disabled = true;
			btn.textContent = cfg.savingText;
		} );

		var data   = collectFields( container );
		data.action = cfg.action;
		data.nonce  = cfg.nonce;

		var xhr = new XMLHttpRequest();
		xhr.open( 'POST', cfg.ajaxUrl, true );
		xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8' );

		xhr.onreadystatechange = function () {
			if ( xhr.readyState !== 4 ) {
				return;
			}

			buttons.forEach( function ( btn ) {
				btn.disabled = false;
				btn.textContent = cfg.saveText;
			} );

			let btn = buttons[ 0 ] || null;

			if ( xhr.status >= 200 && xhr.status < 300 ) {
				var resp;
				try {
					resp = JSON.parse( xhr.responseText );
				} catch ( e ) {
					showNotice( btn, cfg.errorText, 'error' );
					return;
				}

				if ( resp.success ) {
					if ( resp.data && resp.data.errors && resp.data.errors.length ) {
						showErrorModal( resp.data.errors, resp.data.message || null );
					} else {
						showNotice( btn, resp.data && resp.data.message ? resp.data.message : cfg.successText, 'success' );
					}

					// Show info dialog when a new custom FE page was created.
					if ( resp.data && resp.data.newPageDialogBody && window.wp2faDialog ) {
						var dialogTitle = Object.prototype.hasOwnProperty.call( cfg, 'newPageTitle' ) ? cfg.newPageTitle : 'Settings saved';
						wp2faDialog.alert( {
							title:      dialogTitle,
							message:    resp.data.newPageDialogBody,
							buttonText: cfg.newPageClose || 'OK'
						} );
					}
				} else {
					var msg = resp.data && resp.data.message ? resp.data.message : cfg.errorText;
					showNotice( btn, msg, 'error' );
				}
			} else {
				showNotice( btn, cfg.errorText, 'error' );
			}
		};

		xhr.send( serialise( data ) );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var buttons = document.querySelectorAll( '.js-save-policies-new' );
		buttons.forEach( function ( btn ) {
			btn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				handleSave();
			} );
		} );
	} );
} )();
