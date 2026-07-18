<?php
/**
 * Cart toggle block registration.
 *
 * @package StarfinitiCart
 */

namespace Starfiniti\Cart\Blocks;

use Starfiniti\Cart\Frontend\Markup;
use Starfiniti\Cart\Settings;

/**
 * Registers the dynamic starfiniti/cart-toggle block.
 */
final class CartToggleBlock {

	/**
	 * Register the block on init.
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'register_block' ) );
	}

	/**
	 * Register block metadata and server rendering.
	 */
	public static function register_block(): void {
		register_block_type(
			SFCART_PLUGIN_DIR . 'blocks/cart-toggle',
			array(
				'render_callback' => array( self::class, 'render' ),
			)
		);
	}

	/**
	 * Render the cart toggle on the frontend.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	public static function render( array $attributes = array() ): string {
		$settings = Settings::get();
		if ( false === $settings['cart']['header_cart'] ) {
			return '';
		}
		$label = isset( $attributes['label'] ) ? sanitize_text_field( (string) $attributes['label'] ) : '';

		return sprintf(
			'<div %1$s>%2$s</div>',
			get_block_wrapper_attributes( array( 'class' => 'sfcart-block-toggle' ) ),
			Markup::toggle( 'sfcart-shortcode-toggle', $label, $settings )
		);
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
