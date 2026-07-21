# WooCommerce Express Checkout

Plugin WooCommerce yang menggabungkan pengalaman checkout ala Shopify, bundle & upsell, coupon display manager, dan halaman checkout khusus per produk — dalam satu plugin dengan modul yang bisa diaktifkan/dinonaktifkan secara terpisah.

## Fitur

- **Express Checkout** — layout checkout 2 kolom ala Shopify, guest checkout (tanpa perlu login), akun pelanggan dibuat otomatis setelah pembayaran berhasil.
- **Bundle & Upsell** — order bump di halaman checkout, bundle produk di halaman produk, upsell post-purchase setelah order selesai.
- **Coupon Display Manager** — reposisi & styling field kupon di checkout, daftar kupon yang bisa diklik langsung.
- **Checkout per Produk** — halaman checkout khusus per produk (didukung [Secure Custom Fields](https://wordpress.org/plugins/secure-custom-fields/)), 1 URL per produk untuk ditempel ke tombol CTA landing page.

Semua fitur di atas defaultnya **nonaktif** — aktifkan lewat **WooCommerce → Express Checkout** sesuai kebutuhan.

## Requirement

- WordPress 6.0+
- WooCommerce 8.0+
- PHP 8.1+
- (Opsional) [Secure Custom Fields](https://wordpress.org/plugins/secure-custom-fields/) atau ACF — dibutuhkan hanya untuk fitur Checkout per Produk.

## Instalasi

Plugin ini tidak didistribusikan lewat wordpress.org. Ada 2 cara:

1. **Manual** — download ZIP dari [Releases](../../releases), lalu upload lewat **Plugins → Add New → Upload Plugin**.
2. **Update otomatis** — setelah instalasi pertama, plugin akan otomatis mengecek [Releases](../../releases) repo ini dan menampilkan notifikasi "Update tersedia" di wp-admin setiap ada versi baru.

## Update

Plugin menyertakan [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) yang menunjuk ke repo ini. Alur rilis versi baru:

1. Naikkan `Version:` di header `woo-express-checkout.php`.
2. Commit & push ke branch `main`.
3. Buat **GitHub Release** baru dengan tag sesuai versi (mis. `v0.2.0`).
4. Situs yang sudah terpasang akan otomatis mendeteksi rilis baru tersebut.

## Lisensi

GPL-2.0-or-later
