/**
 * WooCommerce Coupon Display Manager — Admin JS
 *
 * @since 1.1.0
 */
jQuery(document).ready(function($) {
	'use strict';

	// 1. Color Picker Initialization
	if ($.isFunction($.fn.wpColorPicker)) {
		$(".wcdm-color-field").wpColorPicker();
	}

	// 2. Toggle Color Fields based on Button Style setting
	function toggleColorFields() {
		var style = $("#wcdm_button_style").val();
		$(".wcdm-color-row").closest("tr").toggle(style === 'custom');
	}
	
	$("#wcdm_button_style").on("change", toggleColorFields);
	toggleColorFields();

	// 2.1. Toggle Available Coupon List configuration fields based on Display Mode
	function toggleListFields() {
		var mode = $("#wcdm_coupon_display_mode").val();
		var showList = (mode === 'list' || mode === 'both');

		$("#wcdm_list_selected_coupons_row").toggle(showList);
		$("#wcdm_list_title").closest("tr").toggle(showList);
		$("#wcdm_list_show_desc").closest("tr").toggle(showList);
		$("#wcdm_list_hide_private").closest("tr").toggle(showList);
	}

	$("#wcdm_coupon_display_mode").on("change", toggleListFields);
	toggleListFields();

	// 2.2. Toggle Dropdown Text field based on "Enable Collapsible Dropdown" setting
	function toggleDropdownTextField() {
		var isChecked = $("#wcdm_dropdown_hide").is(":checked");
		$("#wcdm_dropdown_text").closest("tr").toggle(isChecked);
	}

	$("#wcdm_dropdown_hide").on("change", toggleDropdownTextField);
	toggleDropdownTextField();

	// 3. Custom Coupon Grid Selection & Counter
	var $grid = $(".wcdm-admin-coupon-grid");
	if ($grid.length) {
		
		// Function to update the "X of Y selected" counter
		function updateSelectedCounter() {
			var total = $grid.find(".wcdm-admin-ticket").length;
			var selected = $grid.find(".wcdm-admin-ticket-active").length;
			var $counter = $(".wcdm-admin-selected-counter");
			
			if ($counter.length) {
				$counter.text(selected + ' of ' + total + ' selected');
			}
		}

		// Function to enforce single selection if single coupon mode is active
		function enforceSingleCouponMode() {
			var isSingle = $("#wcdm_single_coupon").is(":checked");
			var $selectAll = $(".wcdm-admin-select-all");
			var $deselectAll = $(".wcdm-admin-deselect-all");
			var $desc = $(".wcdm-admin-coupon-grid-wrapper").find(".description");
			
			if (isSingle) {
				$selectAll.prop("disabled", true).css({ "opacity": 0.5, "pointer-events": "none" });
				$deselectAll.prop("disabled", true).css({ "opacity": 0.5, "pointer-events": "none" });
				
				if ($desc.length) {
					$desc.text("Single Coupon Mode is active. You must select exactly 1 coupon to display on checkout.");
				}
				
				// Keep only the first selected coupon, deselect others
				var $checked = $grid.find(".wcdm-admin-ticket-active");
				if ($checked.length > 1) {
					$checked.slice(1).each(function() {
						$(this).removeClass("wcdm-admin-ticket-active");
						$(this).find("input[type=\"checkbox\"]").prop("checked", false);
					});
				} else if ($checked.length === 0) {
					// Auto-select the first coupon in the list if none are selected
					var $firstCard = $grid.find(".wcdm-admin-ticket").first();
					if ($firstCard.length) {
						$firstCard.addClass("wcdm-admin-ticket-active");
						$firstCard.find("input[type=\"checkbox\"]").prop("checked", true);
					}
				}
			} else {
				$selectAll.prop("disabled", false).css({ "opacity": 1, "pointer-events": "auto" });
				$deselectAll.prop("disabled", false).css({ "opacity": 1, "pointer-events": "auto" });
				
				if ($desc.length) {
					$desc.text("Choose which coupons to display in the list. If none are selected, all valid public coupons will be shown.");
				}
			}
			updateSelectedCounter();
		}

		// Initialize counter and enforce mode on page load
		updateSelectedCounter();
		enforceSingleCouponMode();

		// Listen to change on Single Coupon Mode checkbox
		$("#wcdm_single_coupon").on("change", enforceSingleCouponMode);

		// Card Click Handler
		$grid.on("click", ".wcdm-admin-ticket", function(e) {
			// If click is directly on the hidden checkbox (fallback), let it handle naturally
			if ($(e.target).is("input[type=\"checkbox\"]")) {
				return;
			}
			
			var $card = $(this);
			var $checkbox = $card.find("input[type=\"checkbox\"]");
			var isChecked = $checkbox.prop("checked");
			var isSingle = $("#wcdm_single_coupon").is(":checked");
			
			if (isSingle) {
				if (isChecked) {
					// Cannot uncheck the only checked coupon
					return;
				}
				// Uncheck all other cards first
				$grid.find(".wcdm-admin-ticket").each(function() {
					if ($(this)[0] !== $card[0]) {
						$(this).removeClass("wcdm-admin-ticket-active");
						$(this).find("input[type=\"checkbox\"]").prop("checked", false);
					}
				});
			}
			
			$checkbox.prop("checked", !isChecked).trigger('change');
			$card.toggleClass("wcdm-admin-ticket-active", !isChecked);
			
			updateSelectedCounter();
		});

		// Ensure clicking checkbox directly updates card classes
		$grid.on("change", "input[type=\"checkbox\"]", function() {
			var $card = $(this).closest(".wcdm-admin-ticket");
			var isChecked = $(this).prop("checked");
			var isSingle = $("#wcdm_single_coupon").is(":checked");
			
			if (isSingle) {
				if (!isChecked) {
					// Cannot uncheck the active card in single mode
					var $active = $grid.find(".wcdm-admin-ticket-active");
					if ($active.length === 0 || ($active.length === 1 && $active[0] === $card[0])) {
						$(this).prop("checked", true);
						$card.addClass("wcdm-admin-ticket-active");
						updateSelectedCounter();
						return;
					}
				} else {
					var $checkbox = $(this);
					$grid.find("input[type=\"checkbox\"]").each(function() {
						if ($(this)[0] !== $checkbox[0]) {
							$(this).prop("checked", false);
							$(this).closest(".wcdm-admin-ticket").removeClass("wcdm-admin-ticket-active");
						}
					});
				}
			}
			
			$card.toggleClass("wcdm-admin-ticket-active", isChecked);
			updateSelectedCounter();
		});

		// Search Coupons Handler (instant client-side filtering)
		$("#wcdm-admin-coupon-search").on("input", function() {
			var query = $(this).val().toLowerCase().trim();
			var visibleCount = 0;
			
			$grid.find(".wcdm-admin-ticket").each(function() {
				var code = ($(this).data("code") || "").toString().toLowerCase();
				if (code.indexOf(query) > -1) {
					$(this).show();
					visibleCount++;
				} else {
					$(this).hide();
				}
			});

			// Show/hide search empty state helper
			var $searchEmpty = $grid.find(".wcdm-admin-search-empty");
			if (visibleCount === 0) {
				if (!$searchEmpty.length) {
					$grid.append('<p class="wcdm-admin-search-empty wcdm-admin-no-coupons" style="grid-column: 1/-1; text-align:center; padding: 20px; color:#64748b;">No coupons match your search.</p>');
				}
			} else {
				$searchEmpty.remove();
			}
		});

		// Select All Button Handler (only selects visible coupons to respect search filters)
		$(".wcdm-admin-select-all").on("click", function(e) {
			e.preventDefault();
			$grid.find(".wcdm-admin-ticket:visible").each(function() {
				$(this).addClass("wcdm-admin-ticket-active");
				$(this).find("input[type=\"checkbox\"]").prop("checked", true);
			});
			updateSelectedCounter();
		});

		// Clear Selection Button Handler (clears all)
		$(".wcdm-admin-deselect-all").on("click", function(e) {
			e.preventDefault();
			$grid.find(".wcdm-admin-ticket").each(function() {
				$(this).removeClass("wcdm-admin-ticket-active");
				$(this).find("input[type=\"checkbox\"]").prop("checked", false);
			});
			updateSelectedCounter();
		});
	}
});
