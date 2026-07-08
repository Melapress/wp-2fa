/**
 * WP 2FA - Profile Backup Codes
 *
 * Handles the "Generate list of backup codes" button on the profile page.
 * Generates codes via AJAX, then displays them in a dialog with
 * Copy, Print, Send via email actions.
 *
 * @package wp2fa
 * @since   4.0.0
 */
( function () {
	'use strict';

	var DIALOG_ID = 'wp2fa-profile-backup-codes-dialog';

	function getAjaxUrl() {
		if ( typeof wp2faProfileData !== 'undefined' && wp2faProfileData.ajaxUrl ) {
			return wp2faProfileData.ajaxUrl;
		}
		return '/wp-admin/admin-ajax.php';
	}

	function getI18n() {
		if ( typeof wp2faProfileData !== 'undefined' && wp2faProfileData.i18n ) {
			return wp2faProfileData.i18n;
		}
		return {};
	}

	/* -------------------------------------------------------
	 * Dialog show / hide
	 * ------------------------------------------------------- */
	function showDialog() {
		var dialog = document.getElementById( DIALOG_ID );
		if ( dialog ) {
			dialog.style.display = '';
			dialog.classList.add( 'wp2fa-profile__confirm--open' );
			document.addEventListener( 'keydown', onEscClose );
		}
	}

	function hideDialog() {
		var dialog = document.getElementById( DIALOG_ID );
		if ( dialog ) {
			dialog.style.display = 'none';
			dialog.classList.remove( 'wp2fa-profile__confirm--open' );
			document.removeEventListener( 'keydown', onEscClose );
		}
	}

	function onEscClose( e ) {
		if ( e.key === 'Escape' ) {
			hideDialog();
			window.location.reload();
		}
	}

	function showStatus( message, isError ) {
		var el = document.getElementById( 'wp2fa-profile-backup-codes-status' );
		if ( el ) {
			el.textContent = message;
			el.style.display = '';
			el.style.color = isError ? '#d63638' : '#00a32a';
			el.style.marginTop = '10px';
		}
	}

	/* -------------------------------------------------------
	 * Generate codes via AJAX and populate dialog
	 * ------------------------------------------------------- */
	function generateAndShowCodes( btn ) {
		var nonce = btn.getAttribute( 'data-nonce' );
		var ajaxUrl = getAjaxUrl();

		btn.disabled = true;
		btn.textContent = btn.textContent.trim() + '…';

		var formData = new FormData();
		formData.append( 'action', 'wp2fa_run_ajax_generate_json' );
		formData.append( 'nonce', nonce );

		fetch( ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData
		} )
			.then( function ( res ) { return res.json(); } )
			.then( function ( data ) {
				btn.disabled = false;
				btn.textContent = btn.textContent.replace( /…$/, '' );

				if ( data.success && data.data && data.data.codes ) {
					populateDialog( data.data.codes );
					showDialog();
				} else {
					var errMsg = ( data.data && typeof data.data === 'string' )
						? data.data
						: 'Failed to generate backup codes.';
					alert( errMsg ); // eslint-disable-line no-alert
				}
			} )
			.catch( function () {
				btn.disabled = false;
				btn.textContent = btn.textContent.replace( /…$/, '' );
				alert( 'Network error. Please try again.' ); // eslint-disable-line no-alert
			} );
	}

	/**
	 * Populate the dialog textarea with formatted codes.
	 *
	 * @param {Array} codes Array of code strings.
	 */
	function populateDialog( codes ) {
		var textarea = document.getElementById( 'wp2fa-profile-backup-codes-textarea' );
		if ( ! textarea ) {
			return;
		}
		var lines = [];
		for ( var i = 0; i < codes.length; i++ ) {
			lines.push( ( i + 1 ) + ': ' + codes[ i ] );
		}
		textarea.value = lines.join( '\n' );
		textarea.rows = Math.max( codes.length, 4 );

		// Store codes for email sending.
		textarea.setAttribute( 'data-raw-codes', JSON.stringify( codes ) );

		// Reset status.
		var status = document.getElementById( 'wp2fa-profile-backup-codes-status' );
		if ( status ) {
			status.style.display = 'none';
			status.textContent = '';
		}
	}

	/* -------------------------------------------------------
	 * Copy codes to clipboard
	 * ------------------------------------------------------- */
	function copyCodes() {
		var textarea = document.getElementById( 'wp2fa-profile-backup-codes-textarea' );
		if ( ! textarea ) {
			return;
		}
		var text = textarea.value;
		var i18n = getI18n();

		if ( navigator.clipboard && window.isSecureContext ) {
			navigator.clipboard.writeText( text ).then( function () {
				showCopyFeedback();
			} );
		} else {
			textarea.select();
			document.execCommand( 'copy' );
			showCopyFeedback();
		}

		function showCopyFeedback() {
			var copyBtn = document.getElementById( 'wp2fa-profile-backup-codes-copy' );
			if ( copyBtn ) {
				var original = copyBtn.textContent;
				copyBtn.textContent = i18n.copied || 'Copied!';
				setTimeout( function () {
					copyBtn.textContent = original;
				}, 2000 );
			}
		}
	}

	/* -------------------------------------------------------
	 * Print codes
	 * ------------------------------------------------------- */
	function printCodes() {
		var textarea = document.getElementById( 'wp2fa-profile-backup-codes-textarea' );
		if ( ! textarea ) {
			return;
		}
		var printWindow = window.open( '', '_blank' );
		if ( printWindow ) {
			var safeText = textarea.value.replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' ).replace( /"/g, '&quot;' );
			printWindow.document.write( '<html><head><title>Backup Codes</title></head><body>' );
			printWindow.document.write( '<h1>Backup Codes</h1>' );
			printWindow.document.write( '<pre>' + safeText + '</pre>' );
			printWindow.document.write( '</body></html>' );
			printWindow.document.close();
			printWindow.print();
		}
	}

	/* -------------------------------------------------------
	 * Send codes via email
	 * ------------------------------------------------------- */
	function sendCodesViaEmail( emailBtn ) {
		var textarea = document.getElementById( 'wp2fa-profile-backup-codes-textarea' );
		if ( ! textarea ) {
			return;
		}
		var nonce = emailBtn.getAttribute( 'data-nonce' );
		var codesText = textarea.value;
		var ajaxUrl = getAjaxUrl();

		emailBtn.disabled = true;

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
					showStatus( getI18n().backupCodesEmailSent || 'Codes sent to your email.', false );
				} else {
					showStatus( getI18n().backupCodesEmailError || 'Failed to send email.', true );
				}
			} )
			.catch( function () {
				emailBtn.disabled = false;
				showStatus( getI18n().networkError || 'Network error. Please try again.', true );
			} );
	}

	/* -------------------------------------------------------
	 * Init
	 * ------------------------------------------------------- */
	function init() {
		var container = document.getElementById( 'wp2fa-profile-section' );
		if ( ! container ) {
			return;
		}

		// Intercept the "Generate list of backup codes" button.
		container.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '[data-wp2fa-generate-backup-codes]' );
			if ( ! btn ) {
				return;
			}
			e.preventDefault();
			e.stopImmediatePropagation();
			generateAndShowCodes( btn );
		} );

		// Dialog: Copy.
		var copyBtn = document.getElementById( 'wp2fa-profile-backup-codes-copy' );
		if ( copyBtn ) {
			copyBtn.addEventListener( 'click', copyCodes );
		}

		// Dialog: Print.
		var printBtn = document.getElementById( 'wp2fa-profile-backup-codes-print' );
		if ( printBtn ) {
			printBtn.addEventListener( 'click', printCodes );
		}

		// Dialog: Send via email.
		var emailBtn = document.getElementById( 'wp2fa-profile-backup-codes-email' );
		if ( emailBtn ) {
			emailBtn.addEventListener( 'click', function () {
				sendCodesViaEmail( emailBtn );
			} );
		}

		// Dialog: Close.
		var closeLink = document.getElementById( 'wp2fa-profile-backup-codes-close' );
		if ( closeLink ) {
			closeLink.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				hideDialog();
				window.location.reload();
			} );
		}

		// Click overlay backdrop to close.
		var dialog = document.getElementById( DIALOG_ID );
		if ( dialog ) {
			dialog.addEventListener( 'click', function ( e ) {
				if ( e.target === dialog ) {
					hideDialog();
					window.location.reload();
				}
			} );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
