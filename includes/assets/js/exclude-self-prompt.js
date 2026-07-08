/**
 * WP 2FA – General Exclude-Self Prompt (New Policies Page)
 *
 * When enforcement is set to "all-users" AND grace period is "no-grace-period",
 * this script shows a modal asking the current admin whether they want to
 * exclude themselves from 2FA policies before saving.
 *
 * Fires on the radio change events. Vanilla JS only.
 *
 * @package wp-2fa
 * @since   4.0.0
 */

( function () {
	'use strict';

	/* ──────────────────────────────────────────────
	 * Config – injected by wp_localize_script
	 * ────────────────────────────────────────────── */

	function getCfg() {
		return window.wp2faExcludeSelfPrompt || {};
	}

	/* ──────────────────────────────────────────────
	 * DOM helpers
	 * ────────────────────────────────────────────── */

	function getContainer() {
		return document.querySelector( '.wp-2fa-policies-new' ) || document.getElementById( 'sitewide-policies-wrap' );
	}

	function getCheckedRadioValue( name ) {
		var container = getContainer();
		if ( ! container ) {
			return '';
		}
		var checked = container.querySelector( 'input[name="' + name + '"]:checked' );
		return checked ? checked.value : '';
	}

	function getExcludedUsersWrap() {
		return document.getElementById( 'excluded-users-item' );
	}

	function isUserAlreadyExcluded( username ) {
		var msaWrap = getExcludedUsersWrap();
		if ( ! msaWrap ) {
			return false;
		}
		var hiddenInput = msaWrap.querySelector( '.wp2fa-msa-value' );
		if ( ! hiddenInput ) {
			return false;
		}
		var ids = hiddenInput.value.split( ',' ).map( function ( s ) { return s.trim(); } ).filter( Boolean );
		return ids.indexOf( username ) !== -1;
	}

	function addUserToExcluded( username ) {
		var msaWrap = getExcludedUsersWrap();
		if ( ! msaWrap ) {
			return;
		}
		var hiddenInput = msaWrap.querySelector( '.wp2fa-msa-value' );
		var tagsInput   = msaWrap.querySelector( '.wp2fa-msa-tags-input' );
		var textInput   = msaWrap.querySelector( '.wp2fa-msa-input' );

		if ( ! hiddenInput || ! tagsInput ) {
			return;
		}

		var ids = hiddenInput.value.split( ',' ).map( function ( s ) { return s.trim(); } ).filter( Boolean );
		if ( ids.indexOf( username ) !== -1 ) {
			return;
		}
		ids.push( username );
		hiddenInput.value = ids.join( ',' );

		var tag       = document.createElement( 'span' );
		tag.className = 'wp2fa-msa-tag';
		tag.setAttribute( 'data-id', username );

		var label = document.createTextNode( username + ' ' );
		tag.appendChild( label );

		var btn       = document.createElement( 'button' );
		btn.type      = 'button';
		btn.className = 'wp2fa-msa-remove';
		btn.setAttribute( 'aria-label', 'Remove' );
		btn.innerHTML = '&times;';
		btn.addEventListener( 'click', function ( e ) {
			e.stopPropagation();
			var currentIds = hiddenInput.value.split( ',' ).map( function ( s ) { return s.trim(); } ).filter( Boolean );
			hiddenInput.value = currentIds.filter( function ( id ) { return id !== username; } ).join( ',' );
			tag.parentNode.removeChild( tag );
		} );
		tag.appendChild( btn );

		if ( textInput ) {
			tagsInput.insertBefore( tag, textInput );
		} else {
			tagsInput.appendChild( tag );
		}
	}

	/* ──────────────────────────────────────────────
	 * Condition check
	 * ────────────────────────────────────────────── */

	function shouldPrompt() {
		var enforcement = getCheckedRadioValue( 'wp_2fa_policy[enforcement-policy]' );
		var grace       = getCheckedRadioValue( 'wp_2fa_policy[grace-policy]' );
		var cfg         = getCfg();

		if ( enforcement !== 'all-users' ) {
			return false;
		}
		if ( grace !== 'no-grace-period' ) {
			return false;
		}
		if ( isUserAlreadyExcluded( cfg.currentUserLogin || '' ) ) {
			return false;
		}
		return true;
	}

	/* ──────────────────────────────────────────────
	 * Modal
	 * ────────────────────────────────────────────── */

	function showExcludeSelfModal() {
		var cfg      = getCfg();
		var username = cfg.currentUserLogin || '';

		var existing = document.getElementById( 'wp2fa-exclude-self-modal' );
		if ( existing ) {
			existing.remove();
		}

		var overlay = document.createElement( 'div' );
		overlay.id  = 'wp2fa-exclude-self-modal';
		overlay.setAttribute( 'role', 'dialog' );
		overlay.setAttribute( 'aria-modal', 'true' );
		overlay.setAttribute( 'aria-labelledby', 'wp2fa-exclude-self-modal-title' );
		overlay.style.cssText =
			'position:fixed;top:0;left:0;width:100%;height:100%;' +
			'background:rgba(0,0,0,0.65);z-index:100000;' +
			'display:flex;align-items:center;justify-content:center;';

		var modal = document.createElement( 'div' );
		modal.style.cssText =
			'background:#fff;border-radius:4px;padding:28px 32px 24px;' +
			'max-width:560px;width:90%;max-height:80vh;overflow-y:auto;' +
			'box-shadow:0 8px 32px rgba(0,0,0,0.28);position:relative;';

		var title       = document.createElement( 'h2' );
		title.id        = 'wp2fa-exclude-self-modal-title';
		title.textContent = cfg.modalTitle || 'Exclude yourself?';
		title.style.cssText = 'margin:0 0 16px;font-size:18px;font-weight:600;color:#1d2327;';
		modal.appendChild( title );

		var body = document.createElement( 'div' );
		body.style.cssText = 'margin:0 0 24px;color:#50575e;font-size:13px;line-height:1.6;';
		body.innerHTML     = cfg.modalBody || '';
		modal.appendChild( body );

		var btnWrap = document.createElement( 'div' );
		btnWrap.style.cssText = 'display:flex;gap:12px;flex-wrap:wrap;';

		var continueBtn       = document.createElement( 'button' );
		continueBtn.type      = 'button';
		continueBtn.className = 'button button-secondary';
		continueBtn.textContent = cfg.continueBtnText || 'Continue anyway';
		continueBtn.addEventListener( 'click', function () {
			overlay.remove();
			document.removeEventListener( 'keydown', escHandler );
		} );

		var excludeBtn       = document.createElement( 'button' );
		excludeBtn.type      = 'button';
		excludeBtn.className = 'button button-primary';
		excludeBtn.textContent = cfg.excludeBtnText || 'Exclude myself from 2FA policies';
		excludeBtn.addEventListener( 'click', function () {
			addUserToExcluded( username );
			overlay.remove();
			document.removeEventListener( 'keydown', escHandler );
		} );

		btnWrap.appendChild( continueBtn );
        btnWrap.appendChild( excludeBtn );
		modal.appendChild( btnWrap );

		if ( cfg.noteText ) {
			var note       = document.createElement( 'p' );
			note.style.cssText = 'margin:16px 0 0;font-size:12px;color:#787c82;font-style:italic;';
			note.textContent   = cfg.noteText;
			modal.appendChild( note );
		}

		overlay.addEventListener( 'click', function ( e ) {
			if ( e.target === overlay ) {
				overlay.remove();
				document.removeEventListener( 'keydown', escHandler );
			}
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
		excludeBtn.focus();
	}

	/* ──────────────────────────────────────────────
	 * Event binding
	 * ────────────────────────────────────────────── */

	function init() {
		var cfg = getCfg();
		if ( ! cfg.currentUserLogin ) {
			return;
		}

		var container = getContainer();
		if ( ! container ) {
			return;
		}

		// Listen to changes on both enforcement-policy and grace-policy radios.
		var radios = container.querySelectorAll(
			'input[name="wp_2fa_policy[enforcement-policy]"], input[name="wp_2fa_policy[grace-policy]"]'
		);

		radios.forEach( function ( radio ) {
			radio.addEventListener( 'change', function () {
				if ( shouldPrompt() ) {
					showExcludeSelfModal();
				}
			} );
		} );
	}

	/* ──────────────────────────────────────────────
	 * Boot
	 * ────────────────────────────────────────────── */

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
