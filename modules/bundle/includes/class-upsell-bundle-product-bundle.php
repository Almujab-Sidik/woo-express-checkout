<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Upsell_Bundle_Product_Bundle {
	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		if ( 'yes' !== get_option( 'upsell_bundle_enable_bundle', 'yes' ) ) {
			return;
		}

		add_shortcode( 'upsell_bundle', array( $this, 'render_bundle_shortcode' ) );

		if ( 'yes' === get_option( 'upsell_bundle_auto_inject_bundle', 'yes' ) ) {
			add_action( 'woocommerce_before_add_to_cart_form', array( $this, 'auto_inject_bundle' ), 20 );
		}

		add_action( 'woocommerce_review_order_before_submit', array( $this, 'render_checkout_bundle_upgrades' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_bundle_assets' ) );

		add_action( 'wp_ajax_upsell_add_bundle_to_cart', array( $this, 'ajax_add_bundle_to_cart' ) );
		add_action( 'wp_ajax_nopriv_upsell_add_bundle_to_cart', array( $this, 'ajax_add_bundle_to_cart' ) );
		add_action( 'wp_ajax_upsell_toggle_checkout_bundle', array( $this, 'ajax_toggle_checkout_bundle' ) );
		add_action( 'wp_ajax_nopriv_upsell_toggle_checkout_bundle', array( $this, 'ajax_toggle_checkout_bundle' ) );

		add_action( 'woocommerce_before_calculate_totals', array( $this, 'apply_bundle_prices' ), 15, 1 );
		add_action( 'woocommerce_cart_item_removed', array( $this, 'remove_linked_bundle_items' ), 15, 2 );
		add_action( 'woocommerce_after_cart_item_quantity_update', array( $this, 'sync_bundle_quantities' ), 15, 4 );
	}

	public function enqueue_bundle_assets() {
		if ( is_product() || ( is_checkout() && ! is_order_received_page() ) ) {
			wp_enqueue_script( 'upsell-bundle-product-bundle-js', UPSELL_BUNDLE_URL . 'assets/js/bundle.js', array( 'jquery' ), UPSELL_BUNDLE_VERSION, true );
			wp_localize_script( 'upsell-bundle-product-bundle-js', 'upsellBundleData', array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'upsell-bundle-nonce' ),
				'cart_url' => wc_get_cart_url(),
			) );
		}
	}

	public function render_bundle_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'id' => '',
		), $atts, 'upsell_bundle' );

		$product_id = ! empty( $atts['id'] ) ? absint( $atts['id'] ) : get_the_ID();
		if ( ! $product_id ) {
			return '';
		}

		ob_start();
		$this->render_bundle_ui( $product_id );
		return ob_get_clean();
	}

	public function auto_inject_bundle() {
		$product_id = get_the_ID();
		if ( $product_id ) {
			// The shortcode may already have rendered this product's bundle box
			// on the same page — don't render it twice.
			if ( ! isset( $GLOBALS['upsell_bundle_rendered_' . $product_id] ) ) {
				$this->render_bundle_ui( $product_id );
			}
		}
	}

	private function render_bundle_ui( $product_id ) {
		$GLOBALS['upsell_bundle_rendered_' . $product_id] = true;

		$enabled = get_post_meta( $product_id, '_upsell_bundle_bundle_enabled', true );
		if ( 'yes' !== $enabled ) {
			return;
		}

		$bundle_products = get_post_meta( $product_id, '_upsell_bundle_bundle_products', true ) ?: array();

		if ( empty( $bundle_products ) ) {
			return;
		}

		$main_product = wc_get_product( $product_id );
		if ( ! $main_product || 'publish' !== $main_product->get_status() ) {
			return;
		}

		// The box shows only the additional products as cards — the main
		// product is already shown on this page. But the paket price and the
		// add-to-cart button DO include the main product: clicking "add
		// bundle" adds the main product plus the additional products.
		// (Trashed products still report in_stock=true, so status is checked.)
		$all_items = array();
		$is_any_out_of_stock = ! $main_product->is_in_stock();

		foreach ( $bundle_products as $b_id ) {
			$b_prod = wc_get_product( $b_id );
			if ( ! $b_prod || 'publish' !== $b_prod->get_status() ) {
				$is_any_out_of_stock = true;
				continue;
			}
			$all_items[] = $b_prod;
			if ( ! $b_prod->is_in_stock() ) {
				$is_any_out_of_stock = true;
			}
		}

		if ( empty( $all_items ) ) {
			return;
		}

		// Sum of main + additional products — no separate discount.
		$total_original_price = floatval( $main_product->get_price() );
		foreach ( $all_items as $item ) {
			$total_original_price += floatval( $item->get_price() );
		}
		$bundle_price = $total_original_price;

		?>
		<div class="upsell-bundle-box">
			<h3><?php esc_html_e( 'Beli Hemat Bersama (Paket Bundle)', 'upsell-bundle-woocommerce' ); ?></h3>
			<p class="bundle-subtitle"><?php esc_html_e( 'Dapatkan harga spesial dengan membeli produk-produk ini sekaligus!', 'upsell-bundle-woocommerce' ); ?></p>

			<div class="bundle-items-list">
				<?php
				$count = count( $all_items );
				foreach ( $all_items as $index => $item ) :
					$img_html = $item->get_image( 'thumbnail' );
					?>
					<div class="bundle-item-card">
						<div class="bundle-item-image">
							<?php echo $img_html; ?>
						</div>
						<div class="bundle-item-info">
							<span class="bundle-item-title"><?php echo esc_html( $item->get_name() ); ?></span>
							<span class="bundle-item-price"><?php echo wc_price( $item->get_price() ); ?></span>
						</div>
					</div>
					<?php if ( $index < $count - 1 ) : ?>
						<div class="bundle-plus-icon">+</div>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>

			<div class="bundle-footer-summary">
				<div class="bundle-pricing-summary">
					<span class="bundle-price-label"><?php esc_html_e( 'Harga Paket Bundle:', 'upsell-bundle-woocommerce' ); ?></span>
					<div class="bundle-price-box">
						<?php if ( $total_original_price > $bundle_price ) : ?>
							<span class="bundle-original-strike"><?php echo wc_price( $total_original_price ); ?></span>
						<?php endif; ?>
						<span class="bundle-final-price"><?php echo wc_price( $bundle_price ); ?></span>
					</div>
				</div>

				<?php if ( $is_any_out_of_stock ) : ?>
					<div class="bundle-stock-notice out-of-stock">
						<?php esc_html_e( 'Stok tidak tersedia pada salah satu produk paket ini.', 'upsell-bundle-woocommerce' ); ?>
					</div>
					<button class="button alt upsell-add-bundle-btn" disabled>
						<?php esc_html_e( 'Stok Habis', 'upsell-bundle-woocommerce' ); ?>
					</button>
				<?php else : ?>
					<button class="button alt upsell-add-bundle-btn"
							data-main-id="<?php echo esc_attr( $product_id ); ?>">
						<?php esc_html_e( 'Tambah Paket Bundle ke Keranjang', 'upsell-bundle-woocommerce' ); ?>
					</button>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	public function ajax_add_bundle_to_cart() {
		check_ajax_referer( 'upsell-bundle-nonce', 'security' );

		$main_id = isset( $_POST['main_id'] ) ? absint( $_POST['main_id'] ) : 0;
		if ( ! $main_id || ! WC()->cart ) {
			wp_send_json_error( array( 'message' => 'Invalid parameters' ) );
		}

		$enabled = get_post_meta( $main_id, '_upsell_bundle_bundle_enabled', true );
		if ( 'yes' !== $enabled ) {
			wp_send_json_error( array( 'message' => 'Bundle not enabled' ) );
		}

		$bundle_products = get_post_meta( $main_id, '_upsell_bundle_bundle_products', true ) ?: array();

		// Reject: without this, a request could add just the main product at
		// the "bundle" price without any additional products.
		if ( empty( $bundle_products ) ) {
			wp_send_json_error( array( 'message' => 'Invalid bundle configuration' ) );
		}

		$product_ids = array_merge( array( $main_id ), $bundle_products );
		$products = array();
		$total_original_price = 0;

		foreach ( $product_ids as $pid ) {
			$prod = wc_get_product( $pid );
			if ( ! $prod || 'publish' !== $prod->get_status() || ! $prod->is_in_stock() ) {
				wp_send_json_error( array( 'message' => 'Salah satu produk tidak tersedia atau habis stok.' ) );
			}
			$price = floatval( $prod->get_price() );
			$products[] = array(
				'id'             => $pid,
				'original_price' => $price,
			);
			$total_original_price += $price;
		}

		// Sum of the products' own prices — no separate discount.
		$bundle_price = $total_original_price;

		$discount_ratio = ( $total_original_price > 0 ) ? ( $bundle_price / $total_original_price ) : 1;
		$bundle_group_id = uniqid( 'bundle_' );

		$discounted_prices = array();
		$sum_discounted = 0;
		$item_count = count( $products );

		for ( $i = 0; $i < $item_count; $i++ ) {
			if ( $i === $item_count - 1 ) {
				// Last item gets the remainder to avoid rounding issues.
				$discounted_price = $bundle_price - $sum_discounted;
			} else {
				$discounted_price = round( $products[ $i ]['original_price'] * $discount_ratio, 2 );
				$sum_discounted += $discounted_price;
			}
			$discounted_prices[ $products[ $i ]['id'] ] = $discounted_price;
		}

		foreach ( $products as $p ) {
			$cart_item_data = array(
				'bundle_group_id'         => $bundle_group_id,
				'bundle_main_product_id'  => $main_id,
				'bundle_original_price'   => $p['original_price'],
				'bundle_discounted_price' => $discounted_prices[ $p['id'] ],
			);

			WC()->cart->add_to_cart( $p['id'], 1, 0, array(), $cart_item_data );
		}

		WC()->cart->calculate_totals();
		wp_send_json_success();
	}

	public function apply_bundle_prices( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			if ( isset( $cart_item['bundle_group_id'] ) && isset( $cart_item['bundle_discounted_price'] ) ) {
				$price = floatval( $cart_item['bundle_discounted_price'] );
				$cart_item['data']->set_price( $price );
			}
		}
	}

	/**
	 * If one bundle item is removed, remove all other items in that bundle group.
	 */
	public function remove_linked_bundle_items( $removed_cart_item_key, $cart ) {
		$removed_item = isset( $cart->removed_cart_contents[ $removed_cart_item_key ] ) ? $cart->removed_cart_contents[ $removed_cart_item_key ] : null;
		if ( ! $removed_item || ! isset( $removed_item['bundle_group_id'] ) ) {
			return;
		}

		$bundle_group_id = $removed_item['bundle_group_id'];

		// remove_cart_item() below fires this same hook again — unhook first
		// to avoid infinite recursion.
		remove_action( 'woocommerce_cart_item_removed', array( $this, 'remove_linked_bundle_items' ), 15 );

		foreach ( $cart->get_cart() as $key => $item ) {
			if ( isset( $item['bundle_group_id'] ) && $item['bundle_group_id'] === $bundle_group_id ) {
				$cart->remove_cart_item( $key );
			}
		}

		add_action( 'woocommerce_cart_item_removed', array( $this, 'remove_linked_bundle_items' ), 15, 2 );
	}

	/**
	 * Keep quantities of all products in the same bundle group in sync.
	 */
	public function sync_bundle_quantities( $cart_item_key, $quantity, $old_quantity, $cart ) {
		$cart_item = isset( $cart->get_cart()[ $cart_item_key ] ) ? $cart->get_cart()[ $cart_item_key ] : null;
		if ( ! $cart_item || ! isset( $cart_item['bundle_group_id'] ) ) {
			return;
		}

		$bundle_group_id = $cart_item['bundle_group_id'];

		// set_quantity() below fires this same hook again — unhook first to
		// avoid infinite recursion.
		remove_action( 'woocommerce_after_cart_item_quantity_update', array( $this, 'sync_bundle_quantities' ), 15 );

		foreach ( $cart->get_cart() as $key => $item ) {
			if ( $key !== $cart_item_key && isset( $item['bundle_group_id'] ) && $item['bundle_group_id'] === $bundle_group_id ) {
				$cart->set_quantity( $key, $quantity, false );
			}
		}

		add_action( 'woocommerce_after_cart_item_quantity_update', array( $this, 'sync_bundle_quantities' ), 15, 4 );
	}

	public function render_checkout_bundle_upgrades() {
		if ( ! WC()->cart ) {
			return;
		}

		$cart = WC()->cart->get_cart();
		$rendered_main_ids = array();

		foreach ( $cart as $cart_item_key => $cart_item ) {
			$product_id = $cart_item['product_id'];

			if ( in_array( $product_id, $rendered_main_ids ) ) {
				continue;
			}

			// A bundle sub-item's cart row belongs to the main product, not itself.
			if ( isset( $cart_item['bundle_group_id'] ) ) {
				$product_id = $cart_item['bundle_main_product_id'];
				if ( in_array( $product_id, $rendered_main_ids ) ) {
					continue;
				}
			}

			$enabled          = get_post_meta( $product_id, '_upsell_bundle_bundle_enabled', true );
			$checkout_upgrade = get_post_meta( $product_id, '_upsell_bundle_bundle_checkout_upgrade', true );

			if ( 'yes' !== $enabled || 'yes' !== $checkout_upgrade ) {
				continue;
			}

			$bundle_products = get_post_meta( $product_id, '_upsell_bundle_bundle_products', true ) ?: array();

			if ( empty( $bundle_products ) ) {
				continue;
			}

			// Trashed products still report in_stock=true, so status is checked too.
			$is_any_out_of_stock = false;
			$main_product = wc_get_product( $product_id );
			if ( ! $main_product || 'publish' !== $main_product->get_status() || ! $main_product->is_in_stock() ) {
				$is_any_out_of_stock = true;
			}
			foreach ( $bundle_products as $b_id ) {
				$b_prod = wc_get_product( $b_id );
				if ( ! $b_prod || 'publish' !== $b_prod->get_status() || ! $b_prod->is_in_stock() ) {
					$is_any_out_of_stock = true;
				}
			}

			if ( $is_any_out_of_stock ) {
				continue;
			}

			$rendered_main_ids[] = $product_id;

			$checked = false;
			foreach ( $cart as $item ) {
				if ( isset( $item['bundle_group_id'] ) && $item['bundle_main_product_id'] == $product_id ) {
					$checked = true;
					break;
				}
			}

			// Sum of main + additional products — no separate discount.
			$total_original_price = floatval( $main_product->get_price() );
			foreach ( $bundle_products as $b_id ) {
				$b_prod = wc_get_product( $b_id );
				if ( $b_prod ) {
					$total_original_price += floatval( $b_prod->get_price() );
				}
			}
			$bundle_price = $total_original_price;

			$display_title = get_post_meta( $product_id, '_upsell_bundle_bundle_checkout_title', true );
			if ( empty( $display_title ) ) {
				$display_title = sprintf( __( 'Upgrade ke Paket Bundle %s', 'upsell-bundle-woocommerce' ), $main_product->get_title() );
			}
			$display_desc = get_post_meta( $product_id, '_upsell_bundle_bundle_checkout_desc', true );
			if ( empty( $display_desc ) ) {
				$display_desc = __( 'Dapatkan penawaran bundle hemat untuk pesanan Anda!', 'upsell-bundle-woocommerce' );
			}

			$img_product = ! empty( $bundle_products ) ? wc_get_product( $bundle_products[0] ) : $main_product;
			$image_html  = $img_product ? $img_product->get_image( 'thumbnail' ) : '';

			?>
			<div class="upsell-bundle-checkout-bumps upsell-bundle-checkout-upgrade-box">
				<div class="upsell-bundle-bump-card <?php echo $checked ? 'bump-checked' : ''; ?>">
					<div class="bump-checkbox-wrapper">
						<input type="checkbox"
							   class="upsell-checkout-bundle-checkbox"
							   id="upsell-checkout-bundle-<?php echo esc_attr( $product_id ); ?>"
							   data-main-id="<?php echo esc_attr( $product_id ); ?>"
							   <?php checked( $checked ); ?> />
					</div>
					<div class="bump-image">
						<?php echo $image_html; ?>
					</div>
					<div class="bump-details">
						<label for="upsell-checkout-bundle-<?php echo esc_attr( $product_id ); ?>" class="bump-title">
							<?php echo esc_html( $display_title ); ?>
						</label>
						<p class="bump-desc"><?php echo esc_html( $display_desc ); ?></p>
					</div>
					<div class="bump-pricing">
						<?php if ( $total_original_price > $bundle_price ) : ?>
							<span class="original-price"><?php echo wc_price( $total_original_price ); ?></span>
						<?php endif; ?>
						<span class="bump-price-val"><?php echo wc_price( $bundle_price ); ?></span>
					</div>
				</div>
			</div>
			<?php
		}
	}

	public function ajax_toggle_checkout_bundle() {
		check_ajax_referer( 'upsell-bundle-nonce', 'security' );

		$main_id = isset( $_POST['main_id'] ) ? absint( $_POST['main_id'] ) : 0;
		$checked = isset( $_POST['checked'] ) ? ( $_POST['checked'] === 'true' ) : false;

		if ( ! $main_id || ! WC()->cart ) {
			wp_send_json_error( array( 'message' => 'Invalid parameters' ) );
		}

		$cart = WC()->cart->get_cart();

		if ( $checked ) {
			// UPGRADE TO BUNDLE

			$main_cart_item_key = '';
			foreach ( $cart as $key => $item ) {
				if ( $item['product_id'] == $main_id && ! isset( $item['bundle_group_id'] ) ) {
					$main_cart_item_key = $key;
					break;
				}
			}

			if ( ! $main_cart_item_key ) {
				wp_send_json_error( array( 'message' => 'Produk utama tidak ditemukan di keranjang.' ) );
			}

			$bundle_products = get_post_meta( $main_id, '_upsell_bundle_bundle_products', true ) ?: array();

			// Reject: without this, a request could upgrade to the "bundle"
			// price while only the main product is in the cart.
			if ( empty( $bundle_products ) ) {
				wp_send_json_error( array( 'message' => 'Konfigurasi bundle tidak valid.' ) );
			}

			$bundle_group_id = uniqid( 'bundle_' );

			$product_ids = array_merge( array( $main_id ), $bundle_products );
			$products = array();
			$total_original_price = 0;

			foreach ( $product_ids as $pid ) {
				$prod = wc_get_product( $pid );
				if ( ! $prod || 'publish' !== $prod->get_status() || ! $prod->is_in_stock() ) {
					wp_send_json_error( array( 'message' => 'Salah satu produk bundle habis stok.' ) );
				}
				$price = floatval( $prod->get_price() );
				$products[] = array(
					'id'             => $pid,
					'original_price' => $price,
				);
				$total_original_price += $price;
			}

			// Sum of the products' own prices — no separate discount.
			$bundle_price = $total_original_price;

			$discount_ratio = ( $total_original_price > 0 ) ? ( $bundle_price / $total_original_price ) : 1;
			$discounted_prices = array();
			$sum_discounted = 0;
			$item_count = count( $products );

			for ( $i = 0; $i < $item_count; $i++ ) {
				if ( $i === $item_count - 1 ) {
					$discounted_price = $bundle_price - $sum_discounted;
				} else {
					$discounted_price = round( $products[ $i ]['original_price'] * $discount_ratio, 2 );
					$sum_discounted += $discounted_price;
				}
				$discounted_prices[ $products[ $i ]['id'] ] = $discounted_price;
			}

			// 1. Update the existing main product cart item with bundle meta.
			WC()->cart->cart_contents[ $main_cart_item_key ]['bundle_group_id']         = $bundle_group_id;
			WC()->cart->cart_contents[ $main_cart_item_key ]['bundle_main_product_id']  = $main_id;
			WC()->cart->cart_contents[ $main_cart_item_key ]['bundle_original_price']   = floatval( wc_get_product( $main_id )->get_price() );
			WC()->cart->cart_contents[ $main_cart_item_key ]['bundle_discounted_price'] = $discounted_prices[ $main_id ];

			// 2. Add the additional items to the cart.
			foreach ( $bundle_products as $b_id ) {
				$cart_item_data = array(
					'bundle_group_id'         => $bundle_group_id,
					'bundle_main_product_id'  => $main_id,
					'bundle_original_price'   => floatval( wc_get_product( $b_id )->get_price() ),
					'bundle_discounted_price' => $discounted_prices[ $b_id ],
				);
				WC()->cart->add_to_cart( $b_id, 1, 0, array(), $cart_item_data );
			}

		} else {
			// DOWNGRADE FROM BUNDLE

			$bundle_group_id = '';
			$main_cart_item_key = '';
			foreach ( $cart as $key => $item ) {
				if ( $item['product_id'] == $main_id && isset( $item['bundle_group_id'] ) && $item['bundle_main_product_id'] == $main_id ) {
					$bundle_group_id = $item['bundle_group_id'];
					$main_cart_item_key = $key;
					break;
				}
			}

			if ( ! $bundle_group_id ) {
				wp_send_json_error( array( 'message' => 'Grup bundle tidak ditemukan di keranjang.' ) );
			}

			// 1. Remove the bundle meta from the main product and restore its regular price in memory.
			unset( WC()->cart->cart_contents[ $main_cart_item_key ]['bundle_group_id'] );
			unset( WC()->cart->cart_contents[ $main_cart_item_key ]['bundle_main_product_id'] );
			unset( WC()->cart->cart_contents[ $main_cart_item_key ]['bundle_original_price'] );
			unset( WC()->cart->cart_contents[ $main_cart_item_key ]['bundle_discounted_price'] );

			$orig_product = wc_get_product( $main_id );
			if ( $orig_product ) {
				WC()->cart->cart_contents[ $main_cart_item_key ]['data']->set_price( $orig_product->get_price() );
			}

			// 2. Remove all other items belonging to this bundle group.
			foreach ( $cart as $key => $item ) {
				if ( $key !== $main_cart_item_key && isset( $item['bundle_group_id'] ) && $item['bundle_group_id'] === $bundle_group_id ) {
					WC()->cart->remove_cart_item( $key );
				}
			}
		}

		WC()->cart->calculate_totals();
		wp_send_json_success();
	}
}
