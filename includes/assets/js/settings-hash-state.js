/**
 * Settings Hash State — preserves navigation state in the URL hash so users
 * can refresh / share a direct link and land on the same section, sub-tab,
 * and open provider accordion(s).
 *
 * Hash format:  #section=email-settings&tab=templates&provider=0,2
 *
 *  - section  : the settings-page ID without the "-wrap" suffix
 *  - tab      : the radio-button ID without the "tab-" prefix (sub-tabs)
 *  - provider : comma-separated indices of open .provider-item elements
 *
 * @package wp-2fa
 * @since   2.8.0
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
	s.id = 'wp2fa-hash-loading';
	s.textContent =
		'.wp-2fa-settings-new .main-settings-new { opacity: 0; }' +
		'.wp2fa-loading { display: flex !important; justify-content: center;' +
		'  align-items: center; padding: 60px 0; }' +
		'.wp2fa-loading::after { content: ""; width: 36px; height: 36px;' +
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
		var wrapper = document.querySelector( '.wp-2fa-settings-new' );
		if ( ! wrapper ) {
			return;
		}
		var el = document.createElement( 'div' );
		el.className = 'wp2fa-loading';
		wrapper.insertBefore( el, wrapper.querySelector( '.main-settings-new' ) );
	}

	function hideLoading() {
		var loader = document.querySelector( '.wp2fa-loading' );
		if ( loader ) {
			loader.remove();
		}
		var style = document.getElementById( 'wp2fa-hash-loading' );
		if ( style ) {
			style.remove();
		}
		var main = document.querySelector( '.wp-2fa-settings-new .main-settings-new' );
		if ( main ) {
			main.style.opacity = '';
		}
	}

	/* ── navigate back to settings overview ───────────────── */

	function navigateBack() {
		var container = document.querySelector( '.general-settings-main-wrapper' );
		if ( ! container ) {
			return;
		}
		// Show the main settings list.
		container.style.display = '';

		// Hide every settings-page panel.
		document.querySelectorAll( '.settings-page' ).forEach( function ( page ) {
			page.style.display = 'none';
		} );

		// Close any open provider accordions.
		document.querySelectorAll( '.provider-item.open' ).forEach( function ( item ) {
			item.classList.remove( 'open' );
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

		// 3. Which provider-items are open (globally — they only appear in one section)?
		var allProviders = document.querySelectorAll( '.provider-item' );
		var openIndices  = [];
		allProviders.forEach( function ( item, idx ) {
			if ( item.classList.contains( 'open' ) ) {
				openIndices.push( idx );
			}
		} );
		if ( openIndices.length ) {
			state.provider = openIndices.join( ',' );
		}

		return state;
	}

	/* ── restore state from hash ─────────────────────────── */

	function restoreState() {
		var state     = parseHash();
		var container = document.querySelector( '.general-settings-main-wrapper' );

		if ( ! Object.keys( state ).length || ! container ) {
			hideLoading();
			return;
		}

		// 1. Section
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

		// 3. Provider accordions.
		if ( state.provider ) {
			var indices      = state.provider.split( ',' ).map( Number );
			var allProviders = document.querySelectorAll( '.provider-item' );
			indices.forEach( function ( idx ) {
				if ( allProviders[ idx ] ) {
					allProviders[ idx ].classList.add( 'open' );
				}
			} );
		}

		hideLoading();
	}

	/* ── update hash from DOM ────────────────────────────── */

	function syncHash() {
		writeHash( readDOMState() );
	}

	/* ── attach listeners ────────────────────────────────── */

	function installListeners() {
		var container = document.querySelector( '.general-settings-main-wrapper' );
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
		// The inline script in general-settings.php only binds these when the
		// sidebar item is physically clicked — so when we restore from a hash,
		// those listeners are never created. We register our own on every
		// .back-click element and handle the full navigation ourselves.
		document.querySelectorAll( '.back-click' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				navigateBack();
			} );
		} );

		// Sub-tab radio changes.
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

		// Provider accordion toggles (event delegation).
		document.addEventListener( 'click', function ( e ) {
			if ( e.target.closest( '.provider-summary' ) ) {
				setTimeout( syncHash, 0 );
			}
		} );
	}

	/* ── bootstrap ───────────────────────────────────────── */

	document.addEventListener( 'DOMContentLoaded', function () {
		var needsRestore = window.location.hash.indexOf( 'section=' ) !== -1;

		if ( needsRestore ) {
			showLoading();
		}

		// Run after the inline scripts in general-settings.php have bound
		// their own DOMContentLoaded listeners.
		setTimeout( function () {
			restoreState();
			installListeners();
		}, 50 );
	} );
} )();
