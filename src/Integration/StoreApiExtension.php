<?php
/**
 * WooCommerce Store API extensions.
 *
 * @package StarfinitiCart
 */

namespace Starfiniti\Cart\Integration;

use Automattic\WooCommerce\StoreApi\Schemas\V1\CartItemSchema;
use Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema;
use Starfiniti\Cart\Cart\Quantity;
use WC_Product;

/**
 * Exposes namespaced drawer data to WooCommerce block consumers.
 */
final class StoreApiExtension {

	/**
	 * Whether endpoint data has been registered.
	 *
	 * @var bool
	 */
	private static bool $registered = false;

	/**
	 * Register after WooCommerce Blocks finishes loading.
	 */
	public static function register(): void {
		if ( did_action( 'woocommerce_blocks_loaded' ) ) {
			self::register_endpoint_data();
			return;
		}

		add_action( 'woocommerce_blocks_loaded', array( self::class, 'register_endpoint_data' ) );
	}

	/**
	 * Register cart and cart-item extension schemas.
	 */
	public static function register_endpoint_data(): void {
		if ( self::$registered || ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			return;
		}

		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => CartSchema::IDENTIFIER,
				'namespace'       => 'starfiniti-cart',
				'data_callback'   => array( self::class, 'cart_data' ),
				'schema_callback' => array( self::class, 'cart_schema' ),
				'schema_type'     => ARRAY_A,
			)
		);

		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => CartItemSchema::IDENTIFIER,
				'namespace'       => 'starfiniti-cart',
				'data_callback'   => array( self::class, 'cart_item_data' ),
				'schema_callback' => array( self::class, 'cart_item_schema' ),
				'schema_type'     => ARRAY_A,
			)
		);

		self::$registered = true;
	}

	/**
	 * Return drawer metadata for wc/store/cart.
	 *
	 * @return array{available: bool, cart_hash: string, item_count: int}
	 */
	public static function cart_data(): array {
		$cart = WC()->cart;

		return array(
			'available'  => true,
			'cart_hash'  => $cart->get_cart_hash(),
			'item_count' => $cart->get_cart_contents_count(),
		);
	}

	/**
	 * Return schema for cart extension data.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function cart_schema(): array {
		return array(
			'available'  => array(
				'description' => __( 'Whether the Starfiniti side cart is available.', 'starfiniti-cart' ),
				'type'        => 'boolean',
				'readonly'    => true,
			),
			'cart_hash'  => array(
				'description' => __( 'Current WooCommerce cart hash.', 'starfiniti-cart' ),
				'type'        => 'string',
				'readonly'    => true,
			),
			'item_count' => array(
				'description' => __( 'Current number of items in the cart.', 'starfiniti-cart' ),
				'type'        => 'integer',
				'readonly'    => true,
			),
		);
	}

	/**
	 * Return quantity metadata for one Store API cart item.
	 *
	 * @param array<string, mixed> $cart_item WooCommerce cart item.
	 * @return array<string, mixed>
	 */
	public static function cart_item_data( array $cart_item ): array {
		$product = $cart_item['data'] ?? null;

		if ( ! $product instanceof WC_Product ) {
			return array();
		}

		$limits = Quantity::limits( $product );

		return array(
			'editable'      => $limits['editable'],
			'quantity_min'  => $limits['min'],
			'quantity_max'  => $limits['max'],
			'quantity_step' => $limits['step'],
		);
	}

	/**
	 * Return schema for cart-item extension data.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function cart_item_schema(): array {
		return array(
			'editable'      => array(
				'description' => __( 'Whether the cart item quantity can be changed.', 'starfiniti-cart' ),
				'type'        => 'boolean',
				'readonly'    => true,
			),
			'quantity_min'  => array(
				'description' => __( 'Minimum allowed cart-item quantity.', 'starfiniti-cart' ),
				'type'        => 'number',
				'readonly'    => true,
			),
			'quantity_max'  => array(
				'description' => __( 'Maximum allowed cart-item quantity.', 'starfiniti-cart' ),
				'type'        => array( 'number', 'null' ),
				'readonly'    => true,
			),
			'quantity_step' => array(
				'description' => __( 'Allowed cart-item quantity increment.', 'starfiniti-cart' ),
				'type'        => 'number',
				'readonly'    => true,
			),
		);
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
