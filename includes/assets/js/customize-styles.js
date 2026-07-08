/* global wp, jQuery */
/**
 * Customize Styles – admin JS for the "Edit styles" tab on the Customize 2FA
 * code page settings screen.
 *
 * Responsibilities:
 *  - Boot WP native color-picker on every .wp2fa-color-picker input.
 *  - Open the WP media library when a .wp2fa-media-upload button is clicked.
 *  - Handle image removal via .wp2fa-media-remove.
 *  - Apply the selected font-family to the font-select preview.
 *
 * NOTE: jQuery is only used here because wp-color-picker ships as a jQuery
 * plugin. No jQuery is used in the PHP Settings_Builder class itself.
 *
 * @package wp-2fa
 * @since   latest
 */

( function ( $ ) {
	'use strict';

	/* ------------------------------------------------------------------ */
	/* Color pickers                                                        */
	/* ------------------------------------------------------------------ */

	function initColorPickers() {
		$( '.wp2fa-color-picker' ).each( function () {
			$( this ).wpColorPicker();
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Image / logo selectors                                               */
	/* ------------------------------------------------------------------ */

	function initImageSelectors() {
		$( document ).on( 'click', '.wp2fa-media-upload', function ( e ) {
			e.preventDefault();

			var $btn      = $( this );
			var targetId  = $btn.data( 'target' );
			var $wrap     = $btn.closest( '.wp2fa-image-select-wrap' );
			var $hidden   = $wrap.find( 'input[type="hidden"]' );
			var $preview  = $wrap.find( '.wp2fa-image-preview' );
			var $img      = $preview.find( 'img' );

			var frame = wp.media( {
				title    : wp2faCustomizeStyles.selectMediaTitle,
				library  : { type: 'image' },
				button   : { text: wp2faCustomizeStyles.selectMediaButton },
				multiple : false,
			} );

			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first().toJSON();
				$hidden.val( attachment.url ).trigger( 'change' );
				$img.attr( 'src', attachment.url );
				$preview.removeClass( 'hidden' );
			} );

			frame.open();
		} );

		$( document ).on( 'click', '.wp2fa-media-remove', function ( e ) {
			e.preventDefault();

			var $wrap = $( this ).closest( '.wp2fa-image-select-wrap' );
			$wrap.find( 'input[type="hidden"]' ).val( '' ).trigger( 'change' );
			$wrap.find( '.wp2fa-image-preview img' ).attr( 'src', '' );
			$wrap.find( '.wp2fa-image-preview' ).addClass( 'hidden' );
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Font select preview                                                  */
	/* ------------------------------------------------------------------ */

	function initFontSelects() {
		$( '.wp2fa-font-select' ).each( function () {
			applyFontPreview( $( this ) );
		} );

		$( document ).on( 'change', '.wp2fa-font-select', function () {
			applyFontPreview( $( this ) );
		} );
	}

	function applyFontPreview( $select ) {
		var fontFamily = $select.val();
		// Show the selected font in the select element itself.
		$select.css( 'font-family', fontFamily );
	}

	/* ------------------------------------------------------------------ */
	/* Init                                                                 */
	/* ------------------------------------------------------------------ */

	$( document ).ready( function () {
		initColorPickers();
		initImageSelectors();
		initFontSelects();
	} );

} )( jQuery );
