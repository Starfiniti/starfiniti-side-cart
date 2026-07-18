<?php
/**
 * Cart-only compatibility adapters.
 *
 * @package StarfinitiCart
 */

namespace Starfiniti\Cart\Compatibility;

/**
 * Provides lightweight adapters for common WooCommerce ecosystem plugins.
 */
final class CompatibilityManager {

	/** LiteSpeed tag applied only to cached pages containing the drawer. */
	public const LITESPEED_CACHE_TAG = 'sfcart';

	/** Register compatibility hooks. */
	public static function register(): void {
		do_action( 'litespeed_nonce', 'sfcart_cart' );
		add_filter( 'sfcart_reward_threshold', array( self::class, 'convert_reward_amount' ), 20 );
		add_filter( 'sfcart_product_id_for_current_language', array( self::class, 'map_product_id' ), 20 );
		add_action( 'sfcart_settings_updated', array( self::class, 'purge_page_cache' ) );
		add_action( 'send_headers', array( self::class, 'send_cache_headers' ) );
	}

	/** Tag the current LiteSpeed page as containing Starfiniti Cart output. */
	public static function tag_page_cache(): void {
		do_action( 'litespeed_tag_add', self::LITESPEED_CACHE_TAG );
	}

	/** Purge only LiteSpeed pages containing Starfiniti Cart output. */
	public static function purge_page_cache(): void {
		do_action( 'litespeed_purge', self::LITESPEED_CACHE_TAG );
	}

	/**
	 * Convert fixed reward thresholds through installed multicurrency plugins.
	 *
	 * @param float $amount Reward threshold amount.
	 */
	public static function convert_reward_amount( float $amount ): float {
		$converted = $amount;

		if ( has_filter( 'wc_aelia_cs_convert' ) ) {
			$converted = (float) apply_filters( 'wc_aelia_cs_convert', $converted, get_option( 'woocommerce_currency' ), get_woocommerce_currency() );
		} elseif ( function_exists( 'wmc_get_price' ) ) {
			$converted = (float) wmc_get_price( $converted );
		} elseif ( function_exists( 'woocs_exchange_value' ) ) {
			$converted = (float) woocs_exchange_value( $converted );
		} elseif ( function_exists( 'yay_currency_get_price_by_currency' ) ) {
			$converted = (float) yay_currency_get_price_by_currency( $converted );
		}

		/**
		 * Filters the converted fixed reward amount after built-in adapters.
		 *
		 * @param float $converted Converted amount.
		 * @param float $amount    Original shop-base amount.
		 */
		return max( 0.0, (float) apply_filters( 'sfcart_compatible_reward_amount', $converted, $amount ) );
	}

	/**
	 * Map product identifiers to the active visitor language where available.
	 *
	 * @param int $product_id Product identifier.
	 */
	public static function map_product_id( int $product_id ): int {
		$mapped = $product_id;

		if ( function_exists( 'pll_get_post' ) ) {
			$polylang = absint( pll_get_post( $mapped ) );
			$mapped   = $polylang > 0 ? $polylang : $mapped;
		} elseif ( has_filter( 'wpml_object_id' ) ) {
			$wpml   = absint( apply_filters( 'wpml_object_id', $mapped, 'product', true ) );
			$mapped = $wpml > 0 ? $wpml : $mapped;
		} elseif ( has_filter( 'trp_translate_object_id' ) ) {
			$translated = absint( apply_filters( 'trp_translate_object_id', $mapped, 'product' ) );
			$mapped     = $translated > 0 ? $translated : $mapped;
		}

		return $mapped;
	}

	/** Mark Starfiniti Cart AJAX and REST responses as non-cacheable. */
	public static function send_cache_headers(): void {
		if ( ! self::is_cart_endpoint() ) {
			return;
		}

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		do_action( 'litespeed_control_set_nocache', 'Starfiniti Cart dynamic endpoint' );
		nocache_headers();
	}

	/** Report whether the current request belongs to this plugin's dynamic cart APIs. */
	private static function is_cart_endpoint(): bool {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) ) : '';

		return str_contains( $request_uri, '/starfiniti-cart/v1/' ) || str_contains( $request_uri, 'sfcart_' );
	}

	/** Prevent construction. */
	private function __construct() {
	}
}
