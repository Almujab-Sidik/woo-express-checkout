/**
 * WooCommerce Coupon Display Manager — Frontend JS
 *
 * Reposition Mode: the coupon field is rendered below the Place Order button
 * by PHP. This script handles:
 *  - Syncing the mock input to the real hidden WooCommerce coupon form
 *  - Available-coupon badge one-click apply
 *  - Applied-coupon pill state (show/hide, remove)
 *  - Re-init after WooCommerce checkout AJAX refresh
 *
 * Checkout builders supported:
 *  - WooCommerce Classic (shortcode)
 *  - CartFlows (free & pro)
 *  - FunnelKit / WooFunnels
 *
 * @since 1.1.0
 */
( function ( $ ) {
	'use strict';

	if ( typeof wcdm_params === 'undefined' ) {
		return;
	}

	var p = wcdm_params;



	// -------------------------------------------------------------------------
	// Selector map — add new checkout builders here, no logic changes needed.
	// -------------------------------------------------------------------------
	var SEL = {
		mockInput   : '.wcdm-checkout-coupon-repositioned:not(#wcdm-repositioned-coupon-html-hidden *) #wcdm_coupon_code_mock',
		mockBtn     : '.wcdm-checkout-coupon-repositioned:not(#wcdm-repositioned-coupon-html-hidden *) #wcdm_apply_coupon_mock',
		mockWrap    : '.wcdm-checkout-coupon-repositioned:not(#wcdm-repositioned-coupon-html-hidden *)',
		hiddenTpl   : '#wcdm-repositioned-coupon-html-hidden',
		realInput   : '#coupon_code',
		realForm    : 'form.checkout_coupon',
		// All real remove-coupon links (WC + CartFlows + FunnelKit)
		removeLinks : '.woocommerce-remove-coupon, .wcf-remove-coupon, .wffn-remove-coupon',
	};

	// -------------------------------------------------------------------------
	// Apply button style class to the mock button
	// -------------------------------------------------------------------------
	function applyBtnStyle() {
		var cls = {
			secondary : 'wcdm-btn-secondary',
			link      : 'wcdm-btn-link',
			custom    : 'wcdm-btn-custom',
		}[ p.button_style ] || 'wcdm-btn-secondary';

		$( SEL.mockBtn )
			.removeClass( 'wcdm-btn-secondary wcdm-btn-link wcdm-btn-custom' )
			.addClass( cls );

		if ( p.button_text ) {
			$( SEL.mockBtn ).text( p.button_text );
		}
	}

	// -------------------------------------------------------------------------
	// Inject available-coupons list into the repositioned widget
	// -------------------------------------------------------------------------
	function injectCouponList() {
		if ( p.enable_list !== 'yes' ) {
			return;
		}
		var $wrap = $( SEL.mockWrap );
		if ( ! $wrap.length || $wrap.find( '.wcdm-available-coupons-container' ).length ) {
			return;
		}
		var html = $( '#wcdm-available-coupons-hidden' ).html();
		if ( html ) {
			$wrap.prepend( html );
		}
	}

	// -------------------------------------------------------------------------
	// Detect applied coupon and update pill state
	// -------------------------------------------------------------------------
	function handleAppliedState() {
		var $real = $( SEL.removeLinks ).not( '.wcdm-remove-coupon' );
		var $wrap = $( SEL.mockWrap );
		var $interactive = $wrap.find( '.wcdm-coupon-interactive-area' );
		var isDropdown = $wrap.hasClass( 'wcdm-layout-dropdown-hide' );

		var appliedCodes = [];
		$real.each( function () {
			var code = $( this ).attr( 'data-coupon' ) || '';
			if ( ! code ) {
				var m = ( $( this ).attr( 'href' ) || '' ).match( /remove_coupon=([^&]+)/ );
				if ( m ) {
					code = decodeURIComponent( m[ 1 ] );
				}
			}
			if ( code ) {
				var uc = code.toUpperCase();
				if ( appliedCodes.indexOf( uc ) === -1 ) {
					appliedCodes.push( uc );
				}
			}
		} );

		// Update applied badges state
		$wrap.find( '.wcdm-coupon-badge-item' ).each( function () {
			var badgeCode = ( $( this ).data( 'code' ) || '' ).toString().toUpperCase();
			if ( appliedCodes.indexOf( badgeCode ) > -1 ) {
				$( this ).addClass( 'wcdm-badge-applied' );
				if ( ! $( this ).find( '.wcdm-badge-check' ).length ) {
					$( this ).find( '.wcdm-badge-code' ).append( '<span class="wcdm-badge-check">&#10003;</span>' );
				}
			} else {
				$( this ).removeClass( 'wcdm-badge-applied' );
				$( this ).find( '.wcdm-badge-check' ).remove();
			}
		} );

		if ( appliedCodes.length === 0 ) {
			// No coupon active — restore input if pill is showing.
			if ( $wrap.hasClass( 'wcdm-coupon-applied-state' ) ) {
				var $tpl = $( SEL.hiddenTpl );
				if ( $tpl.length && $interactive.length ) {
					$wrap.removeClass( 'wcdm-coupon-applied-state wcdm-has-coupon' );
					$interactive.html( $tpl.find( '.wcdm-coupon-interactive-area' ).html() );
					if ( isDropdown ) {
						$wrap.removeClass( 'wcdm-dropdown-open' );
						$wrap.find( '.wcdm-dropdown-toggle' ).show().removeClass( 'wcdm-dropdown-active' );
						$wrap.find( '.wcdm-dropdown-content' ).hide();
					}
					applyBtnStyle();
				}
			}
			$( 'body' ).removeClass( 'wcdm-coupon-active' );
			return;
		}

		$( 'body' ).addClass( 'wcdm-coupon-active' );

		// Suppress duplicate WC success notices.
		$( '.woocommerce-message' ).filter( function () {
			return /applied|sukses|berhasil|coupon|kupon/i.test( $( this ).text() );
		} ).hide();

		// Update the interactive area with applied pills & optionally input field
		$wrap.addClass( 'wcdm-coupon-applied-state wcdm-has-coupon' );

		var pillsHtml = '<div class="wcdm-coupon-applied-pills-container">';
		$.each( appliedCodes, function ( index, code ) {
			pillsHtml += '<div class="wcdm-coupon-applied-pill" style="margin-bottom: 6px;">' +
				'<span class="wcdm-pill-check">&#10003;</span>' +
				'<span class="wcdm-pill-label">Coupon <strong>' + code + '</strong> ' + ( p.applied_text || 'Applied!' ) + '</span>' +
				'<a href="#" class="wcdm-remove-coupon" data-coupon="' + code.toLowerCase() + '">' + ( p.remove_text || '(Remove)' ) + '</a>' +
			'</div>';
		} );
		pillsHtml += '</div>';

		// If show_input is enabled AND we are NOT in single coupon mode, render the input field below the pills
		var isSingleCoupon = p.single_coupon === 'yes';
		if ( p.show_input === 'yes' && ! isSingleCoupon ) {
			pillsHtml += '<div class="wcdm-coupon-input-wrapper-spacer" style="margin-top: 12px;"></div>' +
				'<div class="wcdm-coupon-input-wrapper">' +
					'<input type="text" id="wcdm_coupon_code_mock" class="input-text" placeholder="' + ( p.placeholder_text || 'Coupon Code' ) + '" value="" autocomplete="off" />' +
					'<button type="button" id="wcdm_apply_coupon_mock" class="button">' + ( p.button_text || 'Apply Coupon' ) + '</button>' +
				'</div>';
		}

		$interactive.html( pillsHtml );
		if ( isDropdown ) {
			$wrap.find( '.wcdm-dropdown-toggle' ).hide();
			$wrap.find( '.wcdm-dropdown-content' ).show();
		}
		applyBtnStyle();
	}

	// -------------------------------------------------------------------------
	// Run all init functions
	// -------------------------------------------------------------------------
	function init() {
		applyBtnStyle();
		injectCouponList();
		handleAppliedState();
	}

	// -------------------------------------------------------------------------
	// Core apply — works regardless of show_input setting
	// -------------------------------------------------------------------------
	function applyCode( code ) {
		if ( ! code ) {
			return;
		}

		// 1. Check if CartFlows is active and visible
		var $wcfBtn = $( '.wcf-submit-coupon' );
		if ( $wcfBtn.length && $wcfBtn.is( ':visible' ) ) {
			$( '.wcf-coupon-code-input' ).val( code ).trigger( 'change' ).trigger( 'input' );
			$wcfBtn.trigger( 'click' );
			return;
		}

		// 2. Check if FunnelKit is active and visible
		var $fkBtn = $( '.wffn-submit-coupon, .wffn-coupon-submit, .wfacp-coupon-btn, .wfacp_coupon_field_wrap button' );
		if ( $fkBtn.length && $fkBtn.is( ':visible' ) ) {
			$( '.wffn-coupon-code-input, #wfacp-coupon-code, .wfacp-coupon-input input' ).val( code ).trigger( 'change' ).trigger( 'input' );
			$fkBtn.trigger( 'click' );
			return;
		}

		// 3. WooCommerce Classic / default fallback
		// Fill all possible default coupon inputs
		$( '#coupon_code, input[name="coupon_code"]' ).val( code ).trigger( 'change' ).trigger( 'input' );
		
		// Submit the classic checkout form directly
		var $form = $( 'form.checkout_coupon' ).first();
		if ( $form.length ) {
			$form.submit();
		} else {
			$( document.body ).trigger( 'apply_coupon', code );
		}
	}

	// -------------------------------------------------------------------------
	// Mock Apply button click
	// -------------------------------------------------------------------------
	// Boot & Bind Events on Document Ready
	// -------------------------------------------------------------------------
	$( document ).ready( function () {
		// Skip on Block Checkout — blocks.js handles everything there.
		// Combine DOM detection (in case blocks.js fails) with the PHP-detected type
		// (p.checkout_type = 'block') which is reliable at ready-time even before
		// React has rendered any DOM elements.
		var isBlockCheckout = p.checkout_type === 'block'
			|| $( '.wp-block-woocommerce-checkout, .wc-block-checkout, [data-block-name="woocommerce/checkout"], .wc-block-components-checkout' ).length > 0;
		if ( isBlockCheckout ) {
			return;
		}

		// Initialize layout and styles
		init();

		// Toggle dropdown content
		$( document.body ).on( 'click', '.wcdm-dropdown-toggle', function ( e ) {
			e.preventDefault();
			var $wrap = $( this ).closest( '.wcdm-checkout-coupon-repositioned' );
			var $content = $( this ).siblings( '.wcdm-dropdown-content' );
			$content.slideToggle( 200 );
			$( this ).toggleClass( 'wcdm-dropdown-active' );
			$wrap.toggleClass( 'wcdm-dropdown-open' );
		} );

		// Mock Apply button click
		$( document.body ).on( 'click', SEL.mockBtn, function ( e ) {
			e.preventDefault();

			var code = $( SEL.mockInput ).val().trim();
			if ( ! code ) {
				$( SEL.mockInput ).focus();
				return;
			}
			applyCode( code );
		} );

		// Enter key on mock input.
		$( document.body ).on( 'keypress', SEL.mockInput, function ( e ) {
			if ( e.which === 13 ) {
				e.preventDefault();
				applyCode( $( this ).val().trim() );
			}
		} );

		// Sync mock → real on keystroke.
		$( document.body ).on( 'input', SEL.mockInput, function () {
			$( SEL.realInput ).val( $( this ).val() );
		} );

		// Available-coupon badge click — auto-fill and apply
		$( document.body ).on( 'click keypress', '.wcdm-coupon-badge-item', function ( e ) {
			if ( e.type === 'keypress' && e.which !== 13 ) {
				return;
			}
			e.preventDefault();

			var code = $( this ).data( 'code' );
			if ( ! code ) {
				return;
			}

			// Fill mock input if visible (show_input = yes), then apply directly.
			$( SEL.mockInput ).val( code );
			applyCode( code );
		} );

		// Remove coupon (WCDM pill remove link)
		$( document.body ).on( 'click', '.wcdm-remove-coupon', function ( e ) {
			e.preventDefault();

			var couponCode = ( $( this ).attr( 'data-coupon' ) || '' ).toLowerCase();
			var $btn = $( this );
			$btn.css( 'pointer-events', 'none' ).text( p.removing_text || 'Removing...' );

			// Strategy 1: click the real WC / CartFlows / FunnelKit remove link.
			var $real = $( SEL.removeLinks ).not( '.wcdm-remove-coupon' ).filter( function () {
				var dc   = ( $( this ).attr( 'data-coupon' ) || '' ).toLowerCase();
				var href = ( $( this ).attr( 'href' )        || '' ).toLowerCase();
				return dc === couponCode || href.indexOf( 'remove_coupon=' + couponCode ) > -1;
			} );

			if ( ! $real.length ) {
				$real = $( SEL.removeLinks ).not( '.wcdm-remove-coupon' ).first();
			}

			if ( $real.length ) {
				$real[ 0 ].click();
				return;
			}

			// Strategy 2: direct AJAX.
			var ajaxUrl =
				( typeof wc_checkout_params !== 'undefined' ? wc_checkout_params.ajax_url  : null ) ||
				( typeof woocommerce_params  !== 'undefined' ? woocommerce_params.ajax_url   : null ) ||
				'/wp-admin/admin-ajax.php';

			var nonce = typeof wc_checkout_params !== 'undefined' ? ( wc_checkout_params.apply_coupon_nonce || '' ) : '';

			$.post( ajaxUrl, {
				action      : 'remove_coupon',
				coupon_code : couponCode,
				security    : nonce,
			} ).always( function () {
				$( document.body ).trigger( 'update_checkout' );
			} );
		} );

		// Re-init after WooCommerce AJAX refresh + CartFlows refresh event
		$( document ).on( 'updated_checkout wcf_update_checkout_data', init );
	} );

} )( jQuery );
