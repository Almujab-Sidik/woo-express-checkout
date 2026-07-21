/* ============================================================
   WooCommerce Express Checkout — Checkout Frontend JS
   Phase 2: Mobile accordion + Coupon AJAX
   ============================================================ */

( function () {
	'use strict';

	/* ---------- Mobile accordion toggle ---------- *
	 * Vanilla JS — tidak butuh jQuery.
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
	 * Memakai jQuery (sudah di-load oleh WC checkout) + wc_checkout_params.
	 * Event delegation dipakai agar handler tetap berfungsi setelah
	 * fragment AJAX mengganti elemen (tanpa handler duplikat).
	 */
	function initCoupon() {
		if ( typeof jQuery === 'undefined' || typeof wc_checkout_params === 'undefined' ) {
			return;
		}

		var $ = jQuery;

		// Tombol "Pakai" — event delegation (sekali bind, selalu jalan).
		$( document ).on( 'click', '.wec-coupon-btn', function ( e ) {
			e.preventDefault();
			var field = $( this ).closest( '.wec-coupon-field' );
			var input = field.find( '.wec-coupon-input' );
			applyCoupon( $, input, field );
		} );

		// Enter key pada input kupon.
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

				// Bersihkan notice lama di semua field kupon.
				$( '.wec-coupon-msg' ).empty();

				if ( response ) {
					if (
						response.indexOf( 'woocommerce-error' ) !== -1 ||
						response.indexOf( 'is-error' ) !== -1
					) {
						// Error — tampilkan pesan di field ini.
						$msg.html( response );
					} else {
						// Sukses — bersihkan semua input kupon & tampilkan konfirmasi.
						$( '.wec-coupon-input' ).val( '' );
						$msg.html( response );

						// Trigger WC update_checkout untuk refresh order summary.
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
