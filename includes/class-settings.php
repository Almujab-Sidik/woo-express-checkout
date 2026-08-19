<?php

/**
 * Unified settings page at WooCommerce > Express Checkout.
 *
 * Three module toggles control Express Checkout, Bundle & Upsell, and Coupon
 * Display Manager. Embedded panels call the original module renderers so
 * their existing controls remain consistent.
 *
 * @package WEC
 */

namespace WEC;

if (! defined('ABSPATH')) {
    exit;
}

class Settings
{
    const PAGE_SLUG = 'wec-express-checkout';

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function add_admin_menu()
    {
        add_submenu_page(
            'woocommerce',
            __('Express Checkout Settings', 'woo-express-checkout'),
            __('Express Checkout', 'woo-express-checkout'),
            'manage_woocommerce',
            self::PAGE_SLUG,
            array($this, 'render_page')
        );
    }

    public function register_settings()
    {
        register_setting('wec_module_settings_group', 'wec_module_checkout_enabled');
        register_setting('wec_module_settings_group', 'wec_module_bundle_enabled');
        register_setting('wec_module_settings_group', 'wec_module_coupon_enabled');

        register_setting('wec_checkout_settings_group', 'wec_checkout_guest_enabled');
        register_setting('wec_checkout_settings_group', 'wec_checkout_layout_enabled');
        register_setting('wec_checkout_settings_group', 'wec_checkout_product_pages_enabled');
        register_setting(
            'wec_checkout_settings_group',
            'wec_product_checkout_url_slug',
            array(
                'sanitize_callback' => array($this, 'sanitize_product_checkout_slug'),
            )
        );
        register_setting('wec_checkout_settings_group', 'wec_checkout_order_button_text', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('wec_checkout_settings_group', 'wec_checkout_order_button_format', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('wec_checkout_settings_group', 'wec_checkout_order_button_background', array('sanitize_callback' => array($this, 'sanitize_checkout_color')));
        register_setting('wec_checkout_settings_group', 'wec_checkout_order_button_text_color', array('sanitize_callback' => array($this, 'sanitize_checkout_color')));
        register_setting('wec_checkout_settings_group', 'wec_checkout_order_button_hover_background', array('sanitize_callback' => array($this, 'sanitize_checkout_color')));

        // Brand / heading customization.
        register_setting('wec_checkout_settings_group', 'wec_checkout_brand_text', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('wec_checkout_settings_group', 'wec_checkout_brand_logo', array('sanitize_callback' => 'absint'));

        // Payment method images (keyed by method slug).
        register_setting(
            'wec_checkout_settings_group',
            'wec_payment_method_images',
            array('sanitize_callback' => array($this, 'sanitize_payment_method_images'))
        );

        // Master module toggles default to off so every feature is opt-in.
        foreach (array('wec_module_checkout_enabled', 'wec_module_bundle_enabled', 'wec_module_coupon_enabled') as $option) {
            if (false === get_option($option)) {
                update_option($option, 'no');
            }
        }

        // Express Checkout sub-feature toggles default to on and only take
        // effect when the parent module is enabled.
        foreach (array('wec_checkout_guest_enabled', 'wec_checkout_layout_enabled') as $option) {
            if (false === get_option($option)) {
                update_option($option, 'yes');
            }
        }

        // Product-Specific Checkout requires a separate SCF setup step, so it
        // defaults to off.
        if (false === get_option('wec_checkout_product_pages_enabled')) {
            update_option('wec_checkout_product_pages_enabled', 'no');
        }

        if (false === get_option('wec_product_checkout_url_slug')) {
            update_option('wec_product_checkout_url_slug', 'checkout');
        }

        $button_defaults = array(
            'wec_checkout_order_button_text'             => 'Place order',
            'wec_checkout_order_button_format'           => '{text}',
            'wec_checkout_order_button_background'       => '#7f54b3',
            'wec_checkout_order_button_text_color'       => '#ffffff',
            'wec_checkout_order_button_hover_background' => '#68429a',
        );
        foreach ($button_defaults as $option => $default) {
            if (false === get_option($option)) {
                update_option($option, $default);
            }
        }

        if (false === get_option('wec_checkout_brand_text')) {
            update_option('wec_checkout_brand_text', 'BiZSchool');
        }
        if (false === get_option('wec_checkout_brand_logo')) {
            update_option('wec_checkout_brand_logo', 0);
        }
        if (false === get_option('wec_payment_method_images')) {
            update_option('wec_payment_method_images', array());
        }

        // WA Reminder settings
        register_setting('wec_wa_reminder_group', 'wec_wa_reminder_enabled');
        register_setting(
            'wec_wa_reminder_group',
            'wec_starsender_api_key',
            array(
                'sanitize_callback' => 'sanitize_text_field',
            )
        );
        register_setting(
            'wec_wa_reminder_group',
            'wec_wa_reminder_delay',
            array(
                'sanitize_callback' => 'absint',
            )
        );
        register_setting(
            'wec_wa_reminder_group',
            'wec_wa_reminder_template',
            array(
                'sanitize_callback' => 'sanitize_textarea_field',
            )
        );

        if (false === get_option('wec_wa_reminder_enabled')) {
            update_option('wec_wa_reminder_enabled', 'no');
        }
        if (false === get_option('wec_wa_reminder_delay')) {
            update_option('wec_wa_reminder_delay', '120');
        }
        if (false === get_option('wec_wa_reminder_template')) {
            update_option('wec_wa_reminder_template', $this->get_default_wa_template());
        }
    }

    public function sanitize_product_checkout_slug($value)
    {
        $slug = sanitize_title($value);
        return $slug ? $slug : 'checkout';
    }

    public function sanitize_checkout_color($value)
    {
        return sanitize_hex_color($value) ?: '#7f54b3';
    }

    /**
     * Sanitize payment method image map: { method_slug => attachment_id }.
     */
    public function sanitize_payment_method_images($value)
    {
        if (! is_array($value)) {
            return array();
        }

        $clean = array();
        foreach ($value as $slug => $attachment_id) {
            $slug = sanitize_title($slug);
            $attachment_id = absint($attachment_id);
            if ($slug && $attachment_id) {
                $clean[$slug] = $attachment_id;
            }
        }

        return $clean;
    }

    /**
     * Render the brand header markup for the checkout page.
     */
    public static function render_brand()
    {
        $text = get_option('wec_checkout_brand_text', 'BiZSchool');
        $logo = (int) get_option('wec_checkout_brand_logo', 0);

        if ($logo) {
            $src = wp_get_attachment_image_url($logo, 'medium');
            if ($src) {
                echo '<img class="wec-brand-logo" src="' . esc_url($src) . '" alt="' . esc_attr($text) . '" />';
                return;
            }
        }

        // Fallback: text with a colored period accent.
        echo '<span>' . esc_html($text) . '<span>.</span></span>';
    }

    /**
     * Render the payment methods list with optional uploaded images.
     */
    public static function render_payment_methods()
    {
        $methods = array(
            'qris'    => 'QRIS',
            'bca'     => 'BCA',
            'mandiri' => 'mandiri',
            'bni'     => 'BNI',
            'bri'     => 'BRI',
            'bsi'     => 'BSI',
            'dana'    => 'DANA',
        );

        $images = get_option('wec_payment_method_images', array());
        if (! is_array($images)) {
            $images = array();
        }

        echo '<div class="payment-methods">';
        foreach ($methods as $slug => $label) {
            $attachment_id = isset($images[$slug]) ? (int) $images[$slug] : 0;
            $src = $attachment_id ? wp_get_attachment_image_url($attachment_id, 'medium') : '';

            if ($src) {
                echo '<span class="payment-method payment-method--' . esc_attr($slug) . '">';
                echo '<img src="' . esc_url($src) . '" alt="' . esc_attr($label) . '" />';
                echo '</span>';
            } else {
                echo '<span class="payment-method payment-method--' . esc_attr($slug) . '">' . esc_html($label) . '</span>';
            }
        }
        echo '</div>';
    }

    private function get_default_wa_template()
    {
        return "Halo %billing_name%,\n\n_(Mohon abaikan pesan ini jika bukan Anda yang melakukan pemesanan)_\n\nTerimakasih untuk pemesanan Anda:\n\nOrder ID: *%order_id%*\nTanggal: *%order_date%*\nStatus: *%order_status%*\nProduk: *%order_items%*\nTotal: *%order_total%*\n\nSilakan lanjutkan pembayaran melalui link berikut:\n%payment_url%\n\nLink pembayaran berlaku 24 jam.\n\nTerimakasih banyak sudah berbelanja di website kami.\n\nSalam Hangat,\n%site_name%";
    }

    public function render_page()
    {
        $checkout_on = 'yes' === get_option('wec_module_checkout_enabled', 'no');
        $bundle_on   = 'yes' === get_option('wec_module_bundle_enabled', 'no');
        $coupon_on   = 'yes' === get_option('wec_module_coupon_enabled', 'no');
?>
        <div class="wrap wec-settings-wrap">
            <h1><?php esc_html_e('WooCommerce Express Checkout', 'woo-express-checkout'); ?></h1>
            <p class="description">
                <?php esc_html_e('Enable or disable the three modules below. Detailed settings appear when a module is enabled.', 'woo-express-checkout'); ?>
            </p>

            <div class="card" style="max-width: 100%; margin-top: 16px;">
                <h2><?php esc_html_e('Enabled Modules', 'woo-express-checkout'); ?></h2>
                <form method="post" action="options.php">
                    <?php settings_fields('wec_module_settings_group'); ?>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><?php esc_html_e('Express Checkout', 'woo-express-checkout'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="wec_module_checkout_enabled" value="yes" <?php checked($checkout_on); ?> />
                                    <?php esc_html_e('Streamlined checkout layout, guest checkout, and automatic account creation.', 'woo-express-checkout'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Bundle & Upsell', 'woo-express-checkout'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="wec_module_bundle_enabled" value="yes" <?php checked($bundle_on); ?> />
                                    <?php esc_html_e('Checkout order bumps, product bundles, and post-purchase upsells.', 'woo-express-checkout'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Coupon Display Manager', 'woo-express-checkout'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="wec_module_coupon_enabled" value="yes" <?php checked($coupon_on); ?> />
                                    <?php esc_html_e('Coupon placement, styling, and clickable coupon lists.', 'woo-express-checkout'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(__('Save Enabled Modules', 'woo-express-checkout')); ?>
                </form>
            </div>

            <?php if ($checkout_on) : ?>
                <div class="card wec-settings-subpanel" style="max-width: 100%; margin-top: 16px;">
                    <h2><?php esc_html_e('Express Checkout Settings', 'woo-express-checkout'); ?></h2>
                    <form method="post" action="options.php">
                        <?php settings_fields('wec_checkout_settings_group'); ?>
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><?php esc_html_e('Guest Checkout', 'woo-express-checkout'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="wec_checkout_guest_enabled" value="yes" <?php checked('yes' === get_option('wec_checkout_guest_enabled', 'yes')); ?> />
                                        <?php esc_html_e('Hide the login form and allow checkout without an account. New accounts are created automatically after payment.', 'woo-express-checkout'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Layout 2 Kolom', 'woo-express-checkout'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="wec_checkout_layout_enabled" value="yes" <?php checked('yes' === get_option('wec_checkout_layout_enabled', 'yes')); ?> />
                                        <?php esc_html_e('Two-column checkout layout with the customer form on the left and a sticky order summary on the right.', 'woo-express-checkout'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Brand / Logo', 'woo-express-checkout'); ?></th>
                                <td>
                                    <p>
                                        <label for="wec_checkout_brand_text"><strong><?php esc_html_e('Brand Text', 'woo-express-checkout'); ?></strong></label><br>
                                        <input type="text" id="wec_checkout_brand_text" name="wec_checkout_brand_text" value="<?php echo esc_attr(get_option('wec_checkout_brand_text', 'BiZSchool')); ?>" class="regular-text" />
                                        <span class="description"><?php esc_html_e('Shown when no logo is uploaded.', 'woo-express-checkout'); ?></span>
                                    </p>
                                    <p>
                                        <label for="wec_checkout_brand_logo"><strong><?php esc_html_e('Logo Image', 'woo-express-checkout'); ?></strong></label><br>
                                        <input type="hidden" id="wec_checkout_brand_logo" name="wec_checkout_brand_logo" value="<?php echo esc_attr(get_option('wec_checkout_brand_logo', 0)); ?>" />
                                        <?php
                                        $wec_brand_logo_id = (int) get_option('wec_checkout_brand_logo', 0);
                                        $wec_brand_logo_src = $wec_brand_logo_id ? wp_get_attachment_image_url($wec_brand_logo_id, 'medium') : '';
                                        ?>
                                        <img id="wec_checkout_brand_logo_preview" src="<?php echo esc_url($wec_brand_logo_src); ?>" style="max-height:48px; <?php echo $wec_brand_logo_src ? '' : 'display:none;'; ?>" alt="" />
                                        <button type="button" class="button wec-upload-image" data-target="wec_checkout_brand_logo" data-preview="wec_checkout_brand_logo_preview"><?php esc_html_e('Choose Image', 'woo-express-checkout'); ?></button>
                                        <button type="button" class="button-link-delete wec-remove-image" data-target="wec_checkout_brand_logo" data-preview="wec_checkout_brand_logo_preview" style="<?php echo $wec_brand_logo_src ? '' : 'display:none;'; ?>"><?php esc_html_e('Remove', 'woo-express-checkout'); ?></button>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Payment Method Images', 'woo-express-checkout'); ?></th>
                                <td>
                                    <p class="description"><?php esc_html_e('Upload an image for each payment method. Methods without an image fall back to the text label.', 'woo-express-checkout'); ?></p>
                                    <table class="wec-payment-images" role="presentation">
                                        <?php
                                        $wec_payment_methods = array(
                                            'qris'    => 'QRIS',
                                            'bca'     => 'BCA',
                                            'mandiri' => 'mandiri',
                                            'bni'     => 'BNI',
                                            'bri'     => 'BRI',
                                            'bsi'     => 'BSI',
                                            'dana'    => 'DANA',
                                        );
                                        $wec_payment_images = get_option('wec_payment_method_images', array());
                                        $wec_payment_images = is_array($wec_payment_images) ? $wec_payment_images : array();
                                        foreach ($wec_payment_methods as $wec_pm_slug => $wec_pm_label) :
                                            $wec_pm_id = isset($wec_payment_images[$wec_pm_slug]) ? (int) $wec_payment_images[$wec_pm_slug] : 0;
                                            $wec_pm_src = $wec_pm_id ? wp_get_attachment_image_url($wec_pm_id, 'medium') : '';
                                        ?>
                                            <tr>
                                                <td style="width:80px;"><strong><?php echo esc_html($wec_pm_label); ?></strong></td>
                                                <td>
                                                    <input type="hidden" name="wec_payment_method_images[<?php echo esc_attr($wec_pm_slug); ?>]" value="<?php echo esc_attr($wec_pm_id); ?>" />
                                                    <img class="wec-payment-method-preview" src="<?php echo esc_url($wec_pm_src); ?>" style="max-height:32px; <?php echo $wec_pm_src ? '' : 'display:none;'; ?>" alt="" />
                                                    <button type="button" class="button wec-upload-image" data-target="wec_payment_method_images[<?php echo esc_attr($wec_pm_slug); ?>]" data-preview="" data-preview-class="wec-payment-method-preview"><?php esc_html_e('Choose Image', 'woo-express-checkout'); ?></button>
                                                    <button type="button" class="button-link-delete wec-remove-image" data-target="wec_payment_method_images[<?php echo esc_attr($wec_pm_slug); ?>]" data-preview-class="wec-payment-method-preview" style="<?php echo $wec_pm_src ? '' : 'display:none;'; ?>"><?php esc_html_e('Remove', 'woo-express-checkout'); ?></button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </table>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Place Order Button', 'woo-express-checkout'); ?></th>
                                <td>
                                    <p>
                                        <label for="wec_checkout_order_button_text"><strong><?php esc_html_e('Button Text', 'woo-express-checkout'); ?></strong></label><br>
                                        <input type="text" id="wec_checkout_order_button_text" name="wec_checkout_order_button_text" value="<?php echo esc_attr(get_option('wec_checkout_order_button_text', 'Place order')); ?>" class="regular-text" />
                                    </p>
                                    <p>
                                        <label for="wec_checkout_order_button_format"><strong><?php esc_html_e('Label Format', 'woo-express-checkout'); ?></strong></label><br>
                                        <input type="text" id="wec_checkout_order_button_format" name="wec_checkout_order_button_format" value="<?php echo esc_attr(get_option('wec_checkout_order_button_format', '{text}')); ?>" class="regular-text" />
                                        <span class="description"><?php esc_html_e('Use {text} for the button text and $Price for the current order total. Example: {text} $Price', 'woo-express-checkout'); ?></span>
                                    </p>
                                    <p>
                                        <label for="wec_checkout_order_button_background"><strong><?php esc_html_e('Button Color', 'woo-express-checkout'); ?></strong></label><br>
                                        <input type="color" id="wec_checkout_order_button_background" name="wec_checkout_order_button_background" value="<?php echo esc_attr(get_option('wec_checkout_order_button_background', '#7f54b3')); ?>" />
                                        <label for="wec_checkout_order_button_text_color" style="margin-left: 12px;"><strong><?php esc_html_e('Text Color', 'woo-express-checkout'); ?></strong></label>
                                        <input type="color" id="wec_checkout_order_button_text_color" name="wec_checkout_order_button_text_color" value="<?php echo esc_attr(get_option('wec_checkout_order_button_text_color', '#ffffff')); ?>" />
                                        <label for="wec_checkout_order_button_hover_background" style="margin-left: 12px;"><strong><?php esc_html_e('Hover Color', 'woo-express-checkout'); ?></strong></label>
                                        <input type="color" id="wec_checkout_order_button_hover_background" name="wec_checkout_order_button_hover_background" value="<?php echo esc_attr(get_option('wec_checkout_order_button_hover_background', '#68429a')); ?>" />
                                    </p>
                                    <p class="description">
                                        <?php esc_html_e('These settings apply to the standard WooCommerce Place Order button on the Express Checkout layout.', 'woo-express-checkout'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Product-Specific Checkout (SCF)', 'woo-express-checkout'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="wec_checkout_product_pages_enabled" value="yes" <?php checked('yes' === get_option('wec_checkout_product_pages_enabled', 'no')); ?> />
                                        <?php esc_html_e('Create dedicated checkout pages for individual products. Each page has its own URL for landing-page calls to action.', 'woo-express-checkout'); ?>
                                    </label>
                                    <p class="description">
                                        <?php
                                        echo wp_kses(
                                            __('An active <strong>Secure Custom Fields</strong> (or ACF) plugin is required for the "Product" field to appear on Product-Specific Checkout pages.', 'woo-express-checkout'),
                                            array('strong' => array())
                                        );
                                        ?>
                                    </p>
                                    <?php if ('yes' === get_option('wec_checkout_product_pages_enabled', 'no')) : ?>
                                        <p>
                                            <a href="<?php echo esc_url(admin_url('edit.php?post_type=wec_product_checkout')); ?>" class="button">
                                                <?php esc_html_e('Manage Product Checkout Pages', 'woo-express-checkout'); ?>
                                            </a>
                                            <a href="<?php echo esc_url(admin_url('post-new.php?post_type=wec_product_checkout')); ?>" class="button">
                                                <?php esc_html_e('Add New Page', 'woo-express-checkout'); ?>
                                            </a>
                                        </p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Product Checkout URL Slug', 'woo-express-checkout'); ?></th>
                                <td>
                                    <code><?php echo esc_html(trailingslashit(home_url())); ?></code>
                                    <input
                                        type="text"
                                        name="wec_product_checkout_url_slug"
                                        value="<?php echo esc_attr(get_option('wec_product_checkout_url_slug', 'checkout')); ?>"
                                        class="regular-text"
                                        placeholder="checkout"
                                        pattern="[a-z0-9-]+" />
                                    <code>/example/</code>
                                    <p class="description">
                                        <?php esc_html_e('Controls the URL base for Product-Specific Checkout pages. Example: /checkout/example/. Change this if it conflicts with your WooCommerce checkout URL.', 'woo-express-checkout'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                        <?php submit_button(__('Save Express Checkout Settings', 'woo-express-checkout')); ?>
                    </form>
                </div>
            <?php endif; ?>

            <?php if ($bundle_on && class_exists('\Upsell_Bundle_Admin')) : ?>
                <div class="wec-settings-subpanel" style="margin-top: 16px;">
                    <h2 style="padding: 0 0 8px;"><?php esc_html_e('Bundle & Upsell Settings', 'woo-express-checkout'); ?></h2>
                    <?php \Upsell_Bundle_Admin::get_instance()->render_settings_page(); ?>
                </div>
            <?php endif; ?>

            <?php if ($coupon_on && class_exists('\WCDM_Settings')) : ?>
                <div class="wec-settings-subpanel" style="margin-top: 16px;">
                    <?php \WCDM_Settings::get_instance()->render_submenu_page(); ?>
                </div>
            <?php endif; ?>

            <!-- WA Payment Reminder Settings -->
            <div class="card" style="max-width: 100%; margin-top: 16px;">
                <h2><?php esc_html_e('WA Payment Reminder', 'woo-express-checkout'); ?></h2>
                <form method="post" action="options.php">
                    <?php settings_fields('wec_wa_reminder_group'); ?>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><?php esc_html_e('Enable Reminder', 'woo-express-checkout'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="wec_wa_reminder_enabled" value="yes" <?php checked('yes' === get_option('wec_wa_reminder_enabled', 'no')); ?> />
                                    <?php esc_html_e('Send WhatsApp reminder when order is on-hold (Midtrans pending).', 'woo-express-checkout'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Star Sender API Key', 'woo-express-checkout'); ?></th>
                            <td>
                                <input type="text" name="wec_starsender_api_key" value="<?php echo esc_attr(get_option('wec_starsender_api_key', '')); ?>" class="regular-text" />
                                <p class="description"><?php esc_html_e('Get your API key from Star Sender Device menu.', 'woo-express-checkout'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Delay (seconds)', 'woo-express-checkout'); ?></th>
                            <td>
                                <input type="number" name="wec_wa_reminder_delay" value="<?php echo esc_attr(get_option('wec_wa_reminder_delay', 120)); ?>" min="0" max="3600" />
                                <p class="description"><?php esc_html_e('Delay before sending reminder (120 = 2 minutes).', 'woo-express-checkout'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Message Template', 'woo-express-checkout'); ?></th>
                            <td>
                                <textarea name="wec_wa_reminder_template" rows="18" class="large-text code"><?php echo esc_textarea(get_option('wec_wa_reminder_template', $this->get_default_wa_template())); ?></textarea>
                                <p class="description">
                                    <?php esc_html_e('Use placeholders such as %billing_name%, %order_id%, %order_date%, %order_status%, %order_items%, %order_total%, %payment_url%, and %site_name%.', 'woo-express-checkout'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(__('Save WA Reminder Settings', 'woo-express-checkout')); ?>
                </form>
            </div>
        </div>
<?php
    }
}
