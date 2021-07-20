/* global wp2faData */
/* eslint-disable require-jsdoc */

jQuery( function ( ) {

	const select2Autocomplete = function ( source, functionName ) {
		jQuery( source ).select2( {
			width: 'resolve',
			ajax: {
				url: `${wp2faData.ajaxURL}?wp_2fa_nonce=${wp2faData.nonce}`, // AJAX URL is predefined in WordPress admin
				dataType: 'json',
				delay: 250, // delay in ms while typing when to perform a AJAX search
				data: function ( params ) {
					return {
						term: params.term, // search query
						action: functionName // AJAX action for admin-ajax.php
					};
				},
				processResults: function ( data ) {
					const options = [];
					if ( data ) {

						// data is the array of arrays, and each of them contains ID and the Label of the option
						jQuery.each( data, function ( index, text ) { // do not forget that "index" is just auto incremented value
							options.push( { id: text['label'], text: text['value'] } );
						} );

					}
					return {
						results: options
					};
				},
				cache: true
			},
			minimumInputLength: 2 // the minimum of symbols to input before perform a search
		} );
	};

	// Excluded users
	if ( jQuery( '#excluded-users-multi-select' ).length ) {
		select2Autocomplete( '#excluded-users-multi-select', 'get_all_users' );
	}

	// Enforced users
	if ( jQuery( '#enforced_users-multi-select' ).length ) {
		select2Autocomplete( '#enforced_users-multi-select', 'get_all_users' );
	}

	// Excluded roles
	if ( jQuery( '#excluded-roles-multi-select' ).length ) {
		jQuery( '#excluded-roles-multi-select' ).select2();
	}

	// Enforced roles
	if ( jQuery( '#enforced-roles-multi-select' ).length ) {
		jQuery( '#enforced-roles-multi-select' ).select2();
	}

	// Excluded sites
	if ( jQuery( '#excluded-sites-multi-select' ).length ) {
		select2Autocomplete( '#excluded-sites-multi-select', 'get_all_network_sites' );
	}
} );
