<?php

/**
 * HTML email template for setting an account password.
 *
 * @package WEC
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Variabel tersedia:
 *
 * @var string     $email_heading
 * @var \WP_User   $user
 * @var string     $reset_url
 * @var int        $order_id
 * @var \WC_Email  $email
 */

do_action('woocommerce_email_header', $email_heading, $email);
?>

<p><?php printf(esc_html__('Hello %s,', 'woo-express-checkout'), esc_html($user->display_name)); ?></p>

<p><?php esc_html_e('Thank you for shopping with us. An account was created automatically during checkout so you can view your order history and complete future purchases faster.', 'woo-express-checkout'); ?></p>

<p><?php esc_html_e('Please set a password to access your account:', 'woo-express-checkout'); ?></p>

<p style="text-align:center; padding: 16px 0;">
    <a href="<?php echo esc_url($reset_url); ?>" style="display:inline-block; background:#7f54b3; color:#ffffff; padding:12px 28px; border-radius:4px; text-decoration:none; font-weight:bold; font-size:15px;">
        <?php esc_html_e('Set Password Now', 'woo-express-checkout'); ?>
    </a>
</p>

<p style="color:#999; font-size:12px;">
    <?php esc_html_e('The link above expires in 24 hours. If the button does not work, copy the following link into your browser:', 'woo-express-checkout'); ?>
    <br>
    <a href="<?php echo esc_url($reset_url); ?>"><?php echo esc_url($reset_url); ?></a>
</p>

<p><?php esc_html_e('If you did not place this order, you can safely ignore this email.', 'woo-express-checkout'); ?></p>

<?php
do_action('woocommerce_email_footer', $email);
