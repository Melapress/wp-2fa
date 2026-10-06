/**
 * Unified License page AJAX handlers.
 *
 * Handles license activation, deactivation, and sync for both
 * Freemius (sk_ prefix) and EDD license keys through a single
 * unified interface.
 *
 * Plugin-agnostic — all strings, config, and element ID prefix are
 * passed via the localized `unifiedLicense` object from PHP.
 *
 * @since 3.3.0
 */
( function() {
	'use strict';

	const UnifiedLicense = {

		/** @type {HTMLElement|null} Notice message container. */
		messageEl: null,

		/** @type {string} Element ID prefix (e.g. 'wp2fa'). */
		prefix: '',

		/** @type {string} Stored original license key for cancel restore. */
		originalKey: '',

		/** @type {boolean} Whether the change license flow is active. */
		isChanging: false,

		/**
		 * Initialize cached elements and bind events.
		 */
		init() {
			this.prefix    = unifiedLicense.prefix || 'wp2fa';
			this.messageEl = document.getElementById( this.prefix + '-license-message' );
			this.bindEvents();
		},

		/**
		 * Bind click events to license action buttons.
		 */
		bindEvents() {
			const activateBtn   = document.getElementById( this.prefix + '-license-activate' );
			const deactivateBtn = document.getElementById( this.prefix + '-license-deactivate' );
			const syncBtn       = document.getElementById( this.prefix + '-license-sync' );

			if ( activateBtn ) {
				activateBtn.addEventListener( 'click', () => this.activate() );
			}

			if ( deactivateBtn ) {
				deactivateBtn.addEventListener( 'click', () => this.deactivate() );
			}

			if ( syncBtn ) {
				syncBtn.addEventListener( 'click', () => this.sync() );
			}

			const changeBtn = document.getElementById( this.prefix + '-license-change' );

			if ( changeBtn ) {
				changeBtn.addEventListener( 'click', () => this.startChange() );
			}
		},

		/**
		 * Show a notice message on the license page.
		 *
		 * @param {string} text - The message text.
		 * @param {string} type - Notice type: 'success', 'error', 'warning'.
		 */
		showMessage( text, type ) {
			this.messageEl.className = 'wp2fa-license-notice';
			const typeMap = { success: 'success', error: 'error', warning: 'error' };
			this.messageEl.classList.add( 'wp2fa-license-notice--' + ( typeMap[ type ] || 'info' ) );
			this.messageEl.textContent = text;
		},

		/**
		 * Send a POST request to the WordPress AJAX endpoint.
		 *
		 * @param {string} action    - The AJAX action name.
		 * @param {Object} extraData - Additional key/value pairs to send.
		 *
		 * @return {Promise<Object>} Parsed JSON response.
		 */
		async postAjax( action, extraData = {} ) {
			const body = new URLSearchParams( {
				action,
				nonce: unifiedLicense.nonce,
				...extraData,
			} );

			const response = await fetch( unifiedLicense.ajaxUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
				},
				body,
			} );

			return response.json();
		},

		/**
		 * Handle license activation.
		 *
		 * Sends the key to the unified AJAX endpoint which detects
		 * key type (sk_ for Freemius, otherwise EDD) server-side.
		 */
		async activate() {
			const button     = document.getElementById( this.prefix + '-license-activate' );
			const licenseKey = document.getElementById( this.prefix + '-license-key' ).value.trim();

			if ( ! licenseKey ) {
				this.showMessage( unifiedLicense.i18n.enterLicenseKey, 'error' );
				return;
			}

			button.disabled    = true;
			button.textContent = unifiedLicense.i18n.activatingText;
			this.messageEl.className = 'wp2fa-license-notice';

			try {
				const response = await this.postAjax( unifiedLicense.actions.activate, {
					license_key: licenseKey,
				} );

				if ( response.success ) {
					const msg = response.data && response.data.message
						? response.data.message
						: unifiedLicense.i18n.activatedSuccess;
					this.showMessage( msg, 'success' );
					setTimeout( () => {
						window.location.href = unifiedLicense.redirectUrl;
					}, 1000 );
				} else {
					const msg = response.data && response.data.message
						? response.data.message
						: unifiedLicense.i18n.activationFailed;
					this.showMessage( msg, 'error' );
					button.disabled    = false;
					button.textContent = unifiedLicense.i18n.activateBtn;
				}
			} catch ( e ) {
				this.showMessage( unifiedLicense.i18n.networkError, 'error' );
				button.disabled    = false;
				button.textContent = unifiedLicense.i18n.activateBtn;
			}
		},

		/**
		 * Handle license deactivation.
		 */
		async deactivate() {
			const button = document.getElementById( this.prefix + '-license-deactivate' );

			button.disabled    = true;
			button.textContent = unifiedLicense.i18n.deactivatingText;
			this.messageEl.className = 'wp2fa-license-notice';

			try {
				const response = await this.postAjax( unifiedLicense.actions.deactivate );

				if ( response.success ) {
					const msg = response.data && response.data.message
						? response.data.message
						: unifiedLicense.i18n.deactivatedSuccess;
					this.showMessage( msg, 'success' );
					setTimeout( () => {
						window.location.href = unifiedLicense.redirectUrl;
					}, 1000 );
				} else {
					const msg = response.data && response.data.message
						? response.data.message
						: unifiedLicense.i18n.deactivationFailed;
					this.showMessage( msg, 'error' );
					button.disabled    = false;
					button.textContent = unifiedLicense.i18n.deactivateBtn;
				}
			} catch ( e ) {
				this.showMessage( unifiedLicense.i18n.networkError, 'error' );
				button.disabled    = false;
				button.textContent = unifiedLicense.i18n.deactivateBtn;
			}
		},

		/**
		 * Handle license sync.
		 */
		async sync() {
			const button = document.getElementById( this.prefix + '-license-sync' );

			button.disabled    = true;
			button.textContent = unifiedLicense.i18n.syncingText;
			this.messageEl.className = 'wp2fa-license-notice';

			try {
				const response = await this.postAjax( unifiedLicense.actions.sync );

				if ( response.success ) {
					const msg = response.data && response.data.message
						? response.data.message
						: unifiedLicense.i18n.syncedSuccess;
					this.showMessage( msg, 'success' );
					setTimeout( () => {
						window.location.reload();
					}, 1000 );
				} else {
					const msg = response.data && response.data.message
						? response.data.message
						: unifiedLicense.i18n.syncFailed;
					this.showMessage( msg, 'error' );
				}
			} catch ( e ) {
				this.showMessage( unifiedLicense.i18n.networkError, 'error' );
			}

			button.disabled    = false;
			button.textContent = unifiedLicense.i18n.syncBtn;
		},

		/**
		 * Start the license change flow.
		 *
		 * Hides existing buttons, makes the input editable,
		 * and shows "Activate New" + "Cancel" buttons.
		 */
		startChange() {
			if ( this.isChanging ) {
				return;
			}

			this.isChanging = true;

			const input         = document.getElementById( this.prefix + '-license-key' );
			const deactivateBtn = document.getElementById( this.prefix + '-license-deactivate' );
			const syncBtn       = document.getElementById( this.prefix + '-license-sync' );
			const changeBtn     = document.getElementById( this.prefix + '-license-change' );

			// Store original key for cancel restore.
			this.originalKey = input.value;

			// Hide existing buttons.
			deactivateBtn.style.display = 'none';
			syncBtn.style.display       = 'none';
			changeBtn.style.display     = 'none';

			// Make input editable.
			input.removeAttribute( 'readonly' );
			input.type  = 'text';
			input.value = '';
			input.focus();

			// Create "Activate New" button in the input row (same position as Deactivate).
			const activateNewBtn       = document.createElement( 'button' );
			activateNewBtn.type        = 'button';
			activateNewBtn.id          = this.prefix + '-license-activate-new';
			activateNewBtn.className   = 'wp2fa-license-btn';
			activateNewBtn.textContent = unifiedLicense.i18n.activateNewBtn;
			activateNewBtn.addEventListener( 'click', () => this.changeLicense() );
			deactivateBtn.parentNode.insertBefore( activateNewBtn, deactivateBtn );

			// Create "Cancel" button in the actions row (same area as Sync/Change).
			const cancelBtn       = document.createElement( 'button' );
			cancelBtn.type        = 'button';
			cancelBtn.id          = this.prefix + '-license-cancel-change';
			cancelBtn.className   = 'wp2fa-license-btn-secondary wp2fa-license-btn-cancel';
			cancelBtn.textContent = unifiedLicense.i18n.cancelBtn;
			cancelBtn.addEventListener( 'click', () => this.cancelChange() );
			syncBtn.parentNode.insertBefore( cancelBtn, syncBtn );
		},

		/**
		 * Cancel the license change flow.
		 *
		 * Restores the original input state and buttons.
		 */
		cancelChange() {
			const input          = document.getElementById( this.prefix + '-license-key' );
			const deactivateBtn  = document.getElementById( this.prefix + '-license-deactivate' );
			const syncBtn        = document.getElementById( this.prefix + '-license-sync' );
			const changeBtn      = document.getElementById( this.prefix + '-license-change' );
			const activateNewBtn = document.getElementById( this.prefix + '-license-activate-new' );
			const cancelBtnEl    = document.getElementById( this.prefix + '-license-cancel-change' );

			// Restore input.
			input.value = this.originalKey;
			input.type  = 'password';
			input.setAttribute( 'readonly', '' );

			// Remove temporary buttons.
			if ( activateNewBtn ) {
				activateNewBtn.remove();
			}

			if ( cancelBtnEl ) {
				cancelBtnEl.remove();
			}

			// Show original buttons.
			deactivateBtn.style.display = '';
			syncBtn.style.display       = '';
			changeBtn.style.display     = '';

			this.messageEl.className = 'wp2fa-license-notice';
			this.isChanging = false;
		},

		/**
		 * Handle the license change AJAX request.
		 *
		 * Sends the new key to the server. On success, the old key
		 * is deactivated server-side and the page reloads.
		 */
		async changeLicense() {
			const button     = document.getElementById( this.prefix + '-license-activate-new' );
			const cancelBtn  = document.getElementById( this.prefix + '-license-cancel-change' );
			const licenseKey = document.getElementById( this.prefix + '-license-key' ).value.trim();

			if ( ! licenseKey ) {
				this.showMessage( unifiedLicense.i18n.enterLicenseKey, 'error' );
				return;
			}

			button.disabled    = true;
			button.textContent = unifiedLicense.i18n.changingText;
			cancelBtn.disabled = true;
			this.messageEl.className = 'wp2fa-license-notice';

			try {
				const response = await this.postAjax( unifiedLicense.actions.change, {
					license_key: licenseKey,
				} );

				if ( response.success ) {
					const msg = response.data && response.data.message
						? response.data.message
						: unifiedLicense.i18n.changedSuccess;
					this.showMessage( msg, 'success' );
					setTimeout( () => {
						window.location.href = unifiedLicense.redirectUrl;
					}, 1000 );
				} else {
					const msg = response.data && response.data.message
						? response.data.message
						: unifiedLicense.i18n.changeFailed;
					this.showMessage( msg, 'error' );
					button.disabled    = false;
					button.textContent = unifiedLicense.i18n.activateNewBtn;
					cancelBtn.disabled = false;
				}
			} catch ( e ) {
				this.showMessage( unifiedLicense.i18n.networkError, 'error' );
				button.disabled    = false;
				button.textContent = unifiedLicense.i18n.activateNewBtn;
				cancelBtn.disabled = false;
			}
		},
	};

	// Initialize when DOM is ready.
	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', () => UnifiedLicense.init() );
	} else {
		UnifiedLicense.init();
	}
} )();
