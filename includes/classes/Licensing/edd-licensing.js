/**
 * EDD License page AJAX handlers.
 *
 * Handles license activation, deactivation, sync, and multisite
 * batch progress polling. Plugin-agnostic — all strings and config
 * are passed via the localized `eddLicense` object from PHP.
 *
 * @since 2.4.0
 */
( function() {
	'use strict';

	const EDDLicense = {

		/** @type {HTMLElement|null} Notice message container. */
		messageEl: null,

		/** @type {HTMLElement|null} Progress bar container. */
		progressEl: null,

		/** @type {HTMLElement|null} Progress text element. */
		progressTextEl: null,

		/** @type {HTMLElement|null} Progress bar element. */
		progressBarEl: null,

		/** @type {number|null} Polling interval timer ID. */
		pollTimer: null,

		/**
		 * Initialize cached elements and bind events.
		 */
		init() {
			this.messageEl      = document.getElementById( 'edd-license-message' );
			this.progressEl     = document.getElementById( 'edd-license-progress' );
			this.progressTextEl = document.getElementById( 'edd-license-progress-text' );
			this.progressBarEl  = document.getElementById( 'edd-license-progress-bar' );

			this.bindEvents();
		},

		/**
		 * Bind click events to license action buttons.
		 */
		bindEvents() {
			const activateBtn   = document.getElementById( 'edd-license-activate' );
			const deactivateBtn = document.getElementById( 'edd-license-deactivate' );
			const syncBtn       = document.getElementById( 'edd-license-sync' );

			if ( activateBtn ) {
				activateBtn.addEventListener( 'click', () => this.activate() );
			}

			if ( deactivateBtn ) {
				deactivateBtn.addEventListener( 'click', () => this.deactivate() );
			}

			if ( syncBtn ) {
				syncBtn.addEventListener( 'click', () => this.sync() );
			}
		},

		/**
		 * Show a notice message on the license page.
		 *
		 * @param {string} text - The message text.
		 * @param {string} type - Notice type: 'success', 'error', 'warning'.
		 */
		showMessage( text, type ) {
			this.messageEl.classList.remove( 'notice-success', 'notice-error', 'notice-warning' );
			this.messageEl.classList.add( 'notice-' + type );
			this.messageEl.querySelector( 'p' ).textContent = text;
			this.messageEl.style.display = '';
		},

		/**
		 * Show the progress bar with text.
		 *
		 * @param {number} completed - Number of completed items.
		 * @param {number} total     - Total number of items.
		 * @param {string} label     - Action label (e.g., 'Activating', 'Deactivating').
		 */
		showProgress( completed, total, label ) {
			const percent = total > 0 ? Math.round( ( completed / total ) * 100 ) : 0;
			this.progressTextEl.textContent = label + '... (' + completed + ' / ' + total + ' ' + eddLicense.i18n.sitesComplete + ')';
			this.progressBarEl.value = percent;
			this.progressEl.style.display = '';
		},

		/**
		 * Hide the progress bar.
		 */
		hideProgress() {
			this.progressEl.style.display = 'none';
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
				nonce: eddLicense.nonce,
				...extraData,
			} );

			const response = await fetch( eddLicense.ajaxUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
				},
				body,
			} );

			return response.json();
		},

		/**
		 * Start polling the activation progress endpoint.
		 *
		 * @param {string} label - Action label for the progress display.
		 */
		startPolling( label ) {
			this.stopPolling();

			this.pollTimer = setInterval( async () => {
				try {
					const response = await this.postAjax( eddLicense.actions.progress );

					if ( ! response.success || ! response.data ) {
						return;
					}

					const data = response.data;

					if ( 'processing' === data.status ) {
						this.showProgress( data.completed, data.total, label );
					}

					if ( 'complete' === data.status ) {
						this.stopPolling();
						this.showProgress( data.completed, data.total, label );

						if ( data.errors && data.errors.length > 0 ) {
							/*
							 * _n() here rather than a string from PHP: the count is only known
							 * in the browser, and languages with more than two plural forms
							 * cannot be served by patching a single pre-translated string.
							 */
							const errorCount = data.errors.length;
							const errorText  = wp.i18n.sprintf(
								/* translators: %d: how many sites failed. */
								wp.i18n._n(
									'completed with %d error.',
									'completed with %d errors.',
									errorCount,
									'wp-2fa'
								),
								errorCount
							);

							this.showMessage( label + ' ' + errorText, 'warning' );
						} else {
							this.showMessage( label + ' ' + eddLicense.i18n.completedSuccessfully, 'success' );
						}

						setTimeout( () => {
							window.location.href = eddLicense.redirectUrl;
						}, 2000 );
					}
				} catch ( e ) {
					// Silently ignore polling errors to avoid interrupting the batch operation.
				}
			}, eddLicense.pollInterval );
		},

		/**
		 * Stop the polling timer.
		 */
		stopPolling() {
			if ( this.pollTimer ) {
				clearInterval( this.pollTimer );
				this.pollTimer = null;
			}
		},

		/**
		 * Handle license activation.
		 */
		async activate() {
			const button     = document.getElementById( 'edd-license-activate' );
			const licenseKey = document.getElementById( 'edd-license-key' ).value.trim();

			if ( ! licenseKey ) {
				this.showMessage( eddLicense.i18n.enterLicenseKey, 'error' );
				return;
			}

			button.disabled    = true;
			button.textContent = eddLicense.i18n.activatingText;
			this.messageEl.style.display = 'none';

			// Start polling if multisite (batch activation takes time).
			if ( eddLicense.isMultisite ) {
				this.startPolling( eddLicense.i18n.activatingLabel );
			}

			try {
				const response = await this.postAjax( eddLicense.actions.activate, {
					license_key: licenseKey,
				} );

				this.stopPolling();

				if ( response.success ) {
					const msg = response.data && response.data.message
						? response.data.message
						: eddLicense.i18n.activatedSuccess;
					this.showMessage( msg, 'success' );
					this.hideProgress();
					setTimeout( () => {
						window.location.href = eddLicense.redirectUrl;
					}, 1000 );
				} else {
					const msg = response.data && response.data.message
						? response.data.message
						: eddLicense.i18n.activationFailed;
					this.showMessage( msg, 'error' );
					this.hideProgress();
					button.disabled    = false;
					button.textContent = eddLicense.i18n.activateBtn;
				}
			} catch ( e ) {
				this.stopPolling();
				this.showMessage( eddLicense.i18n.networkError, 'error' );
				this.hideProgress();
				button.disabled    = false;
				button.textContent = eddLicense.i18n.activateBtn;
			}
		},

		/**
		 * Handle license deactivation.
		 */
		async deactivate() {
			const button = document.getElementById( 'edd-license-deactivate' );

			button.disabled    = true;
			button.textContent = eddLicense.i18n.deactivatingText;
			this.messageEl.style.display = 'none';

			// Start polling if multisite (batch deactivation takes time).
			if ( eddLicense.isMultisite ) {
				this.startPolling( eddLicense.i18n.deactivatingLabel );
			}

			try {
				const response = await this.postAjax( eddLicense.actions.deactivate );

				this.stopPolling();

				if ( response.success ) {
					const msg = response.data && response.data.message
						? response.data.message
						: eddLicense.i18n.deactivatedSuccess;
					this.showMessage( msg, 'success' );
					this.hideProgress();
					setTimeout( () => {
						window.location.href = eddLicense.redirectUrl;
					}, 1000 );
				} else {
					const msg = response.data && response.data.message
						? response.data.message
						: eddLicense.i18n.deactivationFailed;
					this.showMessage( msg, 'error' );
					this.hideProgress();
					button.disabled    = false;
					button.textContent = eddLicense.i18n.deactivateBtn;
				}
			} catch ( e ) {
				this.stopPolling();
				this.showMessage( eddLicense.i18n.networkError, 'error' );
				this.hideProgress();
				button.disabled    = false;
				button.textContent = eddLicense.i18n.deactivateBtn;
			}
		},

		/**
		 * Handle license sync.
		 */
		async sync() {
			const button = document.getElementById( 'edd-license-sync' );

			button.disabled    = true;
			button.textContent = eddLicense.i18n.syncingText;
			this.messageEl.style.display = 'none';

			// Start polling if multisite (sync may activate/deactivate sites).
			if ( eddLicense.isMultisite ) {
				this.startPolling( eddLicense.i18n.syncingLabel );
			}

			try {
				const response = await this.postAjax( eddLicense.actions.sync );

				this.stopPolling();

				if ( response.success ) {
					const msg = response.data && response.data.message
						? response.data.message
						: eddLicense.i18n.syncedSuccess;
					this.showMessage( msg, 'success' );
					this.hideProgress();
					setTimeout( () => {
						window.location.reload();
					}, 1000 );
				} else {
					const msg = response.data && response.data.message
						? response.data.message
						: eddLicense.i18n.syncFailed;
					this.showMessage( msg, 'error' );
					this.hideProgress();
					button.disabled    = false;
					button.textContent = eddLicense.i18n.syncBtn;
				}
			} catch ( e ) {
				this.stopPolling();
				this.showMessage( eddLicense.i18n.networkError, 'error' );
				this.hideProgress();
				button.disabled    = false;
				button.textContent = eddLicense.i18n.syncBtn;
			}
		},
	};

	document.addEventListener( 'DOMContentLoaded', () => EDDLicense.init() );
} )();
