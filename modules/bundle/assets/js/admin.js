jQuery(document).ready(function($) {
	// 1. Toggle Section Enable/Disable visual styling
	$(document).on('change', '.section-enable-toggle', function() {
		var $section = $(this).closest('.upsell-bundle-section');
		var $content = $section.find('.upsell-bundle-section-content');

		if ($(this).is(':checked')) {
			$content.removeClass('disabled-content');
		} else {
			$content.addClass('disabled-content');
		}
	});

	// 2. Add Row button handler
	$(document).on('click', '.add-row-btn', function(e) {
		e.preventDefault();
		var targetTableId = $(this).data('target');
		var templateId = '';

		if (targetTableId === 'order-bump-table') {
			templateId = 'order-bump-template-row';
		} else if (targetTableId === 'post-purchase-table') {
			templateId = 'post-purchase-template-row';
		}

		if (templateId) {
			var $template = $('#' + templateId);
			if ($template.length) {
				var $newRow = $template.clone();
				$newRow.removeAttr('id').addClass('repeatable-row'); // Keep row styling without duplicating the template ID

				// The template's product-search <select> was already turned into
				// a select2 widget on page load (WooCommerce initializes every
				// .wc-product-search). Cloning copies that initialized state, and
				// WooCommerce's re-init skips anything already flagged '.enhanced'
				// — so without cleanup the new row's dropdown is dead. Strip all
				// select2 artifacts so the clone is treated as a brand-new field.
				$newRow.find('.select2-container').remove();
				$newRow.find('select.wc-product-search')
					.removeClass('enhanced select2-hidden-accessible')
					.removeAttr('data-select2-id')
					.removeAttr('aria-hidden')
					.removeAttr('tabindex')
					.empty()
					.val(null);

				// Append to table body
				$('#' + targetTableId + ' tbody').append($newRow);

				// Re-initialize select2 on the newly added (now-pristine) field.
				$(document.body).trigger('wc-enhanced-select-init');
			}
		}
	});

	// 3. Remove Row button handler
	$(document).on('click', '.remove-row-btn', function(e) {
		e.preventDefault();
		$(this).closest('tr').remove();
	});

	// 3.5. Toggle Checkout Upgrade subfields
	$(document).on('change', '#_upsell_bundle_bundle_checkout_upgrade', function() {
		if ($(this).is(':checked')) {
			$('.bundle-checkout-fields').slideDown(200);
		} else {
			$('.bundle-checkout-fields').slideUp(200);
		}
	});

	// 4. Ensure select2 fields are correctly initialized on load
	$(document.body).trigger('wc-enhanced-select-init');
});
