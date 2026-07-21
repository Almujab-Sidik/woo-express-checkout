<?php

/**
 * Email template (HTML) — Buat Password.
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

<p><?php printf(esc_html__('Halo %s,', 'woo-express-checkout'), esc_html($user->display_name)); ?></p>

<p><?php esc_html_e('Terima kasih telah berbelanja di toko kami. Saat checkout, akun Anda telah dibuat secara otomatis sehingga Anda dapat melihat riwayat pesanan dan melakukan pembelian berikutnya lebih cepat.', 'woo-express-checkout'); ?></p>

<p><?php esc_html_e('Silakan buat password untuk mengakses akun Anda:', 'woo-express-checkout'); ?></p>

<p style="text-align:center; padding: 16px 0;">
    <a href="<?php echo esc_url($reset_url); ?>" style="display:inline-block; background:#7f54b3; color:#ffffff; padding:12px 28px; border-radius:4px; text-decoration:none; font-weight:bold; font-size:15px;">
        <?php esc_html_e('Buat Password Sekarang', 'woo-express-checkout'); ?>
    </a>
</p>

<p style="color:#999; font-size:12px;">
    <?php esc_html_e('Link di atas akan kadaluarsa dalam 24 jam. Jika tombol tidak berfungsi, salin link berikut ke browser Anda:', 'woo-express-checkout'); ?>
    <br>
    <a href="<?php echo esc_url($reset_url); ?>"><?php echo esc_url($reset_url); ?></a>
</p>

<p><?php esc_html_e('Jika Anda tidak merasa melakukan pembelian ini, abaikan email ini dan tidak ada akun yang akan dibuat.', 'woo-express-checkout'); ?></p>

<?php
do_action('woocommerce_email_footer', $email);
