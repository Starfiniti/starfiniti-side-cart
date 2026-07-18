<?php
/**
 * Batch 2 integration-test fixture. This file is not included in release ZIPs.
 */

add_filter(
	'woocommerce_add_cart_item_data',
	static function ( array $cart_item_data, int $product_id ): array {
		if ( (int) get_option( 'sfcart_test_simple_product_id' ) === $product_id ) {
			$cart_item_data['sfcart_test_engraving'] = 'Starfiniti';
		}

		return $cart_item_data;
	},
	10,
	2
);

add_filter(
	'woocommerce_get_item_data',
	static function ( array $item_data, array $cart_item ): array {
		if ( isset( $cart_item['sfcart_test_engraving'] ) ) {
			$item_data[] = array(
				'key'     => 'Engraving',
				'value'   => (string) $cart_item['sfcart_test_engraving'],
				'display' => (string) $cart_item['sfcart_test_engraving'],
			);
		}

		return $item_data;
	},
	10,
	2
);

add_shortcode(
	'sfcart_test_menu',
	static function (): string {
		$menu = wp_nav_menu(
			array(
				'container'   => 'nav',
				'echo'        => false,
				'fallback_cb' => false,
				'menu'        => 'sfcart-test',
			)
		);

		return is_string( $menu ) ? $menu : '';
	}
);

add_action(
	'rest_api_init',
	static function (): void {
		register_rest_route(
			'sfcart-test/v1',
			'/attribution',
			array(
				'methods'             => 'POST',
				'permission_callback' => static fn (): bool => current_user_can( 'manage_woocommerce' ),
				'callback'            => static function (): WP_REST_Response|WP_Error {
					if ( ! WC()->cart instanceof WC_Cart ) {
						wc_load_cart();
					}

					$product_id        = (int) get_option( 'sfcart_test_recommendation_product_id' );
					$source_product_id = (int) get_option( 'sfcart_test_simple_product_id' );
					WC()->cart->empty_cart();
					$cart_item_key = WC()->cart->add_to_cart(
						$product_id,
						2,
						0,
						array(),
						array(
							\Starfiniti\Cart\Recommendations\Attribution::CART_ITEM_KEY => array(
								'product_id'        => $product_id,
								'source'            => 'upsell',
								'source_product_id' => $source_product_id,
							),
						)
					);
					if ( ! is_string( $cart_item_key ) ) {
						return new WP_Error( 'sfcart_test_add_failed', 'Test cart item could not be created.', array( 'status' => 500 ) );
					}

					$order_id = WC()->checkout()->create_order( array() );
					if ( is_wp_error( $order_id ) ) {
						return $order_id;
					}

					$order = wc_get_order( $order_id );
					if ( ! $order instanceof WC_Order ) {
						return new WP_Error( 'sfcart_test_order_failed', 'Test order could not be loaded.', array( 'status' => 500 ) );
					}

					$order->payment_complete();
					\Starfiniti\Cart\Recommendations\Attribution::record_revenue( $order->get_id() );
					\Starfiniti\Cart\Recommendations\Attribution::record_revenue( $order->get_id() );
					\Starfiniti\Cart\Analytics\Recorder::record_order( $order );
					\Starfiniti\Cart\Analytics\Recorder::record_order( $order );
					$item = current( $order->get_items( 'line_item' ) );
					if ( ! $item instanceof WC_Order_Item_Product ) {
						return new WP_Error( 'sfcart_test_item_failed', 'Test order item could not be loaded.', array( 'status' => 500 ) );
					}

					$refund = wc_create_refund(
						array(
							'amount'        => 9,
							'order_id'      => $order->get_id(),
							'reason'        => 'Starfiniti Cart attribution test',
							'restock_items' => false,
							'line_items'    => array(
								$item->get_id() => array(
									'qty'          => -1,
									'refund_total' => -9,
									'refund_tax'   => array(),
								),
							),
						)
					);
					if ( is_wp_error( $refund ) || ! $refund instanceof WC_Order_Refund ) {
						return is_wp_error( $refund ) ? $refund : new WP_Error( 'sfcart_test_refund_failed', 'Test refund failed.', array( 'status' => 500 ) );
					}

					\Starfiniti\Cart\Recommendations\Attribution::record_refund( $refund->get_id() );
					\Starfiniti\Cart\Analytics\Recorder::record_refund( $refund->get_id() );
					\Starfiniti\Cart\Analytics\Recorder::record_refund( $refund->get_id() );
					$order  = wc_get_order( $order->get_id() );
					$refund = wc_get_order( $refund->get_id() );

					return new WP_REST_Response(
						array(
							'item_recommendation' => $item->get_meta( '_sfcart_recommendation', true ),
							'item_source'         => $item->get_meta( '_sfcart_recommendation_source', true ),
							'impression_count'    => $order instanceof WC_Order ? count( (array) $order->get_meta( '_sfcart_recommendation_impressions', true ) ) : 0,
							'revenue'             => $order instanceof WC_Order ? $order->get_meta( '_sfcart_recommendation_revenue', true ) : '',
							'refunded'            => $order instanceof WC_Order ? $order->get_meta( '_sfcart_recommendation_refunded', true ) : '',
							'refund_attributed'   => $refund instanceof WC_Order_Refund ? $refund->get_meta( '_sfcart_recommendation_attributed', true ) : '',
						)
					);
				},
			)
		);

		register_rest_route(
			'sfcart-test/v1',
			'/rewards',
			array(
				'methods'             => 'POST',
				'permission_callback' => static fn (): bool => current_user_can( 'manage_woocommerce' ),
				'callback'            => static function ( WP_REST_Request $request ): WP_REST_Response|WP_Error {
					if ( ! WC()->cart instanceof WC_Cart ) {
						wc_load_cart();
					}
					WC()->cart->calculate_totals();
					$packages = WC()->shipping()->calculate_shipping( WC()->cart->get_shipping_packages() );
					$rates    = array();
					foreach ( $packages as $package ) {
						foreach ( (array) ( $package['rates'] ?? array() ) as $rate ) {
							if ( $rate instanceof WC_Shipping_Rate ) {
								$rates[] = $rate->get_id();
							}
						}
					}

					$order_meta     = array();
					$order_item_meta = array();
					if ( $request->get_param( 'create_order' ) ) {
						$order = wc_create_order();
						if ( is_wp_error( $order ) || ! $order instanceof WC_Order ) {
							return is_wp_error( $order ) ? $order : new WP_Error( 'sfcart_test_order_failed', 'Test order could not be created.', array( 'status' => 500 ) );
						}
						foreach ( WC()->cart->get_cart() as $cart_item_key => $values ) {
							$product = $values['data'] ?? null;
							if ( ! $product instanceof WC_Product ) {
								continue;
							}
							$item = new WC_Order_Item_Product();
							$item->set_product( $product );
							$item->set_quantity( (int) ( $values['quantity'] ?? 1 ) );
							do_action( 'woocommerce_checkout_create_order_line_item', $item, $cart_item_key, $values, $order );
							$order->add_item( $item );
						}
						do_action( 'woocommerce_checkout_create_order', $order, array() );
						$order->save();
						$order_meta = (array) $order->get_meta( '_sfcart_rewards_achieved', true );
						foreach ( $order->get_items( 'line_item' ) as $item ) {
							if ( 'yes' === $item->get_meta( '_sfcart_reward_gift', true ) ) {
								$order_item_meta[] = array(
									'gift'         => $item->get_meta( '_sfcart_reward_gift', true ),
									'milestone_id' => $item->get_meta( '_sfcart_reward_milestone_id', true ),
									'type'         => $item->get_meta( '_sfcart_reward_type', true ),
								);
							}
						}
					}

					$gift_count = count(
						array_filter(
							WC()->cart->get_cart(),
							array( \Starfiniti\Cart\Rewards\RewardEngine::class, 'is_gift' )
						)
					);

					return new WP_REST_Response(
						array(
							'applied_coupons' => WC()->cart->get_applied_coupons(),
							'gift_count'      => $gift_count,
							'order_item_meta' => $order_item_meta,
							'order_meta'      => $order_meta,
							'rates'           => array_values( array_unique( $rates ) ),
							'rewards'         => \Starfiniti\Cart\Rewards\RewardEngine::snapshot( WC()->cart ),
						)
					);
				},
			)
		);

		register_rest_route(
			'sfcart-test/v1',
			'/special-addon',
			array(
				'methods'             => 'POST',
				'permission_callback' => static fn (): bool => current_user_can( 'manage_woocommerce' ),
				'callback'            => static function (): WP_REST_Response|WP_Error {
					if ( ! WC()->cart instanceof WC_Cart ) {
						wc_load_cart();
					}
					$order = wc_create_order();
					if ( is_wp_error( $order ) || ! $order instanceof WC_Order ) {
						return is_wp_error( $order ) ? $order : new WP_Error( 'sfcart_test_order_failed', 'Test order could not be created.', array( 'status' => 500 ) );
					}

					$metadata = array();
					foreach ( WC()->cart->get_cart() as $cart_item_key => $values ) {
						$product = $values['data'] ?? null;
						if ( ! $product instanceof WC_Product ) {
							continue;
						}
						$item = new WC_Order_Item_Product();
						$item->set_product( $product );
						$item->set_quantity( (int) ( $values['quantity'] ?? 1 ) );
						do_action( 'woocommerce_checkout_create_order_line_item', $item, $cart_item_key, $values, $order );
						$order->add_item( $item );
						if ( 'yes' === $item->get_meta( '_sfcart_special_addon', true ) ) {
							$metadata[] = array(
								'addon'      => $item->get_meta( '_sfcart_special_addon', true ),
								'product_id' => (int) $item->get_meta( '_sfcart_special_addon_product_id', true ),
							);
						}
					}
					$order->save();

					return new WP_REST_Response(
						array(
							'addon_count' => count(
								array_filter(
									WC()->cart->get_cart(),
									array( \Starfiniti\Cart\AddOn\SpecialAddOn::class, 'is_addon' )
								)
							),
							'metadata'    => $metadata,
							'snapshot'    => \Starfiniti\Cart\AddOn\SpecialAddOn::snapshot( WC()->cart ),
						)
					);
				},
			)
		);
	}
);
