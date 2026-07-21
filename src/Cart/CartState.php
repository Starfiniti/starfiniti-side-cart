<?php
/**
 * Side-cart response state.
 *
 * @package StarfinitiCart
 */

namespace Starfiniti\Cart\Cart;

use RuntimeException;
use Starfiniti\Cart\AddOn\SpecialAddOn;
use Starfiniti\Cart\Recommendations\RecommendationEngine;
use Starfiniti\Cart\Rewards\RewardEngine;
use WC_Cart;
use WC_Product;

/**
 * Serializes the current WooCommerce session cart for the owned frontend.
 */
final class CartState {

	/**
	 * Build the complete drawer state.
	 *
	 * @param list<array{type: string, message: string}> $notices Response notices.
	 * @return array<string, mixed>
	 * @throws RuntimeException When the WooCommerce cart is unavailable.
	 */
	public static function snapshot( array $notices = array() ): array {
		$cart = WC()->cart;

		if ( ! $cart instanceof WC_Cart ) {
			throw new RuntimeException( esc_html__( 'The WooCommerce cart is unavailable.', 'starfiniti-cart' ) );
		}

		SpecialAddOn::reconcile( $cart );
		$cart->calculate_totals();

		return array(
			'cart_hash'       => $cart->get_cart_hash(),
			'item_count'      => $cart->get_cart_contents_count(),
			'is_empty'        => $cart->is_empty(),
			'items'           => self::items( $cart ),
			'coupons'         => self::coupons( $cart ),
			'coupons_enabled' => wc_coupons_enabled(),
			'subtotal'        => self::plain_text( $cart->get_cart_subtotal() ),
			'total'           => self::plain_text( $cart->get_total() ),
			'shipping'        => self::shipping_text( $cart ),
			'tax'             => self::tax_text( $cart ),
			'notices'         => $notices,
			'recommendations' => RecommendationEngine::snapshot( $cart ),
			'rewards'         => RewardEngine::snapshot( $cart ),
			'special_addon'   => SpecialAddOn::snapshot( $cart ),
			'nonce'           => wp_create_nonce( 'sfcart_cart' ),
			'token'           => SessionToken::current(),
			'urls'            => array(
				'cart'     => wc_get_cart_url(),
				'checkout' => wc_get_checkout_url(),
				'shop'     => wc_get_page_permalink( 'shop' ),
			),
		);
	}

	/**
	 * Serialize visible cart items.
	 *
	 * @param WC_Cart $cart Active WooCommerce cart.
	 * @return list<array<string, mixed>>
	 */
	private static function items( WC_Cart $cart ): array {
		$items = array();

		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			$product = $cart_item['data'] ?? null;

			if ( ! $product instanceof WC_Product || ! $product->exists() ) {
				continue;
			}

			if ( ! apply_filters( 'woocommerce_widget_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
				continue;
			}

			$quantity  = (int) ( $cart_item['quantity'] ?? 0 );
			$limits    = Quantity::limits( $product );
			$is_gift   = RewardEngine::is_gift( $cart_item );
			$is_addon  = SpecialAddOn::is_addon( $cart_item );
			$permalink = $product->is_visible() ? $product->get_permalink() : '';
			$permalink = apply_filters( 'woocommerce_cart_item_permalink', $permalink, $cart_item, $cart_item_key );
			$name      = apply_filters( 'woocommerce_cart_item_name', $product->get_name(), $cart_item, $cart_item_key );

			$items[] = array(
				'key'              => (string) $cart_item_key,
				'product_id'       => (int) ( $cart_item['product_id'] ?? $product->get_id() ),
				'variation_id'     => (int) ( $cart_item['variation_id'] ?? 0 ),
				'name'             => self::plain_text( (string) $name ),
				'url'              => is_string( $permalink ) ? $permalink : '',
				'image'            => self::image_url( $product ),
				'meta'             => $is_gift ? __( 'Free gift', 'starfiniti-cart' ) : ( $is_addon ? __( 'Special add-on', 'starfiniti-cart' ) : self::plain_text( wc_get_formatted_cart_item_data( $cart_item, true ) ) ),
				'is_reward_gift'   => $is_gift,
				'is_special_addon' => $is_addon,
				'quantity'         => $quantity,
				'quantity_min'     => $limits['min'],
				'quantity_max'     => $limits['max'],
				'quantity_step'    => $limits['step'],
				'editable'         => ( $is_gift || $is_addon ) ? false : $limits['editable'],
				'removable'        => ! $is_gift || RewardEngine::gift_removal_allowed(),
				'unit_price'       => self::plain_text( $cart->get_product_price( $product ) ),
				'line_total'       => self::plain_text( $cart->get_product_subtotal( $product, $quantity ) ),
				'savings'          => self::savings( $product, $quantity ),
				'backorder'        => $product->backorders_require_notification() && $product->is_on_backorder( $quantity )
					? __( 'Available on backorder', 'woocommerce' )
					: '',
			);
		}

		return $items;
	}

	/**
	 * Serialize applied coupons.
	 *
	 * @param WC_Cart $cart Active WooCommerce cart.
	 * @return list<array{code: string, discount: string}>
	 */
	private static function coupons( WC_Cart $cart ): array {
		$coupons = array();

		foreach ( $cart->get_applied_coupons() as $code ) {
			$discount  = $cart->get_coupon_discount_amount( $code, ! $cart->display_prices_including_tax() );
			$coupons[] = array(
				'code'     => (string) $code,
				'discount' => self::plain_text( wc_price( $discount ) ),
			);
		}

		return $coupons;
	}

	/**
	 * Return the product image URL or the WooCommerce placeholder.
	 *
	 * @param WC_Product $product Cart item product.
	 */
	private static function image_url( WC_Product $product ): string {
		$image_id = (int) $product->get_image_id();
		$image    = $image_id > 0 ? wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) : false;

		return is_string( $image ) ? $image : wc_placeholder_img_src( 'woocommerce_thumbnail' );
	}

	/**
	 * Return regular-price savings for one cart line.
	 *
	 * @param WC_Product $product  Cart item product.
	 * @param int        $quantity Cart item quantity.
	 * @return array{amount: string, percentage: int}|null
	 */
	private static function savings( WC_Product $product, int $quantity ): ?array {
		$regular_price = (float) $product->get_regular_price();
		$current_price = (float) $product->get_price();

		if ( $regular_price <= 0 || $current_price >= $regular_price ) {
			return null;
		}

		$regular_display = (float) wc_get_price_to_display( $product, array( 'price' => $regular_price ) );
		$current_display = (float) wc_get_price_to_display( $product, array( 'price' => $current_price ) );
		$amount          = max( 0.0, ( $regular_display - $current_display ) * $quantity );

		return array(
			'amount'     => self::plain_text( wc_price( $amount ) ),
			'percentage' => (int) round( ( ( $regular_price - $current_price ) / $regular_price ) * 100 ),
		);
	}

	/**
	 * Return the current shipping summary.
	 *
	 * @param WC_Cart $cart Active WooCommerce cart.
	 */
	private static function shipping_text( WC_Cart $cart ): string {
		if ( ! $cart->needs_shipping() ) {
			return '';
		}

		$customer = WC()->customer;

		if ( null !== $customer && $customer->has_calculated_shipping() ) {
			return self::plain_text( $cart->get_cart_shipping_total() );
		}

		return __( 'Calculated at checkout', 'starfiniti-cart' );
	}

	/**
	 * Return the formatted tax total when non-zero.
	 *
	 * @param WC_Cart $cart Active WooCommerce cart.
	 */
	private static function tax_text( WC_Cart $cart ): string {
		$total = (float) $cart->get_total_tax();

		return $total > 0 ? self::plain_text( wc_price( $total ) ) : '';
	}

	/**
	 * Convert WooCommerce formatted HTML to safe display text.
	 *
	 * @param string $value WooCommerce formatted value.
	 */
	private static function plain_text( string $value ): string {
		return trim( html_entity_decode( wp_strip_all_tags( $value ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
