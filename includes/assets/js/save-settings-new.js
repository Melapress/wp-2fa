/**
 * Save Settings New
 *
 * Handles the AJAX submission of all settings found inside .main-settings-new.
 * Attaches to every element carrying the class js-save-settings-new.
 *
 * No jQuery. No transpiler. Plain, readable vanilla JS.
 *
 * @package wp-2fa
 * @since   latest
 */

( function () {
	'use strict';

	/* ------------------------------------------------------------------ */
	/* Field collection                                                     */
	/* ------------------------------------------------------------------ */

	/**
	 * Collect all named form fields inside a container element.
	 *
	 * Handles: text, number, email, url, hidden, checkbox, radio, select,
	 * textarea, and colour-picker inputs.
	 *
	 * Returns a plain object whose keys are the raw `name` attribute values
	 * (bracket notation preserved, e.g. "option_group[field]") and whose
	 * values are either a string or an array of strings for multi-value fields.
	 *
	 * Unchecked checkboxes are intentionally omitted so PHP receives nothing
	 * for them — callers should treat a missing key as "false / off".
	 *
	 * @param {HTMLElement} container
	 * @returns {Object}
	 */
	function collectFields( container ) {
		var data     = {};
		var elements = container.querySelectorAll( 'input, select, textarea' );

		elements.forEach( function ( el ) {
			// Skip unnamed, disabled, and submit/button/image controls.
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
					// Send empty string for unchecked checkboxes so the key is
					// still present in POST — PHP merge logic needs it to know
					// the setting was intentionally turned off.
					value = '';
				} else {
					value = el.value !== '' ? el.value : '1';
				}

			} else if ( el.type === 'radio' ) {
				// Skip deselected radios; the selected one wins.
				if ( ! el.checked ) {
					return;
				}
				value = el.value;

			} else {
				// Check if this is a textarea with a TinyMCE editor instance.
				if ( el.tagName === 'TEXTAREA' && window.tinymce && el.id ) {
					var editor = window.tinymce.get( el.id );
					if ( editor ) {
						value = editor.getContent();
					} else {
						value = el.value;
					}
				} else {
					value = el.value;
				}
			}

			// Accumulate multiple values under the same name into an array
			// (handles `name[]` patterns used by some WP settings).
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

	/* ------------------------------------------------------------------ */
	/* Request body serialisation                                           */
	/* ------------------------------------------------------------------ */

	/**
	 * Serialise a flat or array-valued object into an x-www-form-urlencoded
	 * string, preserving bracket notation so PHP can parse it natively.
	 *
	 * @param {Object} data
	 * @returns {string}
	 */
	function buildFormBody( data ) {
		var pairs = [];

		Object.keys( data ).forEach( function ( key ) {
			var val = data[ key ];

			if ( Array.isArray( val ) ) {
				val.forEach( function ( v ) {
					pairs.push( encodeURIComponent( key ) + '=' + encodeURIComponent( v ) );
				} );
			} else {
				pairs.push( encodeURIComponent( key ) + '=' + encodeURIComponent( val ) );
			}
		} );

		return pairs.join( '&' );
	}

	/* ------------------------------------------------------------------ */
	/* Inline feedback notice                                               */
	/* ------------------------------------------------------------------ */

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
	 * Show a brief toast notification (coloured bar).
	 *
	 * @param {string} message
	 * @param {string} type    'success' or 'error'
	 */
	function showToast( message, type ) {
		var existing = document.querySelector( '.wp2fa-toast' );
		if ( existing ) {
			existing.remove();
		}

		var toast       = document.createElement( 'div' );
		toast.className = 'wp2fa-toast wp2fa-toast--' + type;
		toast.textContent = message;
		document.body.appendChild( toast );

		void toast.offsetWidth;
		toast.classList.add( 'wp2fa-toast--visible' );

		setTimeout( function () {
			toast.classList.remove( 'wp2fa-toast--visible' );
			setTimeout( function () {
				toast.remove();
			}, 400 );
		}, 3000 );
	}

	/* ------------------------------------------------------------------ */
	/* Error modal                                                          */
	/* ------------------------------------------------------------------ */

	/**
	 * Display a blocking modal dialog listing all settings errors returned
	 * by the server. The overlay prevents interaction with the page until
	 * the user explicitly dismisses it.
	 *
	 * @param {Array}  errors   Array of {code, message, type} objects.
	 * @param {string} summary  Optional top-level summary message.
	 */
	function showErrorModal( errors, summary ) {
		// Remove any pre-existing modal.
		var existing = document.getElementById( 'wp2fa-settings-error-modal' );
		if ( existing ) {
			existing.remove();
		}

		// --- Overlay (backdrop) ----------------------------------------------
		var overlay = document.createElement( 'div' );
		overlay.id = 'wp2fa-settings-error-modal';
		overlay.setAttribute( 'role', 'dialog' );
		overlay.setAttribute( 'aria-modal', 'true' );
		overlay.setAttribute( 'aria-labelledby', 'wp2fa-error-modal-title' );
		overlay.style.cssText =
			'position:fixed;top:0;left:0;width:100%;height:100%;' +
			'background:rgba(0,0,0,0.65);z-index:100000;' +
			'display:flex;align-items:center;justify-content:center;';

		// --- Modal box -------------------------------------------------------
		var modal = document.createElement( 'div' );
		modal.style.cssText =
			'background:#fff;border-radius:4px;padding:28px 32px 24px;' +
			'max-width:540px;width:90%;max-height:80vh;overflow-y:auto;' +
			'box-shadow:0 8px 32px rgba(0,0,0,0.28);position:relative;';

		// --- Title -----------------------------------------------------------
		var title = document.createElement( 'h2' );
		title.id = 'wp2fa-error-modal-title';
		title.textContent = wp2faSaveSettingsNew.errorsModalTitle || 'Settings saved with warnings';
		title.style.cssText = 'margin:0 0 8px;font-size:16px;font-weight:600;color:#1d2327;';

		// --- Optional summary ------------------------------------------------
		if ( summary ) {
			var summaryEl = document.createElement( 'p' );
			summaryEl.textContent = summary;
			summaryEl.style.cssText = 'margin:0 0 16px;color:#50575e;font-size:13px;';
			modal.appendChild( summaryEl );
		}

		// --- Error list ------------------------------------------------------
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

		// --- Dismiss button --------------------------------------------------
		var closeBtn = document.createElement( 'button' );
		closeBtn.type = 'button';
		closeBtn.className = 'button button-primary';
		closeBtn.textContent = wp2faSaveSettingsNew.errorsModalClose || 'OK, I understand';
		closeBtn.style.cssText = 'display:block;margin-top:4px;';
		closeBtn.addEventListener( 'click', function () {
			overlay.remove();
		} );

		// Also close on backdrop click.
		overlay.addEventListener( 'click', function ( e ) {
			if ( e.target === overlay ) {
				overlay.remove();
			}
		} );

		// Close on Escape key.
		var escHandler = function ( e ) {
			if ( e.key === 'Escape' || e.keyCode === 27 ) {
				overlay.remove();
				document.removeEventListener( 'keydown', escHandler );
			}
		};
		document.addEventListener( 'keydown', escHandler );

		modal.appendChild( title );
		modal.appendChild( list );
		modal.appendChild( closeBtn );
		overlay.appendChild( modal );
		document.body.appendChild( overlay );

		// Move focus into the modal so keyboard users can dismiss immediately.
		closeBtn.focus();
	}

	/* ------------------------------------------------------------------ */
	/* Core save routine                                                    */
	/* ------------------------------------------------------------------ */

	/**
	 * Collect settings from .main-settings-new and POST them via AJAX.
	 * Nonce + capability checks happen server-side in Settings_Page_New::ajax_save().
	 *
	 * @param {HTMLElement} btn  The button that was activated.
	 */
	function saveSettings( btn ) {
		var container = document.querySelector( '.main-settings-new' );
		if ( ! container ) {
			return;
		}

		var fields = collectFields( container );

		// Append the WordPress AJAX action identifier and the security nonce.
		fields.action = wp2faSaveSettingsNew.action;
		fields.nonce  = wp2faSaveSettingsNew.nonce;

		// Optimistic UI: disable the button while the request is in flight.
		btn.disabled    = true;
		btn.textContent = wp2faSaveSettingsNew.savingText;

		var xhr = new XMLHttpRequest();
		xhr.open( 'POST', wp2faSaveSettingsNew.ajaxUrl, true );
		xhr.setRequestHeader(
			'Content-Type',
			'application/x-www-form-urlencoded; charset=UTF-8'
		);

		xhr.onload = function () {
			btn.disabled    = false;
			btn.textContent = wp2faSaveSettingsNew.saveText;

			var response;
			try {
				response = JSON.parse( xhr.responseText );
			} catch ( parseError ) {
				showNotice( btn, wp2faSaveSettingsNew.errorText, 'error' );
				return;
			}

			if ( response && response.success ) {
				var errors = response.data && response.data.errors;
				if ( errors && errors.length ) {
					// Show blocking modal so the user must acknowledge each warning/error.
					showErrorModal( errors, response.data.message || null );
				} else {
					showNotice(
						btn,
						( response.data && response.data.message ) || wp2faSaveSettingsNew.successText,
						'success'
					);
				}
			} else {
				showNotice(
					btn,
					( response && response.data && response.data.message ) || wp2faSaveSettingsNew.errorText,
					'error'
				);
			}
		};

		xhr.onerror = function () {
			btn.disabled    = false;
			btn.textContent = wp2faSaveSettingsNew.saveText;
			showNotice( btn, wp2faSaveSettingsNew.errorText, 'error' );
		};

		xhr.send( buildFormBody( fields ) );
	}

	/* ------------------------------------------------------------------ */
	/* Event wiring                                                         */
	/* ------------------------------------------------------------------ */

	/**
	 * Use event delegation so dynamically inserted buttons are also handled.
	 */
	function init() {
		document.addEventListener( 'click', function ( event ) {
			var btn = event.target.closest( '.js-save-settings-new' );
			if ( ! btn ) {
				return;
			}

			event.preventDefault();
			saveSettings( btn );
		} );
	}

	// Boot as soon as the DOM is interactive.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

}() );
