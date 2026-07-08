/**
 * Policies Hash State — preserves navigation state in the URL hash so users
 * can refresh / share a direct link and land on the same policy section,
 * sub-tab (Method Selection / User Access), and role panel.
 *
 * Hash format:  #section=sitewide-policies&tab=user-access
 *
 *  - section : the settings-page ID without the "-wrap" suffix
 *  - tab     : the sub-tab radio ID without the "tab-" prefix
 *
 * @package wp-2fa
 * @since   3.1.2
 */

/* ── early hide: inject styles immediately (before paint) when hash is
 *    present so the page doesn't flash its default state while JS restores
 *    the correct view ─────────────────────────────────────────────────── */
( function () {
	var hash = window.location.hash.replace( /^#/, '' );
	if ( ! hash || hash.indexOf( 'section=' ) === -1 ) {
		return;
	}
	var s = document.createElement( 'style' );
	s.id = 'wp2fa-policies-hash-loading';
	s.textContent =
		'.wp-2fa-policies-new .main-settings-new { opacity: 0; }' +
		'.wp2fa-policies-loading { display: flex !important; justify-content: center;' +
		'  align-items: center; padding: 60px 0; }' +
		'.wp2fa-policies-loading::after { content: ""; width: 36px; height: 36px;' +
		'  border: 3px solid #ddd; border-top-color: #0073aa;' +
		'  border-radius: 50%; animation: wp2fa-spin .6s linear infinite; }' +
		'@keyframes wp2fa-spin { to { transform: rotate(360deg); } }';
	document.head.appendChild( s );
} )();

( function () {
	'use strict';

	/* ── helpers ──────────────────────────────────────────── */

	function parseHash() {
		var raw = window.location.hash.replace( /^#/, '' );
		if ( ! raw ) {
			return {};
		}
		var state = {};
		raw.split( '&' ).forEach( function ( pair ) {
			var parts = pair.split( '=' );
			if ( parts.length === 2 ) {
				state[ decodeURIComponent( parts[ 0 ] ) ] = decodeURIComponent( parts[ 1 ] );
			}
		} );
		return state;
	}

	function writeHash( state ) {
		var parts = [];
		Object.keys( state ).forEach( function ( key ) {
			if ( state[ key ] !== undefined && state[ key ] !== null && state[ key ] !== '' ) {
				parts.push( encodeURIComponent( key ) + '=' + encodeURIComponent( state[ key ] ) );
			}
		} );
		var hash = parts.length ? '#' + parts.join( '&' ) : window.location.pathname + window.location.search;
		history.replaceState( null, '', hash );
	}

	/* ── loading overlay ─────────────────────────────────── */

	function showLoading() {
		var wrapper = document.querySelector( '.wp-2fa-policies-new' );
		if ( ! wrapper ) {
			return;
		}
		var el = document.createElement( 'div' );
		el.className = 'wp2fa-policies-loading';
		wrapper.insertBefore( el, wrapper.querySelector( '.main-settings-new' ) );
	}

	function hideLoading() {
		var loader = document.querySelector( '.wp2fa-policies-loading' );
		if ( loader ) {
			loader.remove();
		}
		var style = document.getElementById( 'wp2fa-policies-hash-loading' );
		if ( style ) {
			style.remove();
		}
		var main = document.querySelector( '.wp-2fa-policies-new .main-settings-new' );
		if ( main ) {
			main.style.opacity = '';
		}
	}

	/* ── navigate back to policies overview ────────────────── */

	function navigateBack() {
		var container = document.querySelector( '.policies-settings-main-wrapper' );
		if ( ! container ) {
			return;
		}
		// Show the main policies list.
		container.style.display = '';

		// Hide every settings-page panel.
		document.querySelectorAll( '.settings-page' ).forEach( function ( page ) {
			page.style.display = 'none';
		} );

		// Clear the hash.
		writeHash( {} );
	}

	/* ── read current DOM state ──────────────────────────── */

	function readDOMState() {
		var state = {};

		// 1. Which settings-page is visible?
		var pages = document.querySelectorAll( '.settings-page' );
		pages.forEach( function ( page ) {
			if ( page.style.display === 'block' && page.id ) {
				state.section = page.id.replace( /-wrap$/, '' );
			}
		} );

		// 2. Which sub-tab radio is checked inside the visible section?
		if ( state.section ) {
			var wrap = document.getElementById( state.section + '-wrap' );
			if ( wrap ) {
				var checked = wrap.querySelector( '.tab-radio:checked' );
				if ( checked && checked.id ) {
					state.tab = checked.id.replace( /^tab-/, '' );
				}
			}
		}

		return state;
	}

	/* ── restore state from hash ─────────────────────────── */

	function restoreState() {
		var state     = parseHash();
		var container = document.querySelector( '.policies-settings-main-wrapper' );

		if ( ! Object.keys( state ).length || ! container ) {
			hideLoading();
			return;
		}

		// 1. Section — find and show the target settings-page.
		if ( state.section ) {
			var target = document.getElementById( state.section + '-wrap' );
			if ( target ) {
				// Hide every other settings-page first.
				document.querySelectorAll( '.settings-page' ).forEach( function ( p ) {
					p.style.display = 'none';
				} );
				target.style.display = 'block';
				container.style.display = 'none';
			}
		}

		// 2. Sub-tab — programmatically check the correct radio.
		if ( state.tab ) {
			var radio = document.getElementById( 'tab-' + state.tab );
			if ( radio ) {
				radio.checked = true;
			}
		}

		hideLoading();
	}

	/* ── update hash from DOM ────────────────────────────── */

	function syncHash() {
		writeHash( readDOMState() );
	}

	/* ── attach listeners ────────────────────────────────── */

	function installListeners() {
		var container = document.querySelector( '.policies-settings-main-wrapper' );
		if ( ! container ) {
			return;
		}

		// Sidebar list-item clicks → update hash after the inline script runs.
		container.querySelectorAll( '.settings-group li' ).forEach( function ( li ) {
			li.addEventListener( 'click', function () {
				setTimeout( syncHash, 0 );
			} );
		} );

		// Back-button clicks → navigate back to the main overview.
		// Register on every .back-click element so that hash-restored pages
		// also get the listener (the inline script only binds when physically clicked).
		document.querySelectorAll( '.back-click' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				navigateBack();
			} );
		} );

		// Sub-tab radio changes (Method Selection / User Access tabs).
		document.querySelectorAll( '.tab-radio' ).forEach( function ( radio ) {
			radio.addEventListener( 'change', function () {
				syncHash();
			} );
		} );

		// Sub-tab label clicks (for radios driven by <label for="">).
		document.querySelectorAll( '.tab-label' ).forEach( function ( label ) {
			label.addEventListener( 'click', function () {
				setTimeout( syncHash, 0 );
			} );
		} );
	}

	/* ── bootstrap ───────────────────────────────────────── */

	document.addEventListener( 'DOMContentLoaded', function () {
		var needsRestore = window.location.hash.indexOf( 'section=' ) !== -1;

		if ( needsRestore ) {
			showLoading();
		}

		// Run after the inline scripts in policies-settings.php have bound
		// their own DOMContentLoaded listeners.
		setTimeout( function () {
			restoreState();
			installListeners();
		}, 50 );
	} );
} )();
