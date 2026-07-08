/**
 * Passkeys User Profile – Modal and UI handling.
 *
 * Vanilla JS replacement for the inline jQuery-based modal code.
 * Works with wp.template templates loaded on the page.
 *
 * @package wp2fa
 * @since   4.0.0
 */

/* global wp, wp2faPasskeys */

( function () {
	'use strict';

	// Unicode-safe regex: letters (any language), digits, dash, underscore, space.
	var VALID_NAME_PATTERN = /^[\p{L}\p{N}\-_ ]+$/u;

	var overlay = null;
	var activeCleanup = null;

	/* ------------------------------------------------------------------ */
	/*  Helpers                                                           */
	/* ------------------------------------------------------------------ */

	/**
	 * Create and return the shared overlay element (singleton).
	 */
	function getOverlay() {
		if ( overlay ) {
			return overlay;
		}
		overlay = document.createElement( 'div' );
		overlay.className = 'wp2fa-passkey-overlay';
		overlay.id = 'wp2fa-passkey-overlay';
		overlay.setAttribute( 'aria-hidden', 'true' );
		document.body.appendChild( overlay );
		return overlay;
	}

	/**
	 * Show the overlay with the given HTML content.
	 */
	function showModal( html ) {
		var el = getOverlay();
		el.innerHTML = html;
		el.classList.add( 'is-visible' );
		el.setAttribute( 'aria-hidden', 'false' );

		// Focus the dialog itself for screen readers.
		var dialog = el.querySelector( '[role="dialog"]' );
		if ( dialog ) {
			dialog.focus();
		}

		// Trap focus & listen for Escape.
		activeCleanup = trapFocus( el );
	}

	/**
	 * Hide the overlay and clean up.
	 */
	function hideModal() {
		var el = getOverlay();
		el.classList.remove( 'is-visible' );
		el.setAttribute( 'aria-hidden', 'true' );
		el.innerHTML = '';
		if ( activeCleanup ) {
			activeCleanup();
			activeCleanup = null;
		}
	}

	/**
	 * Simple focus trap for modals.
	 * Returns a cleanup function that removes the listener.
	 */
	function trapFocus( container ) {
		function handler( e ) {
			// Escape key closes.
			if ( e.key === 'Escape' ) {
				e.preventDefault();
				hideModal();
				return;
			}

			if ( e.key !== 'Tab' ) {
				return;
			}

			var focusable = container.querySelectorAll(
				'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
			);
			if ( ! focusable.length ) {
				return;
			}

			var first = focusable[ 0 ];
			var last = focusable[ focusable.length - 1 ];

			if ( e.shiftKey ) {
				if ( document.activeElement === first ) {
					e.preventDefault();
					last.focus();
				}
			} else {
				if ( document.activeElement === last ) {
					e.preventDefault();
					first.focus();
				}
			}
		}

		document.addEventListener( 'keydown', handler, true );
		return function () {
			document.removeEventListener( 'keydown', handler, true );
		};
	}

	/* ------------------------------------------------------------------ */
	/*  Setup Modal                                                        */
	/* ------------------------------------------------------------------ */

	/**
	 * Open the "Set up Passkey Login" modal.
	 */
	function openSetupModal() {
		var tmpl = wp.template( 'wp2fa-passkey-setup-modal' );
		var data = wp2faPasskeys;

		showModal( tmpl( {
			title:        data.setupTitle,
			description:  data.setupDescription,
			stepsHeading: data.stepsHeading,
			step1:        data.step1,
			step2:        data.step2,
			step3:        data.step3,
			cancelLabel:  data.cancelLabel,
			usbLabel:     data.usbLabel,
			passkeyLabel: data.passkeyLabel,
			nonce:        data.nonce,
		} ) );

		// Bind close buttons.
		getOverlay().querySelectorAll( '[data-wp2fa-passkey-close]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				hideModal();
			} );
		} );

		// Overlay click to close.
		getOverlay().addEventListener( 'click', function ( e ) {
			if ( e.target === getOverlay() ) {
				hideModal();
			}
		} );

		// Bind register buttons.
		var registerBtn = document.getElementById( 'wp2fa-passkey-register' );
		var registerUsbBtn = document.getElementById( 'wp2fa-passkey-register-usb' );

		if ( registerBtn ) {
			registerBtn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				handleRegistration( false, this );
			} );
		}

		if ( registerUsbBtn ) {
			registerUsbBtn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				handleRegistration( true, this );
			} );
		}
	}

	/* ------------------------------------------------------------------ */
	/*  Registration flow                                                  */
	/* ------------------------------------------------------------------ */

	/**
	 * Handle passkey registration.
	 * Dispatches a custom event that the existing user-profile.js /
	 * user-profile-ajax.js can listen to, or calls the exposed API.
	 */
	function handleRegistration( isUsb, buttonEl ) {
		var errorEl = document.getElementById( 'wp2fa-passkey-setup-error' );
		if ( errorEl ) {
			errorEl.textContent = '';
		}

		// Disable buttons during registration.
		var registerBtn = document.getElementById( 'wp2fa-passkey-register' );
		var registerUsbBtn = document.getElementById( 'wp2fa-passkey-register-usb' );
		if ( registerBtn ) {
			registerBtn.disabled = true;
		}
		if ( registerUsbBtn ) {
			registerUsbBtn.disabled = true;
		}

		// Dispatch event so the existing registration scripts can hook in.
		var event = new CustomEvent( 'wp2fa-passkey-register', {
			detail: {
				isUsb: isUsb,
				nonce: buttonEl.dataset.nonce,
				button: buttonEl,
			},
		} );
		document.dispatchEvent( event );
	}

	/* ------------------------------------------------------------------ */
	/*  Success / Name prompt modal                                        */
	/* ------------------------------------------------------------------ */

	/**
	 * Open the "Passkey Added!" modal and return a Promise that resolves
	 * with the passkey name.
	 *
	 * This function is exposed globally as window.wp2faOpenPasskeyPrompt
	 * so the existing user-profile.js and user-profile-ajax.js can call it.
	 */
	function openPrompt() {
		return new Promise( function ( resolve ) {
			hideModal();

			var tmpl = wp.template( 'wp2fa-passkey-success' );
			var data = wp2faPasskeys;

			showModal( tmpl( {
				title:       data.successTitle,
				description: data.successDescription,
				placeholder: data.namePlaceholder,
				submitLabel: data.submitLabel,
			} ) );

			var nameInput = document.getElementById( 'wp2fa-passkey-name-input' );
			var nameError = document.getElementById( 'wp2fa-passkey-name-error' );
			var nameSubmit = document.getElementById( 'wp2fa-passkey-name-submit' );

			if ( nameInput ) {
				nameInput.focus();
			}

			// Bind close buttons – resolve empty to use auto-generated name.
			getOverlay().querySelectorAll( '[data-wp2fa-passkey-close]' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function ( e ) {
					e.preventDefault();
					cleanup();
					hideModal();
					resolve( '' );
				} );
			} );

			function validate( value ) {
				if ( ! value ) {
					// Allow empty – server will auto-generate a name.
					return true;
				}
				return VALID_NAME_PATTERN.test( value );
			}

			function handleSubmit( e ) {
				if ( e ) {
					e.preventDefault();
					e.stopPropagation();
				}

				var value = nameInput ? nameInput.value.trim() : '';

				if ( ! validate( value ) ) {
					if ( nameError ) {
						nameError.textContent = data.nameValidationError || 'Only letters, numbers, dashes, underscores, and spaces allowed.';
					}
					if ( nameInput ) {
						nameInput.classList.add( 'is-invalid' );
						nameInput.focus();
					}
					return;
				}

				cleanup();
				hideModal();
				resolve( value );
			}

			function handleKeydown( e ) {
				if ( e.key === 'Enter' ) {
					e.preventDefault();
					e.stopPropagation();
					handleSubmit();
				}
			}

			function cleanup() {
				if ( nameInput ) {
					nameInput.removeEventListener( 'keydown', handleKeydown );
				}
				if ( nameSubmit ) {
					nameSubmit.removeEventListener( 'click', handleSubmit );
				}
			}

			if ( nameSubmit ) {
				nameSubmit.addEventListener( 'click', handleSubmit );
			}
			if ( nameInput ) {
				nameInput.addEventListener( 'keydown', handleKeydown );
				// Clear error on input.
				nameInput.addEventListener( 'input', function () {
					this.classList.remove( 'is-invalid' );
					if ( nameError ) {
						nameError.textContent = '';
					}
				} );
			}
		} );
	}

	/* ------------------------------------------------------------------ */
	/*  Error display helper                                               */
	/* ------------------------------------------------------------------ */

	/**
	 * Show an error message in the setup modal.
	 * Can be called from external scripts.
	 */
	function showSetupError( message ) {
		var errorEl = document.getElementById( 'wp2fa-passkey-setup-error' );
		if ( errorEl ) {
			errorEl.textContent = message;
		}

		// Re-enable buttons.
		var registerBtn = document.getElementById( 'wp2fa-passkey-register' );
		var registerUsbBtn = document.getElementById( 'wp2fa-passkey-register-usb' );
		if ( registerBtn ) {
			registerBtn.disabled = false;
		}
		if ( registerUsbBtn ) {
			registerUsbBtn.disabled = false;
		}
	}

	/* ------------------------------------------------------------------ */
	/*  Table column sorting                                               */
	/* ------------------------------------------------------------------ */

	/**
	 * Initialise client-side sorting for the passkey list table.
	 *
	 * Columns marked with class "sortable" (or "sorted") are made interactive.
	 * Clicking a column header toggles ascending ↔ descending order.
	 * Numeric and text sort types are supported via `data-sort-type` on <th>.
	 */
	function initTableSorting() {
		var table = document.getElementById( 'wp-2fa-passkey-table' );
		if ( ! table ) {
			return;
		}

		var allSortableHeaders = table.querySelectorAll( 'thead th.sortable a, thead th.sorted a, tfoot th.sortable a, tfoot th.sorted a' );

		allSortableHeaders.forEach( function ( link ) {
			link.addEventListener( 'click', function ( e ) {
				e.preventDefault();

				var th = this.closest( 'th' );
				var columnIndex = Array.prototype.indexOf.call( th.parentNode.children, th );
				var sortType = th.getAttribute( 'data-sort-type' ) || 'text';

				// Determine new direction: toggle from current.
				var isCurrentlyAsc = th.classList.contains( 'asc' );
				var newDir = isCurrentlyAsc ? 'desc' : 'asc';

				// Reset all sortable headers (thead + tfoot) to default state.
				table.querySelectorAll( 'thead th, tfoot th' ).forEach( function ( h ) {
					if ( h.classList.contains( 'sorted' ) || h.classList.contains( 'sortable' ) ) {
						h.classList.remove( 'sorted', 'asc', 'desc' );
						h.classList.add( 'sortable', 'asc' );
					}
				} );

				// Mark the clicked column (and its mirror in tfoot/thead) as sorted.
				var selector = 'thead th:nth-child(' + ( columnIndex + 1 ) + '), tfoot th:nth-child(' + ( columnIndex + 1 ) + ')';
				table.querySelectorAll( selector ).forEach( function ( h ) {
					h.classList.remove( 'sortable', 'asc', 'desc' );
					h.classList.add( 'sorted', newDir );
				} );

				// Sort tbody rows.
				var tbody = table.querySelector( 'tbody' );
				var rows = Array.prototype.slice.call( tbody.querySelectorAll( 'tr' ) );

				rows.sort( function ( a, b ) {
					var cellA = a.children[ columnIndex ];
					var cellB = b.children[ columnIndex ];

					if ( ! cellA || ! cellB ) {
						return 0;
					}

					var valA = cellA.getAttribute( 'data-sort-value' );
					var valB = cellB.getAttribute( 'data-sort-value' );

					if ( valA === null ) {
						valA = cellA.textContent.trim();
					}
					if ( valB === null ) {
						valB = cellB.textContent.trim();
					}

					var result;

					if ( sortType === 'numeric' ) {
						var numA = parseFloat( valA ) || 0;
						var numB = parseFloat( valB ) || 0;
						result = numA - numB;
					} else {
						result = valA.localeCompare( valB, undefined, { sensitivity: 'base' } );
					}

					return newDir === 'asc' ? result : -result;
				} );

				rows.forEach( function ( row ) {
					tbody.appendChild( row );
				} );
			} );
		} );
	}

	/* ------------------------------------------------------------------ */
	/*  Init on DOM ready                                                  */
	/* ------------------------------------------------------------------ */

	function init() {
		// Bind the "Add a Passkey" button on the profile page.
		document.addEventListener( 'click', function ( e ) {
			var trigger = e.target.closest( '[data-open-configure-2fa-wizard-passkey]' );
			if ( trigger ) {
				e.preventDefault();
				// Remove beforeunload to allow navigation.
				window.onbeforeunload = null;
				openSetupModal();
			}
		} );

		// Initialise sortable column headers.
		initTableSorting();
	}

	// Use wp.domReady if available, otherwise fallback to DOMContentLoaded.
	if ( typeof wp !== 'undefined' && wp.domReady ) {
		wp.domReady( init );
	} else {
		document.addEventListener( 'DOMContentLoaded', init );
	}

	/* ------------------------------------------------------------------ */
	/*  Public API                                                         */
	/* ------------------------------------------------------------------ */

	window.wp2faOpenPasskeyPrompt = openPrompt;
	window.wp2faShowPasskeySetupError = showSetupError;
	window.wp2faHidePasskeyModal = hideModal;
	window.wp2faOpenPasskeySetup = openSetupModal;
} )();
