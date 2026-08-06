/* ============================================================
   WooCommerce Express Checkout — Checkout Frontend JS
   Phase 2: Mobile accordion + Coupon AJAX
   ============================================================ */

( function () {
	'use strict';

	/* ---------- Mobile accordion toggle ---------- *
	 * Uses vanilla JavaScript and does not require jQuery.
	 */
	function initAccordion() {
		var toggle = document.getElementById( 'wec-summary-toggle' );
		var mobileSummary = document.getElementById( 'wec-summary-mobile' );

		if ( ! toggle || ! mobileSummary ) {
			return;
		}

		var btn = toggle.querySelector( '.wec-summary-toggle-btn' );
		if ( ! btn ) {
			return;
		}

		btn.addEventListener( 'click', function () {
			var expanded = btn.getAttribute( 'aria-expanded' ) === 'true';
			btn.setAttribute( 'aria-expanded', String( ! expanded ) );

			if ( expanded ) {
				mobileSummary.setAttribute( 'hidden', '' );
			} else {
				mobileSummary.removeAttribute( 'hidden' );
			}
		} );
	}

	/* ---------- Coupon AJAX apply ---------- *
	 * Uses jQuery, which is loaded by WooCommerce checkout, and wc_checkout_params.
	 * Event delegation keeps the handler working after AJAX fragments replace
	 * the coupon elements without creating duplicate handlers.
	 */
	function initCoupon() {
		if ( typeof jQuery === 'undefined' || typeof wc_checkout_params === 'undefined' ) {
			return;
		}

		var $ = jQuery;

		// Apply button using delegated event handling.
		$( document ).on( 'click', '.wec-coupon-btn', function ( e ) {
			e.preventDefault();
			var field = $( this ).closest( '.wec-coupon-field' );
			var input = field.find( '.wec-coupon-input' );
			applyCoupon( $, input, field );
		} );

		// Apply the coupon when the customer presses Enter.
		$( document ).on( 'keydown', '.wec-coupon-input', function ( e ) {
			if ( e.key === 'Enter' || e.keyCode === 13 ) {
				e.preventDefault();
				var field = $( this ).closest( '.wec-coupon-field' );
				applyCoupon( $, $( this ), field );
			}
		} );
	}

	function applyCoupon( $, $input, $field ) {
		var code = ( $input.val() || '' ).trim();
		if ( ! code ) {
			return;
		}

		var $msg = $field.find( '.wec-coupon-msg' );
		var email = '';

		var $emailInput = $( 'form.checkout' ).find( 'input[name="billing_email"]' );
		if ( $emailInput.length ) {
			email = $emailInput.val();
		}

		$field.addClass( 'processing' );
		$msg.empty();

		$.ajax( {
			type: 'POST',
			url: wc_checkout_params.wc_ajax_url.toString().replace( '%%endpoint%%', 'apply_coupon' ),
			data: {
				security: wc_checkout_params.apply_coupon_nonce,
				coupon_code: code,
				billing_email: email
			},
			success: function ( response ) {
				$field.removeClass( 'processing' );

				// Clear previous notices from all coupon fields.
				$( '.wec-coupon-msg' ).empty();

				if ( response ) {
					if (
						response.indexOf( 'woocommerce-error' ) !== -1 ||
						response.indexOf( 'is-error' ) !== -1
					) {
						// Display the error in the current coupon field.
						$msg.html( response );
					} else {
						// Clear coupon inputs and display the confirmation message.
						$( '.wec-coupon-input' ).val( '' );
						$msg.html( response );

						// Refresh the WooCommerce order summary and checkout totals.
						$( document.body ).trigger( 'applied_coupon_in_checkout', [ code ] );
						$( document.body ).trigger( 'update_checkout', {
							update_shipping_method: false
						} );
					}
				}
			},
			error: function () {
				$field.removeClass( 'processing' );
			},
			dataType: 'html'
		} );
	}

	/* ---------- Init ---------- */
	function init() {
		initAccordion();
		initCoupon();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
