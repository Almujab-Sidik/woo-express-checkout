jQuery(document).ready(function($) {
	// Add Bundle to Cart button click handler
	$(document).on('click', '.upsell-add-bundle-btn', function(e) {
		e.preventDefault();
		
		var $button = $(this);
		var mainId = $button.data('main-id');
		
		if (!mainId) {
			return;
		}

		// Show visual loading indicator
		$button.prop('disabled', true).addClass('button-loading').text('Menambahkan Paket...');

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
					// Redirect to cart page
					window.location.href = upsellBundleData.cart_url;
				} else {
					$button.prop('disabled', false).removeClass('button-loading').text('Tambah Paket Bundle ke Keranjang');
					alert(response.data.message || 'Gagal menambahkan bundle ke keranjang.');
				}
			},
			error: function() {
				$button.prop('disabled', false).removeClass('button-loading').text('Tambah Paket Bundle ke Keranjang');
				alert('Terjadi kesalahan koneksi.');
			}
		});
	});

	// Checkout Bundle Upgrade checkbox handler
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
					// Update WooCommerce checkout totals
					$(document.body).trigger('update_checkout');
				} else {
					$checkbox.prop('checked', !checked);
					if (!checked) {
						$card.addClass('bump-checked');
					} else {
						$card.removeClass('bump-checked');
					}
					alert(response.data.message || 'Gagal memperbarui paket bundle.');
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
