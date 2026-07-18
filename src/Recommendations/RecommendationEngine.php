<?php
/**
 * WooCommerce-native cart recommendation resolver.
 *
 * @package StarfinitiCart
 */

namespace Starfiniti\Cart\Recommendations;

use Starfiniti\Cart\Cart\ProductSnapshot;
use Starfiniti\Cart\Settings;
use WC_Cart;
use WC_Product;

/**
 * Resolves and serializes products that can be added without leaving the drawer.
 */
final class RecommendationEngine {

	/**
	 * Build the recommendation portion of a cart response.
	 *
	 * @param WC_Cart|null              $cart     Active cart, or the global cart.
	 * @param array<string, mixed>|null $settings Normalized settings override.
	 * @return array{enabled: bool, heading: string, layout: string, placement: string, items: list<array<string, mixed>>}
	 */
	public static function snapshot( ?WC_Cart $cart = null, ?array $settings = null ): array {
		$settings = is_array( $settings ) ? Settings::sanitize( $settings ) : Settings::get();
		$options  = $settings['upsells'];
		$result   = array(
			'enabled'   => (bool) $options['enabled'],
			'heading'   => (string) $options['heading'],
			'layout'    => (string) $options['layout'],
			'placement' => (string) $options['placement'],
			'items'     => array(),
		);

		$cart = $cart ?? WC()->cart;
		if ( ! $result['enabled'] || ! $cart instanceof WC_Cart || $cart->is_empty() ) {
			return $result;
		}

		$candidates = self::candidate_rows( $cart, $options );
		$items      = array();

		foreach ( $candidates as $candidate ) {
			$product = ProductSnapshot::resolve( $candidate['id'] );
			if ( ! $product instanceof WC_Product || ! $product->is_visible() || ! ProductSnapshot::is_supported( $product ) ) {
				continue;
			}

			$item = self::serialize_product( $product, $candidate );
			if ( null !== $item ) {
				$items[] = $item;
			}
		}

		self::order_items( $items, (string) $options['ordering'] );
		$result['items'] = array_slice( $items, 0, (int) $options['display_limit'] );

		return $result;
	}

	/**
	 * Resolve ordered, de-duplicated candidate identifiers and attribution sources.
	 *
	 * @param WC_Cart              $cart    Active cart.
	 * @param array<string, mixed> $options Normalized recommendation settings.
	 * @return list<array{id: int, source: string, source_product_id: int}>
	 */
	private static function candidate_rows( WC_Cart $cart, array $options ): array {
		$mode     = (string) $options['mode'];
		$excluded = array();
		foreach ( array_map( 'absint', $options['excluded_product_ids'] ) as $excluded_id ) {
			$excluded[ $excluded_id ] = true;
			$product                  = ProductSnapshot::resolve( $excluded_id );
			if ( $product instanceof WC_Product ) {
				$excluded[ $product->get_id() ] = true;
			}
		}
		$in_cart    = array();
		$candidates = array();

		foreach ( $cart->get_cart() as $cart_item ) {
			$product_id = absint( $cart_item['product_id'] ?? 0 );
			if ( $product_id > 0 ) {
				$in_cart[ $product_id ] = true;
			}
		}

		foreach ( array_keys( $in_cart ) as $source_product_id ) {
			$product = wc_get_product( $source_product_id );
			if ( ! $product instanceof WC_Product ) {
				continue;
			}

			if ( in_array( $mode, array( 'upsells', 'both' ), true ) ) {
				self::append_candidates( $candidates, $product->get_upsell_ids(), 'upsell', $source_product_id );
			}
			if ( in_array( $mode, array( 'cross_sells', 'both' ), true ) ) {
				self::append_candidates( $candidates, $product->get_cross_sell_ids(), 'cross_sell', $source_product_id );
			}
		}

		if ( array() === $candidates || (bool) $options['always_show_defaults'] ) {
			self::append_candidates( $candidates, $options['default_product_ids'], 'default', 0 );
		}

		return array_values(
			array_filter(
				$candidates,
				static fn ( array $candidate ): bool => ! isset( $in_cart[ $candidate['id'] ] ) && ! isset( $excluded[ $candidate['id'] ] )
			)
		);
	}

	/**
	 * Add unique candidates without losing native WooCommerce relationship order.
	 *
	 * @param array<int, array{id: int, source: string, source_product_id: int}> $candidates Candidate map.
	 * @param mixed                                                              $identifiers Product identifiers.
	 * @param string                                                             $source Source type.
	 * @param int                                                                $source_product_id Source cart product.
	 */
	private static function append_candidates( array &$candidates, mixed $identifiers, string $source, int $source_product_id ): void {
		if ( ! is_array( $identifiers ) ) {
			return;
		}

		foreach ( $identifiers as $identifier ) {
			$stored_id  = absint( $identifier );
			$product    = $stored_id > 0 ? ProductSnapshot::resolve( $stored_id ) : null;
			$product_id = $product instanceof WC_Product ? $product->get_id() : $stored_id;
			if ( $product_id > 0 && ! isset( $candidates[ $product_id ] ) ) {
				$candidates[ $product_id ] = array(
					'id'                => $product_id,
					'source'            => $source,
					'source_product_id' => $source_product_id,
				);
			}
		}
	}

	/**
	 * Convert a product to the safe frontend representation.
	 *
	 * @param WC_Product                                             $product Candidate product.
	 * @param array{id: int, source: string, source_product_id: int} $candidate Attribution source.
	 * @return array<string, mixed>|null
	 */
	private static function serialize_product( WC_Product $product, array $candidate ): ?array {
		$snapshot = ProductSnapshot::serialize( $product );
		if ( null === $snapshot ) {
			return null;
		}

		return array_merge(
			$snapshot,
			array(
				'source'            => $candidate['source'],
				'source_product_id' => $candidate['source_product_id'],
			)
		);
	}

	/**
	 * Apply the configured order while retaining relevance order by default.
	 *
	 * @param list<array<string, mixed>> $items Items to order in place.
	 * @param string                     $ordering Ordering mode.
	 */
	private static function order_items( array &$items, string $ordering ): void {
		if ( 'relevance' === $ordering ) {
			return;
		}

		usort(
			$items,
			static function ( array $left, array $right ) use ( $ordering ): int {
				if ( 'name' === $ordering ) {
					return strnatcasecmp( (string) $left['name'], (string) $right['name'] );
				}

				$comparison = (float) $left['price_value'] <=> (float) $right['price_value'];
				return 'price_desc' === $ordering ? -$comparison : $comparison;
			}
		);
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
