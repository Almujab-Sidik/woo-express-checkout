<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Upsell_Bundle_Order_Bump {
	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		if ( 'yes' !== get_option( 'upsell_bundle_enable_bump', 'yes' ) ) {
			return;
		}

		add_action( 'woocommerce_review_order_before_submit', array( $this, 'render_order_bumps' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_checkout_assets' ) );

		add_action( 'wp_ajax_upsell_toggle_bump', array( $this, 'ajax_toggle_bump' ) );
		add_action( 'wp_ajax_nopriv_upsell_toggle_bump', array( $this, 'ajax_toggle_bump' ) );

		add_action( 'woocommerce_before_calculate_totals', array( $this, 'apply_bump_prices' ), 10, 1 );
		add_action( 'woocommerce_cart_item_removed', array( $this, 'remove_bump_on_main_removed' ), 10, 2 );
	}

	public function enqueue_checkout_assets() {
		if ( is_checkout() && ! is_order_received_page() ) {
			wp_enqueue_script( 'upsell-bundle-order-bump-js', UPSELL_BUNDLE_URL . 'assets/js/order-bump.js', array( 'jquery' ), UPSELL_BUNDLE_VERSION, true );
			wp_localize_script( 'upsell-bundle-order-bump-js', 'upsellBundleBump', array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'upsell-bump-nonce' ),
			) );
		}
	}

	private function is_bump_in_cart( $bump_product_id, $main_product_id ) {
		if ( ! WC()->cart ) {
			return false;
		}

		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			if ( $cart_item['product_id'] == $bump_product_id &&
				 isset( $cart_item['is_order_bump'] ) &&
				 $cart_item['bump_main_product'] == $main_product_id ) {
				return true;
			}
		}

		return false;
	}

	private function get_bump_discount_percent( $main_product_id, $bump_product_id ) {
		$bumps = get_post_meta( $main_product_id, '_upsell_bundle_bumps', true );

		if ( ! is_array( $bumps ) ) {
			return 0;
		}

		foreach ( $bumps as $bump ) {
			if ( isset( $bump['product_id'] ) && absint( $bump['product_id'] ) === $bump_product_id ) {
				$discount = isset( $bump['discount_percent'] ) ? (float) $bump['discount_percent'] : 0;
				return min( 100, max( 0, $discount ) );
			}
		}

		return 0;
	}

	public function render_order_bumps() {
		if ( ! WC()->cart ) {
			return;
		}

		$cart = WC()->cart->get_cart();
		$bumps_to_render = array();
		$rendered_bump_pids = array();

		foreach ( $cart as $cart_item_key => $cart_item ) {
			$product_id = $cart_item['product_id'];

			if ( isset( $cart_item['is_order_bump'] ) ) {
				continue;
			}

			$bump_enabled = get_post_meta( $product_id, '_upsell_bundle_bump_enabled', true );
			if ( 'yes' !== $bump_enabled ) {
				continue;
			}

			$bumps = get_post_meta( $product_id, '_upsell_bundle_bumps', true );
			if ( ! empty( $bumps ) && is_array( $bumps ) ) {
				foreach ( $bumps as $bump ) {
					$bump_pid = isset( $bump['product_id'] ) ? absint( $bump['product_id'] ) : 0;
					if ( ! $bump_pid ) {
						continue;
					}

					// Validate each configured product before rendering the offer group.
					$bump_product = wc_get_product( $bump_pid );
					if ( ! $bump_product || 'publish' !== $bump_product->get_status() || ! $bump_product->is_in_stock() ) {
						continue;
					}

					// Keep the offer visible even when the product is already present in
					// the cart, for example as part of another bundle.

					// Deduplicate by bump product so the same product is not offered
					// multiple times when triggered by different cart items.
					if ( in_array( $bump_pid, $rendered_bump_pids, true ) ) {
						continue;
					}
					$rendered_bump_pids[] = $bump_pid;

					$bumps_to_render[] = array(
						'main_product_id' => $product_id,
						'bump_product_id' => $bump_pid,
						'title'           => $bump['title'],
						'description'     => $bump['description'],
						'price'           => $bump['price'],
						'discount_percent' => isset( $bump['discount_percent'] ) ? (float) $bump['discount_percent'] : 0,
					);
				}
			}
		}

		if ( empty( $bumps_to_render ) ) {
			return;
		}

		echo '<div class="upsell-bundle-checkout-bumps">';
		echo '<h4>' . esc_html__( 'Special Offer', 'upsell-bundle-woocommerce' ) . '</h4>';

		foreach ( $bumps_to_render as $bump ) {
			$bump_product = wc_get_product( $bump['bump_product_id'] );
			if ( ! $bump_product || 'publish' !== $bump_product->get_status() || ! $bump_product->is_in_stock() ) {
				continue;
			}

			$main_id = $bump['main_product_id'];
			$bump_id = $bump['bump_product_id'];

			$base_price       = (float) $bump_product->get_price();
			$original_price   = (float) $bump_product->get_regular_price();
			$original_price   = $original_price > 0 ? max( $original_price, $base_price ) : $base_price;
			$discount_percent = min( 100, max( 0, (float) $bump['discount_percent'] ) );
			$bump_price       = $base_price * ( 1 - ( $discount_percent / 100 ) );
			$display_title  = ! empty( $bump['title'] ) ? $bump['title'] : $bump_product->get_title();

			$checked = $this->is_bump_in_cart( $bump_id, $main_id );
			$image   = $bump_product->get_image( 'thumbnail' );

			?>
			<div class="upsell-bundle-bump-card <?php echo $checked ? 'bump-checked' : ''; ?>">
				<div class="bump-checkbox-wrapper">
					<input type="checkbox"
						   class="upsell-bump-checkbox"
						   id="upsell-bump-<?php echo esc_attr( $main_id . '-' . $bump_id ); ?>"
						   data-main-id="<?php echo esc_attr( $main_id ); ?>"
						   data-bump-id="<?php echo esc_attr( $bump_id ); ?>"
						   <?php checked( $checked ); ?> />
				</div>
				<div class="bump-image">
					<?php echo $image; ?>
				</div>
				<div class="bump-details">
					<label for="upsell-bump-<?php echo esc_attr( $main_id . '-' . $bump_id ); ?>" class="bump-title">
						<?php echo esc_html( $display_title ); ?>
					</label>
					<p class="bump-desc"><?php echo esc_html( $bump['description'] ); ?></p>
				</div>
				<div class="bump-pricing">
					<?php if ( $bump_price < $original_price ) : ?>
						<span class="original-price"><?php echo wc_price( $original_price ); ?></span>
					<?php endif; ?>
					<span class="bump-price-val"><?php echo wc_price( $bump_price ); ?></span>
				</div>
			</div>
			<?php
		}
		echo '</div>';
	}

	public function ajax_toggle_bump() {
		check_ajax_referer( 'upsell-bump-nonce', 'security' );

		$main_id = isset( $_POST['main_id'] ) ? absint( $_POST['main_id'] ) : 0;
		$bump_id = isset( $_POST['bump_id'] ) ? absint( $_POST['bump_id'] ) : 0;
		$checked = isset( $_POST['checked'] ) ? ( $_POST['checked'] === 'true' ) : false;

		if ( ! $main_id || ! $bump_id || ! WC()->cart ) {
			wp_send_json_error( array( 'message' => 'Invalid parameters' ) );
		}

		if ( $checked ) {
			$bump_product = wc_get_product( $bump_id );
			if ( ! $bump_product || 'publish' !== $bump_product->get_status() || ! $bump_product->is_in_stock() ) {
				wp_send_json_error( array( 'message' => 'The selected product is unavailable.' ) );
			}

			$discount_percent = $this->get_bump_discount_percent( $main_id, $bump_id );
			$bump_price       = (float) $bump_product->get_price() * ( 1 - ( $discount_percent / 100 ) );

			$cart_item_data = array(
				'is_order_bump'       => true,
				'bump_main_product'   => $main_id,
				'upsell_bump_price'   => $bump_price,
				'upsell_bump_discount' => $discount_percent,
			);

			WC()->cart->add_to_cart( $bump_id, 1, 0, array(), $cart_item_data );
		} else {
			foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
				if ( $cart_item['product_id'] == $bump_id &&
					 isset( $cart_item['is_order_bump'] ) &&
					 $cart_item['bump_main_product'] == $main_id ) {
					WC()->cart->remove_cart_item( $cart_item_key );
					break;
				}
			}
		}

		WC()->cart->calculate_totals();
		wp_send_json_success();
	}

	public function apply_bump_prices( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			if ( isset( $cart_item['is_order_bump'] ) && isset( $cart_item['upsell_bump_price'] ) ) {
				$price = floatval( $cart_item['upsell_bump_price'] );
				$cart_item['data']->set_price( $price );
			}
		}
	}

	/**
	 * If the main product is removed from cart, remove its order bumps too.
	 */
	public function remove_bump_on_main_removed( $removed_cart_item_key, $cart ) {
		$removed_item = isset( $cart->removed_cart_contents[ $removed_cart_item_key ] ) ? $cart->removed_cart_contents[ $removed_cart_item_key ] : null;
		if ( ! $removed_item ) {
			return;
		}

		$removed_product_id = $removed_item['product_id'];

		// Product may still be in the cart in another line (e.g. different qty).
		$main_product_still_in_cart = false;
		foreach ( $cart->get_cart() as $key => $item ) {
			if ( $item['product_id'] == $removed_product_id && ! isset( $item['is_order_bump'] ) ) {
				$main_product_still_in_cart = true;
				break;
			}
		}

		if ( ! $main_product_still_in_cart ) {
			foreach ( $cart->get_cart() as $key => $item ) {
				if ( isset( $item['is_order_bump'] ) && $item['bump_main_product'] == $removed_product_id ) {
					$cart->remove_cart_item( $key );
				}
			}
		}
	}
}
