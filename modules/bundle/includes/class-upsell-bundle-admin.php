<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Upsell_Bundle_Admin {
	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_product_data_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'render_product_data_panel' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_meta' ) );
	}

	public function add_admin_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Upsell & Bundle Settings', 'upsell-bundle-woocommerce' ),
			__( 'Upsell & Bundle', 'upsell-bundle-woocommerce' ),
			'manage_options',
			'upsell-bundle-settings',
			array( $this, 'render_settings_page' )
		);
	}

	public function register_settings() {
		register_setting( 'upsell_bundle_settings_group', 'upsell_bundle_enable_bump' );
		register_setting( 'upsell_bundle_settings_group', 'upsell_bundle_enable_bundle' );
		register_setting( 'upsell_bundle_settings_group', 'upsell_bundle_enable_post_purchase' );
		register_setting( 'upsell_bundle_settings_group', 'upsell_bundle_auto_inject_bundle' );

		if ( false === get_option( 'upsell_bundle_enable_bump' ) ) {
			update_option( 'upsell_bundle_enable_bump', 'yes' );
		}
		if ( false === get_option( 'upsell_bundle_enable_bundle' ) ) {
			update_option( 'upsell_bundle_enable_bundle', 'yes' );
		}
		if ( false === get_option( 'upsell_bundle_enable_post_purchase' ) ) {
			update_option( 'upsell_bundle_enable_post_purchase', 'yes' );
		}
		if ( false === get_option( 'upsell_bundle_auto_inject_bundle' ) ) {
			update_option( 'upsell_bundle_auto_inject_bundle', 'yes' );
		}
	}

	public function enqueue_admin_assets( $hook ) {
		// Also loads on the unified woo-express-checkout settings page, since
		// this panel is embedded there instead of its own submenu.
		if ( 'post.php' === $hook || 'post-new.php' === $hook || 'woocommerce_page_upsell-bundle-settings' === $hook || 'woocommerce_page_wec-express-checkout' === $hook ) {
			if ( 'post.php' === $hook || 'post-new.php' === $hook ) {
				wp_enqueue_script( 'wc-enhanced-select' );
			}

			// Use filemtime() for the version so CSS/JS edits are picked up
			// immediately (the plugin version string is static during dev, so
			// browsers would otherwise serve a stale cached stylesheet).
			$css_path = UPSELL_BUNDLE_PATH . 'assets/css/admin.css';
			$js_path  = UPSELL_BUNDLE_PATH . 'assets/js/admin.js';
			$css_ver  = file_exists( $css_path ) ? filemtime( $css_path ) : UPSELL_BUNDLE_VERSION;
			$js_ver   = file_exists( $js_path ) ? filemtime( $js_path ) : UPSELL_BUNDLE_VERSION;

			// Inter font — matches the shadcn-style component design.
			wp_enqueue_style( 'upsell-bundle-inter', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap', array(), null );

			wp_enqueue_style( 'upsell-bundle-admin-css', UPSELL_BUNDLE_URL . 'assets/css/admin.css', array( 'upsell-bundle-inter' ), $css_ver );
			wp_enqueue_script( 'upsell-bundle-admin-js', UPSELL_BUNDLE_URL . 'assets/js/admin.js', array( 'jquery' ), $js_ver, true );

			wp_localize_script( 'upsell-bundle-admin-js', 'upsellBundleAdmin', array(
				'currency_symbol' => get_woocommerce_currency_symbol(),
			) );
		}
	}

	public function render_settings_page() {
		?>
		<div class="wrap upsell-bundle-settings-wrap">
			<h1><?php esc_html_e( 'Upsell & Bundle WooCommerce Settings', 'upsell-bundle-woocommerce' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Manage global toggles and display preferences for your upsell features.', 'upsell-bundle-woocommerce' ); ?></p>
			
			<form method="post" action="options.php" class="upsell-bundle-settings-form">
				<?php settings_fields( 'upsell_bundle_settings_group' ); ?>
				<?php do_settings_sections( 'upsell_bundle_settings_group' ); ?>
				
				<div class="card settings-card">
					<h2><?php esc_html_e( 'Feature Toggles', 'upsell-bundle-woocommerce' ); ?></h2>
					<table class="form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'Enable Order Bump', 'upsell-bundle-woocommerce' ); ?></th>
							<td>
								<label class="switch">
									<input type="checkbox" name="upsell_bundle_enable_bump" value="yes" <?php checked( get_option( 'upsell_bundle_enable_bump' ), 'yes' ); ?> />
									<span class="slider round"></span>
								</label>
								<p class="description"><?php esc_html_e( 'Display order bumps on the checkout page.', 'upsell-bundle-woocommerce' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Enable Product Bundle', 'upsell-bundle-woocommerce' ); ?></th>
							<td>
								<label class="switch">
									<input type="checkbox" name="upsell_bundle_enable_bundle" value="yes" <?php checked( get_option( 'upsell_bundle_enable_bundle' ), 'yes' ); ?> />
									<span class="slider round"></span>
								</label>
								<p class="description"><?php esc_html_e( 'Display product bundles on single product pages.', 'upsell-bundle-woocommerce' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Enable Post-Purchase Upsell', 'upsell-bundle-woocommerce' ); ?></th>
							<td>
								<label class="switch">
									<input type="checkbox" name="upsell_bundle_enable_post_purchase" value="yes" <?php checked( get_option( 'upsell_bundle_enable_post_purchase' ), 'yes' ); ?> />
									<span class="slider round"></span>
								</label>
								<p class="description"><?php esc_html_e( 'Display upsell offers on the checkout thank-you page.', 'upsell-bundle-woocommerce' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<div class="card settings-card">
					<h2><?php esc_html_e( 'Display Customization', 'upsell-bundle-woocommerce' ); ?></h2>
					<table class="form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'Auto-Inject Bundle', 'upsell-bundle-woocommerce' ); ?></th>
							<td>
								<label class="switch">
									<input type="checkbox" name="upsell_bundle_auto_inject_bundle" value="yes" <?php checked( get_option( 'upsell_bundle_auto_inject_bundle' ), 'yes' ); ?> />
									<span class="slider round"></span>
								</label>
								<p class="description"><?php esc_html_e( 'Automatically inject the bundle display below the product price/add-to-cart form on single product pages. If disabled, you must use the [upsell_bundle] shortcode.', 'upsell-bundle-woocommerce' ); ?></p>
							</td>
						</tr>
					</table>
				</div>
				
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	public function add_product_data_tab( $tabs ) {
		$tabs['upsell_bundle_tab'] = array(
			'label'    => __( 'Upsell & Bundle', 'upsell-bundle-woocommerce' ),
			'target'   => 'upsell_bundle_product_data',
			'class'    => array( 'show_if_simple', 'show_if_variable' ),
			'priority' => 75,
		);
		return $tabs;
	}

	public function render_product_data_panel() {
		global $post;

		$bump_enabled          = get_post_meta( $post->ID, '_upsell_bundle_bump_enabled', true ) ?: 'no';
		$bumps                 = get_post_meta( $post->ID, '_upsell_bundle_bumps', true ) ?: array();
		$bundle_enabled        = get_post_meta( $post->ID, '_upsell_bundle_bundle_enabled', true ) ?: 'no';
		$bundle_product_ids    = get_post_meta( $post->ID, '_upsell_bundle_bundle_products', true ) ?: array();
		$bundle_checkout_upgrade = get_post_meta( $post->ID, '_upsell_bundle_bundle_checkout_upgrade', true ) ?: 'no';
		$bundle_checkout_title   = get_post_meta( $post->ID, '_upsell_bundle_bundle_checkout_title', true ) ?: '';
		$bundle_checkout_desc    = get_post_meta( $post->ID, '_upsell_bundle_bundle_checkout_desc', true ) ?: '';
		$upsell_enabled        = get_post_meta( $post->ID, '_upsell_bundle_upsell_enabled', true ) ?: 'no';
		$upsells               = get_post_meta( $post->ID, '_upsell_bundle_upsell_products', true ) ?: array();

		?>
		<div id="upsell_bundle_product_data" class="panel woocommerce_options_panel hidden">
			<div class="upsell-bundle-tab-header">
				<h2><?php esc_html_e( 'Configure Upsells & Bundles', 'upsell-bundle-woocommerce' ); ?></h2>
				<p><?php esc_html_e( 'Define extra offers, package bundles, and post-checkout upsells for this product.', 'upsell-bundle-woocommerce' ); ?></p>
			</div>

			<!-- 1. ORDER BUMP SUBSECTION -->
			<div class="upsell-bundle-section">
				<div class="upsell-bundle-section-header">
					<h3><?php esc_html_e( '1. Order Bump (Halaman Checkout)', 'upsell-bundle-woocommerce' ); ?></h3>
					<span class="section-toggle">
						<label class="switch switch-sm">
							<input type="checkbox" name="_upsell_bundle_bump_enabled" value="yes" <?php checked( $bump_enabled, 'yes' ); ?> class="section-enable-toggle" />
							<span class="slider round"></span>
						</label>
					</span>
				</div>
				<div class="upsell-bundle-section-content <?php echo ( 'yes' === $bump_enabled ) ? '' : 'disabled-content'; ?>">
					<table class="upsell-bundle-repeatable-table" id="order-bump-table">
						<thead>
							<tr>
								<th style="width: 32%;"><?php esc_html_e( 'Pilih Produk Bump', 'upsell-bundle-woocommerce' ); ?></th>
								<th style="width: 28%;"><?php esc_html_e( 'Override Judul (Opsional)', 'upsell-bundle-woocommerce' ); ?></th>
								<th style="width: 34%;"><?php esc_html_e( 'Deskripsi Penawaran', 'upsell-bundle-woocommerce' ); ?></th>
								<th style="width: 6%; text-align: center;"></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $bumps as $index => $bump ) : 
								$product_id  = isset( $bump['product_id'] ) ? absint( $bump['product_id'] ) : 0;
								$title       = isset( $bump['title'] ) ? esc_attr( $bump['title'] ) : '';
								$description = isset( $bump['description'] ) ? esc_textarea( $bump['description'] ) : '';
								?>
								<tr class="repeatable-row">
									<td>
										<select class="wc-product-search" name="upsell_bump_product_id[]" data-placeholder="<?php esc_attr_e( 'Cari produk...', 'woocommerce' ); ?>" data-action="woocommerce_json_search_products_and_variations" style="width: 100%;">
											<?php if ( $product_id ) : 
												$prod = wc_get_product( $product_id );
												if ( $prod && 'publish' === $prod->get_status() ) : ?>
													<option value="<?php echo esc_attr( $product_id ); ?>" selected><?php echo esc_html( $prod->get_formatted_name() ); ?></option>
												<?php endif;
											endif; ?>
										</select>
									</td>
									<td>
										<input type="text" name="upsell_bump_title[]" value="<?php echo $title; ?>" placeholder="<?php esc_attr_e( 'Nama produk default', 'upsell-bundle-woocommerce' ); ?>" style="width: 100%;" />
									</td>
									<td>
										<textarea name="upsell_bump_description[]" rows="2" placeholder="<?php esc_attr_e( 'Dapatkan produk tambahan ini seharga...', 'upsell-bundle-woocommerce' ); ?>" style="width: 100%; height: 50px;"><?php echo $description; ?></textarea>
									</td>
									<td style="text-align: center; vertical-align: middle;">
										<a href="#" class="button remove-row-btn">&times;</a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<button type="button" class="button button-primary add-row-btn" data-target="order-bump-table"><?php esc_html_e( 'Tambah Order Bump', 'upsell-bundle-woocommerce' ); ?></button>
				</div>
			</div>

			<hr />

			<!-- 2. BUNDLE SUBSECTION -->
			<div class="upsell-bundle-section">
				<div class="upsell-bundle-section-header">
					<h3><?php esc_html_e( '2. Bundle Produk (Halaman Produk & Upgrade Checkout)', 'upsell-bundle-woocommerce' ); ?></h3>
					<span class="section-toggle">
						<label class="switch switch-sm">
							<input type="checkbox" name="_upsell_bundle_bundle_enabled" value="yes" <?php checked( $bundle_enabled, 'yes' ); ?> class="section-enable-toggle" />
							<span class="slider round"></span>
						</label>
					</span>
				</div>
				<div class="upsell-bundle-section-content <?php echo ( 'yes' === $bundle_enabled ) ? '' : 'disabled-content'; ?>">
					<div class="bundle-settings-group">
						<p class="form-field">
							<label><?php esc_html_e( 'Produk Tambahan dalam Bundle', 'upsell-bundle-woocommerce' ); ?></label>
							<select class="wc-product-search upsell-bundle-input-select" multiple="multiple" name="_upsell_bundle_bundle_products[]" data-placeholder="<?php esc_attr_e( 'Cari produk lain untuk digabungkan...', 'woocommerce' ); ?>" data-action="woocommerce_json_search_products_and_variations">
								<?php
								foreach ( $bundle_product_ids as $prod_id ) {
									$prod = wc_get_product( $prod_id );
									// Only show products that still exist and are published — a
									// trashed/deleted product shouldn't linger as a saved selection.
									if ( $prod && 'publish' === $prod->get_status() ) {
										echo '<option value="' . esc_attr( $prod_id ) . '" selected="selected">' . esc_html( $prod->get_formatted_name() ) . '</option>';
									}
								}
								?>
							</select>
							<span class="description"><?php esc_html_e( 'Pilih satu atau lebih produk lain untuk digabungkan dengan produk utama ini.', 'upsell-bundle-woocommerce' ); ?></span>
						</p>

						<p class="form-field upsell-bundle-checkbox-field">
							<label for="_upsell_bundle_bundle_checkout_upgrade">
								<input type="checkbox" id="_upsell_bundle_bundle_checkout_upgrade" name="_upsell_bundle_bundle_checkout_upgrade" value="yes" <?php checked( $bundle_checkout_upgrade, 'yes' ); ?> />
								<?php esc_html_e( 'Tampilkan upgrade bundle ini di halaman checkout', 'upsell-bundle-woocommerce' ); ?>
							</label>
							<span class="description"><?php esc_html_e( 'Tawarkan pembeli untuk meng-upgrade produk ini ke Paket Bundle di halaman checkout.', 'upsell-bundle-woocommerce' ); ?></span>
						</p>

						<div class="bundle-checkout-fields" style="<?php echo ( 'yes' === $bundle_checkout_upgrade ) ? '' : 'display:none;'; ?>">
							<p class="form-field">
								<label for="_upsell_bundle_bundle_checkout_title"><?php esc_html_e( 'Judul Penawaran Checkout', 'upsell-bundle-woocommerce' ); ?></label>
								<input type="text" class="upsell-bundle-input-text" id="_upsell_bundle_bundle_checkout_title" name="_upsell_bundle_bundle_checkout_title" value="<?php echo esc_attr( $bundle_checkout_title ); ?>" placeholder="<?php esc_attr_e( 'Contoh: Upgrade ke Paket Hemat!', 'upsell-bundle-woocommerce' ); ?>" />
							</p>

							<p class="form-field">
								<label for="_upsell_bundle_bundle_checkout_desc"><?php esc_html_e( 'Deskripsi Penawaran Checkout', 'upsell-bundle-woocommerce' ); ?></label>
								<textarea id="_upsell_bundle_bundle_checkout_desc" class="upsell-bundle-input-text" name="_upsell_bundle_bundle_checkout_desc" rows="2" placeholder="<?php esc_attr_e( 'Contoh: Dapatkan produk tambahan B dan C hanya dengan menambah...', 'upsell-bundle-woocommerce' ); ?>"><?php echo esc_textarea( $bundle_checkout_desc ); ?></textarea>
							</p>
						</div>

						<div class="shortcode-hint">
							<p>
								<strong>Shortcode Bundle:</strong>
								<code>[upsell_bundle]</code> atau <code>[upsell_bundle id="<?php echo $post->ID; ?>"]</code>
							</p>
						</div>
					</div>
				</div>
			</div>

			<hr />

			<!-- 3. POST-PURCHASE UPSELL SUBSECTION -->
			<div class="upsell-bundle-section">
				<div class="upsell-bundle-section-header">
					<h3><?php esc_html_e( '3. Post-Purchase Upsell (Halaman Thank-you)', 'upsell-bundle-woocommerce' ); ?></h3>
					<span class="section-toggle">
						<label class="switch switch-sm">
							<input type="checkbox" name="_upsell_bundle_upsell_enabled" value="yes" <?php checked( $upsell_enabled, 'yes' ); ?> class="section-enable-toggle" />
							<span class="slider round"></span>
						</label>
					</span>
				</div>
				<div class="upsell-bundle-section-content <?php echo ( 'yes' === $upsell_enabled ) ? '' : 'disabled-content'; ?>">
					<table class="upsell-bundle-repeatable-table" id="post-purchase-table">
						<thead>
							<tr>
								<th style="width: 30%;"><?php esc_html_e( 'Pilih Produk Upsell', 'upsell-bundle-woocommerce' ); ?></th>
								<th style="width: 25%;"><?php esc_html_e( 'Override Judul (Opsional)', 'upsell-bundle-woocommerce' ); ?></th>
								<th style="width: 30%;"><?php esc_html_e( 'Deskripsi Penawaran', 'upsell-bundle-woocommerce' ); ?></th>
								<th style="width: 10%;"><?php esc_html_e( 'Harga Upsell', 'upsell-bundle-woocommerce' ); ?></th>
								<th style="width: 5%; text-align: center;"></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $upsells as $index => $upsell ) : 
								$product_id  = isset( $upsell['product_id'] ) ? absint( $upsell['product_id'] ) : 0;
								$title       = isset( $upsell['title'] ) ? esc_attr( $upsell['title'] ) : '';
								$description = isset( $upsell['description'] ) ? esc_textarea( $upsell['description'] ) : '';
								$price       = isset( $upsell['price'] ) ? esc_attr( $upsell['price'] ) : '';
								?>
								<tr class="repeatable-row">
									<td>
										<select class="wc-product-search" name="upsell_post_product_id[]" data-placeholder="<?php esc_attr_e( 'Cari produk...', 'woocommerce' ); ?>" data-action="woocommerce_json_search_products_and_variations" style="width: 100%;">
											<?php if ( $product_id ) : 
												$prod = wc_get_product( $product_id );
												if ( $prod && 'publish' === $prod->get_status() ) : ?>
													<option value="<?php echo esc_attr( $product_id ); ?>" selected><?php echo esc_html( $prod->get_formatted_name() ); ?></option>
												<?php endif;
											endif; ?>
										</select>
									</td>
									<td>
										<input type="text" name="upsell_post_title[]" value="<?php echo $title; ?>" placeholder="<?php esc_attr_e( 'Nama produk default', 'upsell-bundle-woocommerce' ); ?>" style="width: 100%;" />
									</td>
									<td>
										<textarea name="upsell_post_description[]" rows="2" placeholder="<?php esc_attr_e( 'Tawaran istimewa setelah checkout...', 'upsell-bundle-woocommerce' ); ?>" style="width: 100%; height: 50px;"><?php echo $description; ?></textarea>
									</td>
									<td>
										<input type="number" step="any" min="0" name="upsell_post_price[]" value="<?php echo $price; ?>" placeholder="<?php esc_attr_e( 'Harga diskon', 'upsell-bundle-woocommerce' ); ?>" style="width: 100%;" />
									</td>
									<td style="text-align: center; vertical-align: middle;">
										<a href="#" class="button remove-row-btn">&times;</a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<button type="button" class="button button-primary add-row-btn" data-target="post-purchase-table"><?php esc_html_e( 'Tambah Post-Purchase Upsell', 'upsell-bundle-woocommerce' ); ?></button>
				</div>
			</div>
		</div>

		<!-- Hidden template rows for repeatable content in JS -->
		<table style="display:none;">
			<tr id="order-bump-template-row">
				<td>
					<select class="wc-product-search" name="upsell_bump_product_id[]" data-placeholder="<?php esc_attr_e( 'Cari produk...', 'woocommerce' ); ?>" data-action="woocommerce_json_search_products_and_variations" style="width: 100%;">
					</select>
				</td>
				<td>
					<input type="text" name="upsell_bump_title[]" placeholder="<?php esc_attr_e( 'Nama produk default', 'upsell-bundle-woocommerce' ); ?>" style="width: 100%;" />
				</td>
				<td>
					<textarea name="upsell_bump_description[]" rows="2" placeholder="<?php esc_attr_e( 'Dapatkan produk tambahan ini seharga...', 'upsell-bundle-woocommerce' ); ?>" style="width: 100%; height: 50px;"></textarea>
				</td>
				<td style="text-align: center; vertical-align: middle;">
					<a href="#" class="button remove-row-btn">&times;</a>
				</td>
			</tr>
			<tr id="post-purchase-template-row">
				<td>
					<select class="wc-product-search" name="upsell_post_product_id[]" data-placeholder="<?php esc_attr_e( 'Cari produk...', 'woocommerce' ); ?>" data-action="woocommerce_json_search_products_and_variations" style="width: 100%;">
					</select>
				</td>
				<td>
					<input type="text" name="upsell_post_title[]" placeholder="<?php esc_attr_e( 'Nama produk default', 'upsell-bundle-woocommerce' ); ?>" style="width: 100%;" />
				</td>
				<td>
					<textarea name="upsell_post_description[]" rows="2" placeholder="<?php esc_attr_e( 'Tawaran istimewa setelah checkout...', 'upsell-bundle-woocommerce' ); ?>" style="width: 100%; height: 50px;"></textarea>
				</td>
				<td>
					<input type="number" step="any" min="0" name="upsell_post_price[]" placeholder="<?php esc_attr_e( 'Harga diskon', 'upsell-bundle-woocommerce' ); ?>" style="width: 100%;" />
				</td>
				<td style="text-align: center; vertical-align: middle;">
					<a href="#" class="button remove-row-btn">&times;</a>
				</td>
			</tr>
		</table>
		<?php
	}

	public function save_product_meta( $post_id ) {
		// 1. Order Bump fields
		$bump_enabled = isset( $_POST['_upsell_bundle_bump_enabled'] ) ? 'yes' : 'no';
		update_post_meta( $post_id, '_upsell_bundle_bump_enabled', $bump_enabled );

		$bumps = array();
		if ( isset( $_POST['upsell_bump_product_id'] ) && is_array( $_POST['upsell_bump_product_id'] ) ) {
			$product_ids  = $_POST['upsell_bump_product_id'];
			$titles       = isset( $_POST['upsell_bump_title'] ) ? $_POST['upsell_bump_title'] : array();
			$descriptions = isset( $_POST['upsell_bump_description'] ) ? $_POST['upsell_bump_description'] : array();

			for ( $i = 0; $i < count( $product_ids ); $i++ ) {
				$pid = absint( $product_ids[ $i ] );
				if ( $pid > 0 ) {
					$bumps[] = array(
						'product_id'  => $pid,
						'title'       => sanitize_text_field( $titles[ $i ] ),
						'description' => sanitize_textarea_field( $descriptions[ $i ] ),
						'price'       => '', // order bump has no separate price; sold at product's own price
					);
				}
			}
		}
		update_post_meta( $post_id, '_upsell_bundle_bumps', $bumps );

		// 2. Bundle fields
		$bundle_enabled = isset( $_POST['_upsell_bundle_bundle_enabled'] ) ? 'yes' : 'no';
		update_post_meta( $post_id, '_upsell_bundle_bundle_enabled', $bundle_enabled );

		$bundle_product_ids = array();
		if ( isset( $_POST['_upsell_bundle_bundle_products'] ) && is_array( $_POST['_upsell_bundle_bundle_products'] ) ) {
			$bundle_product_ids = array_map( 'absint', $_POST['_upsell_bundle_bundle_products'] );
			// A product can't be its own bundle add-on — exclude self-references
			// that would otherwise duplicate the main item in the bundle.
			$bundle_product_ids = array_values( array_diff( $bundle_product_ids, array( $post_id ) ) );
		}
		update_post_meta( $post_id, '_upsell_bundle_bundle_products', $bundle_product_ids );

		$bundle_checkout_upgrade = isset( $_POST['_upsell_bundle_bundle_checkout_upgrade'] ) ? 'yes' : 'no';
		update_post_meta( $post_id, '_upsell_bundle_bundle_checkout_upgrade', $bundle_checkout_upgrade );

		$bundle_checkout_title = isset( $_POST['_upsell_bundle_bundle_checkout_title'] ) ? sanitize_text_field( $_POST['_upsell_bundle_bundle_checkout_title'] ) : '';
		update_post_meta( $post_id, '_upsell_bundle_bundle_checkout_title', $bundle_checkout_title );

		$bundle_checkout_desc = isset( $_POST['_upsell_bundle_bundle_checkout_desc'] ) ? sanitize_textarea_field( $_POST['_upsell_bundle_bundle_checkout_desc'] ) : '';
		update_post_meta( $post_id, '_upsell_bundle_bundle_checkout_desc', $bundle_checkout_desc );

		// 3. Post-Purchase fields
		$upsell_enabled = isset( $_POST['_upsell_bundle_upsell_enabled'] ) ? 'yes' : 'no';
		update_post_meta( $post_id, '_upsell_bundle_upsell_enabled', $upsell_enabled );

		$upsells = array();
		if ( isset( $_POST['upsell_post_product_id'] ) && is_array( $_POST['upsell_post_product_id'] ) ) {
			$product_ids  = $_POST['upsell_post_product_id'];
			$titles       = isset( $_POST['upsell_post_title'] ) ? $_POST['upsell_post_title'] : array();
			$descriptions = isset( $_POST['upsell_post_description'] ) ? $_POST['upsell_post_description'] : array();
			$prices       = isset( $_POST['upsell_post_price'] ) ? $_POST['upsell_post_price'] : array();

			for ( $i = 0; $i < count( $product_ids ); $i++ ) {
				$pid = absint( $product_ids[ $i ] );
				if ( $pid > 0 ) {
					$upsells[] = array(
						'product_id'  => $pid,
						'title'       => sanitize_text_field( $titles[ $i ] ),
						'description' => sanitize_textarea_field( $descriptions[ $i ] ),
						'price'       => ( $prices[ $i ] !== '' ) ? max( 0, floatval( $prices[ $i ] ) ) : '',
					);
				}
			}
		}
		update_post_meta( $post_id, '_upsell_bundle_upsell_products', $upsells );
	}
}
