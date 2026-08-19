/* WooCommerce Express Checkout — admin interactions */
( function ( $ ) {
	'use strict';

	// Dismiss the checkout block compatibility notice.
	$( document ).on( 'click', '.notice[data-wec-dismiss] .notice-dismiss', function () {
		$.post( wecAdmin.ajaxUrl, {
			action: 'wec_dismiss_notice',
			nonce:  wecAdmin.nonce
		} );
	} );

	// Open the media library and assign the selected image to a hidden input.
	$( document ).on( 'click', '.wec-upload-image', function ( e ) {
		e.preventDefault();

		var button = $( this );
		var target = button.data( 'target' );
		var previewId = button.data( 'preview' );
		var previewClass = button.data( 'preview-class' );
		var frame = wp.media( {
			title: wecAdmin.mediaTitle || 'Select image',
			multiple: false,
			library: { type: 'image' }
		} );

		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();

			$( 'input[name="' + target + '"]' ).val( attachment.id );

			var preview;
			if ( previewId ) {
				preview = $( '#' + previewId );
			} else {
				preview = button.closest( 'td' ).find( 'img.' + previewClass ).first();
			}

			if ( preview.length ) {
				preview.attr( 'src', attachment.url ).show();
			}

			button.closest( 'p, td' ).find( '.wec-remove-image' ).show();
		} );

		frame.open();
	} );

	// Clear the selected image.
	$( document ).on( 'click', '.wec-remove-image', function ( e ) {
		e.preventDefault();

		var button = $( this );
		var target = button.data( 'target' );
		var previewId = button.data( 'preview' );
		var previewClass = button.data( 'preview-class' );

		$( 'input[name="' + target + '"]' ).val( '' );

		var preview;
		if ( previewId ) {
			preview = $( '#' + previewId );
		} else {
			preview = button.closest( 'td' ).find( 'img.' + previewClass ).first();
		}

		if ( preview.length ) {
			preview.attr( 'src', '' ).hide();
		}

		button.hide();
	} );

} )( jQuery );
