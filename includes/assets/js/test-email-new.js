/**
 * Test Email (New Settings) — handles [data-check-email-2fa] buttons.
 *
 * For each button:
 *   1. Walk up the DOM to find the closest provider-content (or settings-card).
 *   2. Look for a TinyMCE editor first, then a plain <textarea> for the body.
 *   3. Look for an <input[type=text]> for the subject.
 *   4. POST subject + body to the AJAX endpoint; if no body is found the
 *      server sends a generic delivery-test message.
 *
 * Depends on wp2faTestEmail (localized from PHP):
 *   - ajaxUrl, nonce, sendingText, errorText
 *
 * @package wp-2fa
 * @since   2.8.0
 */
( function () {
	'use strict';

	/**
	 * Try to read the content of a TinyMCE editor instance whose textarea
	 * lives inside `container`. Returns the HTML string or empty string.
	 */
	function getTinyMCEContent( container ) {
		if ( typeof window.tinymce === 'undefined' ) {
			return '';
		}
		var textareas = container.querySelectorAll( 'textarea' );
		for ( var i = 0; i < textareas.length; i++ ) {
			var editor = window.tinymce.get( textareas[ i ].id );
			if ( editor ) {
				return editor.getContent();
			}
		}
		return '';
	}

	/**
	 * Find the email body from the closest ancestor that wraps the button.
	 * TinyMCE takes precedence; falls back to a plain <textarea>.
	 */
	function findBody( button ) {
		// Walk up to the provider-content, settings-card, or tab-panel.
		var container = button.closest( '.provider-content' )
			|| button.closest( '.settings-card' )
			|| button.closest( '.tab-panel' )
			|| button.closest( '.settings-page' );

		if ( ! container ) {
			return '';
		}

		// 1. TinyMCE
		var tmceContent = getTinyMCEContent( container );
		if ( tmceContent ) {
			return tmceContent;
		}

		// 2. Plain textarea
		var textarea = container.querySelector( 'textarea' );
		if ( textarea && textarea.value.trim() ) {
			return textarea.value.trim();
		}

		return '';
	}

	/**
	 * Find the email subject from the closest ancestor.
	 */
	function findSubject( button ) {
		var container = button.closest( '.provider-content' )
			|| button.closest( '.settings-card' )
			|| button.closest( '.tab-panel' )
			|| button.closest( '.settings-page' );

		if ( ! container ) {
			return '';
		}

		var input = container.querySelector( 'input[type="text"]' );
		return input ? input.value.trim() : '';
	}

	/**
	 * Show a notice next to the button.
	 */
	function showNotice( button, message, isSuccess ) {
		// Remove any previous notice on this button.
		var prev = button.parentNode.querySelector( '.wp2fa-test-email-notice' );
		if ( prev ) {
			prev.remove();
		}

		var notice = document.createElement( 'span' );
		notice.className = 'wp2fa-test-email-notice';
		notice.style.cssText = 'display:inline-block;margin-left:10px;padding:4px 10px;border-radius:3px;font-size:13px;'
			+ ( isSuccess
				? 'color:#155724;background:#d4edda;border:1px solid #c3e6cb;'
				: 'color:#721c24;background:#f8d7da;border:1px solid #f5c6cb;' );
		notice.innerHTML = message;

		button.parentNode.insertBefore( notice, button.nextSibling );

		// Auto-dismiss after 8 seconds.
		setTimeout( function () {
			if ( notice.parentNode ) {
				notice.remove();
			}
		}, 8000 );
	}

	/**
	 * Handle the click.
	 */
	function onTestEmailClick( e ) {
		var button = e.target.closest( '[data-check-email-2fa]' );
		if ( ! button ) {
			return;
		}
		e.preventDefault();

		// Prevent double-clicks.
		if ( button.disabled ) {
			return;
		}

		var originalValue = button.value;
		button.disabled   = true;
		button.value      = wp2faTestEmail.sendingText;

		var body    = findBody( button );
		var subject = findSubject( button );

		var formData = new FormData();
		formData.append( 'action', 'wp2fa_send_test_email_new' );
		formData.append( 'nonce', wp2faTestEmail.nonce );
		formData.append( 'subject', subject );
		formData.append( 'body', body );

		fetch( wp2faTestEmail.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData,
		} )
		.then( function ( res ) {
			return res.json();
		} )
		.then( function ( data ) {
			var msg = ( data && data.data && data.data.message ) ? data.data.message : '';
			if ( data.success ) {
				showNotice( button, msg, true );
			} else {
				showNotice( button, msg || wp2faTestEmail.errorText, false );
			}
		} )
		.catch( function () {
			showNotice( button, wp2faTestEmail.errorText, false );
		} )
		.finally( function () {
			button.disabled = false;
			button.value    = originalValue;
		} );
	}

	// Event delegation — works for buttons rendered now and in the future.
	document.addEventListener( 'click', onTestEmailClick );
} )();
