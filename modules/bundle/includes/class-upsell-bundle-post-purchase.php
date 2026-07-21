<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Upsell_Bundle_Post_Purchase {
	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		if ( 'yes' !== get_option( 'upsell_bundle_enable_post_purchase', 'yes' ) ) {
			return;
		}

		add_action( 'woocommerce_thankyou', array( $this, 'render_post_purchase_offer' ), 5 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_thankyou_assets' ) );

		add_action( 'wp_ajax_upsell_accept_post_purchase', array( $this, 'ajax_accept_upsell' ) );
		add_action( 'wp_ajax_nopriv_upsell_accept_post_purchase', array( $this, 'ajax_accept_upsell' ) );
	}

	public function enqueue_thankyou_assets() {
		if ( is_order_received_page() ) {
			wp_enqueue_script( 'upsell-bundle-post-purchase-js', UPSELL_BUNDLE_URL . 'assets/js/post-purchase.js', array( 'jquery' ), UPSELL_BUNDLE_VERSION, true );
			wp_localize_script( 'upsell-bundle-post-purchase-js', 'upsellBundlePost', array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'upsell-post-nonce' ),
			) );
		}
	}

	public function render_post_purchase_offer( $order_id ) {
		if ( ! $order_id ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Don't re-offer on refresh or when revisiting the thank-you page.
		$already_offered = $order->get_meta( '_upsell_bundle_post_purchase_offered' );
		if ( 'yes' === $already_offered ) {
			return;
		}

		$upsell_offer = null;
		$found_main_product_id = 0;

		foreach ( $order->get_items() as $item ) {
			$product_id = $item->get_product_id();

			$upsell_enabled = get_post_meta( $product_id, '_upsell_bundle_upsell_enabled', true );
			if ( 'yes' !== $upsell_enabled ) {
				continue;
			}

			$upsells = get_post_meta( $product_id, '_upsell_bundle_upsell_products', true ) ?: array();
			if ( ! empty( $upsells ) && is_array( $upsells ) ) {
				foreach ( $upsells as $upsell ) {
					$upsell_pid = isset( $upsell['product_id'] ) ? absint( $upsell['product_id'] ) : 0;
					if ( ! $upsell_pid ) {
						continue;
					}

					$upsell_product = wc_get_product( $upsell_pid );
					if ( $upsell_product && 'publish' === $upsell_product->get_status() && $upsell_product->is_in_stock() ) {
						$upsell_offer = $upsell;
						$found_main_product_id = $product_id;
						break 2; // First valid offer wins — stop scanning order items too.
					}
				}
			}
		}

		if ( ! $upsell_offer ) {
			return;
		}

		$order->update_meta_data( '_upsell_bundle_post_purchase_offered', 'yes' );
		$order->save();

		$upsell_product = wc_get_product( $upsell_offer['product_id'] );
		if ( ! $upsell_product ) {
			return;
		}

		$upsell_id      = $upsell_offer['product_id'];
		$original_price = floatval( $upsell_product->get_price() );
		$upsell_price   = ( $upsell_offer['price'] !== '' ) ? floatval( $upsell_offer['price'] ) : $original_price;
		$display_title  = ! empty( $upsell_offer['title'] ) ? $upsell_offer['title'] : $upsell_product->get_title();
		$display_desc   = $upsell_offer['description'];
		$image_html     = $upsell_product->get_image( 'medium' );

		?>
		<div class="upsell-bundle-post-purchase-overlay" id="upsell-post-purchase-modal">
			<div class="upsell-bundle-post-purchase-modal-content">
				<div class="upsell-header-banner">
					<h2><?php esc_html_e( 'TUNGGU! ADA PENAWARAN SPESIAL UNTUK ANDA', 'upsell-bundle-woocommerce' ); ?></h2>
					<p><?php esc_html_e( 'Tambahkan produk ini ke pesanan Anda dengan harga diskon khusus!', 'upsell-bundle-woocommerce' ); ?></p>
				</div>

				<div class="upsell-body">
					<div class="upsell-image-box">
						<?php echo $image_html; ?>
					</div>
					<div class="upsell-info-box">
						<h3><?php echo esc_html( $display_title ); ?></h3>
						<div class="upsell-price-box">
							<?php if ( $upsell_price < $original_price ) : ?>
								<span class="upsell-price-original"><?php echo wc_price( $original_price ); ?></span>
							<?php endif; ?>
							<span class="upsell-price-final"><?php echo wc_price( $upsell_price ); ?></span>
						</div>
						<p class="upsell-description"><?php echo esc_html( $display_desc ); ?></p>
					</div>
				</div>

				<div class="upsell-footer-actions">
					<button class="button alt upsell-accept-btn"
							data-parent-order-id="<?php echo esc_attr( $order_id ); ?>"
							data-upsell-product-id="<?php echo esc_attr( $upsell_id ); ?>"
							data-main-id="<?php echo esc_attr( $found_main_product_id ); ?>">
						<?php esc_html_e( 'Ya, Tambahkan ke Pesanan Saya!', 'upsell-bundle-woocommerce' ); ?>
					</button>
					<button class="button upsell-decline-btn" id="upsell-decline-trigger">
						<?php esc_html_e( 'Tidak, terima kasih. Lewati penawaran ini.', 'upsell-bundle-woocommerce' ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Accepting the upsell creates a separate order (not a line item on the
	 * original), so the customer pays for it via its own checkout-payment URL.
	 */
	public function ajax_accept_upsell() {
		check_ajax_referer( 'upsell-post-nonce', 'security' );

		$parent_order_id = isset( $_POST['parent_order_id'] ) ? absint( $_POST['parent_order_id'] ) : 0;
		$upsell_product_id = isset( $_POST['upsell_product_id'] ) ? absint( $_POST['upsell_product_id'] ) : 0;
		$main_id         = isset( $_POST['main_id'] ) ? absint( $_POST['main_id'] ) : 0;

		if ( ! $parent_order_id || ! $upsell_product_id || ! $main_id ) {
			wp_send_json_error( array( 'message' => 'Invalid parameters' ) );
		}

		$parent_order = wc_get_order( $parent_order_id );
		if ( ! $parent_order ) {
			wp_send_json_error( array( 'message' => 'Parent order not found' ) );
		}

		$upsell_product = wc_get_product( $upsell_product_id );
		if ( ! $upsell_product || 'publish' !== $upsell_product->get_status() || ! $upsell_product->is_in_stock() ) {
			wp_send_json_error( array( 'message' => 'Upsell product out of stock' ) );
		}

		$upsells = get_post_meta( $main_id, '_upsell_bundle_upsell_products', true ) ?: array();
		$upsell_price_configured = '';
		foreach ( $upsells as $u ) {
			if ( $u['product_id'] == $upsell_product_id ) {
				$upsell_price_configured = ( $u['price'] !== '' ) ? floatval( $u['price'] ) : '';
				break;
			}
		}

		try {
			$new_order = wc_create_order();

			$item_id = $new_order->add_product( $upsell_product, 1 );
			if ( ! $item_id ) {
				throw new Exception( 'Failed to add product to order' );
			}

			if ( $upsell_price_configured !== '' ) {
				foreach ( $new_order->get_items() as $item ) {
					if ( $item->get_product_id() == $upsell_product_id ) {
						$item->set_subtotal( $upsell_price_configured );
						$item->set_total( $upsell_price_configured );
					}
				}
			}

			// Copy checkout info from the parent order.
			$new_order->set_billing_first_name( $parent_order->get_billing_first_name() );
			$new_order->set_billing_last_name( $parent_order->get_billing_last_name() );
			$new_order->set_billing_company( $parent_order->get_billing_company() );
			$new_order->set_billing_address_1( $parent_order->get_billing_address_1() );
			$new_order->set_billing_address_2( $parent_order->get_billing_address_2() );
			$new_order->set_billing_city( $parent_order->get_billing_city() );
			$new_order->set_billing_state( $parent_order->get_billing_state() );
			$new_order->set_billing_postcode( $parent_order->get_billing_postcode() );
			$new_order->set_billing_country( $parent_order->get_billing_country() );
			$new_order->set_billing_email( $parent_order->get_billing_email() );
			$new_order->set_billing_phone( $parent_order->get_billing_phone() );

			$new_order->set_shipping_first_name( $parent_order->get_shipping_first_name() );
			$new_order->set_shipping_last_name( $parent_order->get_shipping_last_name() );
			$new_order->set_shipping_company( $parent_order->get_shipping_company() );
			$new_order->set_shipping_address_1( $parent_order->get_shipping_address_1() );
			$new_order->set_shipping_address_2( $parent_order->get_shipping_address_2() );
			$new_order->set_shipping_city( $parent_order->get_shipping_city() );
			$new_order->set_shipping_state( $parent_order->get_shipping_state() );
			$new_order->set_shipping_postcode( $parent_order->get_shipping_postcode() );
			$new_order->set_shipping_country( $parent_order->get_shipping_country() );

			$new_order->update_meta_data( '_upsell_parent_order_id', $parent_order_id );

			$new_order->calculate_totals();
			$new_order->save();

			$pay_url = $new_order->get_checkout_payment_url();

			wp_send_json_success( array( 'pay_url' => $pay_url ) );

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}
}
