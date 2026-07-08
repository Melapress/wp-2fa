/**
 * WP 2FA – New First-Time Setup Wizard
 *
 * Three-screen wizard: Welcome → Steps (with nav) → Finish.
 * Vanilla JS, no jQuery, no transpiler.
 *
 * @package wp-2fa
 * @since   3.2.0
 */

( function () {
	'use strict';

	function boot() {

	var cfg = window.wp2faWizardNew || {};

	/* ── Screen references ────────────────────── */

	var welcomeScreen = document.getElementById( 'wp2fa-wizard-welcome' );
	var stepsScreen   = document.getElementById( 'wp2fa-wizard-steps-screen' );
	var finishScreen  = document.getElementById( 'wp2fa-wizard-finish' );

	if ( ! welcomeScreen || ! stepsScreen || ! finishScreen ) {
		return;
	}

	/* ── Step panels ──────────────────────────── */

	var panels    = Array.prototype.slice.call( stepsScreen.querySelectorAll( '.wp2fa-wizard-panel' ) );
	var navItems  = Array.prototype.slice.call( stepsScreen.querySelectorAll( '.wp2fa-wizard-nav li' ) );
	var continueBtn = stepsScreen.querySelector( '.js-wizard-continue' );
	var finishBtn   = stepsScreen.querySelector( '.js-wizard-finish' );
	var form        = document.getElementById( 'wp2fa-wizard-form' );
	var currentStep = 0;

	/* ── Screen switching ─────────────────────── */

	function showScreen( screen ) {
		welcomeScreen.style.display = 'none';
		welcomeScreen.classList.remove( 'wp2fa-wizard-screen--active' );
		stepsScreen.style.display   = 'none';
		finishScreen.style.display  = 'none';

		screen.style.display = '';
		screen.classList.add( 'wp2fa-wizard-screen--active' );
		window.scrollTo( 0, 0 );
	}

	function setFinishMode( isCurrentUserExcluded ) {
		var excludedBlock = finishScreen.querySelector( '[data-finish-excluded]' );
		var normalBlock   = finishScreen.querySelector( '[data-finish-normal]' );

		if ( ! excludedBlock || ! normalBlock ) {
			return;
		}

		excludedBlock.style.display = isCurrentUserExcluded ? '' : 'none';
		normalBlock.style.display = isCurrentUserExcluded ? 'none' : '';
	}

	/* ── Step navigation ──────────────────────── */

	function showPanel( idx ) {
		panels.forEach( function ( panel, i ) {
			panel.style.display = ( i === idx ) ? '' : 'none';
		} );
		currentStep = idx;
		updateNav();
		updateButtons();
		window.scrollTo( 0, 0 );
	}

	function updateNav() {
		navItems.forEach( function ( li, i ) {
			li.classList.remove( 'is-active', 'is-done' );
			if ( i === currentStep ) {
				li.classList.add( 'is-active' );
			} else if ( i < currentStep ) {
				li.classList.add( 'is-done' );
			}
		} );
	}

	function updateButtons() {
		var isLast = ( currentStep === panels.length - 1 );
		if ( continueBtn ) {
			continueBtn.style.display = isLast ? 'none' : '';
		}
		if ( finishBtn ) {
			finishBtn.style.display = isLast ? '' : 'none';
		}
		validateMethodsPanel();
	}

	/* ── Methods-panel validation ─────────────── */

	var methodsPanel = stepsScreen.querySelector( '[data-panel="methods"]' );

	function hasCheckedMethod() {
		if ( ! methodsPanel ) {
			return true;
		}
		var checkboxes = methodsPanel.querySelectorAll( '.wp2fa-sortable-checkbox input[type="checkbox"], .wizard-method-card input[type="checkbox"]' );
		for ( var i = 0; i < checkboxes.length; i++ ) {
			if ( checkboxes[ i ].checked ) {
				return true;
			}
		}
		return false;
	}

	function validateMethodsPanel() {
		if ( currentStep !== 0 || ! methodsPanel ) {
			return;
		}
		var valid  = hasCheckedMethod();
		var notice = methodsPanel.querySelector( '.wp2fa-wizard-methods-notice' );

		if ( ! valid ) {
			if ( continueBtn ) {
				continueBtn.disabled = true;
				continueBtn.classList.add( 'disabled' );
			}
			if ( ! notice ) {
				notice = document.createElement( 'div' );
				notice.className = 'wp2fa-wizard-methods-notice';
				notice.textContent = cfg.methodsRequiredText || 'Please select at least one 2FA method';
				var desc = methodsPanel.querySelector( 'p.description' );
				if ( desc ) {
					desc.parentNode.insertBefore( notice, desc.nextSibling );
				} else {
					methodsPanel.insertBefore( notice, methodsPanel.firstChild );
				}
			}
		} else {
			if ( continueBtn ) {
				continueBtn.disabled = false;
				continueBtn.classList.remove( 'disabled' );
			}
			if ( notice ) {
				notice.remove();
			}
		}
	}

	// Listen for checkbox changes inside the methods panel.
	if ( methodsPanel ) {
		methodsPanel.addEventListener( 'change', function ( e ) {
			if ( e.target.matches( '.wp2fa-sortable-checkbox input[type="checkbox"], .wizard-method-card input[type="checkbox"]' ) ) {
				validateMethodsPanel();
			}
		} );
	}

	function nextPanel() {
		if ( currentStep < panels.length - 1 ) {
			showPanel( currentStep + 1 );
		}
	}

	/* ── Collect form fields ──────────────────── */

	function collectFields() {
		if ( ! form ) {
			return {};
		}
		var data     = {};
		var elements = form.querySelectorAll( 'input, select, textarea' );

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
				if ( el.checked ) {
					value = el.value !== '' ? el.value : '1';
				} else {
					if ( /\[\]$/.test( name ) ) {
						return;
					}
					value = '';
				}
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

	/* ── AJAX save ────────────────────────────── */

	function saveWizard( btn ) {
		var origText = btn.textContent;
		btn.disabled = true;
		btn.textContent = cfg.savingText || 'Saving…';

		var fields = collectFields();
		var body   = serialise( fields );
		body += '&action=' + encodeURIComponent( cfg.action || 'wp2fa_wizard_save' );
		body += '&nonce=' + encodeURIComponent( cfg.nonce || '' );

		var xhr = new XMLHttpRequest();
		xhr.open( 'POST', cfg.ajaxUrl || window.ajaxurl, true );
		xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8' );

		xhr.onload = function () {
			btn.disabled = false;
			btn.textContent = origText;

			if ( xhr.status >= 200 && xhr.status < 300 ) {
				try {
					var resp = JSON.parse( xhr.responseText );
					if ( resp.success ) {
							if ( resp.data && typeof resp.data.isCurrentUserExcluded !== 'undefined' ) {
								setFinishMode( !! resp.data.isCurrentUserExcluded );
							}
						showScreen( finishScreen );
						return;
					}
					if ( resp.data && resp.data.message ) {
						showError( btn, resp.data.message );
						return;
					}
				} catch ( e ) { /* fall through */ }
			}
			showError( btn, cfg.errorText || 'An error occurred. Please try again.' );
		};

		xhr.onerror = function () {
			btn.disabled = false;
			btn.textContent = origText;
			showError( btn, cfg.errorText || 'An error occurred. Please try again.' );
		};

		xhr.send( body );
	}

	function showError( btn, message ) {
		var parent   = btn.parentNode;
		var existing = parent.querySelector( '.wp2fa-wizard-error' );
		if ( existing ) {
			existing.remove();
		}

		var notice = document.createElement( 'span' );
		notice.className = 'wp2fa-wizard-error';
		notice.textContent = message;
		parent.insertBefore( notice, btn.nextSibling );

		setTimeout( function () {
			if ( notice.parentNode ) {
				notice.remove();
			}
		}, 5000 );
	}

	/* ── Toggle sub-fields (grace period, etc.) ── */

	function initToggles() {
		var radios = document.querySelectorAll( '[data-toggle-target]' );
		radios.forEach( function ( radio ) {
			var targetSel = radio.getAttribute( 'data-toggle-target' );
			if ( ! targetSel ) {
				return;
			}

			radio.addEventListener( 'change', function () {
				var target = document.querySelector( targetSel );
				if ( target ) {
					target.style.display = radio.checked ? '' : 'none';
				}
			} );

			// Hide when a sibling radio (same name) is selected.
			var siblings = document.querySelectorAll( 'input[type="radio"][name="' + radio.name + '"]' );
			siblings.forEach( function ( sib ) {
				if ( sib === radio ) {
					return;
				}
				sib.addEventListener( 'change', function () {
					var target = document.querySelector( targetSel );
					if ( target && sib.checked ) {
						target.style.display = 'none';
					}
				} );
			} );
		} );
	}

	/* ── Multi-select AJAX search wiring ────────── */

	function initMultiSelectSearch() {
		if ( ! window.wp2faSavePoliciesNew ) {
			window.wp2faSavePoliciesNew = {
				ajaxUrl:      cfg.ajaxUrl,
				nonce:        cfg.searchNonce || cfg.nonce,
				searchAction: cfg.searchAction || 'wp2fa_search_policy_items',
			};
		}
	}

	/* ── Event delegation ─────────────────────── */

	// Welcome screen: "Let's get started!" button.
	welcomeScreen.addEventListener( 'click', function ( e ) {
		if ( e.target.closest( '.js-wizard-start' ) ) {
			showScreen( stepsScreen );
			showPanel( 0 );
		}
	} );

	// Steps screen: Continue and Finish buttons.
	stepsScreen.addEventListener( 'click', function ( e ) {
		if ( e.target.closest( '.js-wizard-continue' ) ) {
			if ( continueBtn && continueBtn.disabled ) {
				return;
			}
			nextPanel();
			return;
		}
		if ( e.target.closest( '.js-wizard-finish' ) ) {
			saveWizard( e.target.closest( '.js-wizard-finish' ) );
		}
	} );

	/* ── Skip wizard confirmation ─────────────── */

	document.addEventListener( 'click', function ( e ) {
		var skipLink = e.target.closest( '.wp2fa-wizard-skip-link' );
		if ( ! skipLink ) {
			return;
		}

		// Don't show confirmation on the finish screen.
		if ( skipLink.closest( '#wp2fa-wizard-finish' ) ) {
			return;
		}

		e.preventDefault();

		var targetUrl = skipLink.getAttribute( 'href' );

		wp2faDialog.confirm( {
			title: '',
			message: cfg.skipConfirmMessage || '',
			confirmText: cfg.skipConfirmOk || 'OK',
			cancelText: cfg.skipConfirmCancel || 'Cancel',
			onConfirm: function () {
				window.location.href = targetUrl;
			}
		} );
	} );

	/* ── Initialise ──────────────────────────── */

	initToggles();
	initMultiSelectSearch();
	updateButtons();

	} // end boot()

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}

} )();
