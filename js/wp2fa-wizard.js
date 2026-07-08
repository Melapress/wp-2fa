/**
 * WP 2FA - Setup Wizard Core
 *
 * A fully extensible, wp.hooks-driven wizard for configuring 2FA methods
 * on a per-user basis. Methods register themselves via hooks so the wizard
 * is decoupled from any specific 2FA provider.
 *
 * @package wp2fa
 * @since   4.0.0
 *
 * Hooks reference (all via wp.hooks):
 *
 *   Filters:
 *     wp2fa_wizard_methods              - Collect enabled methods for current user.
 *                                         Each method adds an object: { id, name, description, order }
 *     wp2fa_wizard_backup_methods       - Collect enabled backup/secondary methods for current user.
 *                                         Each backup method adds: { id, name, description, order }
 *     wp2fa_wizard_step_title           - Filter the title shown for a given step.
 *     wp2fa_wizard_can_proceed          - Whether the "Continue" button should be enabled.
 *     wp2fa_wizard_modal_classes        - Filter additional CSS classes on the modal container.
 *
 *   Actions:
 *     wp2fa_wizard_opened               - Fired when wizard modal opens.
 *     wp2fa_wizard_closed               - Fired when wizard modal closes.
 *     wp2fa_wizard_method_selected      - Fired when a method is selected (args: methodId).
 *     wp2fa_wizard_step_shown           - Fired when a wizard step becomes visible (args: stepId).
 *     wp2fa_wizard_render_method        - Render method-specific configuration UI (args: methodId, containerEl).
 *     wp2fa_wizard_render_backup_method - Render backup-method UI (args: methodId, containerEl, context).
 *     wp2fa_wizard_before_proceed       - Fired before moving to next step (args: currentStepId, nextStepId).
 *     wp2fa_wizard_after_proceed        - Fired after moving to next step (args: newStepId).
 *     wp2fa_wizard_before_back          - Fired before going back a step.
 *     wp2fa_wizard_after_back           - Fired after going back a step.
 *     wp2fa_wizard_validate_method      - Fired when user submits verification for a method (args: methodId, containerEl).
 *     wp2fa_wizard_method_validated     - Fired when a method has been validated successfully (args: methodId).
 *                                         The core listens for this and proceeds to backup methods or closes.
 *     wp2fa_wizard_init                 - Fired after the wizard core initializes.
 *     wp2fa_wizard_complete             - Fired when the entire wizard flow is complete (no more steps).
 */
( function () {
	'use strict';

	/* -------------------------------------------------------
	 * Guard: wp.hooks must be available
	 * ------------------------------------------------------- */
	if ( ! window.wp || ! window.wp.hooks ) {
		// eslint-disable-next-line no-console
		console.error( '[WP2FA Wizard] wp.hooks is required. Ensure "wp-hooks" is a script dependency.' );
		return;
	}

	var hooks = wp.hooks;

	/**
	 * Validate that a URL is same-origin before redirecting.
	 * Returns the URL if safe, or the current page URL as fallback.
	 */
	function safeSameOriginUrl( url ) {
		if ( ! url || '' === url.trim() ) {
			return window.location.href;
		}
		try {
			var parsed = new URL( url, window.location.origin );
			if ( parsed.origin === window.location.origin ) {
				return parsed.href;
			}
		} catch ( e ) {}
		return window.location.href;
	}

	function isPrimaryWizardButton( el ) {
		if ( ! el || ! el.classList ) {
			return false;
		}

		return el.classList.contains( 'wp2fa-wizard-btn-primary' ) || el.classList.contains( 'wp-2fa-button-primary' ) || el.classList.contains( 'button-primary' );
	}

	function getPrimaryFooterButton( modal ) {
		if ( ! modal ) {
			return null;
		}

		return modal.querySelector( '#wp2fa-wizard-footer .wp2fa-wizard-btn-primary:not([disabled]), #wp2fa-wizard-footer .wp-2fa-button-primary:not([disabled]), #wp2fa-wizard-footer .button-primary:not([disabled])' );
	}

	/* -------------------------------------------------------
	 * Internal state
	 * ------------------------------------------------------- */
	var state = {
		currentStep: 'select-method',  // 'select-method' | 'configure-method' | 'backup-method' | 'select-backup-method'
		selectedMethod: null,
		selectedBackupMethod: null,
		methods: [],
		backupMethods: [],
		currentBackupIndex: 0,
		userId: 0,
		userRole: '',
		nonce: '',
		overlay: null,
		modal: null,
		wizardCompleted: false
	};

	/* -------------------------------------------------------
	 * DOM helpers
	 * ------------------------------------------------------- */
	function el( tag, attrs, children ) {
		var node = document.createElement( tag );
		if ( attrs ) {
			Object.keys( attrs ).forEach( function ( key ) {
				if ( key === 'className' ) {
					node.className = attrs[ key ];
				} else if ( key === 'textContent' ) {
					node.textContent = attrs[ key ];
				} else if ( key === 'innerHTML' ) {
					node.innerHTML = attrs[ key ];
				} else if ( key.indexOf( 'data-' ) === 0 || key === 'id' || key === 'type' || key === 'name' || key === 'value' || key === 'for' || key === 'placeholder' || key === 'autocomplete' || key === 'pattern' || key === 'readonly' || key === 'disabled' || key === 'checked' || key === 'aria-label' || key === 'role' || key === 'tabindex' || key === 'aria-hidden' ) {
					node.setAttribute( key, attrs[ key ] );
				}
			} );
		}
		if ( children ) {
			if ( ! Array.isArray( children ) ) {
				children = [ children ];
			}
			children.forEach( function ( child ) {
				if ( typeof child === 'string' ) {
					node.appendChild( document.createTextNode( child ) );
				} else if ( child instanceof Node ) {
					node.appendChild( child );
				}
			} );
		}
		return node;
	}

	/* -------------------------------------------------------
	 * Collect enabled methods via hook
	 * ------------------------------------------------------- */
	function collectMethods() {
		/**
		 * Filter: wp2fa_wizard_methods
		 *
		 * Each method module should hook into this and push its descriptor:
		 * { id: 'totp', name: 'One-time code via 2FA App (TOTP)', description: '...', order: 1 }
		 *
		 * The second argument is the user context: { userId, userRole }
		 */
		var methods = hooks.applyFilters( 'wp2fa_wizard_methods', [], {
			userId: state.userId,
			userRole: state.userRole
		} );

		// Sort by order (use != null to correctly handle order 0)
		methods.sort( function ( a, b ) {
			return ( a.order != null ? a.order : 99 ) - ( b.order != null ? b.order : 99 );
		} );

		state.methods = methods;
		return methods;
	}

	/* -------------------------------------------------------
	 * Collect enabled backup/secondary methods via hook
	 * ------------------------------------------------------- */
	function collectBackupMethods() {
		/**
		 * Filter: wp2fa_wizard_backup_methods
		 *
		 * Each backup method module should hook into this and push its descriptor:
		 * { id: 'backup_codes', name: 'Backup Codes', description: '...', order: 1 }
		 *
		 * The second argument is the user context: { userId, userRole }
		 */
		var backupMethods = hooks.applyFilters( 'wp2fa_wizard_backup_methods', [], {
			userId: state.userId,
			userRole: state.userRole
		} );

		// Deduplicate by method id (the same JS file may be loaded
		// under different script handles, registering the filter twice).
		var seen = {};
		backupMethods = backupMethods.filter( function ( m ) {
			if ( seen[ m.id ] ) {
				return false;
			}
			seen[ m.id ] = true;
			return true;
		} );

		// Sort by order (use != null to correctly handle order 0)
		backupMethods.sort( function ( a, b ) {
			return ( a.order != null ? a.order : 99 ) - ( b.order != null ? b.order : 99 );
		} );

		state.backupMethods = backupMethods;
		return backupMethods;
	}

	/* -------------------------------------------------------
	 * Post-validation flow: backup methods or close
	 *
	 * Listens for wp2fa_wizard_method_validated and decides
	 * whether to show backup method steps or finish the wizard.
	 * ------------------------------------------------------- */
	function handleMethodValidated( methodId ) {
		// Collect backup methods available for this user
		collectBackupMethods();

		// Only allow email backup when the primary method is one of the
		// supported types (TOTP, Authy, Yubico, Twilio, Clickatell).
		var emailBackupAllowedMethods = [ 'totp', 'authy', 'yubico', 'twilio', 'clickatell' ];
		if ( emailBackupAllowedMethods.indexOf( state.selectedMethod ) === -1 ) {
			state.backupMethods = state.backupMethods.filter( function ( m ) {
				return m.id !== 'backup_email';
			} );
		}

		if ( state.backupMethods.length > 1 ) {
			// Multiple backup methods — show the selection step.
			state.selectedBackupMethod = null;
			setTimeout( function () {
				renderBackupMethodSelectionStep();
			}, 800 );
		} else if ( state.backupMethods.length === 1 ) {
			// Only one backup method — go directly to its setup.
			state.currentBackupIndex = 0;
			setTimeout( function () {
				renderBackupMethodStep( state.currentBackupIndex );
			}, 800 );
		} else {
			// No backup methods — show completion step
			setTimeout( function () {
				renderCompletionStep();
			}, 800 );
		}
	}

	/* -------------------------------------------------------
	 * Render the backup method selection step
	 *
	 * Shown after primary method validation when more than one
	 * backup method is available.  The user picks which backup
	 * method to configure next.
	 * ------------------------------------------------------- */
	function renderBackupMethodSelectionStep() {
		var body = state.modal.querySelector( '#wp2fa-wizard-body' );
		body.innerHTML = '';

		var title = state.modal.querySelector( '#wp2fa-wizard-title' );
		title.innerHTML = '';

		// Decide intro text: use multi-method text when available, fall back to the single-method continue text.
		var introTitle = wp2faWizardData.i18n.backupSelectTitle || '';
		var introDesc  = wp2faWizardData.i18n.backupSelectDescription || '';
		var sectionTitle = wp2faWizardData.i18n.backupSelectSectionTitle || '';

		// Render intro banner (green checkmark + text).
		var introTmpl = wp.template( 'wp2fa-wizard-backup-select-intro' );
		body.innerHTML = introTmpl( {
			title: introTitle,
			description: introDesc,
			sectionTitle: sectionTitle
		} );

		// Build the radio list of backup methods.
		var methodItemTmpl = wp.template( 'wp2fa-wizard-backup-method-item' );
		var list = document.createElement( 'ul' );
		list.className = 'wp2fa-wizard-methods-list';
		list.id = 'wp2fa-wizard-backup-methods-list';
		list.setAttribute( 'role', 'radiogroup' );

		state.backupMethods.forEach( function ( method, index ) {
			var checked = false;
			if ( index === 0 && ! state.selectedBackupMethod ) {
				checked = true;
				state.selectedBackupMethod = method.id;
			} else if ( state.selectedBackupMethod === method.id ) {
				checked = true;
			}

			list.innerHTML += methodItemTmpl( {
				id: method.id,
				name: method.name,
				description: method.description || '',
				checked: checked
			} );
		} );

		// Attach radio change listeners.
		var radios = list.querySelectorAll( '.wp2fa-wizard-backup-method-radio' );
		radios.forEach( function ( radio ) {
			radio.addEventListener( 'change', function () {
				state.selectedBackupMethod = this.value;
			} );
		} );

		body.appendChild( list );

		// Footer buttons.
		var footer = state.modal.querySelector( '#wp2fa-wizard-footer' );
		footer.innerHTML = '';

		var footerTmpl = wp.template( 'wp2fa-wizard-backup-select-footer' );
		footer.innerHTML = footerTmpl( {
			closeLaterLabel: wp2faWizardData.i18n.closeLater || 'Close wizard',
			configureLabel: wp2faWizardData.i18n.configureBackup || 'Continue',
			disabled: ! state.selectedBackupMethod
		} );

		footer.querySelector( '#wp2fa-wizard-backup-select-btn-close' ).addEventListener( 'click', function () {
			finishWizard();
		} );

		footer.querySelector( '#wp2fa-wizard-backup-select-btn-configure' ).addEventListener( 'click', function () {
			if ( ! state.selectedBackupMethod ) {
				return;
			}
			// Find the index of the selected backup method and render its setup step.
			for ( var i = 0; i < state.backupMethods.length; i++ ) {
				if ( state.backupMethods[ i ].id === state.selectedBackupMethod ) {
					state.currentBackupIndex = i;
					renderBackupMethodStep( i );
					return;
				}
			}
		} );

		state.currentStep = 'select-backup-method';
		hooks.doAction( 'wp2fa_wizard_step_shown', 'select-backup-method' );
		focusFirstInteractive();
	}

	/* -------------------------------------------------------
	 * Render a backup method step
	 * ------------------------------------------------------- */
	function renderBackupMethodStep( index ) {
		if ( index >= state.backupMethods.length ) {
			finishWizard();
			return;
		}

		var backupMethod = state.backupMethods[ index ];
		var body = state.modal.querySelector( '#wp2fa-wizard-body' );
		body.innerHTML = '';

		var title = state.modal.querySelector( '#wp2fa-wizard-title' );
		title.innerHTML = hooks.applyFilters(
			'wp2fa_wizard_step_title',
			backupMethod.name || 'Backup Method',
			'backup-method-' + backupMethod.id
		);

		var container = el( 'div', {
			className: 'wp2fa-wizard-backup-method-configure',
			id: 'wp2fa-wizard-backup-method-configure-' + backupMethod.id,
			'data-backup-method-id': backupMethod.id
		} );

		body.appendChild( container );

		/**
		 * Action: wp2fa_wizard_render_backup_method
		 *
		 * Each backup method module listens for this and renders its UI
		 * into the provided container element.
		 */
		hooks.doAction( 'wp2fa_wizard_render_backup_method', backupMethod.id, container, {
			userId: state.userId,
			userRole: state.userRole,
			nonce: state.nonce
		} );

		// Footer: Skip + Close (backup methods can add their own buttons).
		var footer = state.modal.querySelector( '#wp2fa-wizard-footer' );
		footer.innerHTML = '';

		var backupFooterTmpl = wp.template( 'wp2fa-wizard-backup-footer' );
		footer.innerHTML = backupFooterTmpl( {
			skipLabel: wp2faWizardData.i18n.skipBackup || "I'll set this up later"
		} );

		footer.querySelector( '#wp2fa-wizard-backup-btn-skip' ).addEventListener( 'click', function () {
			finishWizard();
		} );

		state.currentStep = 'backup-method';
		hooks.doAction( 'wp2fa_wizard_step_shown', 'backup-method-' + backupMethod.id );
		focusFirstInteractive();
	}

	/* -------------------------------------------------------
	 * Advance to the next backup method (called by modules)
	 * ------------------------------------------------------- */
	function nextBackupMethod() {
		finishWizard();
	}

	/* -------------------------------------------------------
	 * Render the completion step
	 *
	 * Shown at the end of the wizard when no backup methods
	 * are available. Displays the "no further action" white-label
	 * text with a close button.
	 * ------------------------------------------------------- */
	function renderCompletionStep() {
		var body = state.modal.querySelector( '#wp2fa-wizard-body' );
		body.innerHTML = '';

		var title = state.modal.querySelector( '#wp2fa-wizard-title' );
		title.innerHTML = '';

		var contentHtml = wp2faWizardData.i18n.noFurtherAction || '';

		var bodyTmpl = wp.template( 'wp2fa-wizard-completion' );
		body.innerHTML = bodyTmpl( {
			contentHtml: contentHtml
		} );

		// Footer with close button.
		var footer = state.modal.querySelector( '#wp2fa-wizard-footer' );
		footer.innerHTML = '';

		var footerTmpl = wp.template( 'wp2fa-wizard-completion-footer' );
		footer.innerHTML = footerTmpl( {
			closeLabel: wp2faWizardData.i18n.closeWizard || 'Close wizard'
		} );

		// Close button handler.
		var closeBtn = footer.querySelector( '#wp2fa-wizard-btn-completion-close' );
		if ( closeBtn ) {
			closeBtn.addEventListener( 'click', function () {
				finishWizard();
			} );
		}
	}

	/* -------------------------------------------------------
	 * Finish wizard: fire complete action, then close + reload
	 * ------------------------------------------------------- */
	function finishWizard() {
		state.wizardCompleted = true;
		hooks.doAction( 'wp2fa_wizard_complete' );
		setTimeout( function () {
			closeWizard( true );

			// If re-login after 2FA setup is enabled, log the user out first.
			if ( wp2faWizardData.reLoginEnabled && wp2faWizardData.reLogin && wp2faWizardData.reLoginEnabled === wp2faWizardData.reLogin.trim() ) {
				var xhr = new XMLHttpRequest();
				xhr.open( 'POST', wp2faWizardData.ajaxUrl, true );
				xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded' );
				xhr.onreadystatechange = function () {
					if ( xhr.readyState === 4 ) {
						window.location.replace( safeSameOriginUrl( wp2faWizardData.loginUrl || wp2faWizardData.redirectToUrl ) );
					}
				};
				xhr.send( 'action=custom_ajax_logout&_wpnonce=' + encodeURIComponent( wp2faWizardData.nonce ) );
				return;
			}

			if ( wp2faWizardData.redirectToUrl && '' !== wp2faWizardData.redirectToUrl.trim() ) {
				window.location.replace( safeSameOriginUrl( wp2faWizardData.redirectToUrl ) );
			} else if ( wp2faWizardData.reloadOnSuccess ) {
				var url = new URL( window.location.href );
				if ( url.searchParams.get( 'show' ) === 'wp-2fa-setup' ) {
					url.searchParams.delete( 'show' );
					window.location.replace( url.toString() );
				} else {
					window.location.reload();
				}
			}
		}, 800 );
	}

	/* -------------------------------------------------------
	 * Check whether the required intro step should be shown
	 *
	 * Shown only for enforced users who haven't seen it yet,
	 * when the setting content is provided and not empty.
	 * ------------------------------------------------------- */
	function hasRequiredIntroStep() {
		return typeof wp2faWizardData.requiredIntroHtml === 'string' && wp2faWizardData.requiredIntroHtml.length > 0;
	}

	/* -------------------------------------------------------
	 * Build & render the required intro step
	 *
	 * This is the very first step shown to enforced users.
	 * On "Continue", an AJAX call marks the intro as seen
	 * so it won't appear again.
	 * ------------------------------------------------------- */
	function renderRequiredIntroStep() {
		var body = state.modal.querySelector( '#wp2fa-wizard-body' );
		body.innerHTML = '';

		var title = state.modal.querySelector( '#wp2fa-wizard-title' );
		title.innerHTML = hooks.applyFilters( 'wp2fa_wizard_step_title', '', 'required-intro' );

		var introTmpl = wp.template( 'wp2fa-wizard-required-intro' );
		body.innerHTML = introTmpl( {
			contentHtml: wp2faWizardData.requiredIntroHtml
		} );

		// Footer.
		var footer = state.modal.querySelector( '#wp2fa-wizard-footer' );
		footer.innerHTML = '';

		var footerTmpl = wp.template( 'wp2fa-wizard-required-intro-footer' );
		footer.innerHTML = footerTmpl( {
			continueLabel: wp2faWizardData.i18n.continueBtn || 'Continue'
		} );

		footer.querySelector( '#wp2fa-wizard-btn-required-intro-continue' ).addEventListener( 'click', function () {
			// Mark intro as seen via AJAX (fire-and-forget).
			var xhr = new XMLHttpRequest();
			xhr.open( 'POST', wp2faWizardData.ajaxUrl, true );
			xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded' );
			xhr.send(
				'action=wp2fa_dismiss_required_intro&nonce=' + encodeURIComponent( wp2faWizardData.dismissIntroNonce )
			);

			// Clear the HTML so the step won't appear again in this session.
			wp2faWizardData.requiredIntroHtml = '';

			hooks.doAction( 'wp2fa_wizard_before_proceed', 'required-intro', 'welcome' );

			// Proceed to the next appropriate step.
			if ( hasWelcomeStep() ) {
				renderWelcomeStep();
			} else if ( state.methods.length === 1 ) {
				state.selectedMethod = state.methods[ 0 ].id;
				hooks.doAction( 'wp2fa_wizard_method_selected', state.selectedMethod );
				renderMethodConfigureStep();
			} else {
				renderMethodSelectionStep();
			}

			hooks.doAction( 'wp2fa_wizard_after_proceed', state.currentStep );
		} );

		state.currentStep = 'required-intro';
		hooks.doAction( 'wp2fa_wizard_step_shown', 'required-intro' );
		focusFirstInteractive();
	}

	/* -------------------------------------------------------
	 * Check whether the welcome step should be shown
	 * ------------------------------------------------------- */
	function hasWelcomeStep() {
		return typeof wp2faWizardData.welcomeHtml === 'string' && wp2faWizardData.welcomeHtml.length > 0;
	}

	/* -------------------------------------------------------
	 * Build & render the welcome step
	 * ------------------------------------------------------- */
	function renderWelcomeStep() {
		var body = state.modal.querySelector( '#wp2fa-wizard-body' );
		body.innerHTML = '';

		var title = state.modal.querySelector( '#wp2fa-wizard-title' );
		title.innerHTML = hooks.applyFilters( 'wp2fa_wizard_step_title', '', 'welcome' );

		var welcomeTmpl = wp.template( 'wp2fa-wizard-welcome' );
		body.innerHTML = welcomeTmpl( {
			contentHtml: wp2faWizardData.welcomeHtml
		} );

		// Footer.
		var footer = state.modal.querySelector( '#wp2fa-wizard-footer' );
		footer.innerHTML = '';

		var footerTmpl = wp.template( 'wp2fa-wizard-welcome-footer' );
		footer.innerHTML = footerTmpl( {
			goBackLabel: wp2faWizardData.i18n.goBack || 'Go back',
			continueLabel: wp2faWizardData.i18n.continueBtn || 'Continue'
		} );

		footer.querySelector( '#wp2fa-wizard-btn-goback' ).addEventListener( 'click', function () {
			closeWizard();
		} );

		footer.querySelector( '#wp2fa-wizard-btn-continue' ).addEventListener( 'click', function () {
			hooks.doAction( 'wp2fa_wizard_before_proceed', 'welcome', 'select-method' );
			// If only one method, skip selection and go directly to configure.
			if ( state.methods.length === 1 ) {
				state.selectedMethod = state.methods[ 0 ].id;
				hooks.doAction( 'wp2fa_wizard_method_selected', state.selectedMethod );
				renderMethodConfigureStep();
				hooks.doAction( 'wp2fa_wizard_after_proceed', 'configure-method' );
			} else {
				renderMethodSelectionStep();
				hooks.doAction( 'wp2fa_wizard_after_proceed', 'select-method' );
			}
		} );

		state.currentStep = 'welcome';
		hooks.doAction( 'wp2fa_wizard_step_shown', 'welcome' );
		focusFirstInteractive();
	}

	/* -------------------------------------------------------
	 * Reconfiguration helpers
	 * ------------------------------------------------------- */

	/**
	 * Check if we are in reconfiguration mode (user already has 2FA set up).
	 */
	function isReconfiguring() {
		return !! wp2faWizardData.isReconfiguration;
	}

	/**
	 * Replace reconfiguration placeholders in text.
	 *
	 * @param {string} text     - The raw text with placeholders.
	 * @param {string} methodId - The method ID being rendered.
	 * @return {string} Text with placeholders replaced.
	 */
	function replaceReconfigurePlaceholders( text, methodId ) {
		if ( ! text ) {
			return '';
		}

		var currentMethod = wp2faWizardData.currentMethod || '';
		var isCurrentMethod = ( currentMethod === methodId );
		var capitalizedText = isCurrentMethod ? ( wp2faWizardData.i18n.reconfigureCapitalized || 'Reconfigure' ) : ( wp2faWizardData.i18n.configureCapitalized || 'Configure' );
		var lowerText = isCurrentMethod ? ( wp2faWizardData.i18n.reconfigureLower || 'reconfigure' ) : ( wp2faWizardData.i18n.configureLower || 'configure' );

		return text
			.replace( /\{reconfigure_or_configure_capitalized\}/g, capitalizedText )
			.replace( /\{reconfigure_or_configure\}/g, lowerText );
	}

	/**
	 * Get the reconfigure intro text for a given method.
	 *
	 * @param {string} methodId - The method ID.
	 * @return {string} The HTML intro text (with placeholders replaced), or empty string.
	 */
	function getReconfigureIntro( methodId ) {
		var intros = wp2faWizardData.reconfigureIntros || {};
		return replaceReconfigurePlaceholders( intros[ methodId ] || '', methodId );
	}

	/**
	 * Get the unavailable reconfigure intro text for a given method.
	 *
	 * @param {string} methodId - The method ID.
	 * @return {string} The HTML unavailable text (with placeholders replaced), or empty string.
	 */
	function getReconfigureIntroUnavailable( methodId ) {
		var intros = wp2faWizardData.reconfigureIntrosUnavailable || {};
		return replaceReconfigurePlaceholders( intros[ methodId ] || '', methodId );
	}

	/**
	 * Check if a method is marked as unavailable.
	 *
	 * @param {string} methodId - The method ID.
	 * @return {boolean}
	 */
	function isMethodUnavailable( methodId ) {
		var unavailable = wp2faWizardData.unavailableMethods || [];
		return unavailable.indexOf( methodId ) !== -1;
	}

	/* -------------------------------------------------------
	 * Build & render the method selection step
	 * ------------------------------------------------------- */
	function renderMethodSelectionStep() {
		var body = state.modal.querySelector( '#wp2fa-wizard-body' );
		body.innerHTML = '';

		var title = state.modal.querySelector( '#wp2fa-wizard-title' );
		var chooseMethodText = ( wp2faWizardData.i18n.chooseMethod || 'Choose 2FA Method' )
			.replace( '{available_methods_count}', String( state.methods.length ) );
		title.innerHTML = hooks.applyFilters( 'wp2fa_wizard_step_title', chooseMethodText, 'select-method' );

		var methodItemTmpl = wp.template( 'wp2fa-wizard-method-item' );
		var list = document.createElement( 'ul' );
		list.className = 'wp2fa-wizard-methods-list';
		list.id = 'wp2fa-wizard-methods-list';
		list.setAttribute( 'role', 'radiogroup' );

		state.methods.forEach( function ( method, index ) {
			var checked = false;
			if ( index === 0 && ! state.selectedMethod ) {
				checked = true;
				state.selectedMethod = method.id;
			} else if ( state.selectedMethod === method.id ) {
				checked = true;
			}

			var hints = wp2faWizardData.methodHints || {};
			var description = method.description || '';
			var unavailable = false;

			// In reconfiguration mode, show reconfigure intro texts.
			if ( isReconfiguring() ) {
				if ( isMethodUnavailable( method.id ) ) {
					unavailable = true;
					description = getReconfigureIntroUnavailable( method.id ) || description;
					// Do not pre-select unavailable methods.
					if ( checked && state.selectedMethod === method.id ) {
						checked = false;
						state.selectedMethod = null;
					}
				} else {
					var reconfigureText = getReconfigureIntro( method.id );
					if ( reconfigureText ) {
						description = reconfigureText;
					}
				}
			}

			list.innerHTML += methodItemTmpl( {
				id: method.id,
				name: method.name,
				description: description,
				hint: hints[ method.id ] || '',
				checked: checked,
				unavailable: unavailable
			} );
		} );

		// If no method is selected (e.g. all pre-selected were unavailable), pick first available.
		if ( ! state.selectedMethod ) {
			for ( var i = 0; i < state.methods.length; i++ ) {
				if ( ! isMethodUnavailable( state.methods[ i ].id ) ) {
					state.selectedMethod = state.methods[ i ].id;
					var radioToCheck = list.querySelector( '#wp2fa-wizard-method-' + state.methods[ i ].id );
					if ( radioToCheck ) {
						radioToCheck.checked = true;
					}
					break;
				}
			}
		}

		// Attach radio change listeners.
		var radios = list.querySelectorAll( '.wp2fa-wizard-method-radio' );
		radios.forEach( function ( radio ) {
			radio.addEventListener( 'change', function () {
				state.selectedMethod = this.value;
				hooks.doAction( 'wp2fa_wizard_method_selected', this.value );
				updateFooterButtons();
			} );
		} );

		// Attach hint toggle listeners.
		var hintToggles = list.querySelectorAll( '.wp2fa-wizard-hint-toggle' );
		hintToggles.forEach( function ( btn ) {
			btn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				e.stopPropagation();
				var methodId = this.getAttribute( 'data-hint-for' );
				var hintEl = list.querySelector( '#wp2fa-wizard-hint-' + methodId );
				if ( hintEl ) {
					var isVisible = hintEl.style.display !== 'none';
					hintEl.style.display = isVisible ? 'none' : '';
					this.classList.toggle( 'wp2fa-wizard-hint-active', ! isVisible );
				}
			} );
		} );

		body.appendChild( list );

		// Footer.
		renderFooter( 'select-method' );

		state.currentStep = 'select-method';
		hooks.doAction( 'wp2fa_wizard_step_shown', 'select-method' );
		focusFirstInteractive();
	}

	/* -------------------------------------------------------
	 * Build & render the method configuration step
	 * ------------------------------------------------------- */
	function renderMethodConfigureStep() {
		var body = state.modal.querySelector( '#wp2fa-wizard-body' );
		body.innerHTML = '';

		var methodObj = getMethodById( state.selectedMethod );
		var stepTitle = methodObj ? methodObj.name : 'Configure Method';
		var title = state.modal.querySelector( '#wp2fa-wizard-title' );
		title.innerHTML = hooks.applyFilters( 'wp2fa_wizard_step_title', stepTitle, 'configure-method' );

		var container = el( 'div', {
			className: 'wp2fa-wizard-method-configure',
			id: 'wp2fa-wizard-method-configure-' + state.selectedMethod,
			'data-method-id': state.selectedMethod
		} );

		body.appendChild( container );

		/**
		 * Action: wp2fa_wizard_render_method
		 *
		 * Each method module listens for this and renders its configuration UI
		 * into the provided container element.
		 */
		hooks.doAction( 'wp2fa_wizard_render_method', state.selectedMethod, container, {
			userId: state.userId,
			userRole: state.userRole,
			nonce: state.nonce
		} );

		// Footer
		// renderFooter( 'configure-method' );

		state.currentStep = 'configure-method';
		hooks.doAction( 'wp2fa_wizard_step_shown', 'configure-method' );
		focusFirstInteractive();
	}

	/* -------------------------------------------------------
	 * Footer buttons
	 * ------------------------------------------------------- */
	function renderFooter( step ) {
		var footer = state.modal.querySelector( '#wp2fa-wizard-footer' );
		footer.innerHTML = '';

		if ( step === 'select-method' ) {
			var selectFooterTmpl = wp.template( 'wp2fa-wizard-select-footer' );
			var canProceed = hooks.applyFilters( 'wp2fa_wizard_can_proceed', !! state.selectedMethod, 'select-method' );

			footer.innerHTML = selectFooterTmpl( {
				goBackLabel: wp2faWizardData.i18n.goBack || 'Go back',
				continueLabel: wp2faWizardData.i18n.continueBtn || 'Continue',
				disabled: ! canProceed
			} );

			footer.querySelector( '#wp2fa-wizard-btn-goback' ).addEventListener( 'click', function () {
				if ( hasWelcomeStep() ) {
					hooks.doAction( 'wp2fa_wizard_before_back', 'select-method', 'welcome' );
					renderWelcomeStep();
					hooks.doAction( 'wp2fa_wizard_after_back', 'welcome' );
				} else {
					closeWizard();
				}
			} );

			footer.querySelector( '#wp2fa-wizard-btn-continue' ).addEventListener( 'click', function () {
				if ( ! state.selectedMethod ) {
					return;
				}
				hooks.doAction( 'wp2fa_wizard_before_proceed', 'select-method', 'configure-method' );
				renderMethodConfigureStep();
				hooks.doAction( 'wp2fa_wizard_after_proceed', 'configure-method' );
			} );

		} else if ( step === 'configure-method' ) {
			var backBtn = el( 'button', {
				className: 'wp2fa-wizard-btn wp2fa-wizard-btn-secondary wp-2fa-button-secondary',
				id: 'wp2fa-wizard-btn-back',
				type: 'button',
				textContent: wp2faWizardData.i18n.goBack || 'Go back'
			} );

			backBtn.addEventListener( 'click', function () {
				if ( state.methods.length <= 1 ) {
					// No method selection to go back to.
					if ( hasWelcomeStep() ) {
						hooks.doAction( 'wp2fa_wizard_before_back', 'configure-method', 'welcome' );
						renderWelcomeStep();
						hooks.doAction( 'wp2fa_wizard_after_back', 'welcome' );
					} else {
						requestCloseWizard();
					}
				} else {
					hooks.doAction( 'wp2fa_wizard_before_back', 'configure-method', 'select-method' );
					renderMethodSelectionStep();
					hooks.doAction( 'wp2fa_wizard_after_back', 'select-method' );
				}
			} );

			footer.appendChild( backBtn );
		}
	}

	function updateFooterButtons() {
		var continueBtn = state.modal ? state.modal.querySelector( '#wp2fa-wizard-btn-continue' ) : null;
		if ( continueBtn ) {
			var canProceed = hooks.applyFilters( 'wp2fa_wizard_can_proceed', !! state.selectedMethod, state.currentStep );
			continueBtn.disabled = ! canProceed;
		}
	}

	/* -------------------------------------------------------
	 * Utility
	 * ------------------------------------------------------- */
	function getMethodById( id ) {
		for ( var i = 0; i < state.methods.length; i++ ) {
			if ( state.methods[ i ].id === id ) {
				return state.methods[ i ];
			}
		}
		return null;
	}

	/* -------------------------------------------------------
	 * Build modal DOM (using wp.template)
	 * ------------------------------------------------------- */
	function buildModal() {
		var additionalClasses = hooks.applyFilters( 'wp2fa_wizard_modal_classes', '' );
		var title = wp2faWizardData.i18n.chooseMethod || 'Choose 2FA Method';

		var overlay = document.createElement( 'div' );
		overlay.className = 'wp2fa-setup-wizard-overlay';
		overlay.id = 'wp2fa-setup-wizard-overlay';
		overlay.setAttribute( 'aria-hidden', 'true' );

		var tmpl = wp.template( 'wp2fa-wizard-modal' );
		overlay.innerHTML = tmpl( {
			additionalClasses: additionalClasses,
			title: title,
			closeLabel: wp2faWizardData.i18n.close || 'Close'
		} );

		var modal = overlay.querySelector( '#wp2fa-setup-wizard-modal' );

		// Close button event.
		modal.querySelector( '#wp2fa-wizard-close' ).addEventListener( 'click', function () {
			requestCloseWizard();
		} );

		// Click on overlay backdrop to close.
		overlay.addEventListener( 'click', function ( e ) {
			if ( e.target === overlay ) {
				requestCloseWizard();
			}
		} );

		// Global keyboard handler for the wizard.
		document.addEventListener( 'keydown', function ( e ) {
			if ( ! overlay.classList.contains( 'wp2fa-wizard-open' ) ) {
				return;
			}

			// If the confirm dialog is visible, handle its own keys.
			var confirmOverlay = document.getElementById( 'wp2fa-wizard-confirm-overlay' );
			if ( confirmOverlay ) {
				if ( e.key === 'Escape' ) {
					e.preventDefault();
					dismissConfirmDialog();
				} else if ( e.key === 'Enter' ) {
					var focused = document.activeElement;
					// If focused on the "No" button, dismiss; otherwise confirm.
					if ( focused && focused.id === 'wp2fa-wizard-confirm-no' ) {
						e.preventDefault();
						dismissConfirmDialog();
					} else {
						e.preventDefault();
						confirmCloseWizard();
					}
				} else if ( e.key === 'Tab' ) {
					trapFocus( e, confirmOverlay );
				}
				return;
			}

			if ( e.key === 'Escape' ) {
				e.preventDefault();
				requestCloseWizard();
			} else if ( e.key === 'Enter' ) {
				// Trigger the primary button in the footer when Enter is pressed,
				// unless the user is focused on a non-primary button or a textarea.
				var active = document.activeElement;
				if ( active && ( active.tagName === 'TEXTAREA' || ( active.tagName === 'BUTTON' && ! isPrimaryWizardButton( active ) ) || active.tagName === 'A' ) ) {
					return;
				}
				var primaryBtn = getPrimaryFooterButton( modal );
				if ( primaryBtn ) {
					e.preventDefault();
					primaryBtn.click();
				}
			} else if ( e.key === 'Tab' ) {
				trapFocus( e, modal );
			}
		} );

		document.body.appendChild( overlay );

		state.overlay = overlay;
		state.modal = modal;
	}

	/* -------------------------------------------------------
	 * Accessibility: trap Tab focus within a container
	 * ------------------------------------------------------- */
	function trapFocus( e, container ) {
		var focusable = container.querySelectorAll(
			'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), textarea:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'
		);
		if ( focusable.length === 0 ) {
			return;
		}
		var first = focusable[ 0 ];
		var last  = focusable[ focusable.length - 1 ];

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

	/* -------------------------------------------------------
	 * Accessibility: focus the first interactive element
	 * in the wizard body, or fall back to the primary footer
	 * button.
	 * ------------------------------------------------------- */
	function focusFirstInteractive() {
		if ( ! state.modal ) {
			return;
		}
		var body = state.modal.querySelector( '#wp2fa-wizard-body' );
		if ( body ) {
			var target = body.querySelector(
				'input:not([disabled]):not([type="hidden"]):not([readonly]), textarea:not([disabled]), select:not([disabled]), a[href], button:not([disabled])'
			);
			if ( target ) {
				target.focus();
				return;
			}
		}
		var footer = state.modal.querySelector( '#wp2fa-wizard-footer' );
		if ( footer ) {
			var btn = getPrimaryFooterButton( state.modal );
			if ( btn ) {
				btn.focus();
				return;
			}
		}
		state.modal.focus();
	}

	/* -------------------------------------------------------
	 * Close confirmation dialog
	 * ------------------------------------------------------- */
	function requestCloseWizard() {
		// If the wizard just completed (success state), close without confirmation.
		if ( state.wizardCompleted ) {
			closeWizard( true );
			return;
		}
		showConfirmCloseDialog();
	}

	function showConfirmCloseDialog() {
		// Don't stack dialogs.
		if ( document.getElementById( 'wp2fa-wizard-confirm-overlay' ) ) {
			return;
		}

		var tmpl = wp.template( 'wp2fa-wizard-confirm-close' );
		var html = tmpl( {
			bodyHtml: wp2faWizardData.i18n.wizardCancelBody || '<h3>Are you sure?</h3><p>Any unsaved changes will be lost!</p>',
			yesLabel: wp2faWizardData.i18n.confirmYes || 'Yes',
			noLabel: wp2faWizardData.i18n.confirmNo || 'No'
		} );

		var wrapper = document.createElement( 'div' );
		wrapper.innerHTML = html;
		var confirmOverlay = wrapper.firstElementChild;
		state.modal.appendChild( confirmOverlay );

		// Focus the "No" button by default (safe option).
		var noBtn = confirmOverlay.querySelector( '#wp2fa-wizard-confirm-no' );
		if ( noBtn ) {
			noBtn.focus();
		}

		confirmOverlay.querySelector( '#wp2fa-wizard-confirm-yes' ).addEventListener( 'click', function () {
			confirmCloseWizard();
		} );

		confirmOverlay.querySelector( '#wp2fa-wizard-confirm-no' ).addEventListener( 'click', function () {
			dismissConfirmDialog();
		} );

		// Clicking the backdrop of the confirm overlay dismisses it.
		confirmOverlay.addEventListener( 'click', function ( e ) {
			if ( e.target === confirmOverlay ) {
				dismissConfirmDialog();
			}
		} );
	}

	function confirmCloseWizard() {
		dismissConfirmDialog();
		closeWizard( true );
	}

	function dismissConfirmDialog() {
		var confirmOverlay = document.getElementById( 'wp2fa-wizard-confirm-overlay' );
		if ( confirmOverlay && confirmOverlay.parentNode ) {
			confirmOverlay.parentNode.removeChild( confirmOverlay );
		}
		// Return focus to the modal.
		if ( state.modal ) {
			state.modal.focus();
		}
	}

	/* -------------------------------------------------------
	 * Open / Close wizard
	 * ------------------------------------------------------- */
	function openWizard() {
		if ( ! state.overlay ) {
			buildModal();
		}

		collectMethods();

		if ( state.methods.length === 0 ) {
			// eslint-disable-next-line no-console
			console.warn( '[WP2FA Wizard] No methods available for this user.' );
			return;
		}

		// Reset state — pre-select the user's currently configured method if available.
		state.selectedMethod = wp2faWizardData.currentMethod || null;
		state.currentStep = 'select-method';
		state.wizardCompleted = false;

		state.overlay.classList.add( 'wp2fa-wizard-open' );
		state.overlay.setAttribute( 'aria-hidden', 'false' );
		document.body.style.overflow = 'hidden';

		// Show the required intro step first if applicable (enforced users
		// who haven't seen it yet), then the welcome step if configured,
		// otherwise proceed to method selection (or directly to configure
		// if only one method).
		/*if ( hasRequiredIntroStep() ) {
			renderRequiredIntroStep();
		} else*/ if ( hasWelcomeStep() ) {
			renderWelcomeStep();
		} else if ( state.methods.length === 1 ) {
			// If only one method is available, skip the selection step and go
			// directly to configuring that method.
			state.selectedMethod = state.methods[ 0 ].id;
			hooks.doAction( 'wp2fa_wizard_method_selected', state.selectedMethod );
			renderMethodConfigureStep();
		} else {
			renderMethodSelectionStep();
		}

		focusFirstInteractive();
		hooks.doAction( 'wp2fa_wizard_opened' );
	}

	function closeWizard( confirmed ) {
		if ( ! confirmed ) {
			requestCloseWizard();
			return;
		}
		if ( ! state.overlay ) {
			return;
		}
		state.overlay.classList.remove( 'wp2fa-wizard-open' );
		state.overlay.setAttribute( 'aria-hidden', 'true' );
		document.body.style.overflow = '';
		hooks.doAction( 'wp2fa_wizard_closed' );
	}

	/* -------------------------------------------------------
	 * Shared utilities for method sub-modules
	 *
	 * These are common helpers used across multiple wizard
	 * method JS files (TOTP, Email, OOB, etc.) to avoid
	 * repeating the same code in each module.
	 * ------------------------------------------------------- */

	/**
	 * Display a success or error message inside a response element.
	 *
	 * @param {HTMLElement} el      The response container element.
	 * @param {string}      type    'success' or 'error'.
	 * @param {string}      message The message text to display.
	 */
	function showResponse( el, type, message ) {
		el.className = 'wp2fa-wizard-verification-response';
		if ( type === 'success' ) {
			el.classList.add( 'wp2fa-response-success' );
		} else {
			el.classList.add( 'wp2fa-response-error' );
		}
		el.textContent = message;
		setTimeout( function () {
			el.textContent = '';
			el.className = 'wp2fa-wizard-verification-response';
		}, 3000 );
	}

	/**
	 * Validate an email address with a simple pattern check.
	 *
	 * @param {string} email The email address to validate.
	 * @return {boolean} True if the email looks valid.
	 */
	function isValidEmail( email ) {
		return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( email );
	}

	/**
	 * Create and append a spinner element to a button.
	 *
	 * @param {HTMLElement} btn The button to append the spinner to.
	 * @return {HTMLElement} The spinner element (for later removal).
	 */
	function addSpinner( btn ) {
		var spinner = document.createElement( 'span' );
		spinner.className = 'wp2fa-wizard-spinner';
		btn.appendChild( spinner );
		return spinner;
	}

	/**
	 * Remove a spinner element from the DOM.
	 *
	 * @param {HTMLElement} spinner The spinner element to remove.
	 */
	function removeSpinner( spinner ) {
		if ( spinner && spinner.parentNode ) {
			spinner.parentNode.removeChild( spinner );
		}
	}

	/**
	 * Extract an error message from a WP AJAX JSON response.
	 *
	 * @param {Object} data            The parsed JSON response.
	 * @param {string} fallbackMessage Fallback message if none found in response.
	 * @return {string} The error message.
	 */
	function extractAjaxError( data, fallbackMessage ) {
		return ( data.data && ( data.data.error || data.data.message ) )
			? ( data.data.error || data.data.message )
			: ( fallbackMessage || 'An error occurred.' );
	}

	/**
	 * Show or create an inline error message inside a container.
	 *
	 * Reuses an existing .wp2fa-wizard-verification-response element
	 * or creates one and appends it to the container.
	 *
	 * @param {HTMLElement} container The parent container.
	 * @param {string}      message   The error message to display.
	 */
	function showInlineError( container, message ) {
		var existing = container.querySelector( '.wp2fa-wizard-verification-response' );
		if ( ! existing ) {
			existing = document.createElement( 'div' );
			existing.className = 'wp2fa-wizard-verification-response';
			container.appendChild( existing );
		}
		existing.className = 'wp2fa-wizard-verification-response wp2fa-response-error';
		existing.textContent = message;
		setTimeout( function () {
			existing.textContent = '';
			existing.className = 'wp2fa-wizard-verification-response';
		}, 3000 );
	}

	/* -------------------------------------------------------
	 * Public API exposed on window.wp2faWizard
	 * ------------------------------------------------------- */
	window.wp2faWizard = {
		open: openWizard,
		close: requestCloseWizard,
		finish: finishWizard,
		nextBackupMethod: nextBackupMethod,
		focusFirstInteractive: focusFirstInteractive,
		getState: function () {
			return {
				currentStep: state.currentStep,
				selectedMethod: state.selectedMethod,
				selectedBackupMethod: state.selectedBackupMethod,
				methods: state.methods.slice(),
				backupMethods: state.backupMethods.slice(),
				currentBackupIndex: state.currentBackupIndex,
				userId: state.userId,
				userRole: state.userRole
			};
		},
		goToStep: function ( step ) {
			/*if ( step === 'required-intro' && hasRequiredIntroStep() ) {
				renderRequiredIntroStep();
			} else */ if ( step === 'welcome' && hasWelcomeStep() ) {
				renderWelcomeStep();
			} else if ( step === 'select-method' ) {
				// If only one method is available, going "back" to selection
				// should go to welcome if available, or close the wizard.
				if ( state.methods.length <= 1 ) {
					if ( hasWelcomeStep() ) {
						renderWelcomeStep();
					} else {
						requestCloseWizard();
					}
				} else {
					renderMethodSelectionStep();
				}
			} else if ( step === 'configure-method' && state.selectedMethod ) {
				renderMethodConfigureStep();
			}
		},
		utils: {
			showResponse: showResponse,
			isValidEmail: isValidEmail,
			addSpinner: addSpinner,
			removeSpinner: removeSpinner,
			extractAjaxError: extractAjaxError,
			showInlineError: showInlineError
		}
	};

	/* -------------------------------------------------------
	 * Init on DOM ready
	 * ------------------------------------------------------- */
	function init() {
		if ( typeof wp2faWizardData === 'undefined' ) {
			// eslint-disable-next-line no-console
			console.error( '[WP2FA Wizard] wp2faWizardData localization object is missing.' );
			return;
		}

		state.userId = wp2faWizardData.userId || 0;
		state.userRole = wp2faWizardData.userRole || '';
		state.nonce = wp2faWizardData.nonce || '';

		// Bind open buttons
		var openButtons = document.querySelectorAll( '[data-wp2fa-open-wizard]' );
		openButtons.forEach( function ( btn ) {
			btn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				openWizard();
			} );
		} );

		// Listen for primary method validation success — transition to backup methods or close.
		hooks.addAction( 'wp2fa_wizard_method_validated', 'wp2fa/wizard-core', handleMethodValidated );

		hooks.doAction( 'wp2fa_wizard_init' );

		// Auto-open wizard if server-side flagged it (enforced user, grace expired).
		if ( wp2faWizardData.autoOpenWizard ) {
			setTimeout( function () {
				openWizard();
			}, 100 );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
