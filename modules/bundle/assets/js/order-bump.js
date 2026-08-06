jQuery(document).ready(function($) {
	// Handle order bump checkbox changes.
	$(document).on('change', '.upsell-bump-checkbox', function() {
		var $checkbox = $(this);
		var $card = $checkbox.closest('.upsell-bundle-bump-card');
		var mainId = $checkbox.data('main-id');
		var bumpId = $checkbox.data('bump-id');
		var checked = $checkbox.is(':checked');
		
		// Show a loading state while the request is processed.
		$card.addClass('bump-loading');
		$checkbox.prop('disabled', true);

		// Persist the selection through AJAX.
		$.ajax({
			url: upsellBundleBump.ajax_url,
			type: 'POST',
			data: {
				action: 'upsell_toggle_bump',
				security: upsellBundleBump.nonce,
				main_id: mainId,
				bump_id: bumpId,
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
					
					// Refresh WooCommerce checkout fragments and totals.
					$(document.body).trigger('update_checkout');
				} else {
					// Restore the previous checkbox state when the request fails.
					$checkbox.prop('checked', !checked);
					if (!checked) {
						$card.addClass('bump-checked');
					} else {
						$card.removeClass('bump-checked');
					}
					alert('Unable to update the order. Please try again.');
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
