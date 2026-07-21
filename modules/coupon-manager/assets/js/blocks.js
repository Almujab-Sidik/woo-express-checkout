/**
 * WooCommerce Coupon Display Manager — Block Checkout JS
 *
 * Handles WooCommerce Block Checkout (React-rendered).
 * Because PHP hooks cannot reposition React components, this script:
 *  1. Hides the native block coupon field via CSS (done in PHP inline styles)
 *  2. Injects the repositioned mock widget before the Place Order button
 *  3. Proxies apply/remove through the hidden React inputs
 *
 * @since 1.1.0
 */
( function ( $ ) {
	'use strict';

	if ( typeof wcdm_block_params === 'undefined' ) {
		return;
	}

	var p = wcdm_block_params;

	// -----------------------------------------------------------------------
	// Block checkout selectors
	// WC Block Checkout uses different class names across versions — keep all.
	// -----------------------------------------------------------------------
	var SEL = {
		blockRoot    : [
			'.wp-block-woocommerce-checkout',
			'.wc-block-checkout',
			'[data-block-name="woocommerce/checkout"]',
			'.wc-block-components-checkout',
		].join( ', ' ),
		placeOrder   : [
			'.wc-block-components-checkout-place-order-button',
			'.wp-block-woocommerce-checkout-actions-block button[type="submit"]',
			'.wc-block-checkout__actions button[type="submit"]',
			'.wc-block-components-checkout__actions button',
			'.wc-block-checkout button[type="submit"]',
			'.wc-block-checkout__form button[type="submit"]',
			'.wp-block-woocommerce-checkout button[type="submit"]',
		].join( ', ' ),
		couponInput  : '.wc-block-components-totals-coupon__input input, .wc-block-components-totals-coupon input[type="text"], .wc-block-components-totals-coupon input',
		couponToggle : 'button.wc-block-components-totals-coupon-link, .wc-block-components-totals-coupon__link, .wc-block-components-totals-coupon .wc-block-components-panel__button, .wc-block-components-totals-coupon button, .wc-block-components-totals-coupon-link',
		applyBtn     : '.wc-block-components-totals-coupon__button, .wc-block-components-totals-coupon button[type="submit"], .wc-block-components-totals-coupon button',
		couponBadge  : '.wc-block-components-totals-coupon__badge, .wc-block-components-coupon__badge, .wc-block-components-totals-discount__coupon-list-item',
		removeBadge  : '.wc-block-components-totals-coupon__badge button, .wc-block-components-totals-coupon__badge-remove, .wc-block-components-totals-discount__coupon-list-item button, .wc-block-components-totals-discount__coupon-list-item .wc-block-components-chip__remove',
		mockWrap     : '.wcdm-checkout-coupon-repositioned:not(#wcdm-repositioned-coupon-html-hidden *)',
		mockInput    : '.wcdm-checkout-coupon-repositioned:not(#wcdm-repositioned-coupon-html-hidden *) #wcdm_coupon_code_mock',
		mockBtn      : '.wcdm-checkout-coupon-repositioned:not(#wcdm-repositioned-coupon-html-hidden *) #wcdm_apply_coupon_mock',
		hiddenTpl    : '#wcdm-repositioned-coupon-html-hidden',
	};



	// -----------------------------------------------------------------------
	// Map button_style → CSS class
	// -----------------------------------------------------------------------
	function getBtnClass() {
		return {
			secondary : 'wcdm-block-btn-secondary',
			link      : 'wcdm-block-btn-link',
			custom    : 'wcdm-block-btn-custom',
		}[ p.button_style ] || 'wcdm-block-btn-secondary';
	}

	// -----------------------------------------------------------------------
	// Inject available coupons list into mock widget
	// -----------------------------------------------------------------------
	function injectCouponList( $container ) {
		if ( p.enable_list !== 'yes' || ! $container || ! $container.length ) {
			return;
		}
		if ( $container.find( '.wcdm-available-coupons-container' ).length ) {
			return;
		}
		var html = $( '#wcdm-available-coupons-hidden' ).html();
		if ( html ) {
			$container.prepend( html );
		}
	}

	// -----------------------------------------------------------------------
	// Find the best available injection point in the Block Checkout DOM.
	// Returns { $target, before } or null if React hasn't rendered yet.
	// -----------------------------------------------------------------------
	function findInjectionPoint() {
		var isSidebar = p.layout_position === 'sidebar';

		if ( isSidebar ) {
			// In sidebar mode, prioritize injecting at the native coupon's place in the sidebar summary
			var $nativeCoupon = $( '.wc-block-components-totals-coupon, .wc-block-checkout__coupon-code, .wp-block-woocommerce-checkout-order-summary-coupon-block, [data-block-name="woocommerce/checkout-order-summary-coupon-block"]' ).first();
			if ( $nativeCoupon.length ) {
				return { $target: $nativeCoupon, before: true };
			}
		}

		// 1. The Place Order button itself (ideal placement: main column).
		var $btn = $( SEL.placeOrder ).first();
		if ( $btn.length ) {
			return { $target: $btn, before: true };
		}

		// 2. The WC "actions" block wrapper (contains the place-order button).
		var $actions = $( '.wp-block-woocommerce-checkout-actions-block' ).first();
		if ( $actions.length ) {
			return { $target: $actions, before: true };
		}

		// 3. Replace native coupon in the Order Summary sidebar.
		//    .wc-block-components-totals-coupon is hidden by CSS but always in DOM.
		//    Injecting before it places our widget exactly where the native coupon was.
		var $nativeCoupon = $( '.wc-block-components-totals-coupon' ).first();
		if ( $nativeCoupon.length ) {
			return { $target: $nativeCoupon, before: true };
		}

		// 4. After the payment block.
		var $payment = $( '.wp-block-woocommerce-checkout-payment-block, .wc-block-checkout__payment-method' ).first();
		if ( $payment.length ) {
			return { $target: $payment, before: false };
		}

		// 5. After the additional-info / notes block.
		var $notes = $( '.wp-block-woocommerce-checkout-additional-information-block' ).first();
		if ( $notes.length ) {
			return { $target: $notes, before: false };
		}

		// 6. End of the main checkout column.
		var $main = $( '.wc-block-checkout__main, .wc-block-checkout__form' ).first();
		if ( $main.length ) {
			return { $target: $main, before: false };
		}

		return null; // React hasn't rendered enough yet — observer will retry.
	}

	// -----------------------------------------------------------------------
	// Core: inject/update mock widget in the Block Checkout
	// -----------------------------------------------------------------------
	function customizeBlockCoupon() {
		var $tpl = $( SEL.hiddenTpl );
		if ( ! $tpl.length ) {
			return;
		}

		// Inject widget if not already present.
		if ( ! $( SEL.mockWrap ).length ) {
			var point = findInjectionPoint();
			if ( ! point ) {
				return; // React hasn't rendered yet — observer will retry.
			}

			var tplHtml = $tpl.html();
			if ( ! tplHtml ) {
				return;
			}

			var $widget = $( tplHtml );
			if ( point.before ) {
				$widget.insertBefore( point.$target );
			} else {
				$widget.insertAfter( point.$target );
			}

			$( SEL.mockBtn ).removeClass( 'button' ).addClass( getBtnClass() );
			if ( p.button_text ) {
				$( SEL.mockBtn ).text( p.button_text );
			}
			injectCouponList( $( SEL.mockWrap ) );
		}

		// Sync applied state from React badge.
		var $badge    = $( SEL.couponBadge );
		var $mockWrap = $( SEL.mockWrap );
		var $interactive = $mockWrap.find( '.wcdm-coupon-interactive-area' );
		var isDropdown = $mockWrap.hasClass( 'wcdm-layout-dropdown-hide' );
		if ( ! $interactive.length ) {
			$interactive = $mockWrap;
		}

		var appliedCodes = [];
		$badge.each( function () {
			var $el = $( this );
			var code = $el.find( '.wc-block-components-totals-coupon__badge-code, .wc-block-components-coupon__code, .wc-block-components-chip__text' ).text().trim();
			if ( ! code ) {
				var $clone = $el.clone();
				$clone.find( 'button, .screen-reader-text' ).remove();
				code = $clone.text().trim();
			}
			code = code.toUpperCase();
			if ( code && appliedCodes.indexOf( code ) === -1 ) {
				appliedCodes.push( code );
			}
		} );

		// Prevent MutationObserver infinite loops by verifying if the applied coupons set has changed
		var appliedCodesKey = appliedCodes.slice().sort().join( ',' );
		var currentCodesKey = $mockWrap.attr( 'data-applied-codes' ) || '';
		if ( appliedCodesKey === currentCodesKey && $mockWrap.find( '.wcdm-coupon-applied-pill' ).length === appliedCodes.length ) {
			return;
		}
		$mockWrap.attr( 'data-applied-codes', appliedCodesKey );

		// Update applied badges state
		$mockWrap.find( '.wcdm-coupon-badge-item' ).each( function () {
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

		if ( appliedCodes.length > 0 ) {
			$mockWrap.addClass( 'wcdm-coupon-applied-state wcdm-has-coupon' );
			if ( isDropdown ) {
				$mockWrap.find( '.wcdm-dropdown-toggle' ).hide();
				$mockWrap.find( '.wcdm-dropdown-content' ).show();
			}

			var pillsHtml = '<div class="wcdm-coupon-applied-pills-container">';
			$.each( appliedCodes, function ( index, code ) {
				pillsHtml += '<div class="wcdm-coupon-applied-pill" style="margin-bottom: 6px;">' +
					'<span class="wcdm-pill-check">&#10003;</span>' +
					'<span class="wcdm-pill-label">Coupon <strong>' + code + '</strong> Applied!</span>' +
					'<a href="#" class="wcdm-block-remove-coupon wcdm-remove-coupon" data-coupon="' + code.toLowerCase() + '">(Remove)</a>' +
				'</div>';
			} );
			pillsHtml += '</div>';

			var isSingleCoupon = p.single_coupon === 'yes';
			if ( p.show_input === 'yes' && ! isSingleCoupon ) {
				pillsHtml += '<div class="wcdm-coupon-input-wrapper-spacer" style="margin-top: 12px;"></div>' +
					'<div class="wcdm-coupon-input-wrapper">' +
						'<input type="text" id="wcdm_coupon_code_mock" class="input-text" placeholder="Coupon Code" value="" autocomplete="off" />' +
						'<button type="button" id="wcdm_apply_coupon_mock" class="button">' + ( p.button_text || 'Apply Coupon' ) + '</button>' +
					'</div>';
			}

			$interactive.html( pillsHtml );
			$( SEL.mockBtn ).removeClass( 'button' ).addClass( getBtnClass() );
			if ( p.button_text ) {
				$( SEL.mockBtn ).text( p.button_text );
			}

		} else if ( appliedCodes.length === 0 && $mockWrap.hasClass( 'wcdm-coupon-applied-state' ) ) {
			// Coupon removed — restore input.
			$mockWrap.removeClass( 'wcdm-coupon-applied-state wcdm-has-coupon' );
			$interactive.html( $tpl.find( '.wcdm-coupon-interactive-area' ).html() );
			if ( isDropdown ) {
				$mockWrap.removeClass( 'wcdm-dropdown-open' );
				$mockWrap.find( '.wcdm-dropdown-toggle' ).show().removeClass( 'wcdm-dropdown-active' );
				$mockWrap.find( '.wcdm-dropdown-content' ).hide();
			}
			$( SEL.mockBtn ).removeClass( 'button' ).addClass( getBtnClass() );
			if ( p.button_text ) {
				$( SEL.mockBtn ).text( p.button_text );
			}
		}
	}

	// -----------------------------------------------------------------------
	// Core apply — proxy code into hidden React coupon input
	// -----------------------------------------------------------------------
	function applyBlockCode( code ) {
		if ( ! code ) {
			return;
		}

		// Open the React coupon panel if it's not visible.
		var $input = $( SEL.couponInput );
		if ( ! $input.length ) {
			var $toggle = $( SEL.couponToggle );
			if ( $toggle.length ) {
				$toggle.first()[ 0 ].click();
			}
		}

		var attempts = 0;
		var maxAttempts = 20; // 20 * 50ms = 1000ms max wait
		var interval = setInterval( function () {
			var input  = document.querySelector( SEL.couponInput );
			var submit = document.querySelector( SEL.applyBtn );

			if ( input && submit ) {
				clearInterval( interval );
				
				// Standard React 16+ programmatic value setter workaround
				var nativeInputValueSetter = Object.getOwnPropertyDescriptor( window.HTMLInputElement.prototype, "value" ).set;
				if ( nativeInputValueSetter ) {
					nativeInputValueSetter.call( input, code );
				} else {
					input.value = code;
				}
				
				input.dispatchEvent( new Event( 'input',  { bubbles: true } ) );
				input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				setTimeout( function () {
					submit.click();
				}, 50 );
			} else {
				attempts++;
				if ( attempts >= maxAttempts ) {
					clearInterval( interval );
					console.error( 'WCDM: React coupon input or submit button not found after 1s.' );
				}
			}
		}, 50 );
	}

	// -----------------------------------------------------------------------
	// -----------------------------------------------------------------------
	// Boot & Bind Events on Document Ready
	// -----------------------------------------------------------------------
	$( document ).ready( function () {

		// blocks.js is only for WC Block Checkout (and unknown/edge cases).
		// CartFlows, FunnelKit, and Classic are fully handled by frontend.js +
		// PHP hooks — blocks.js should do nothing on those page types.
		if ( p.checkout_type === 'cartflows'
			|| p.checkout_type === 'funnelkit'
			|| p.checkout_type === 'classic' ) {
			return;
		}

		// WC Default (block or classic): attach all event handlers via delegation.
		// Handlers are registered now; the widget will be injected later by the
		// observer (React renders async — DOM may not exist at ready-time yet).

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
			applyBlockCode( code );
		} );

		// Enter key on mock input.
		$( document.body ).on( 'keypress', SEL.mockInput, function ( e ) {
			if ( e.which === 13 ) {
				e.preventDefault();
				applyBlockCode( $( this ).val().trim() );
			}
		} );

		// Available-coupon badge click — fill mock input and apply via React proxy.
		$( document.body ).on( 'click', '.wcdm-coupon-badge-item', function ( e ) {
			e.preventDefault();
			var code = $( this ).data( 'code' );
			if ( ! code ) {
				return;
			}
			$( SEL.mockInput ).val( code );
			applyBlockCode( code );
		} );

		// Remove coupon (pill button).
		$( document.body ).on( 'click', '.wcdm-block-remove-coupon', function ( e ) {
			e.preventDefault();
			var couponCode = ( $( this ).attr( 'data-coupon' ) || '' ).toLowerCase();
			var $real = $( SEL.removeBadge );
			
			var $targetRemove = $real.filter( function () {
				var badge = $( this ).closest( '.wc-block-components-totals-coupon__badge, .wc-block-components-coupon__badge, .wc-block-components-totals-discount__coupon-list-item' );
				var code = badge.find( '.wc-block-components-totals-coupon__badge-code, .wc-block-components-coupon__code, .wc-block-components-chip__text' ).text().trim();
				if ( ! code ) {
					var $clone = badge.clone();
					$clone.find( 'button, .screen-reader-text' ).remove();
					code = $clone.text().trim();
				}
				return code.toLowerCase() === couponCode;
			} );
			
			if ( ! $targetRemove.length ) {
				$targetRemove = $real.first();
			}

			if ( $targetRemove.length ) {
				$targetRemove[ 0 ].click();
			}
		} );

		// Set up MutationObserver (or poll) to catch React re-renders.
		// React renders async — blockRoot may not exist yet at ready-time.
		// Fall back to document.body so the first render is never missed.
		customizeBlockCoupon();

		var blockRoot = document.querySelector(
			'.wp-block-woocommerce-checkout, .wc-block-checkout, ' +
			'[data-block-name="woocommerce/checkout"], .wc-block-components-checkout'
		) || document.body;

		if ( window.MutationObserver ) {
			var observer = new MutationObserver( customizeBlockCoupon );
			observer.observe( blockRoot, { childList: true, subtree: true } );
			$( window ).on( 'unload', function () { observer.disconnect(); } );
		} else {
			var poll = setInterval( customizeBlockCoupon, 500 );
			$( window ).on( 'unload', function () { clearInterval( poll ); } );
		}
	} );

} )( jQuery );
