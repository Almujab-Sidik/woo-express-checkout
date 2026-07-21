jQuery(document).ready(function($) {
	// Listen to Order Bump checkbox toggle
	$(document).on('change', '.upsell-bump-checkbox', function() {
		var $checkbox = $(this);
		var $card = $checkbox.closest('.upsell-bundle-bump-card');
		var mainId = $checkbox.data('main-id');
		var bumpId = $checkbox.data('bump-id');
		var checked = $checkbox.is(':checked');
		
		// Visual feedback
		$card.addClass('bump-loading');
		$checkbox.prop('disabled', true);

		// Send AJAX request
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
					
					// Tell WooCommerce to update checkout fragments and totals
					$(document.body).trigger('update_checkout');
				} else {
					// Revert checkbox state on failure
					$checkbox.prop('checked', !checked);
					if (!checked) {
						$card.addClass('bump-checked');
					} else {
						$card.removeClass('bump-checked');
					}
					alert('Gagal memperbarui pesanan. Silakan coba lagi.');
				}
			},
			error: function() {
				$card.removeClass('bump-loading');
				$checkbox.prop('disabled', false);
				$checkbox.prop('checked', !checked);
				alert('Terjadi kesalahan koneksi.');
			}
		});
	});
});
