/* global wp2faData, MicroModal */
/* eslint-disable require-jsdoc, no-mixed-spaces-and-tabs, camelcase, prefer-destructuring, no-var, no-unused-vars, no-redeclare,  no-inner-declarations, no-console */

jQuery( document ).ready( function() {

	// Excluded sites
	if ( jQuery( '#excluded_sites_search' ).length ) {
		const usersUrl = `${wp2faData.ajaxURL}?action=get_all_network_sites&wp_2fa_nonce=${wp2faData.nonce}`;
		jQuery( '#excluded_sites_search' ).autocomplete( {
			source: usersUrl,
			minLength: 1,
			focus: function() {
				return false;
			},
			select: function( event, ui ) {
				// Grab the current list of excludes folks
				const currentlyExcluded = jQuery( '#excluded_sites' ).val();
				if ( !currentlyExcluded.includes( ui.item.value ) ) {
					jQuery( '#excluded_sites' ).val( `${currentlyExcluded + ui.item.value},` );
				}

				const excludedUsersArray = jQuery( '#excluded_sites' ).val().split( ',' );
				jQuery( '#excluded_sites_buttons' ).html( '' );
				jQuery.each( excludedUsersArray, function( i ) {
					if ( excludedUsersArray[i] ) {
						jQuery( '#excluded_sites_buttons' ).append( `<a class="user-btn button button-secondary" data-user-value="${excludedUsersArray[i]}">${excludedUsersArray[i].split( ':' )[0]}<span class="remove-item">x</span></a>` );
					}
				} );
				jQuery( '#excluded_sites_search' ).val( '' );
				return false;
			},
			open: function( event, ui ) {
				jQuery( '.ui-menu-item' ).each( function( i, obj ) {
					var originalLabel = jQuery( this ).text();
					jQuery( this ).text( originalLabel.split( ':' )[0] );
				} );
			}
		} );

		var excludedUsersArray = jQuery( '#excluded_sites' ).val().split( ',' );
		jQuery.each( excludedUsersArray, function( i ) {
			if ( excludedUsersArray[i] ) {
				jQuery( '#excluded_sites_buttons' ).append( `<a class="user-btn button button-secondary" data-user-value="${excludedUsersArray[i]}">${excludedUsersArray[i].split( ':' )[0]}<span class="remove-item">x</span></a>` );
			}
		} );
	}

	jQuery( 'body' ).on( 'click', '.remove-item', function( e ) {
		e.preventDefault();
		var textToRemove = jQuery( this ).closest( '.user-btn' ).attr( 'data-user-value' );
		var textToRemove = `${textToRemove},`;
		var currentlyExcluded = jQuery( this ).closest( 'div' ).siblings( 'input[type="hidden"]' ).val();
		var currentlyExcluded = currentlyExcluded.replace( textToRemove, '' );
		jQuery( this ).closest( 'div' ).siblings( 'input[type="hidden"]' ).val( currentlyExcluded );
		jQuery( this ).closest( '.user-btn' ).remove();
	} );

	// Detect intereactions so we can determine if we need "exclude yourself?" popup.
	jQuery( '[name="wp_2fa_settings[enforcement-policy]"], [name="wp_2fa_settings[grace-policy]"]' ).on( "input", function() {
		if ( jQuery( 'input[name="wp_2fa_settings[grace-policy]"]:checked' ).val() == 'no-grace-period' && jQuery( 'input[name="wp_2fa_settings[enforcement-policy]"]:checked' ).val() == 'all-users' ) {
			var userToAdd = jQuery( '[data-user-login-name]' ).attr( 'data-user-login-name' );
			var targetElement = jQuery( '#excluded-users-multi-select' );		
			// Only show it if needed.	
			if ( jQuery('.exclude-self-from-instant-2fa').length && ! jQuery( targetElement ).find( 'option[value="' + userToAdd + '"]' ).length ) {
				MicroModal.show( 'exclude-self-from-instant-2fa' );
			}			
		}

		// Warn user about what their changes will mean.
		if ( jQuery( 'input[name="wp_2fa_settings[enforcement-policy]"]:checked' ).val() != 'all-users' ) {
			var userData = jQuery( '#excluded-users-multi-select' ).select2( 'data' );	
			var data = jQuery( '#excluded-roles-multi-select' ).select2( 'data' );			
			if ( jQuery( userData ).length || jQuery( data ).length ) {
				MicroModal.show( 'warn-exclusions-will-be-removed' );
			}	
		}

		// Show/Hide setting as needed/
		if ( jQuery( 'input[name="wp_2fa_settings[enforcement-policy]"]:checked' ).val() == 'all-users' ) {
			jQuery( '#exclusion_settings_wrapper' ).removeClass( 'disabled' ).slideDown( 300 );
		} else {
			jQuery( '#exclusion_settings_wrapper' ).slideUp( 300 ).addClass( 'disabled' );
		}
	});

	// Clear exclusions if users continues.
	jQuery( 'body' ).on( 'click', '[data-clear-exclusions]', function( e ) {
		jQuery( '#excluded-roles-multi-select, #excluded-users-multi-select' ).val(null).trigger('change');
	});

	// Cancel action and return form to previous state.
	jQuery( 'body' ).on( 'click', '#warn-exclusions-will-be-removed [data-cancel-action]', function( e ) {
		jQuery( 'input[id="all-users"]' ).prop( 'checked', true ).trigger( 'click' );
		jQuery( '[name="wp_2fa_settings[grace-policy]"]' ).trigger( 'input' );
	});

	// Add our username to the list.
	jQuery( 'body' ).on( 'click', '[data-user-login-name]', function( e ) {
		e.preventDefault();
		var newValue  = [];
		var userToAdd = jQuery( '[data-user-login-name]' ).attr( 'data-user-login-name' );
		newValue.push( userToAdd );
		var targetElement = jQuery( '#excluded-users-multi-select' );
		if ( ! jQuery( targetElement ).find( 'option[value="' + newValue + '"]' ).length ) {		  
			var newState = new Option( newValue, newValue, true, true );
			jQuery( targetElement ).append( newState ).trigger( 'change' );
		}
	} );

	// Fix "grace-period" to not except anything but number.
	jQuery( 'body' ).on( 'input', 'input[type="number"]#grace-period', function ( e ) {
		var targetElm = jQuery( this );
		var currentValue = targetElm.val();

		//	check if number and above button limit
		var newValue = !!currentValue && 0 <= Math.abs( currentValue ) ? Math.abs( currentValue ) : null;

		//	check the upper limit
		var upperLimit = targetElm.attr( 'max' );
		if ( parseInt( upperLimit ) < parseInt( newValue ) ) {
			newValue = upperLimit;
		}

		//	only update DOM if needed
		if ( newValue != currentValue ) {
			targetElm.val( newValue );
		}
	} );

	jQuery( 'body' ).on( 'focusout', 'input[type="number"]#grace-period', function ( e ) {
		var targetElm = jQuery( this );
		var currentValue = targetElm.val();

		var minVal = targetElm.attr( 'min' );

		if ( '' === jQuery.trim( currentValue ) ) {
			targetElm.val( minVal );
		}
	} );

	// Enabled/Disable the "destroy session" option based on if "grace-period" is checked or not.
	jQuery( 'body' ).on( 'click', 'input[type="checkbox"]#grace-cron', function( e ) {
		if ( jQuery( this ).is( ':checked' ) ) {
			jQuery( '.destory-session-setting' ).removeClass( 'disabled' );
		} else if ( jQuery( this ).is( ':not(:checked)' ) ) {
			jQuery( '.destory-session-setting' ).addClass( 'disabled' );
			jQuery( 'input[type="checkbox"]#destory-session' ).prop( 'checked', false );
		}
	} );
	if ( jQuery( 'input[type="checkbox"]#grace-cron' ).is( ':checked' ) ) {
		jQuery( '.destory-session-setting' ).removeClass( 'disabled' );
	} else if ( jQuery( 'input[type="checkbox"]#grace-cron' ).is( ':not(:checked)' ) ) {
		jQuery( '.destory-session-setting' ).addClass( 'disabled' );
		jQuery( 'input[type="checkbox"]#destory-session' ).prop( 'checked', false );
	}

	jQuery( 'body' ).on( 'click', 'input[type="radio"]#use_custom_page, input[type="radio"]#dont_use_custom_page', function( e ) {
		if ( jQuery( 'input[type="radio"]#use_custom_page' ).is( ':checked' ) ) {
			jQuery( '.custom-user-page-setting' ).removeClass( 'disabled' );
		} else if ( jQuery( 'input[type="radio"]#dont_use_custom_page' ).is( ':checked' ) ) {
			jQuery( '.custom-user-page-setting' ).addClass( 'disabled' );
		}
	} );
	if ( jQuery( 'input[type="radio"]#use_custom_page' ).is( ':checked' ) ) {
		jQuery( '.custom-user-page-setting' ).removeClass( 'disabled' );
	} else if ( jQuery( 'input[type="radio"]#use_custom_page' ).is( ':not(:checked)' ) ) {
		jQuery( '.custom-user-page-setting' ).addClass( 'disabled' );
	}

	// Handle settings submission.
	jQuery( 'body' ).on( 'click', '.js-button-test-email-trigger', function( e ) {
		e.preventDefault();
		const button = jQuery( this );
		const emailId = button.attr( 'data-email-id' );
		const nonceValue = button.attr( 'data-nonce' );

		button.append( '<span class="spinner is-active"></span>' );
		const spinner = button.find( '.spinner' );

		button.siblings( '.notice' ).remove();
		button.attr( 'disabled', 'disabled' );
		button.addClass( 'has-spinner' );

		jQuery.post( wp2faData.ajaxURL, {
			action: 'wp2fa_test_email',
			email_id: emailId,
			_wpnonce: nonceValue
		} ).done(
			function( data ) {
				let classes = 'notice notice-after-button notice-';
				classes += ( data.success ) ? 'success' : 'error';
				var message = ( data.success ) ? wp2faData.email_sent_success : wp2faData.email_sent_failure;
				if ( 'data' in data ) {
					message = data.data;
				}
				button.after( `<span class="${classes}">${message}</span>` );
				spinner.remove();
				button.removeClass( 'has-spinner' );
				button.removeAttr( 'disabled' );

			} );
	} );
} );
