<?php
/**
 * Shared simple and variable product presentation.
 *
 * @package StarfinitiCart
 */

namespace Starfiniti\Cart\Cart;

use WC_Product;
use WC_Product_Variable;
use WC_Product_Variation;

/**
 * Serializes products that can be selected without leaving the drawer.
 */
final class ProductSnapshot {

	/**
	 * Resolve a configured product in the visitor's current language.
	 *
	 * @param int $product_id Stored product identifier.
	 */
	public static function resolve( int $product_id ): ?WC_Product {
		$mapped_id = absint( apply_filters( 'sfcart_product_id_for_current_language', $product_id ) );
		$product   = wc_get_product( $mapped_id > 0 ? $mapped_id : $product_id );

		return $product instanceof WC_Product ? $product : null;
	}

	/**
	 * Convert a purchasable product to the owned frontend representation.
	 *
	 * @param WC_Product $product Product to serialize.
	 * @return array<string, mixed>|null
	 */
	public static function serialize( WC_Product $product ): ?array {
		$attributes = array();
		$variations = array();

		if ( $product instanceof WC_Product_Variable ) {
			$attributes = self::variable_attributes( $product );
			$variations = self::variations( $product );
			if ( array() === $variations ) {
				return null;
			}
		}

		return array(
			'id'          => $product->get_id(),
			'name'        => $product->get_name(),
			'url'         => $product->get_permalink(),
			'image'       => self::image_url( $product ),
			'price'       => self::plain_text( $product->get_price_html() ),
			'price_value' => (float) $product->get_price(),
			'type'        => $product->get_type(),
			'attributes'  => $attributes,
			'variations'  => $variations,
		);
	}

	/**
	 * Determine whether the owned drawer supports selecting this product.
	 *
	 * @param WC_Product $product Candidate product.
	 */
	public static function is_supported( WC_Product $product ): bool {
		$supported_types = array_values(
			array_unique(
				array_map(
					'strval',
					(array) apply_filters( 'sfcart_supported_product_types', array( 'simple', 'variable' ) )
				)
			)
		);
		$supported       = in_array( $product->get_type(), $supported_types, true );

		/**
		 * Filters whether a product has a complete drawer selection adapter.
		 *
		 * Complex products must opt in explicitly; merely installing their plugin
		 * does not make their required configuration fields safe to skip.
		 *
		 * @param bool       $supported Whether the product is supported.
		 * @param WC_Product $product   Candidate product.
		 */
		$supported = (bool) apply_filters( 'sfcart_product_is_supported', $supported, $product );

		return $product->exists()
			&& $product->is_purchasable()
			&& $product->is_in_stock()
			&& $supported;
	}

	/**
	 * Return the first available concrete product selection for preselection.
	 *
	 * @param WC_Product $product Configured add-on product.
	 * @return array{product_id: int, variation_id: int, attributes: array<string, string>}|null
	 */
	public static function first_selection( WC_Product $product ): ?array {
		if ( ! self::is_supported( $product ) ) {
			return null;
		}

		if ( ! $product instanceof WC_Product_Variable ) {
			return array(
				'product_id'   => $product->get_id(),
				'variation_id' => 0,
				'attributes'   => array(),
			);
		}

		foreach ( $product->get_children() as $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( $variation instanceof WC_Product_Variation && $variation->variation_is_visible() && $variation->is_purchasable() && $variation->is_in_stock() ) {
				return array(
					'product_id'   => $product->get_id(),
					'variation_id' => $variation->get_id(),
					'attributes'   => $variation->get_variation_attributes(),
				);
			}
		}

		return null;
	}

	/**
	 * Serialize customer-facing variable attribute choices.
	 *
	 * @param WC_Product_Variable $product Variable product.
	 * @return list<array{key: string, label: string, options: list<array{value: string, label: string}>}>
	 */
	private static function variable_attributes( WC_Product_Variable $product ): array {
		$result = array();

		foreach ( $product->get_variation_attributes() as $attribute_name => $values ) {
			$options = array();
			foreach ( $values as $value ) {
				$label = (string) $value;
				if ( taxonomy_exists( $attribute_name ) ) {
					$term = get_term_by( 'slug', (string) $value, $attribute_name );
					if ( $term instanceof \WP_Term ) {
						$label = $term->name;
					}
				}

				$options[] = array(
					'value' => (string) $value,
					'label' => $label,
				);
			}

			$result[] = array(
				'key'     => 'attribute_' . sanitize_title( $attribute_name ),
				'label'   => wc_attribute_label( $attribute_name, $product ),
				'options' => $options,
			);
		}

		return $result;
	}

	/**
	 * Serialize purchasable variation combinations.
	 *
	 * @param WC_Product_Variable $product Variable product.
	 * @return list<array<string, mixed>>
	 */
	private static function variations( WC_Product_Variable $product ): array {
		$result = array();

		foreach ( $product->get_children() as $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( ! $variation instanceof WC_Product_Variation || ! $variation->variation_is_visible() || ! $variation->is_purchasable() || ! $variation->is_in_stock() ) {
				continue;
			}

			$result[] = array(
				'id'         => $variation->get_id(),
				'attributes' => $variation->get_variation_attributes(),
				'price'      => self::plain_text( $variation->get_price_html() ),
				'image'      => self::image_url( $variation ),
			);
		}

		return $result;
	}

	/**
	 * Resolve an image or the WooCommerce placeholder.
	 *
	 * @param WC_Product $product Product requiring an image.
	 */
	private static function image_url( WC_Product $product ): string {
		$image = wp_get_attachment_image_url( (int) $product->get_image_id(), 'woocommerce_thumbnail' );

		return is_string( $image ) ? $image : wc_placeholder_img_src( 'woocommerce_thumbnail' );
	}

	/**
	 * Convert WooCommerce formatted HTML to display text.
	 *
	 * @param string $value Formatted value.
	 */
	private static function plain_text( string $value ): string {
		return trim( html_entity_decode( wp_strip_all_tags( $value ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	}

	/** Prevent construction. */
	private function __construct() {
	}
}
