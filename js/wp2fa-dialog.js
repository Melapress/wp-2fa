/**
 * WP 2FA – Unified Dialog Utility
 *
 * Provides a simple, vanilla JS modal dialog system used across the plugin
 * (admin and frontend). Replaces legacy jQuery UI .dialog() calls with
 * accessible, keyboard-friendly, consistently styled modals.
 *
 * Usage:
 *   wp2faDialog.alert({ title: '...', message: '...' });
 *   wp2faDialog.confirm({ title: '...', message: '...', onConfirm: fn });
 *   wp2faDialog.open({ title: '...', content: '...', buttons: [...] });
 *   wp2faDialog.close();
 *
 * @package wp-2fa
 * @since   4.0.0
 */

( function () {
	'use strict';

	var OVERLAY_ID = 'wp2fa-dialog-overlay';
	var activeOverlay = null;
	var escHandler = null;

	/* ──────────────────────────────────────────────
	 * Internal: build and show a dialog
	 * ────────────────────────────────────────────── */

	/**
	 * @param {Object} options
	 * @param {string}          options.title      - Dialog title text (plain text, escaped via textContent).
	 * @param {string}          [options.htmlTitle] - Dialog title with HTML markup (uses innerHTML; caller must sanitize).
	 * @param {string}          [options.content]  - HTML content for the body.
	 * @param {string}          [options.message]  - Plain-text or HTML message (alias for content).
	 * @param {Array}           [options.buttons]  - Array of button descriptors.
	 * @param {string}          [options.note]     - Optional footer note text.
	 * @param {Function}        [options.onClose]  - Called when the dialog is closed.
	 * @param {string}          [options.size]     - 'small' (400px) | 'medium' (default, 520px) | 'large' (640px).
	 * @param {string}          [options.overlayClass] - Extra class name for overlay element.
	 * @param {string}          [options.modalClass]   - Extra class name for modal element.
	 */
	function openDialog( options ) {
		// Close any existing dialog first.
		closeDialog();

		var opts = options || {};
		var bodyHtml = opts.content || opts.message || '';
		var buttons = opts.buttons || [];
		var size = opts.size || 'medium';

		// Overlay.
		var overlay = document.createElement( 'div' );
		overlay.id = OVERLAY_ID;
		overlay.className = 'wp2fa-dialog-overlay wp2fa-dialog-overlay--' + size;
		if ( opts.overlayClass ) {
			overlay.className += ' ' + opts.overlayClass;
		}
		overlay.setAttribute( 'role', 'dialog' );
		overlay.setAttribute( 'aria-modal', 'true' );
		var titleText = opts.htmlTitle || opts.title;
		if ( titleText ) {
			overlay.setAttribute( 'aria-labelledby', 'wp2fa-dialog-title' );
		}

		// Modal box.
		var modal = document.createElement( 'div' );
		modal.className = 'wp2fa-dialog-modal';
		if ( opts.modalClass ) {
			modal.className += ' ' + opts.modalClass;
		}

		// Header (title + close button).
		if ( titleText ) {
			var header = document.createElement( 'div' );
			header.className = 'wp2fa-dialog-header';

			var title = document.createElement( 'h2' );
			title.id = 'wp2fa-dialog-title';
			title.className = 'wp2fa-dialog-title';
			if ( opts.htmlTitle ) {
				title.innerHTML = opts.htmlTitle;
			} else {
				title.textContent = opts.title;
			}
			header.appendChild( title );

			var closeBtn = document.createElement( 'button' );
			closeBtn.type = 'button';
			closeBtn.className = 'wp2fa-dialog-close';
			closeBtn.setAttribute( 'aria-label', 'Close' );
			closeBtn.innerHTML = '&times;';
			closeBtn.addEventListener( 'click', function () {
				closeDialog();
				if ( typeof opts.onClose === 'function' ) {
					opts.onClose();
				}
			} );
			header.appendChild( closeBtn );

			modal.appendChild( header );
		}

		// Body.
		var body = document.createElement( 'div' );
		body.className = 'wp2fa-dialog-body';
		body.innerHTML = bodyHtml;
		modal.appendChild( body );

		// Buttons.
		if ( buttons.length > 0 ) {
			var btnWrap = document.createElement( 'div' );
			btnWrap.className = 'wp2fa-dialog-buttons';

			buttons.forEach( function ( btnDef ) {
				var btn = null;

				if ( 'link' === btnDef.element ) {
					btn = document.createElement( 'a' );
					btn.href = '#';
					btn.className = btnDef.className || '';
					btn.textContent = btnDef.text || 'OK';
				} else {
					btn = document.createElement( 'button' );
					btn.type = 'button';
					btn.className = 'button ' + ( btnDef.className || 'button-secondary' );
					btn.textContent = btnDef.text || 'OK';
				}

				btn.addEventListener( 'click', function ( e ) {
					if ( 'link' === btnDef.element ) {
						e.preventDefault();
					}

					if ( typeof btnDef.onClick === 'function' ) {
						btnDef.onClick();
					}
					closeDialog();
				} );
				btnWrap.appendChild( btn );
			} );

			modal.appendChild( btnWrap );
		}

		// Footer note.
		if ( opts.note ) {
			var note = document.createElement( 'p' );
			note.className = 'wp2fa-dialog-note';
			note.textContent = opts.note;
			modal.appendChild( note );
		}

		// Close on overlay click.
		overlay.addEventListener( 'click', function ( e ) {
			if ( e.target === overlay ) {
				closeDialog();
				if ( typeof opts.onClose === 'function' ) {
					opts.onClose();
				}
			}
		} );

		// Close on Escape.
		escHandler = function ( e ) {
			if ( e.key === 'Escape' || e.keyCode === 27 ) {
				closeDialog();
				if ( typeof opts.onClose === 'function' ) {
					opts.onClose();
				}
			}
		};
		document.addEventListener( 'keydown', escHandler );

		overlay.appendChild( modal );
		document.body.appendChild( overlay );
		activeOverlay = overlay;

		// Focus the first button or the close button.
		var focusTarget = modal.querySelector( '.wp2fa-dialog-buttons button, .wp2fa-dialog-buttons a' ) || modal.querySelector( '.wp2fa-dialog-close' );
		if ( focusTarget ) {
			focusTarget.focus();
		}
	}

	/**
	 * Close the currently open dialog.
	 */
	function closeDialog() {
		if ( activeOverlay ) {
			activeOverlay.remove();
			activeOverlay = null;
		}
		if ( escHandler ) {
			document.removeEventListener( 'keydown', escHandler );
			escHandler = null;
		}
	}

	/* ──────────────────────────────────────────────
	 * Public API
	 * ────────────────────────────────────────────── */

	window.wp2faDialog = {

		/**
		 * Show a simple alert dialog (title, message, OK button).
		 *
		 * @param {Object}   options
		 * @param {string}   options.title       - Dialog title.
		 * @param {string}   options.message     - Body message (HTML allowed).
		 * @param {string}   [options.buttonText] - OK button label (default: 'OK').
		 * @param {Function} [options.onClose]   - Callback when closed.
		 * @param {string}   [options.size]      - 'small' | 'medium' | 'large'.
		 */
		alert: function ( options ) {
			var opts = options || {};
			openDialog( {
				title: opts.title || '',
				content: opts.message || '',
				size: opts.size || 'small',
				onClose: opts.onClose || null,
				buttons: [
					{
						text: opts.buttonText || 'OK',
						className: 'button-primary',
						onClick: opts.onClose || null
					}
				]
			} );
		},

		/**
		 * Show a confirmation dialog with confirm and cancel buttons.
		 *
		 * @param {Object}   options
		 * @param {string}   options.title         - Dialog title.
		 * @param {string}   options.message       - Body message (HTML allowed).
		 * @param {string}   [options.confirmText] - Confirm button label (default: 'Confirm').
		 * @param {string}   [options.cancelText]  - Cancel button label (default: 'Cancel').
		 * @param {string}   [options.confirmClass]- CSS class for confirm button (default: 'button-primary').
		 * @param {Function} [options.onConfirm]   - Called on confirm.
		 * @param {Function} [options.onCancel]    - Called on cancel/close.
		 * @param {string}   [options.note]        - Optional footer note.
		 * @param {string}   [options.size]        - 'small' | 'medium' | 'large'.
		 */
		confirm: function ( options ) {
			var opts = options || {};
			openDialog( {
				title: opts.title || '',
				content: opts.message || '',
				size: opts.size || 'medium',
				note: opts.note || '',
				onClose: opts.onCancel || null,
				buttons: [
					{
						text: opts.confirmText || 'Confirm',
						className: opts.confirmClass || 'button-primary',
						onClick: opts.onConfirm || null
					},
					{
						text: opts.cancelText || 'Cancel',
						className: 'button-secondary',
						onClick: opts.onCancel || null
					}
				]
			} );
		},

		/**
		 * Show a fully custom dialog.
		 *
		 * @param {Object}   options
		 * @param {string}   options.title    - Dialog title.
		 * @param {string}   options.content  - Body HTML.
		 * @param {Array}    options.buttons  - Array of { text, className, onClick }.
		 * @param {string}   [options.note]   - Optional footer note.
		 * @param {Function} [options.onClose]- Called when dialog is closed.
		 * @param {string}   [options.size]   - 'small' | 'medium' | 'large'.
		 */
		open: openDialog,

		/**
		 * Programmatically close the active dialog.
		 */
		close: closeDialog
	};

} )();
