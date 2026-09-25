<?php
/**
 * Minimal WooCommerce test doubles used by isolated PHPUnit tests.
 *
 * @package StarfinitiCart
 */

if ( ! class_exists( 'WC_Product' ) ) {
	class WC_Product {
		public bool $sold_individually = false;
		private int $id = 0;
		/** @var list<int> */
		private array $upsell_ids = array();
		/** @var list<int> */
		private array $cross_sell_ids = array();

		public function __construct( int $id = 0 ) {
			$this->id = $id;
		}

		public function get_id(): int {
			return $this->id;
		}

		public function is_sold_individually(): bool {
			return $this->sold_individually;
		}

		/** @return list<int> */
		public function get_upsell_ids(): array {
			return $this->upsell_ids;
		}

		/** @param list<int> $ids Product identifiers. */
		public function set_upsell_ids( array $ids ): void {
			$this->upsell_ids = $ids;
		}

		/** @return list<int> */
		public function get_cross_sell_ids(): array {
			return $this->cross_sell_ids;
		}

		/** @param list<int> $ids Product identifiers. */
		public function set_cross_sell_ids( array $ids ): void {
			$this->cross_sell_ids = $ids;
		}

		public function save(): int {
			return $this->id;
		}
	}
}

if ( ! class_exists( 'WC_Cart' ) ) {
	class WC_Cart {
		public bool $needs_shipping = false;
		public bool $show_shipping = false;
		public string $shipping_total = '';

		public function needs_shipping(): bool {
			return $this->needs_shipping;
		}

		public function show_shipping(): bool {
			return $this->show_shipping;
		}

		public function get_cart_shipping_total(): string {
			return $this->shipping_total;
		}
	}
}

if ( ! function_exists( 'wc_get_quantity_input_args' ) ) {
	function wc_get_quantity_input_args( array $arguments, WC_Product $product ): array {
		return array_merge( $arguments, $GLOBALS['sfcart_test_quantity_args'] );
	}
}
