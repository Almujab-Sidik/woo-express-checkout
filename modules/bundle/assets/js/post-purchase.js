jQuery(document).ready(function($) {
	// Handle acceptance of the post-purchase upsell.
	$(document).on('click', '.upsell-accept-btn', function(e) {
		e.preventDefault();
		
		var $button = $(this);
		var parentOrderId = $button.data('parent-order-id');
		var upsellProductId = $button.data('upsell-product-id');
		var mainId = $button.data('main-id');
		
		if (!parentOrderId || !upsellProductId || !mainId) {
			return;
		}

		// Show a loading state while the request is being processed.
		$button.prop('disabled', true).text('Preparing your order...');

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
					// Redirect to the payment page for the new order.
					window.location.href = response.data.pay_url;
				} else {
					$button.prop('disabled', false).text('Yes, Add It to My Order');
					alert(response.data.message || 'Unable to add the offer to your order.');
				}
			},
			error: function() {
				$button.prop('disabled', false).text('Yes, Add It to My Order');
				alert('A connection error occurred.');
			}
		});
	});

	// Handle declining the post-purchase upsell.
	$(document).on('click', '#upsell-decline-trigger', function(e) {
		e.preventDefault();
		$('#upsell-post-purchase-modal').fadeOut(300, function() {
			$(this).remove();
		});
	});
});
