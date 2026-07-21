# PRD — WooCommerce Express Checkout Plugin

| | |
|---|---|
| **Nama Plugin (kode)** | `woo-express-checkout` (nama sementara) |
| **Versi Dokumen** | 1.0 — Draft |
| **Tanggal** | 6 Juli 2026 |
| **Owner** | Era AI |
| **Status** | Draft — menunggu review |
| **Target Platform** | WordPress 6.x+, WooCommerce 8.x+, PHP 8.1+ |

---

## 1. Problem Statement

Halaman checkout bawaan WooCommerce memiliki friksi tinggi: layout satu kolom yang panjang, form login yang mengintimidasi pembeli baru, terlalu banyak field, dan tidak ada mekanisme upsell/bundle di titik konversi paling kritis. Akibatnya, cart abandonment tinggi dan Average Order Value (AOV) stagnan.

Toko yang terbiasa dengan pengalaman checkout Shopify (2 kolom, ringkas, guest-first) merasakan penurunan kualitas UX saat migrasi atau membangun toko di WooCommerce. Plugin ini menghadirkan pengalaman checkout ala Shopify di WooCommerce: **cepat, tanpa login, form minimal, dan mendukung bundle offer** — sambil tetap otomatis membuatkan akun pelanggan di belakang layar.

## 2. Goals

1. **Menurunkan checkout abandonment rate** minimal 15% dalam 60 hari setelah aktivasi (dibanding baseline checkout bawaan).
2. **Meningkatkan AOV** minimal 10% melalui fitur Bundle Offer di halaman checkout.
3. **Mempercepat waktu penyelesaian checkout** — dari landing di checkout hingga klik "Place Order" di bawah 90 detik untuk pembeli baru.
4. **100% pembeli baru tetap memiliki akun** meskipun checkout tanpa login (akun dibuat otomatis + email set password).
5. **Zero konflik** dengan payment gateway utama (Stripe, bank transfer, COD) dan tema berbasis block maupun classic.

## 3. Non-Goals

1. **Membangun payment gateway sendiri** — plugin hanya mengatur layout & flow; pembayaran tetap ditangani gateway yang sudah terpasang (Stripe, dll). Terlalu kompleks dan berisiko compliance.
2. **One-page checkout / menggabungkan cart + checkout** — v1 fokus pada halaman checkout saja. Cart drawer/upsell di cart adalah inisiatif terpisah.
3. **Kompatibilitas dengan page builder (Elementor, Divi) untuk kustomisasi visual checkout** — v1 menggunakan template PHP + hooks sendiri. Sesuai filosofi lightweight, bukan builder-dependent.
4. **Multi-step checkout (wizard 3 langkah ala Shopify lama)** — Shopify modern sendiri sudah kembali ke single-page 2 kolom; multi-step menambah friksi.
5. **Subscription / recurring bundle** — bundle v1 hanya untuk produk simple & variable sekali beli. Integrasi WooCommerce Subscriptions masuk P2.

## 4. Target Users & Personas

- **Pembeli baru (guest)** — mayoritas trafik; ingin bayar secepat mungkin tanpa membuat akun.
- **Pembeli lama (returning customer)** — sudah punya akun; cukup isi email yang sama, order otomatis ter-link ke akunnya.
- **Pemilik toko / admin** — mengaktifkan plugin, mengatur bundle per produk, memantau konversi.

## 5. User Stories

### Pembeli (Guest)
1. Sebagai pembeli baru, saya ingin checkout **tanpa harus login atau registrasi** agar bisa menyelesaikan pembelian secepat mungkin.
2. Sebagai pembeli baru, saya ingin form checkout yang **hanya meminta data esensial** (email, nama, no. HP) agar tidak merasa terbebani.
3. Sebagai pembeli baru, setelah checkout saya ingin **menerima email untuk membuat password** agar bisa mengakses riwayat order saya nanti tanpa harus registrasi manual.
4. Sebagai pembeli, saya ingin melihat **ringkasan order di kolom kanan yang selalu terlihat** (sticky) agar yakin apa yang saya bayar.

### Pembeli (Returning)
5. Sebagai pembeli lama, ketika saya checkout dengan email yang sudah terdaftar, saya ingin **order otomatis masuk ke akun saya** dan hanya menerima email pembayaran biasa (tanpa email buat password lagi).

### Pembeli (Bundle)
6. Sebagai pembeli, saya ingin melihat **penawaran bundle yang relevan di halaman checkout** dan bisa menambahkannya dengan satu klik tanpa keluar dari checkout.

### Admin
7. Sebagai admin toko, saya ingin **mengatur bundle offer langsung dari halaman edit produk** (produk apa yang ditawarkan, diskonnya berapa) agar tidak perlu plugin/halaman setting terpisah.
8. Sebagai admin toko, saya ingin bisa **mengaktifkan/menonaktifkan layout baru dengan satu toggle** agar mudah rollback jika ada konflik.

## 6. Requirements

### P0 — Must Have

#### F1. Layout Checkout 2 Kolom (Shopify-style)

Mengganti template checkout WooCommerce dengan layout 2 kolom:

- **Kolom kiri**: form informasi kontak → alamat pengiriman (jika produk fisik) → metode pengiriman → metode pembayaran → tombol Place Order.
- **Kolom kanan**: order summary — daftar item + thumbnail, kolom kupon, subtotal, ongkir, diskon, total. **Sticky** saat scroll di desktop.
- **Mobile**: kolom kanan berubah menjadi accordion "Ringkasan Pesanan" collapsible di atas form (persis pola Shopify mobile).

**Teknis:**
- Override via `woocommerce_locate_template` + template custom di plugin (classic checkout). Deteksi Block Checkout: jika halaman checkout menggunakan block, tampilkan admin notice untuk beralih ke shortcode `[woocommerce_checkout]`, atau sediakan kompatibilitas via CSS/JS layer (lihat Open Questions).
- CSS murni tanpa framework (custom properties, `clamp()` untuk fluid typography), tanpa jQuery dependency baru — gunakan Vanilla JS.
- Tetap menjalankan semua hook standar (`woocommerce_checkout_before_customer_details`, `woocommerce_review_order_before_payment`, dst.) agar plugin lain (mis. pixel tracking, CartFlows) tidak rusak.

**Acceptance Criteria:**
- [ ] Desktop ≥1024px: 2 kolom; kolom kanan sticky.
- [ ] Mobile <768px: summary jadi accordion collapsible, default tertutup, menampilkan total di header accordion.
- [ ] Semua payment gateway aktif tetap tampil dan berfungsi (uji: Stripe, BACS, COD).
- [ ] Kupon bisa diaplikasikan via AJAX tanpa reload halaman.
- [ ] Tidak ada error JS di console; skor Lighthouse checkout tidak turun >5 poin dibanding baseline.

#### F2. Guest Checkout Tanpa Form Login

- Hilangkan form login dan prompt "Returning customer? Click here to login" dari halaman checkout.
- Paksa mode guest checkout aktif (`woocommerce_enable_guest_checkout = yes`) saat plugin aktif, dengan filter agar admin bisa override.
- Field checkout diringkas menjadi: **Email, Nama Lengkap, No. HP** (+ alamat pengiriman hanya jika cart berisi produk fisik yang butuh shipping).
- Field lain (company, address 2, dsb.) dihapus via `woocommerce_checkout_fields` filter.

**Acceptance Criteria:**
- [ ] Tidak ada form/link login yang tampil di checkout, baik untuk guest maupun user yang belum login.
- [ ] User yang **sudah** login tetap bisa checkout normal; field email ter-prefill dan readonly.
- [ ] Cart berisi hanya produk virtual/downloadable → field alamat pengiriman tidak muncul.
- [ ] Validasi No. HP: format numerik, min. 9 digit, mendukung prefix `+62`/`08`.
- [ ] Email dan No. HP tersimpan di order meta standar (`billing_email`, `billing_phone`).

#### F3. Bundle Offer di Halaman Checkout (Setting via Product Meta)

Admin mengatur bundle dari halaman edit produk. Saat produk tersebut ada di cart, penawaran bundle muncul di halaman checkout.

**Sisi Admin — metabox baru di edit produk** (meta data baru, prefix `_wec_`):

| Meta Key | Tipe | Deskripsi |
|---|---|---|
| `_wec_bundle_enabled` | `yes/no` | Toggle bundle untuk produk ini |
| `_wec_bundle_product_ids` | array of int | Produk yang ditawarkan sebagai bundle (maks. 3) |
| `_wec_bundle_discount_type` | `percent/fixed` | Jenis diskon |
| `_wec_bundle_discount_value` | float | Nilai diskon per produk bundle |
| `_wec_bundle_title` | string | Judul penawaran, mis. "Lengkapi paketmu 🔥" |

- UI metabox: product search (select2 bawaan WooCommerce `wc-product-search`), tanpa library tambahan.

**Sisi Checkout:**
- Blok "Bundle Offer" tampil di kolom kanan (di atas order summary) atau kolom kiri sebelum payment (posisi via filter).
- Setiap item bundle: thumbnail, nama, harga coret + harga diskon, tombol/checkbox "+ Tambahkan".
- Klik tambah → AJAX add-to-cart + apply diskon → order summary ter-update tanpa reload.
- Diskon diimplementasikan sebagai **negative fee** atau **dynamic pricing via `woocommerce_before_calculate_totals`** (bukan kupon, agar tidak bentrok dengan kupon user). *(Keputusan final: lihat Open Questions.)*

**Acceptance Criteria:**
- [ ] Bundle hanya tampil jika produk trigger ada di cart dan `_wec_bundle_enabled = yes`.
- [ ] Produk bundle yang sudah ada di cart tidak ditawarkan lagi (dedup).
- [ ] Produk bundle out-of-stock otomatis disembunyikan.
- [ ] Harga diskon terhitung benar di subtotal, total, dan tercatat di order (line item meta menandai item berasal dari bundle: `_wec_is_bundle_item = yes`).
- [ ] Jika produk trigger dihapus dari cart, item bundle-nya kehilangan diskon (kembali harga normal) atau ikut terhapus — perilaku dipilih via setting.
- [ ] Multiple produk trigger di cart → bundle offer digabung, tanpa duplikat.

#### F4. Auto Account Creation + Email Set Password

Flow setelah "Place Order" berhasil:

```
Order dibuat
   │
   ├─ Email SUDAH terdaftar sebagai user?
   │     └─ YA → link order ke user tsb (wc_update_new_customer_past_orders)
   │              → kirim email order/pembayaran standar saja
   │
   └─ TIDAK → buat user baru (role: customer)
              → username = email, password = random (tidak dikirim)
              → link order ke user baru
              → kirim email standar + EMAIL "Buat Password Anda"
                 berisi link set password (WP reset-password flow, expiring link)
```

**Teknis:**
- Hook: `woocommerce_checkout_order_processed` (atau `woocommerce_thankyou` sebagai fallback).
- Pembuatan user: `wc_create_new_customer()` dengan password random.
- Link set password: gunakan `get_password_reset_key()` + URL ke `wp-login.php?action=rp` atau halaman My Account (`lost-password` endpoint) — jangan buat mekanisme token sendiri.
- Email menggunakan template email WooCommerce (`WC_Email` class custom) agar konsisten dengan branding email lain dan bisa di-styling via customizer WooCommerce.
- Pastikan **tidak double-send**: hormati transactional email lain (MailPoet/AutomateWoo tidak men-trigger duplikat).

**Acceptance Criteria:**
- [ ] Email belum terdaftar → user baru dibuat, order ter-assign ke user, email "Buat Password" terkirim dalam <2 menit.
- [ ] Email sudah terdaftar → **tidak ada** email buat password; order ter-link ke akun existing; email order standar tetap terkirim.
- [ ] Link set password expired sesuai default WP (24 jam) dan menampilkan pesan jelas + opsi kirim ulang jika kadaluarsa.
- [ ] User yang dibuat otomatis **tidak** menerima email registrasi bawaan WordPress/WooCommerce (suppress `woocommerce_created_customer_notification` bawaan, ganti dengan email custom).
- [ ] Checkout tidak gagal jika pembuatan user error (mis. race condition) — order tetap tercatat sebagai guest, error di-log ke `WC_Logger`.

### P1 — Nice to Have

1. **Halaman Settings sederhana** (WooCommerce > Settings > tab "Express Checkout"): toggle layout on/off, posisi bundle offer, perilaku hapus item trigger, warna aksen (CSS variable).
2. **Trust badges & catatan keamanan** di bawah tombol Place Order (editable).
3. **Auto-format No. HP** ke format `+62` saat blur (untuk konsistensi data & WhatsApp follow-up).
4. **Analitik ringan bundle**: kolom di laporan order / meta agar admin bisa mengukur berapa order yang mengandung bundle item (query by `_wec_is_bundle_item`).
5. **Kompatibilitas eksplisit Block Checkout** via integrasi Store API + `ExtendSchema` (bukan sekadar fallback shortcode).

### P2 — Future Considerations

1. Bundle rules berbasis **kategori/tag** (bukan hanya per produk).
2. Integrasi **WooCommerce Subscriptions**.
3. **A/B testing** layout lama vs baru bawaan plugin.
4. Post-purchase upsell (one-click upsell setelah pembayaran) — wilayah CartFlows, evaluasi overlap dulu.
5. Login via **magic link / OTP WhatsApp** menggantikan password sepenuhnya.

## 7. UX Notes (Layout Reference)

```
┌────────────────────────────────────────────────────────────┐
│  LOGO TOKO                                                 │
├──────────────────────────────┬─────────────────────────────┤
│  KONTAK                      │  ORDER SUMMARY (sticky)     │
│  ├ Email                     │  ├ [img] Produk A   Rp xxx  │
│  ├ Nama Lengkap              │  ├ [img] Produk B   Rp xxx  │
│  └ No. HP                    │  ├ ─────────────────────    │
│                              │  ├ 🔥 Bundle Offer          │
│  PENGIRIMAN (jika fisik)     │  │   [img] +Produk C  -20%  │
│  ├ Alamat ...                │  ├ ─────────────────────    │
│                              │  ├ Kupon [________][Apply]  │
│  PEMBAYARAN                  │  ├ Subtotal         Rp xxx  │
│  ├ (gateway list)            │  ├ Ongkir           Rp xxx  │
│                              │  └ TOTAL            Rp xxx  │
│  [   PLACE ORDER  →   ]      │                             │
└──────────────────────────────┴─────────────────────────────┘
```

Prinsip: satu kolom form yang mengalir dari atas ke bawah, tanpa distraksi, tanpa link keluar dari checkout (header/footer tema disederhanakan — opsional via setting).

## 8. Technical Considerations

- **Struktur plugin**: OOP ringan, namespace `WEC\`, autoload sederhana; folder `templates/`, `assets/`, `includes/`. Tanpa dependency Composer eksternal di v1.
- **Konflik yang harus diuji sejak awal**: LiteSpeed Cache (jangan cache halaman checkout / fragment AJAX — pelajaran dari kasus CAPI sebelumnya), PixelYourSite/pixel tracking (hook checkout harus tetap jalan agar InitiateCheckout & Purchase event tidak hilang), CartFlows (bentrok override template checkout — deteksi & disable layout jika halaman adalah CartFlows step).
- **HPOS compatible** (declare `custom_order_tables` compatibility).
- **i18n**: text domain `woo-express-checkout`, siap diterjemahkan (default bahasa Indonesia + English).
- **Keamanan**: nonce di semua AJAX endpoint, sanitasi meta input admin, capability check `edit_products` untuk metabox.

## 9. Success Metrics

| Metrik | Tipe | Target | Cara Ukur |
|---|---|---|---|
| Checkout abandonment rate | Leading | -15% dalam 60 hari | Meta Pixel (InitiateCheckout vs Purchase) / GA4 |
| Bundle attach rate | Leading | ≥8% order mengandung item bundle | Query order meta `_wec_is_bundle_item` |
| AOV | Lagging | +10% dalam 90 hari | Laporan WooCommerce Analytics |
| Waktu penyelesaian checkout | Leading | <90 detik (median) | GA4 event timing |
| % guest yang set password dalam 7 hari | Leading | ≥25% | Query user meta / klik link email |
| Tiket support terkait checkout | Lagging | Tidak naik | Log support |

## 10. Open Questions

| # | Pertanyaan | Blocking? | Penjawab |
|---|---|---|---|
| 1 | **Field "Pass" di brief** — apakah maksudnya field Nama? PRD ini mengasumsikan form = Email, Nama, No. HP karena password dibuat via link email. Perlu konfirmasi. | ✅ Ya | Owner |
| 2 | Strategi diskon bundle: **negative fee** vs **modifikasi harga via `woocommerce_before_calculate_totals`**? (Fee lebih simpel; modifikasi harga lebih rapi di invoice & laporan.) | ✅ Ya | Engineering |
| 3 | Dukungan **Block Checkout** di v1: fallback shortcode saja, atau investasi Store API integration? (Rekomendasi: fallback dulu, Store API di P1.) | ✅ Ya | Owner + Engineering |
| 4 | Produk **fisik + butuh alamat**: apakah toko target menjual produk fisik, atau mayoritas digital? Menentukan default field alamat. | ✅ Ya | Owner |
| 5 | Item bundle saat produk trigger dihapus dari cart: **hapus ikut** atau **harga kembali normal**? (PRD menyediakan setting, tapi perlu default.) | ❌ Tidak | Owner |
| 6 | Email "Buat Password": kirim **langsung saat order dibuat** atau **setelah pembayaran sukses** (status Processing)? Rekomendasi: saat order dibuat, agar user bisa cek status ordernya. | ❌ Tidak | Owner |
| 7 | Perlukah rate-limit/anti-abuse pembuatan akun otomatis (bot checkout dengan email acak)? | ❌ Tidak | Engineering |

## 11. Timeline & Phasing (Usulan)

| Fase | Scope | Estimasi |
|---|---|---|
| **Fase 1** | F2 (guest checkout + field minimal) + F4 (auto account + email password) — nilai tercepat, risiko terendah | Minggu 1–2 |
| **Fase 2** | F1 (layout 2 kolom) + QA cross-gateway & cross-theme | Minggu 3–4 |
| **Fase 3** | F3 (bundle offer: metabox admin + frontend AJAX) | Minggu 5–6 |
| **Fase 4** | P1 items (settings page, trust badge, analitik bundle) + dokumentasi | Minggu 7 |

Dependensi: keputusan Open Questions #1–#4 harus final sebelum Fase 1 dimulai.

---

*Dokumen ini living document — update versi setiap ada perubahan scope.*
