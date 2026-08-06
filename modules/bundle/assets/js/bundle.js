jQuery(document).ready(function($) {
	// Handle the Add Bundle to Cart button.
	$(document).on('click', '.upsell-add-bundle-btn', function(e) {
		e.preventDefault();
		
		var $button = $(this);
		var mainId = $button.data('main-id');
		
		if (!mainId) {
			return;
		}

		// Show a loading state while the request is processed.
		$button.prop('disabled', true).addClass('button-loading').text('Adding bundle...');

		$.ajax({
			url: upsellBundleData.ajax_url,
			type: 'POST',
			data: {
				action: 'upsell_add_bundle_to_cart',
				security: upsellBundleData.nonce,
				main_id: mainId
			},
			success: function(response) {
				if (response.success) {
					// Redirect to the cart page.
					window.location.href = upsellBundleData.cart_url;
				} else {
					$button.prop('disabled', false).removeClass('button-loading').text('Add Bundle to Cart');
					alert(response.data.message || 'Unable to add the bundle to the cart.');
				}
			},
			error: function() {
				$button.prop('disabled', false).removeClass('button-loading').text('Add Bundle to Cart');
				alert('A connection error occurred.');
			}
		});
	});

	// Handle checkout bundle upgrade checkbox changes.
	$(document).on('change', '.upsell-checkout-bundle-checkbox', function() {
		var $checkbox = $(this);
		var $card = $checkbox.closest('.upsell-bundle-bump-card');
		var mainId = $checkbox.data('main-id');
		var checked = $checkbox.is(':checked');
		
		$card.addClass('bump-loading');
		$checkbox.prop('disabled', true);

		$.ajax({
			url: upsellBundleData.ajax_url,
			type: 'POST',
			data: {
				action: 'upsell_toggle_checkout_bundle',
				security: upsellBundleData.nonce,
				main_id: mainId,
				checked: checked
			},
			success: function(response) {
				$card.removeClass('bump-loading');
				$checkbox.prop('disabled', false);
				
				if (response.success) {
					if (checked) {
						$card.addClass('bump-checked');
					} else {
						$card.removeClass('bump-checked');
					}
					// Refresh WooCommerce checkout totals.
					$(document.body).trigger('update_checkout');
				} else {
					$checkbox.prop('checked', !checked);
					if (!checked) {
						$card.addClass('bump-checked');
					} else {
						$card.removeClass('bump-checked');
					}
					alert(response.data.message || 'Unable to update the bundle.');
				}
			},
			error: function() {
				$card.removeClass('bump-loading');
				$checkbox.prop('disabled', false);
				$checkbox.prop('checked', !checked);
				alert('A connection error occurred.');
			}
		});
	});
});
