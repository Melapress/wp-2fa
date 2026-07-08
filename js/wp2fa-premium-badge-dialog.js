/**
 * WP 2FA - Premium badge dialog handler.
 *
 * Opens a reusable premium upsell dialog when a premium badge is clicked.
 * Content is driven by window.wp2faPremiumBadgeDialog.pages.
 *
 * @package wp-2fa
 * @since   4.1.0
 */

( function () {
	'use strict';

	var config = window.wp2faPremiumBadgeDialog || {};
	if ( ! window.wp2faDialog ) {
		return;
	}

	function isObject( value ) {
		return value && 'object' === typeof value && ! Array.isArray( value );
	}

	function getPageConfig( key ) {
		var pages = isObject( config.pages ) ? config.pages : {};
		return isObject( pages[ key ] ) ? pages[ key ] : {};
	}

	function mergeObjects( base, override ) {
		var out = {};
		var k;
		for ( k in base ) {
			if ( Object.prototype.hasOwnProperty.call( base, k ) ) {
				out[ k ] = base[ k ];
			}
		}
		for ( k in override ) {
			if ( Object.prototype.hasOwnProperty.call( override, k ) ) {
				out[ k ] = override[ k ];
			}
		}
		return out;
	}

	function getContextKey( trigger ) {
		var explicit = trigger.getAttribute( 'data-wp2fa-premium-context' );
		if ( explicit ) {
			return explicit;
		}

		if ( config.currentPage && config.currentTab ) {
			return config.currentPage + ':' + config.currentTab;
		}

		if ( config.currentTab ) {
			return 'tab:' + config.currentTab;
		}

		if ( config.currentPage ) {
			return config.currentPage;
		}

		if ( config.currentScreen ) {
			return 'screen:' + config.currentScreen;
		}

		return 'default';
	}

	function renderBullets( bullets, target ) {
		if ( ! Array.isArray( bullets ) || bullets.length < 1 ) {
			return;
		}

		var list = document.createElement( 'ul' );
		list.className = 'wp2fa-premium-dialog-list';

		bullets.forEach( function ( bullet ) {
			if ( ! isObject( bullet ) || ! bullet.title ) {
				return;
			}

			var item = document.createElement( 'li' );
			item.className = 'wp2fa-premium-dialog-list-item';

			var icon = document.createElement( 'span' );
			icon.className = 'wp2fa-premium-dialog-list-icon';
			icon.setAttribute( 'aria-hidden', 'true' );

			var textWrap = document.createElement( 'div' );
			textWrap.className = 'wp2fa-premium-dialog-list-text';

			var title = document.createElement( 'strong' );
			title.className = 'wp2fa-premium-dialog-list-title';
			title.textContent = bullet.title;
			textWrap.appendChild( title );

			if ( bullet.description ) {
				var description = document.createElement( 'span' );
				description.className = 'wp2fa-premium-dialog-list-description';
				description.textContent = bullet.description;
				textWrap.appendChild( description );
			}

			item.appendChild( icon );
			item.appendChild( textWrap );
			list.appendChild( item );
		} );

		target.appendChild( list );
	}

	function renderContent( dialogData ) {
		var wrap = document.createElement( 'div' );
		wrap.className = 'wp2fa-premium-dialog';

		if ( dialogData.intro ) {
			var intro = document.createElement( 'p' );
			intro.className = 'wp2fa-premium-dialog-intro';
			intro.textContent = dialogData.intro;
			wrap.appendChild( intro );
		}

		if ( dialogData.description ) {
			var description = document.createElement( 'p' );
			description.className = 'wp2fa-premium-dialog-description';
			description.textContent = dialogData.description;
			wrap.appendChild( description );
		}

		renderBullets( dialogData.bullets, wrap );

		if ( dialogData.screenshotUrl ) {
			var screenshot = document.createElement( 'img' );
			screenshot.className = 'wp2fa-premium-dialog-screenshot';
			screenshot.src = dialogData.screenshotUrl;
			screenshot.alt = dialogData.screenshotAlt || '';
			wrap.appendChild( screenshot );
		}

		if ( isObject( dialogData.cta ) && dialogData.cta.text && dialogData.cta.url ) {
			// Use ctaPremium when user has an active license (enabled=false means premium).
			var ctaData = dialogData.cta;
			if ( ! config.enabled && isObject( dialogData.ctaPremium ) && dialogData.ctaPremium.text && dialogData.ctaPremium.url ) {
				ctaData = dialogData.ctaPremium;
			}
			var cta = document.createElement( 'a' );
			cta.className = 'wp2fa-premium-dialog-cta';
			cta.href = ctaData.url;
			cta.target = '_blank';
			cta.rel = 'noopener noreferrer';
			cta.textContent = ctaData.text;
			wrap.appendChild( cta );
		}

		return wrap.outerHTML;
	}

	function openPremiumDialog( trigger ) {
		var defaultData = getPageConfig( 'default' );
		var key = getContextKey( trigger );
		var pageData = getPageConfig( key );
		var dialogData = mergeObjects( defaultData, pageData );

		if ( ! dialogData.title ) {
			dialogData.title = 'Premium feature';
		}

		window.wp2faDialog.open( {
			htmlTitle: dialogData.title,
			content: renderContent( dialogData ),
			buttons: [],
			size: 'large',
			modalClass: 'wp2fa-premium-dialog-modal'
		} );
	}

	document.addEventListener( 'click', function ( event ) {
		var trigger = event.target.closest( '.wp2fa-premium-badge' );
		if ( ! trigger ) {
			return;
		}

		event.preventDefault();
		event.stopPropagation();
		openPremiumDialog( trigger );
	}, true );

	document.addEventListener( 'keydown', function ( event ) {
		var active = document.activeElement;
		var isBadge = active && active.matches && active.matches( '.wp2fa-premium-badge' );
		if ( ! isBadge || ( 'Enter' !== event.key && ' ' !== event.key ) ) {
			return;
		}

		event.preventDefault();
		openPremiumDialog( active );
	} );
} )();
