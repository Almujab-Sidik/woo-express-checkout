<?php

/**
 * Admin Settings Class.
 *
 * Registers WooCommerce Coupon Display settings tab and submenu page.
 *
 * @package WooCouponDisplayManager
 */
if (!defined('ABSPATH')) {
	exit;
}

class WCDM_Settings
{
	/** @var WCDM_Settings */
	private static $instance = null;

	public static function get_instance()
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct()
	{
		add_filter('woocommerce_settings_tabs_array', array($this, 'add_settings_tab'), 50);
		add_action('woocommerce_settings_tabs_wcdm_settings', array($this, 'settings_tab_content'));
		add_action('woocommerce_update_options_wcdm_settings', array($this, 'update_settings'));
		add_action('admin_menu', array($this, 'add_admin_menu'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

		// Custom coupon selection field hooks
		add_action('woocommerce_admin_field_wcdm_coupon_select_grid', array($this, 'render_coupon_select_grid'));
		add_action('woocommerce_update_option_wcdm_coupon_select_grid', array($this, 'save_coupon_select_grid'));
	}

	// =========================================================================
	// Menu
	// =========================================================================

	public function add_admin_menu()
	{
		add_submenu_page(
			'woocommerce',
			__('Coupon Display Settings', 'coupon-display-manager-for-woocommerce'),
			__('Coupon Display', 'coupon-display-manager-for-woocommerce'),
			'manage_woocommerce',
			'wcdm-settings',
			array($this, 'render_submenu_page')
		);
	}

	public function render_submenu_page()
	{
		if (isset($_POST['wcdm_save_settings']) && check_admin_referer('wcdm_save_settings_action', 'wcdm_nonce')) {
			woocommerce_update_options($this->get_settings());
			echo '<div class="updated"><p>' . esc_html__('Settings saved.', 'coupon-display-manager-for-woocommerce') . '</p></div>';
		}
		?>
		<div class="wrap woocommerce wcdm-settings-wrap">
			<h2 class="screen-reader-text"><?php esc_html_e('Coupon Display Settings', 'coupon-display-manager-for-woocommerce'); ?></h2>
			<div class="wcdm-settings-header">
				<div class="wcdm-settings-title-section">
					<span class="wcdm-logo-icon">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="9" y1="9" x2="15" y2="15"></line><line x1="15" y1="9" x2="9" y2="15"></line></svg>
					</span>
					<span class="wcdm-settings-title-text"><?php esc_html_e('Coupon Display Settings', 'coupon-display-manager-for-woocommerce'); ?></span>
				</div>
				<div class="wcdm-settings-header-actions">
					<a href="https://docs.eraai.id" target="_blank" class="button wcdm-doc-button"><?php esc_html_e('Documentation', 'coupon-display-manager-for-woocommerce'); ?></a>
				</div>
			</div>
			
			<div class="wcdm-settings-card">
				<form method="post" action="">
					<?php wp_nonce_field('wcdm_save_settings_action', 'wcdm_nonce'); ?>
					<?php woocommerce_admin_fields($this->get_settings()); ?>
					<p class="submit wcdm-settings-submit-row">
						<button name="wcdm_save_settings" class="button-primary wcdm-save-btn" type="submit">
							<?php esc_html_e('Save changes', 'coupon-display-manager-for-woocommerce'); ?>
						</button>
					</p>
				</form>
			</div>
		</div>
		<?php
	}

	// =========================================================================
	// WC Settings tab
	// =========================================================================

	public function add_settings_tab($tabs)
	{
		$tabs['wcdm_settings'] = __('Coupon Display', 'coupon-display-manager-for-woocommerce');
		return $tabs;
	}

	public function settings_tab_content()
	{
		woocommerce_admin_fields($this->get_settings());
	}

	public function update_settings()
	{
		woocommerce_update_options($this->get_settings());
	}

	// =========================================================================
	// Admin assets (color picker)
	// =========================================================================

	public function enqueue_admin_assets()
	{
		$on_page = (
			(isset($_GET['page']) && 'wc-settings' === $_GET['page'] && isset($_GET['tab']) && 'wcdm_settings' === $_GET['tab']) || // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			(isset($_GET['page']) && 'wcdm-settings' === $_GET['page']) || // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			// Also load on the unified woo-express-checkout settings page,
			// since this panel is now embedded there instead of its own submenu.
			(isset($_GET['page']) && 'wec-express-checkout' === $_GET['page']) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		);

		if (!$on_page) {
			return;
		}

		wp_enqueue_style('wp-color-picker');
		wp_enqueue_script('wp-color-picker');

		wp_enqueue_style('wcdm-admin-css', WCDM_PLUGIN_URL . 'assets/css/admin.css', array('wp-color-picker'), WCDM_VERSION);
		wp_enqueue_script('wcdm-admin-js', WCDM_PLUGIN_URL . 'assets/js/admin.js', array('jquery', 'wp-color-picker'), WCDM_VERSION, true);
	}

	private function get_all_coupons()
	{
		if (!class_exists('WooCommerce')) {
			return array();
		}
		$posts = get_posts(array(
			'post_type' => 'shop_coupon',
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'orderby' => 'title',
			'order' => 'ASC',
		));

		$options = array();
		foreach ($posts as $post) {
			$options[$post->post_title] = $post->post_title;
		}
		return $options;
	}

	public function get_settings()
	{
		$settings = array(
			// ---- General ----
			array(
				'title' => __('Coupon Display Manager for WooCommerce', 'coupon-display-manager-for-woocommerce'),
				'type' => 'title',
				'desc' => __('Moves the coupon field to above the Place Order button so customers never confuse it with the payment button. Works with CartFlows, FunnelKit, Block Checkout, and the WooCommerce default.', 'coupon-display-manager-for-woocommerce'),
				'id' => 'wcdm_settings_title',
			),
			array(
				'title' => __('Activated', 'coupon-display-manager-for-woocommerce'),
				'desc' => __('Enable customized coupon display on checkout.', 'coupon-display-manager-for-woocommerce'),
				'id' => 'wcdm_activated',
				'default' => 'no',
				'type' => 'checkbox',
			),
			array(
				'title' => __('Layout Position', 'coupon-display-manager-for-woocommerce'),
				'desc' => __('Choose where to display the coupon field on checkout. "Above Payment Button" places it right above the Place Order button. "WooCommerce Default / Sidebar" places it in the Order Summary sidebar (for block checkout) or above the Order Review sidebar (for classic checkout).', 'coupon-display-manager-for-woocommerce'),
				'id' => 'wcdm_layout_position',
				'default' => 'above_payment',
				'type' => 'select',
				'class' => 'wc-enhanced-select',
				'options' => array(
					'above_payment' => __('Above Payment Button', 'coupon-display-manager-for-woocommerce'),
					'sidebar' => __('WooCommerce Default / Sidebar', 'coupon-display-manager-for-woocommerce'),
				),
				'desc_tip' => true,
			),
			array(
				'title' => __('Enable Collapsible Dropdown', 'coupon-display-manager-for-woocommerce'),
				'desc' => __('Hide the coupon field behind a clickable toggle link (e.g. "Have a coupon ?").', 'coupon-display-manager-for-woocommerce'),
				'id' => 'wcdm_dropdown_hide',
				'default' => 'no',
				'type' => 'checkbox',
			),
			array(
				'title' => __('Dropdown Toggle Text', 'coupon-display-manager-for-woocommerce'),
				'desc' => __('The text shown for the toggle link when Collapsible Dropdown is enabled.', 'coupon-display-manager-for-woocommerce'),
				'id' => 'wcdm_dropdown_text',
				'default' => __('Have a coupon?', 'coupon-display-manager-for-woocommerce'),
				'type' => 'text',
				'desc_tip' => true,
			),
			// ---- Apply button ----
			array(
				'title' => __('Apply Button Text', 'coupon-display-manager-for-woocommerce'),
				'desc' => __('Label shown on the Apply button.', 'coupon-display-manager-for-woocommerce'),
				'id' => 'wcdm_button_text',
				'default' => __('Apply Coupon', 'coupon-display-manager-for-woocommerce'),
				'type' => 'text',
				'desc_tip' => true,
			),
			array(
				'title' => __('Button Style', 'coupon-display-manager-for-woocommerce'),
				'desc' => __('Visual style for the Apply button. "Secondary" uses a neutral outline; "Link" renders a text-only link; "Custom" lets you set exact colors.', 'coupon-display-manager-for-woocommerce'),
				'id' => 'wcdm_button_style',
				'default' => 'secondary',
				'type' => 'select',
				'class' => 'wc-enhanced-select',
				'options' => array(
					'secondary' => __('Secondary Outline (Gray / Neutral)', 'coupon-display-manager-for-woocommerce'),
					'link' => __('Minimal Text Link', 'coupon-display-manager-for-woocommerce'),
					'custom' => __('Custom Color Scheme', 'coupon-display-manager-for-woocommerce'),
				),
				'desc_tip' => true,
			),
			array(
				'title' => __('Button Background Color', 'coupon-display-manager-for-woocommerce'),
				'id' => 'wcdm_custom_bg_color',
				'default' => '#6c757d',
				'type' => 'text',
				'class' => 'wcdm-color-field wcdm-color-row',
			),
			array(
				'title' => __('Button Text Color', 'coupon-display-manager-for-woocommerce'),
				'id' => 'wcdm_custom_text_color',
				'default' => '#ffffff',
				'type' => 'text',
				'class' => 'wcdm-color-field wcdm-color-row',
			),
			array(
				'title' => __('Button Hover Background', 'coupon-display-manager-for-woocommerce'),
				'id' => 'wcdm_custom_hover_bg_color',
				'default' => '#5a6268',
				'type' => 'text',
				'class' => 'wcdm-color-field wcdm-color-row',
			),
			array(
				'title' => __('Button Hover Text Color', 'coupon-display-manager-for-woocommerce'),
				'id' => 'wcdm_custom_hover_text_color',
				'default' => '#ffffff',
				'type' => 'text',
				'class' => 'wcdm-color-field wcdm-color-row',
			),
			// ---- UX extras ----
			array(
				'title' => __('Show Optional Hint', 'coupon-display-manager-for-woocommerce'),
				'desc' => __('Show optional hint text below the input.', 'coupon-display-manager-for-woocommerce'),
				'id' => 'wcdm_show_hint',
				'default' => 'yes',
				'type' => 'checkbox',
			),
			array(
				'title' => __('Optional Hint Text', 'coupon-display-manager-for-woocommerce'),
				'desc' => __('The text shown below the coupon input field if Show Optional Hint is enabled.', 'coupon-display-manager-for-woocommerce'),
				'id' => 'wcdm_hint_text',
				'default' => __('Optional — only if you have a coupon', 'coupon-display-manager-for-woocommerce'),
				'type' => 'text',
				'desc_tip' => true,
			),
			array(
				'type' => 'sectionend',
				'id' => 'wcdm_general_sectionend',
			),
			// ---- Coupon restriction ----
			array(
				'title' => __('Coupon Restriction', 'coupon-display-manager-for-woocommerce'),
				'type' => 'title',
				'desc' => __('Control how many coupons a customer can apply at once.', 'coupon-display-manager-for-woocommerce'),
				'id' => 'wcdm_restriction_title',
			),
			array(
				'title' => __('Single Coupon Mode', 'coupon-display-manager-for-woocommerce'),
				'desc' => __('Allow only one coupon per order. Applying a new code automatically replaces the existing one. <br/><strong style="color: #ea580c; display: block; margin-top: 5px;">WARNING: Do not uncheck this option unless you explicitly want to allow customers to combine multiple coupons/discounts on a single order. If unchecked, switching coupons on the checkout page may fail due to WooCommerce\'s "Individual use only" restrictions.</strong>', 'coupon-display-manager-for-woocommerce'),
				'id' => 'wcdm_single_coupon',
				'default' => 'yes',
				'type' => 'checkbox',
			),
			array(
				'type' => 'sectionend',
				'id' => 'wcdm_restriction_sectionend',
			),
			// ---- Available coupons list ----
			array(
				'title' => __('Available Coupons List', 'coupon-display-manager-for-woocommerce'),
				'type' => 'title',
				'desc' => __("Show a list of valid coupons so customers can apply one with a single click. Prefix a coupon's description with [private] to hide it from the list.", 'coupon-display-manager-for-woocommerce'),
				'id' => 'wcdm_list_section_title',
			),
			array(
				'title' => __('Coupon Display Mode', 'coupon-display-manager-for-woocommerce'),
				'desc' => __('Choose the type of display for the checkout coupon. "Manual Input Only" shows only the text box. "Clickable Coupon List Only" shows only the clickable badges. "Both" shows both badges and the manual text input field.', 'coupon-display-manager-for-woocommerce'),
				'id' => 'wcdm_coupon_display_mode',
				'default' => 'input',
				'type' => 'select',
				'class' => 'wc-enhanced-select',
				'options' => array(
					'input' => __('Manual Input Only', 'coupon-display-manager-for-woocommerce'),
					'list' => __('Clickable Coupon List Only', 'coupon-display-manager-for-woocommerce'),
					'both' => __('Both (Manual Input & Clickable List)', 'coupon-display-manager-for-woocommerce'),
				),
				'desc_tip' => true,
			),
			array(
				'title' => __('Select Coupons to Display', 'coupon-display-manager-for-woocommerce'),
				'desc' => __('Choose which coupons to display in the list. If none are selected, all valid public coupons will be shown.', 'coupon-display-manager-for-woocommerce'),
				'id' => 'wcdm_list_selected_coupons',
				'type' => 'wcdm_coupon_select_grid',
				'desc_tip' => true,
			),
			array(
				'title' => __('List Heading', 'coupon-display-manager-for-woocommerce'),
				'desc' => __('Heading text shown above the coupon badges.', 'coupon-display-manager-for-woocommerce'),
				'id' => 'wcdm_list_title',
				'default' => __('Available Coupons', 'coupon-display-manager-for-woocommerce'),
				'type' => 'text',
				'desc_tip' => true,
			),
			array(
				'title' => __('Show Coupon Description', 'coupon-display-manager-for-woocommerce'),
				'desc' => __('Show discount info below each badge (e.g. "Discount 10%").', 'coupon-display-manager-for-woocommerce'),
				'id' => 'wcdm_list_show_desc',
				'default' => 'yes',
				'type' => 'checkbox',
			),
			array(
				'title' => __('Hide Private Coupons', 'coupon-display-manager-for-woocommerce'),
				'desc' => __('Hide coupons whose description starts with [private].', 'coupon-display-manager-for-woocommerce'),
				'id' => 'wcdm_list_hide_private',
				'default' => 'no',
				'type' => 'checkbox',
			),
			array(
				'type' => 'sectionend',
				'id' => 'wcdm_settings_sectionend',
			),
			// ---- Checkout Text Customization ----
			array(
				'title' => __('Checkout Text Customization', 'coupon-display-manager-for-woocommerce'),
				'type' => 'title',
				'desc' => __('Customize default WooCommerce checkout headings and messages.', 'coupon-display-manager-for-woocommerce'),
				'id' => 'wcdm_checkout_texts_title',
			),
			array(
				'title' => __('Customer Information Heading', 'coupon-display-manager-for-woocommerce'),
				'desc' => __('Change the default "Customer information" heading.', 'coupon-display-manager-for-woocommerce'),
				'id' => 'wcdm_text_customer_info',
				'default' => '',
				'type' => 'text',
				'desc_tip' => true,
			),
			array(
				'title' => __('Billing Details Heading', 'coupon-display-manager-for-woocommerce'),
				'desc' => __('Change the default "Billing details" heading.', 'coupon-display-manager-for-woocommerce'),
				'id' => 'wcdm_text_billing_details',
				'default' => '',
				'type' => 'text',
				'desc_tip' => true,
			),
			array(
				'title' => __('Payment Heading', 'coupon-display-manager-for-woocommerce'),
				'desc' => __('Change the default "Payment" heading.', 'coupon-display-manager-for-woocommerce'),
				'id' => 'wcdm_text_payment',
				'default' => '',
				'type' => 'text',
				'desc_tip' => true,
			),
			array(
				'title' => __('Order Summary Heading', 'coupon-display-manager-for-woocommerce'),
				'desc' => __('Change the default "Your order" or "Order summary" heading.', 'coupon-display-manager-for-woocommerce'),
				'id' => 'wcdm_text_order_summary',
				'default' => '',
				'type' => 'text',
				'desc_tip' => true,
			),
			array(
				'title' => __('Additional Information Heading', 'coupon-display-manager-for-woocommerce'),
				'desc' => __('Change the default "Additional information" heading.', 'coupon-display-manager-for-woocommerce'),
				'id' => 'wcdm_text_additional_info',
				'default' => '',
				'type' => 'text',
				'desc_tip' => true,
			),
			array(
				'title' => __('Payment Agreement Text', 'coupon-display-manager-for-woocommerce'),
				'desc' => __('Change the default WooCommerce payment agreement checkbox note.', 'coupon-display-manager-for-woocommerce'),
				'id' => 'wcdm_text_agreement',
				'default' => '',
				'type' => 'textarea',
				'desc_tip' => true,
			),
			array(
				'type' => 'sectionend',
				'id' => 'wcdm_checkout_texts_sectionend',
			),
		);

		return apply_filters('wcdm_coupon_display_manager_settings', $settings);
	}

	private function get_all_coupons_detailed()
	{
		if (!class_exists('WooCommerce')) {
			return array();
		}
		$posts = get_posts(array(
			'post_type' => 'shop_coupon',
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'orderby' => 'title',
			'order' => 'ASC',
		));

		$coupons = array();
		foreach ($posts as $post) {
			$code = $post->post_title;
			$coupon = new WC_Coupon($code);
			$type = $coupon->get_discount_type();
			$amount = $coupon->get_amount();

			switch ($type) {
				case 'percent':
					$amount_label = $amount . '%';
					break;
				case 'fixed_cart':
					$amount_label = wc_price($amount);
					break;
				case 'fixed_product':
					$amount_label = wc_price($amount) . ' ' . __('per Product', 'coupon-display-manager-for-woocommerce');
					break;
				default:
					$amount_label = $amount;
					break;
			}

			$expiry_date = $coupon->get_date_expires();
			$expiry_label = '';
			if ($expiry_date) {
				$expiry_label = $expiry_date->date_i18n(get_option('date_format'));
			}

			$usage_limit = $coupon->get_usage_limit();
			$usage_count = $coupon->get_usage_count();
			$limit_label = '';
			if ($usage_limit > 0) {
				/* translators: 1: usage count, 2: usage limit */
				$limit_label = sprintf(__('%1$d/%2$d Used', 'coupon-display-manager-for-woocommerce'), $usage_count, $usage_limit);
			} else {
				/* translators: %d: usage count */
				$limit_label = sprintf(__('%d Used', 'coupon-display-manager-for-woocommerce'), $usage_count);
			}

			$coupons[] = array(
				'code' => $code,
				'amount_label' => wp_strip_all_tags($amount_label),
				'description' => $coupon->get_description(),
				'discount_type' => $type,
				'expiry_label' => $expiry_label,
				'limit_label' => $limit_label,
			);
		}
		return $coupons;
	}

	public function render_coupon_select_grid($value)
	{
		$option_value = get_option($value['id'], array());
		if (!is_array($option_value)) {
			$option_value = array();
		}

		$coupons = $this->get_all_coupons_detailed();
		?>
		<tr valign="top" id="wcdm_list_selected_coupons_row">
			<th scope="row" class="titledesc">
				<label><?php echo esc_html($value['title']); ?></label>
				<?php echo $value['desc_tip'] ? wc_help_tip($value['desc']) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</th>
			<td class="forminp">
				<div class="wcdm-admin-coupon-grid-wrapper">
					<!-- Search and Actions Bar -->
					<div class="wcdm-admin-coupon-bar">
						<div class="wcdm-admin-coupon-search-wrapper">
							<svg class="wcdm-search-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
							<input type="text" id="wcdm-admin-coupon-search" placeholder="<?php esc_attr_e('Search coupons...', 'coupon-display-manager-for-woocommerce'); ?>" />
						</div>
						
						<div class="wcdm-admin-coupon-actions">
							<span class="wcdm-admin-selected-counter">0 selected</span>
							<button type="button" class="button wcdm-admin-select-all"><?php esc_html_e('Select All', 'coupon-display-manager-for-woocommerce'); ?></button>
							<button type="button" class="button wcdm-admin-deselect-all"><?php esc_html_e('Clear Selection', 'coupon-display-manager-for-woocommerce'); ?></button>
						</div>
					</div>

					<!-- Coupon Grid -->
					<div class="wcdm-admin-coupon-grid">
						<?php if (empty($coupons)): ?>
							<div class="wcdm-admin-no-coupons-card">
								<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="wcdm-empty-icon"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="9" y1="9" x2="15" y2="15"></line><line x1="15" y1="9" x2="9" y2="15"></line></svg>
								<p class="wcdm-admin-no-coupons"><?php esc_html_e('No coupons found.', 'coupon-display-manager-for-woocommerce'); ?></p>
								<a href="<?php echo esc_url(admin_url('post-new.php?post_type=shop_coupon')); ?>" class="button button-primary wcdm-create-coupon-btn"><?php esc_html_e('Create Coupon', 'coupon-display-manager-for-woocommerce'); ?></a>
							</div>
						<?php else: ?>
							<?php
							foreach ($coupons as $coupon):
								$checked = in_array($coupon['code'], $option_value, true);
								$active_class = $checked ? ' wcdm-admin-ticket-active' : '';

								$type_label = '';
								$type_class = '';
								switch ($coupon['discount_type']) {
									case 'percent':
										$type_label = __('Percentage', 'coupon-display-manager-for-woocommerce');
										$type_class = 'wcdm-type-percent';
										break;
									case 'fixed_cart':
										$type_label = __('Fixed Cart', 'coupon-display-manager-for-woocommerce');
										$type_class = 'wcdm-type-fixed-cart';
										break;
									case 'fixed_product':
										$type_label = __('Fixed Product', 'coupon-display-manager-for-woocommerce');
										$type_class = 'wcdm-type-fixed-product';
										break;
									default:
										$type_label = ucfirst(str_replace('_', ' ', $coupon['discount_type']));
										$type_class = 'wcdm-type-other';
										break;
								}
								?>
								<div class="wcdm-admin-ticket<?php echo esc_attr($active_class); ?>" data-code="<?php echo esc_attr($coupon['code']); ?>">
									<div class="wcdm-admin-ticket-header">
										<span class="wcdm-admin-ticket-code"><?php echo esc_html($coupon['code']); ?></span>
										<input type="checkbox" name="<?php echo esc_attr($value['id']); ?>[]" value="<?php echo esc_attr($coupon['code']); ?>" <?php checked($checked, true); ?> style="display:none;" />
										<span class="wcdm-admin-ticket-checkbox"></span>
									</div>
									<div class="wcdm-admin-ticket-body">
										<div class="wcdm-admin-ticket-amount-row">
											<span class="wcdm-admin-ticket-amount"><?php echo esc_html($coupon['amount_label']); ?></span>
											<span class="wcdm-admin-ticket-badge <?php echo esc_attr($type_class); ?>"><?php echo esc_html($type_label); ?></span>
										</div>
										<?php if (!empty($coupon['description'])): ?>
											<span class="wcdm-admin-ticket-desc"><?php echo esc_html($coupon['description']); ?></span>
										<?php endif; ?>
									</div>
									<?php if (!empty($coupon['expiry_label']) || !empty($coupon['limit_label'])): ?>
										<div class="wcdm-admin-ticket-footer">
											<?php if (!empty($coupon['expiry_label'])): ?>
												<span class="wcdm-admin-ticket-meta wcdm-expiry" title="<?php esc_attr_e('Expiry Date', 'coupon-display-manager-for-woocommerce'); ?>">
													<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="wcdm-meta-icon"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
													<?php echo esc_html($coupon['expiry_label']); ?>
												</span>
											<?php endif; ?>
											<?php if (!empty($coupon['limit_label'])): ?>
												<span class="wcdm-admin-ticket-meta wcdm-usage" title="<?php esc_attr_e('Usage', 'coupon-display-manager-for-woocommerce'); ?>">
													<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="wcdm-meta-icon"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
													<?php echo esc_html($coupon['limit_label']); ?>
												</span>
											<?php endif; ?>
										</div>
									<?php endif; ?>
								</div>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
					<p class="description"><?php echo esc_html($value['desc']); ?></p>
				</div>
			</td>
		</tr>
		<?php
	}

	public function save_coupon_select_grid($field)
	{
		$id = $field['id'];
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$value = isset($_POST[$id]) ? array_map('sanitize_text_field', wp_unslash($_POST[$id])) : array();
		update_option($id, $value);
	}
}
