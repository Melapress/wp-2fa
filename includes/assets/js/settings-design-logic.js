/**
 * WP 2FA – New Settings Design Logic
 *
 * Handles interactive behaviour for the redesigned policy settings page.
 * Vanilla JS only — no jQuery, no transpiler.
 *
 * @package wp-2fa
 * @since   3.2.0
 */

( function () {
	'use strict';

	/* ──────────────────────────────────────────────
	 * Constants
	 * ────────────────────────────────────────────── */

	var ZERO_SETUP_EMAIL_DATA_ID = 'enable_0_setup_email';
	var EMAIL_BACKUP_CHECKBOX_ID = 'enable-email-backup';
	var SPECIFY_EMAIL_CHECKBOX_ID = 'specify-email_hotp';
	var SORTABLE_ITEM_SELECTOR = '.wp2fa-sortable-item';
	var DISABLED_CLASS = 'disabled';

	/* ──────────────────────────────────────────────
	 * Helper: find the sortable item by data-id
	 * ────────────────────────────────────────────── */

	/**
	 * Returns the <li> sortable item element matching the given data-id value.
	 *
	 * @param {string} dataId  The value of the data-id attribute to find.
	 * @returns {HTMLElement|null}
	 */
	function getSortableItemByDataId( dataId ) {
		return document.querySelector( SORTABLE_ITEM_SELECTOR + '[data-id="' + dataId + '"]' );
	}

	/**
	 * Returns an array of all sortable item elements.
	 *
	 * @returns {HTMLElement[]}
	 */
	function getAllSortableItems() {
		return Array.prototype.slice.call(
			document.querySelectorAll( SORTABLE_ITEM_SELECTOR )
		);
	}

	/* ──────────────────────────────────────────────
	 * Helper: find a checkbox by its ID attribute
	 * ────────────────────────────────────────────── */

	/**
	 * Finds a checkbox input whose ID ends with the given suffix.
	 * Settings_Builder may add a prefix to IDs, so we match the tail.
	 *
	 * @param {string} idSuffix  The ID (or ending portion) to look for.
	 * @returns {HTMLInputElement|null}
	 */
	function findCheckboxById( idSuffix ) {
		// Try exact match first.
		var el = document.getElementById( idSuffix );
		if ( el ) {
			return el;
		}

		// Fallback: find by attribute selector ending with the suffix.
		var candidates = document.querySelectorAll( 'input[type="checkbox"][id$="' + idSuffix + '"]' );
		if ( candidates.length > 0 ) {
			return candidates[ 0 ];
		}

		return null;
	}

	/* ──────────────────────────────────────────────
	 * Disable / Enable helpers
	 * ────────────────────────────────────────────── */

	/**
	 * Disables a sortable item: adds the disabled class,
	 * unchecks its checkbox, and sets it to disabled.
	 *
	 * @param {HTMLElement} item  The <li> sortable item element.
	 */
	function disableSortableItem( item ) {
		item.classList.add( DISABLED_CLASS );
		item.style.opacity = '0.5';
		item.style.pointerEvents = 'none';

		var checkbox = item.querySelector( 'input[type="checkbox"]' );
		if ( checkbox ) {
			checkbox.disabled = true;
			checkbox.checked = false;
		}
	}

	/**
	 * Enables a sortable item: removes the disabled class
	 * and re-enables its checkbox.
	 *
	 * @param {HTMLElement} item  The <li> sortable item element.
	 */
	function enableSortableItem( item ) {
		item.classList.remove( DISABLED_CLASS );
		item.style.opacity = '';
		item.style.pointerEvents = '';

		var checkbox = item.querySelector( 'input[type="checkbox"]' );
		if ( checkbox ) {
			checkbox.disabled = false;
		}
	}

	/**
	 * Disables a standalone checkbox and its parent wrapper.
	 *
	 * @param {HTMLInputElement} checkbox  The checkbox element.
	 */
	function disableStandaloneCheckbox( checkbox ) {
		checkbox.disabled = true;
		checkbox.checked = false;

		// Walk up to the .form-group wrapper and gray it out.
		var wrapper = checkbox.closest( '.form-group' );
		if ( wrapper ) {
			wrapper.classList.add( DISABLED_CLASS );
			wrapper.style.opacity = '0.5';
			wrapper.style.pointerEvents = 'none';
		}
	}

	/**
	 * Enables a standalone checkbox and its parent wrapper.
	 *
	 * @param {HTMLInputElement} checkbox  The checkbox element.
	 */
	function enableStandaloneCheckbox( checkbox ) {
		checkbox.disabled = false;

		var wrapper = checkbox.closest( '.form-group' );
		if ( wrapper ) {
			wrapper.classList.remove( DISABLED_CLASS );
			wrapper.style.opacity = '';
			wrapper.style.pointerEvents = '';
		}
	}

	/* ──────────────────────────────────────────────
	 * Generic toggle-target handler
	 *
	 * Any checkbox (typically a toggle-checkbox) with
	 * a `data-toggle-target` attribute will show or
	 * hide the element matching that CSS selector.
	 * ────────────────────────────────────────────── */

	/**
	 * Applies initial visibility and binds change events
	 * for all checkboxes carrying data-toggle-target.
	 */
	function initToggleTargets() {
		var toggles = document.querySelectorAll( 'input[type="checkbox"][data-toggle-target]' );

		toggles.forEach( function ( checkbox ) {
			var selector = checkbox.getAttribute( 'data-toggle-target' );
			if ( ! selector ) {
				return;
			}

			var target = document.querySelector( selector );
			if ( ! target ) {
				return;
			}

			function applyState() {
				var show = checkbox.checked;
				target.style.display = show ? '' : 'none';

				// Disable / enable form controls so hidden fields are not collected.
				var fields = target.querySelectorAll( 'input, select, textarea' );
				fields.forEach( function ( field ) {
					field.disabled = ! show;
				} );
			}

			// Set initial state.
			applyState();

			// Bind change listener.
			checkbox.addEventListener( 'change', applyState );
		} );
	}

	/* ──────────────────────────────────────────────
	 * Generic radio toggle-target handler
	 *
	 * Any radio button with a `data-toggle-target`
	 * attribute will show the element matching that
	 * CSS selector when checked, and hide it when
	 * another radio in the same name group is selected.
	 * ────────────────────────────────────────────── */

	/**
	 * Applies initial visibility and binds change events
	 * for all radio buttons carrying data-toggle-target.
	 */
	function initRadioToggleTargets() {
		var radios = document.querySelectorAll( 'input[type="radio"][data-toggle-target]' );

		// Collect unique targets keyed by name group so we can manage show/hide.
		var processed = {};

		radios.forEach( function ( radio ) {
			var selector = radio.getAttribute( 'data-toggle-target' );
			var groupName = radio.getAttribute( 'name' );
			if ( ! selector || ! groupName ) {
				return;
			}

			var target = document.querySelector( selector );
			if ( ! target ) {
				return;
			}

			// Avoid duplicate binding per name group + selector.
			var key = groupName + '|' + selector;
			if ( processed[ key ] ) {
				return;
			}
			processed[ key ] = true;

			// Gather all radios in the same name group.
			var groupRadios = document.querySelectorAll( 'input[type="radio"][name="' + groupName + '"]' );

			function applyState() {
				var show = radio.checked;
				target.style.display = show ? '' : 'none';
			}

			// Set initial state.
			applyState();

			// Bind change listener on ALL radios in the group.
			groupRadios.forEach( function ( groupRadio ) {
				groupRadio.addEventListener( 'change', applyState );
			} );
		} );
	}

	/* ──────────────────────────────────────────────
	 * Email Backup → Specify Backup Email visibility
	 *
	 * The "specify-email-choice-wrap" element should
	 * only be visible when the "enable-email-backup"
	 * checkbox is checked.
	 * ────────────────────────────────────────────── */

	var SPECIFY_BACKUP_EMAIL_WRAP_PREFIX = 'specify-email-choice-wrap';
	var WIZARD_EMAIL_BACKUP_CHECKBOX_ID = 'wizard-email-backup';
	var WIZARD_SPECIFY_WRAP_ID = 'wizard-specify-backup-email-wrap';

	/**
	 * Shows or hides the specify-backup-email wrapper based on
	 * the enable-email-backup checkbox state within a given scope.
	 *
	 * @param {HTMLElement} scope  The container to scope queries within.
	 */
	function applyEmailBackupSpecifyVisibility( scope ) {
		// Settings page context.
		var emailBackupCheckbox = scope.querySelector( 'input[type="checkbox"][id$="' + EMAIL_BACKUP_CHECKBOX_ID + '"]' );
		if ( emailBackupCheckbox ) {
			var wrap = scope.querySelector( '[id^="' + SPECIFY_BACKUP_EMAIL_WRAP_PREFIX + '"]' );
			if ( wrap ) {
				var show = emailBackupCheckbox.checked && ! emailBackupCheckbox.disabled;
				wrap.style.display = show ? '' : 'none';
			}
		}

		// Wizard context.
		var wizardCheckbox = scope.querySelector( '#' + WIZARD_EMAIL_BACKUP_CHECKBOX_ID );
		if ( wizardCheckbox ) {
			var wizardWrap = scope.querySelector( '#' + WIZARD_SPECIFY_WRAP_ID );
			if ( wizardWrap ) {
				var wizardShow = wizardCheckbox.checked && ! wizardCheckbox.disabled;
				wizardWrap.style.display = wizardShow ? '' : 'none';
			}
		}
	}

	/**
	 * Binds change listeners on all enable-email-backup checkboxes.
	 */
	function bindEmailBackupSpecifyListener() {
		var checkboxes = document.querySelectorAll( 'input[type="checkbox"][id$="' + EMAIL_BACKUP_CHECKBOX_ID + '"]' );

		checkboxes.forEach( function ( checkbox ) {
			checkbox.addEventListener( 'change', function () {
				var scope = checkbox.closest( '.tabs-wrap' ) || checkbox.closest( '.tab-panel' ) || document;
				applyEmailBackupSpecifyVisibility( scope );
			} );
		} );

		// Wizard context: bind on wizard-email-backup checkbox.
		var wizardCheckbox = document.getElementById( WIZARD_EMAIL_BACKUP_CHECKBOX_ID );
		if ( wizardCheckbox ) {
			wizardCheckbox.addEventListener( 'change', function () {
				applyEmailBackupSpecifyVisibility( document );
			} );
		}
	}

	/**
	 * Applies initial visibility for specify-backup-email wrappers.
	 */
	function initEmailBackupSpecifyVisibility() {
		var scopes = document.querySelectorAll( '.tabs-wrap' );
		if ( scopes.length === 0 ) {
			applyEmailBackupSpecifyVisibility( document );
			return;
		}
		scopes.forEach( function ( scope ) {
			applyEmailBackupSpecifyVisibility( scope );
		} );
	}

	/* ──────────────────────────────────────────────
	 * Core logic: Zero Setup Email exclusivity
	 *
	 * When "Zero setup One-time code via email"
	 * (data-id="0_setup_email") is checked:
	 *   - All OTHER sortable methods become disabled
	 *   - "Allow users to use email based 2FA as
	 *      secondary backup method" becomes disabled
	 *   - "Allow user to specify the email address
	 *      of choice" becomes disabled
	 *
	 * Scoped per parent .tabs-wrap so each role tab
	 * is handled independently.
	 * ────────────────────────────────────────────── */

	/**
	 * Applies or removes the exclusive mode within a given scope element.
	 *
	 * @param {HTMLElement} scope  The container to scope queries within.
	 */
	function applyZeroSetupEmailExclusivityInScope( scope ) {
		var zeroSetupItem = scope.querySelector( SORTABLE_ITEM_SELECTOR + '[data-id="' + ZERO_SETUP_EMAIL_DATA_ID + '"]' );
		if ( ! zeroSetupItem ) {
			return;
		}

		var zeroSetupCheckbox = zeroSetupItem.querySelector( 'input[type="checkbox"]' );
		if ( ! zeroSetupCheckbox ) {
			return;
		}

		var isChecked = zeroSetupCheckbox.checked;

		// 1. Disable / enable all OTHER sortable methods within the same scope.
		var allItems = Array.prototype.slice.call( scope.querySelectorAll( SORTABLE_ITEM_SELECTOR ) );
		allItems.forEach( function ( item ) {
			var itemDataId = item.getAttribute( 'data-id' );
			if ( itemDataId === ZERO_SETUP_EMAIL_DATA_ID ) {
				return;
			}

			if ( isChecked ) {
				disableSortableItem( item );
			} else {
				enableSortableItem( item );
			}
		} );

		// 2. Disable / enable "Allow users to use email based 2FA as secondary backup method".
		var emailBackupCheckbox = scope.querySelector( 'input[type="checkbox"][id$="' + EMAIL_BACKUP_CHECKBOX_ID + '"]' );
		if ( emailBackupCheckbox ) {
			if ( isChecked ) {
				disableStandaloneCheckbox( emailBackupCheckbox );
			} else {
				enableStandaloneCheckbox( emailBackupCheckbox );
			}
		}

		// 3. Disable / enable "Allow user to specify the email address of choice".
		var specifyEmailCheckbox = scope.querySelector( 'input[type="checkbox"][id$="' + SPECIFY_EMAIL_CHECKBOX_ID + '"]' );
		if ( specifyEmailCheckbox ) {
			if ( isChecked ) {
				disableStandaloneCheckbox( specifyEmailCheckbox );
			} else {
				enableStandaloneCheckbox( specifyEmailCheckbox );
			}
		}

		// 4. Re-evaluate specify-backup-email wrap visibility.
		applyEmailBackupSpecifyVisibility( scope );
	}

	/**
	 * Legacy wrapper – applies exclusivity across all tabs.
	 */
	function applyZeroSetupEmailExclusivity() {
		var scopes = document.querySelectorAll( '.tabs-wrap' );
		if ( scopes.length === 0 ) {
			// Fallback to document if there are no tab wrappers.
			applyZeroSetupEmailExclusivityInScope( document );
			return;
		}
		scopes.forEach( function ( scope ) {
			applyZeroSetupEmailExclusivityInScope( scope );
		} );
	}

	/* ──────────────────────────────────────────────
	 * Event binding
	 * ────────────────────────────────────────────── */

	/**
	 * Binds change listeners on all Zero Setup Email checkboxes (one per tab).
	 */
	function bindZeroSetupEmailListener() {
		var zeroSetupItems = document.querySelectorAll( SORTABLE_ITEM_SELECTOR + '[data-id="' + ZERO_SETUP_EMAIL_DATA_ID + '"]' );

		zeroSetupItems.forEach( function ( item ) {
			var checkbox = item.querySelector( 'input[type="checkbox"]' );
			if ( ! checkbox ) {
				return;
			}

			checkbox.addEventListener( 'change', function () {
				var scope = checkbox.closest( '.tabs-wrap' ) || document;
				applyZeroSetupEmailExclusivityInScope( scope );
			} );
		} );
	}

	/* ──────────────────────────────────────────────
	 * Initialisation
	 * ────────────────────────────────────────────── */

	document.addEventListener( 'DOMContentLoaded', function () {
		// Generic toggle-target handler (checkboxes).
		initToggleTargets();

		// Generic toggle-target handler (radio buttons).
		initRadioToggleTargets();

		// Bind the Zero Setup Email exclusivity logic.
		bindZeroSetupEmailListener();

		// Apply the initial state in case the checkbox is already checked on page load.
		applyZeroSetupEmailExclusivity();

		// Bind and apply the email-backup → specify-backup-email visibility.
		bindEmailBackupSpecifyListener();
		initEmailBackupSpecifyVisibility();
	} );

} )();
