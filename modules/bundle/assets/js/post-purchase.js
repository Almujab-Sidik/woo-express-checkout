jQuery(document).ready(function($) {
	// Accept Post-Purchase Upsell
	$(document).on('click', '.upsell-accept-btn', function(e) {
		e.preventDefault();
		
		var $button = $(this);
		var parentOrderId = $button.data('parent-order-id');
		var upsellProductId = $button.data('upsell-product-id');
		var mainId = $button.data('main-id');
		
		if (!parentOrderId || !upsellProductId || !mainId) {
			return;
		}

		// UI visual spinner loading state
		$button.prop('disabled', true).text('Menyiapkan Pesanan...');

		$.ajax({
			url: upsellBundlePost.ajax_url,
			type: 'POST',
			data: {
				action: 'upsell_accept_post_purchase',
				security: upsellBundlePost.nonce,
				parent_order_id: parentOrderId,
				upsell_product_id: upsellProductId,
				main_id: mainId
			},
			success: function(response) {
				if (response.success && response.data.pay_url) {
					// Redirect to standard Pay Page of new order
					window.location.href = response.data.pay_url;
				} else {
					$button.prop('disabled', false).text('Ya, Tambahkan ke Pesanan Saya!');
					alert(response.data.message || 'Gagal menambahkan penawaran ke pesanan.');
				}
			},
			error: function() {
				$button.prop('disabled', false).text('Ya, Tambahkan ke Pesanan Saya!');
				alert('Terjadi kesalahan koneksi.');
			}
		});
	});

	// Decline/Skip Post-Purchase Upsell
	$(document).on('click', '#upsell-decline-trigger', function(e) {
		e.preventDefault();
		$('#upsell-post-purchase-modal').fadeOut(300, function() {
			$(this).remove();
		});
	});
});
