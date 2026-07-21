/* WooCommerce Express Checkout — admin notice dismiss */
( function ( $ ) {
	'use strict';

	$( document ).on( 'click', '.notice[data-wec-dismiss] .notice-dismiss', function () {
		$.post( wecAdmin.ajaxUrl, {
			action: 'wec_dismiss_notice',
			nonce:  wecAdmin.nonce
		} );
	} );

} )( jQuery );
