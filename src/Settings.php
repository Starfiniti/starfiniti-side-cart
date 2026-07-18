<?php
/**
 * Plugin-owned settings storage and validation.
 *
 * @package StarfinitiCart
 */

namespace Starfiniti\Cart;

/**
 * Owns the single versioned Starfiniti Cart settings option.
 */
final class Settings {

	/**
	 * Plugin settings option name.
	 */
	public const OPTION_NAME = 'sfcart_settings';

	/**
	 * Current settings document version.
	 */
	public const SCHEMA_VERSION = 11;

	/**
	 * Delete-data setting key. Kept at the document root for uninstall safety.
	 */
	public const DELETE_DATA_KEY = 'delete_data_on_uninstall';

	/**
	 * Return the complete, owned settings shape.
	 *
	 * Most language values are intentionally blank so the normal WordPress
	 * translation remains authoritative. The optional view-cart label and
	 * calculation note are hidden when their values are blank.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'settings_version'    => self::SCHEMA_VERSION,
			'cart'                => array(
				'position'               => 'right',
				'width'                  => 440,
				'auto_open'              => true,
				'floating_button'        => true,
				'header_cart'            => true,
				'coupons'                => true,
				'show_shipping'          => true,
				'show_tax'               => true,
				'show_cart_link'         => true,
				'show_continue_shopping' => true,
			),
			'design'              => array(
				'floating_icon'              => 'shopping-cart',
				'floating_icon_id'           => 0,
				'floating_icon_url'          => '',
				'floating_background'        => '#ffffff',
				'floating_hover'             => '#f3f4f6',
				'floating_icon_color'        => '#111827',
				'floating_badge_background'  => '#b91c1c',
				'floating_badge_color'       => '#ffffff',
				'floating_size'              => 56,
				'floating_border_radius'     => 50,
				'shortcode_icon'             => 'shopping-cart',
				'shortcode_icon_id'          => 0,
				'shortcode_icon_url'         => '',
				'shortcode_show_count'       => true,
				'shortcode_show_total'       => false,
				'shortcode_icon_size'        => 24,
				'shortcode_text_size'        => 14,
				'shortcode_background'       => '#ffffff',
				'shortcode_hover'            => '#f3f4f6',
				'shortcode_icon_color'       => '#111827',
				'shortcode_border'           => '#e5e7eb',
				'shortcode_border_width'     => 1,
				'shortcode_badge_background' => '#b91c1c',
				'shortcode_badge_color'      => '#ffffff',
				'shortcode_border_radius'    => 8,
				'accent'                     => '#1d4ed8',
				'accent_hover'               => '#1e40af',
				'background'                 => '#ffffff',
				'text'                       => '#111827',
				'muted'                      => '#6b7280',
				'border'                     => '#e5e7eb',
				'success'                    => '#166534',
				'danger'                     => '#b91c1c',
				'border_radius'              => 0,
				'overlay_opacity'            => 58,
				'empty_image_id'             => 0,
				'empty_image_url'            => '',
			),
			'language'            => array(
				'title'             => '',
				'close'             => '',
				'loading'           => '',
				'empty_title'       => '',
				'empty_message'     => '',
				'continue_shopping' => '',
				'coupon_code'       => '',
				'apply_coupon'      => '',
				'checkout'          => '',
				'view_cart'         => '',
				'calculation_note'  => '',
				'open_cart'         => '',
			),
			'upsells'             => array(
				'enabled'              => false,
				'mode'                 => 'both',
				'layout'               => 'style1',
				'placement'            => 'after_items',
				'heading'              => __( 'You may also like', 'starfiniti-cart' ),
				'ordering'             => 'relevance',
				'default_product_ids'  => array(),
				'excluded_product_ids' => array(),
				'display_limit'        => 3,
				'always_show_defaults' => false,
			),
			'rewards'             => array(
				'enabled'            => false,
				'calculation_mode'   => 'subtotal',
				'progress_design'    => 'bar',
				'complete_message'   => __( 'All rewards unlocked!', 'starfiniti-cart' ),
				'allow_gift_removal' => false,
				'milestones'         => array(),
			),
			'special_addon'       => array(
				'enabled'           => false,
				'product_id'        => 0,
				'preselected'       => false,
				'selection_type'    => 'checkbox',
				'heading'           => __( 'Add this to your order', 'starfiniti-cart' ),
				'description'       => '',
				'image_enabled'     => true,
				'image_source'      => 'product',
				'image_id'          => 0,
				'image_url'         => '',
				'image_size'        => 56,
				'background'        => '#f8fafc',
				'accent'            => '#1d4ed8',
				'heading_color'     => '#111827',
				'description_color' => '#6b7280',
			),
			self::DELETE_DATA_KEY => false,
		);
	}

	/**
	 * Read and normalize stored settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function get(): array {
		$stored = get_option( self::OPTION_NAME, array() );

		return self::sanitize( is_array( $stored ) ? $stored : array() );
	}

	/**
	 * Persist normalized settings without autoloading them.
	 *
	 * @param array<string, mixed> $settings Settings to persist.
	 * @return array<string, mixed> Persisted settings document.
	 */
	public static function update( array $settings ): array {
		$stored   = get_option( self::OPTION_NAME, false );
		$settings = self::sanitize( $settings );
		$changed  = ! is_array( $stored ) || self::sanitize( $stored ) !== $settings;
		update_option( self::OPTION_NAME, $settings, false );
		if ( $changed ) {
			do_action( 'sfcart_settings_updated', $settings );
		}

		return $settings;
	}

	/**
	 * Create the settings option or repair missing defaults.
	 */
	public static function ensure_defaults(): void {
		$settings = self::get();

		if ( false === get_option( self::OPTION_NAME, false ) ) {
			add_option( self::OPTION_NAME, $settings, '', false );
			return;
		}

		self::update( $settings );
	}

	/**
	 * Report whether destructive uninstall cleanup was explicitly enabled.
	 */
	public static function should_delete_data_on_uninstall(): bool {
		$settings = self::get();

		return true === $settings[ self::DELETE_DATA_KEY ];
	}

	/**
	 * Return a language override or the translated runtime fallback.
	 *
	 * @param string $key      Language setting key.
	 * @param string $fallback Translated runtime fallback.
	 */
	public static function language( string $key, string $fallback ): string {
		$settings = self::get();
		$language = is_array( $settings['language'] ?? null ) ? $settings['language'] : array();
		$value    = $language[ $key ] ?? '';

		return is_string( $value ) && '' !== $value ? $value : $fallback;
	}

	/**
	 * Validate settings before accepting an administration API write.
	 *
	 * @param array<string, mixed> $settings Candidate document.
	 * @return array<string, string> Field path to error message map.
	 */
	public static function validation_errors( array $settings ): array {
		$errors = array();
		$cart   = is_array( $settings['cart'] ?? null ) ? $settings['cart'] : array();
		$design = is_array( $settings['design'] ?? null ) ? $settings['design'] : array();

		if ( isset( $cart['position'] ) && ! in_array( $cart['position'], array( 'left', 'right' ), true ) ) {
			$errors['cart.position'] = __( 'Cart position must be left or right.', 'starfiniti-cart' );
		}

		if ( isset( $cart['width'] ) && ( ! is_numeric( $cart['width'] ) || (int) $cart['width'] < 320 || (int) $cart['width'] > 640 ) ) {
			$errors['cart.width'] = __( 'Cart width must be between 320 and 640 pixels.', 'starfiniti-cart' );
		}

		foreach ( array( 'accent', 'accent_hover', 'background', 'text', 'muted', 'border', 'success', 'danger', 'floating_background', 'floating_hover', 'floating_icon_color', 'floating_badge_background', 'floating_badge_color', 'shortcode_background', 'shortcode_hover', 'shortcode_icon_color', 'shortcode_border', 'shortcode_badge_background', 'shortcode_badge_color' ) as $color_key ) {
			if ( isset( $design[ $color_key ] ) && ! self::is_hex_color( $design[ $color_key ] ) ) {
				$errors[ 'design.' . $color_key ] = __( 'Use a six- or eight-digit hexadecimal color.', 'starfiniti-cart' );
			}
		}

		$design_colors = array_merge( self::defaults()['design'], $design );
		self::validate_contrast( $errors, 'design.accent', '#ffffff', $design_colors['accent'], $design_colors['background'] );
		self::validate_contrast( $errors, 'design.accent_hover', '#ffffff', $design_colors['accent_hover'], $design_colors['background'] );
		self::validate_contrast( $errors, 'design.text', $design_colors['text'], $design_colors['background'] );
		self::validate_contrast( $errors, 'design.muted', $design_colors['muted'], $design_colors['background'] );
		self::validate_contrast( $errors, 'design.success', $design_colors['success'], $design_colors['background'] );
		self::validate_contrast( $errors, 'design.danger', $design_colors['danger'], $design_colors['background'] );
		self::validate_contrast( $errors, 'design.floating_icon_color', $design_colors['floating_icon_color'], $design_colors['floating_background'] );
		self::validate_contrast( $errors, 'design.floating_hover', $design_colors['floating_icon_color'], $design_colors['floating_hover'] );
		self::validate_contrast( $errors, 'design.floating_badge_color', $design_colors['floating_badge_color'], $design_colors['floating_badge_background'] );
		self::validate_contrast( $errors, 'design.shortcode_icon_color', $design_colors['shortcode_icon_color'], $design_colors['shortcode_background'] );
		self::validate_contrast( $errors, 'design.shortcode_hover', $design_colors['shortcode_icon_color'], $design_colors['shortcode_hover'] );
		self::validate_contrast( $errors, 'design.shortcode_badge_color', $design_colors['shortcode_badge_color'], $design_colors['shortcode_badge_background'] );

		if ( isset( $design['border_radius'] ) && ( ! is_numeric( $design['border_radius'] ) || (int) $design['border_radius'] < 0 || (int) $design['border_radius'] > 32 ) ) {
			$errors['design.border_radius'] = __( 'Border radius must be between 0 and 32 pixels.', 'starfiniti-cart' );
		}

		if ( isset( $design['overlay_opacity'] ) && ( ! is_numeric( $design['overlay_opacity'] ) || (int) $design['overlay_opacity'] < 0 || (int) $design['overlay_opacity'] > 90 ) ) {
			$errors['design.overlay_opacity'] = __( 'Overlay opacity must be between 0 and 90 percent.', 'starfiniti-cart' );
		}

		if ( isset( $design['floating_size'] ) && ( ! is_numeric( $design['floating_size'] ) || (int) $design['floating_size'] < 44 || (int) $design['floating_size'] > 72 ) ) {
			$errors['design.floating_size'] = __( 'Floating button size must be between 44 and 72 pixels.', 'starfiniti-cart' );
		}

		if ( isset( $design['floating_border_radius'] ) && ( ! is_numeric( $design['floating_border_radius'] ) || (int) $design['floating_border_radius'] < 0 || (int) $design['floating_border_radius'] > 50 ) ) {
			$errors['design.floating_border_radius'] = __( 'Floating button radius must be between 0 and 50 percent.', 'starfiniti-cart' );
		}

		if ( isset( $design['shortcode_icon_size'] ) && ( ! is_numeric( $design['shortcode_icon_size'] ) || (int) $design['shortcode_icon_size'] < 16 || (int) $design['shortcode_icon_size'] > 64 ) ) {
			$errors['design.shortcode_icon_size'] = __( 'Header cart icon size must be between 16 and 64 pixels.', 'starfiniti-cart' );
		}

		if ( isset( $design['shortcode_text_size'] ) && ( ! is_numeric( $design['shortcode_text_size'] ) || (int) $design['shortcode_text_size'] < 10 || (int) $design['shortcode_text_size'] > 32 ) ) {
			$errors['design.shortcode_text_size'] = __( 'Header cart text size must be between 10 and 32 pixels.', 'starfiniti-cart' );
		}

		if ( isset( $design['shortcode_border_radius'] ) && ( ! is_numeric( $design['shortcode_border_radius'] ) || (int) $design['shortcode_border_radius'] < 0 || (int) $design['shortcode_border_radius'] > 32 ) ) {
			$errors['design.shortcode_border_radius'] = __( 'Shortcode button radius must be between 0 and 32 pixels.', 'starfiniti-cart' );
		}

		if ( isset( $design['shortcode_border_width'] ) && ( ! is_numeric( $design['shortcode_border_width'] ) || (int) $design['shortcode_border_width'] < 0 || (int) $design['shortcode_border_width'] > 4 ) ) {
			$errors['design.shortcode_border_width'] = __( 'Header cart border width must be between 0 and 4 pixels.', 'starfiniti-cart' );
		}

		foreach ( array( 'floating', 'shortcode' ) as $icon_context ) {
			$icon_key = $icon_context . '_icon';
			$id_key   = $icon_context . '_icon_id';
			if ( isset( $design[ $icon_key ] ) && ! in_array( $design[ $icon_key ], array( 'shopping-cart', 'shopping-bag', 'shopping-basket', 'baggage-claim', 'custom' ), true ) ) {
				$errors[ 'design.' . $icon_key ] = __( 'Choose one of the supported cart icons.', 'starfiniti-cart' );
			}
			if ( 'custom' === ( $design[ $icon_key ] ?? '' ) && empty( $design[ $id_key ] ) ) {
				$errors[ 'design.' . $id_key ] = __( 'Choose an image for the custom cart icon.', 'starfiniti-cart' );
			}
		}

		$upsells = is_array( $settings['upsells'] ?? null ) ? $settings['upsells'] : array();
		if ( isset( $upsells['mode'] ) && ! in_array( $upsells['mode'], array( 'upsells', 'cross_sells', 'both' ), true ) ) {
			$errors['upsells.mode'] = __( 'Choose a supported recommendation mode.', 'starfiniti-cart' );
		}
		if ( isset( $upsells['layout'] ) && ! in_array( $upsells['layout'], array( 'style1', 'style2', 'style3', 'carousel' ), true ) ) {
			$errors['upsells.layout'] = __( 'Choose one of the supported recommendation layouts.', 'starfiniti-cart' );
		}
		if ( isset( $upsells['placement'] ) && ! in_array( $upsells['placement'], array( 'before_items', 'after_items', 'before_totals', 'after_checkout' ), true ) ) {
			$errors['upsells.placement'] = __( 'Choose a supported recommendation position.', 'starfiniti-cart' );
		}
		if ( isset( $upsells['ordering'] ) && ! in_array( $upsells['ordering'], array( 'relevance', 'name', 'price_asc', 'price_desc' ), true ) ) {
			$errors['upsells.ordering'] = __( 'Choose a supported recommendation order.', 'starfiniti-cart' );
		}

		$rewards = is_array( $settings['rewards'] ?? null ) ? $settings['rewards'] : array();
		if ( isset( $rewards['calculation_mode'] ) && ! in_array( $rewards['calculation_mode'], array( 'subtotal', 'total' ), true ) ) {
			$errors['rewards.calculation_mode'] = __( 'Choose subtotal or total for reward calculations.', 'starfiniti-cart' );
		}
		if ( isset( $rewards['progress_design'] ) && ! in_array( $rewards['progress_design'], array( 'bar', 'steps', 'compact' ), true ) ) {
			$errors['rewards.progress_design'] = __( 'Choose a supported reward progress design.', 'starfiniti-cart' );
		}

		$milestones = is_array( $rewards['milestones'] ?? null ) ? $rewards['milestones'] : array();
		if ( count( $milestones ) > 10 ) {
			$errors['rewards.milestones'] = __( 'A maximum of ten reward milestones is supported.', 'starfiniti-cart' );
		}
		$milestone_ids = array();
		foreach ( $milestones as $index => $milestone ) {
			if ( ! is_array( $milestone ) ) {
				$errors[ 'rewards.milestones.' . $index ] = __( 'Each reward milestone must be an object.', 'starfiniti-cart' );
				continue;
			}
			$type = $milestone['type'] ?? '';
			$id   = sanitize_key( is_scalar( $milestone['id'] ?? null ) ? (string) $milestone['id'] : '' );
			if ( '' !== $id && in_array( $id, $milestone_ids, true ) ) {
				$errors[ 'rewards.milestones.' . $index . '.id' ] = __( 'Reward milestone identifiers must be unique.', 'starfiniti-cart' );
			}
			$milestone_ids[] = $id;
			if ( ! in_array( $type, array( 'free_shipping', 'coupon', 'gift' ), true ) ) {
				$errors[ 'rewards.milestones.' . $index . '.type' ] = __( 'Choose a supported reward type.', 'starfiniti-cart' );
			}
			if ( ! isset( $milestone['threshold'] ) || ! is_numeric( $milestone['threshold'] ) || (float) $milestone['threshold'] < 0 ) {
				$errors[ 'rewards.milestones.' . $index . '.threshold' ] = __( 'Reward thresholds must be zero or greater.', 'starfiniti-cart' );
			}
			if ( 'coupon' === $type && empty( $milestone['coupon_id'] ) ) {
				$errors[ 'rewards.milestones.' . $index . '.coupon_id' ] = __( 'Select a coupon for this reward.', 'starfiniti-cart' );
			}
			if ( 'gift' === $type && empty( $milestone['gift_product_id'] ) ) {
				$errors[ 'rewards.milestones.' . $index . '.gift_product_id' ] = __( 'Select a product for this gift.', 'starfiniti-cart' );
			}
		}

		$addon = is_array( $settings['special_addon'] ?? null ) ? $settings['special_addon'] : array();
		if ( ! empty( $addon['enabled'] ) && empty( $addon['product_id'] ) ) {
			$errors['special_addon.product_id'] = __( 'Select a product for the special add-on.', 'starfiniti-cart' );
		}
		if ( isset( $addon['selection_type'] ) && ! in_array( $addon['selection_type'], array( 'checkbox', 'toggle' ), true ) ) {
			$errors['special_addon.selection_type'] = __( 'Choose checkbox or toggle for the add-on control.', 'starfiniti-cart' );
		}
		if ( isset( $addon['image_source'] ) && ! in_array( $addon['image_source'], array( 'product', 'custom' ), true ) ) {
			$errors['special_addon.image_source'] = __( 'Choose the product image or a custom image.', 'starfiniti-cart' );
		}
		if ( isset( $addon['image_size'] ) && ( ! is_numeric( $addon['image_size'] ) || (int) $addon['image_size'] < 32 || (int) $addon['image_size'] > 96 ) ) {
			$errors['special_addon.image_size'] = __( 'Add-on image size must be between 32 and 96 pixels.', 'starfiniti-cart' );
		}
		foreach ( array( 'background', 'accent', 'heading_color', 'description_color' ) as $color_key ) {
			if ( isset( $addon[ $color_key ] ) && ! self::is_hex_color( $addon[ $color_key ] ) ) {
				$errors[ 'special_addon.' . $color_key ] = __( 'Use a six- or eight-digit hexadecimal color.', 'starfiniti-cart' );
			}
		}
		$addon_colors = array_merge( self::defaults()['special_addon'], $addon );
		self::validate_contrast( $errors, 'special_addon.heading_color', $addon_colors['heading_color'], $addon_colors['background'] );
		self::validate_contrast( $errors, 'special_addon.description_color', $addon_colors['description_color'], $addon_colors['background'] );

		return $errors;
	}

	/**
	 * Normalize the complete owned settings document.
	 *
	 * Unknown fields are deliberately discarded so untrusted REST payloads cannot
	 * turn the option into arbitrary storage.
	 *
	 * @param array<string, mixed> $settings Settings to normalize.
	 * @return array<string, mixed>
	 */
	public static function sanitize( array $settings ): array {
		$defaults          = self::defaults();
		$cart              = self::section( $settings, 'cart' );
		$design            = self::section( $settings, 'design' );
		$language          = self::section( $settings, 'language' );
		$upsells           = self::section( $settings, 'upsells' );
		$rewards           = self::section( $settings, 'rewards' );
		$addon             = self::section( $settings, 'special_addon' );
		$icons             = array( 'shopping-cart', 'shopping-bag', 'shopping-basket', 'baggage-claim', 'custom' );
		$legacy_icon       = in_array( $design['cart_icon'] ?? '', $icons, true ) ? $design['cart_icon'] : 'shopping-cart';
		$legacy_icon_id    = self::positive_integer( $design['cart_icon_id'] ?? 0 );
		$floating_icon     = in_array( $design['floating_icon'] ?? '', $icons, true ) ? $design['floating_icon'] : $legacy_icon;
		$shortcode_icon    = in_array( $design['shortcode_icon'] ?? '', $icons, true ) ? $design['shortcode_icon'] : $legacy_icon;
		$floating_icon_id  = self::positive_integer( $design['floating_icon_id'] ?? $legacy_icon_id );
		$shortcode_icon_id = self::positive_integer( $design['shortcode_icon_id'] ?? $legacy_icon_id );

		$normalized           = $defaults;
		$normalized['cart']   = array(
			'position'               => in_array( $cart['position'] ?? '', array( 'left', 'right' ), true ) ? $cart['position'] : $defaults['cart']['position'],
			'width'                  => self::bounded_integer( $cart['width'] ?? $defaults['cart']['width'], 320, 640, 440 ),
			'auto_open'              => self::boolean( $cart['auto_open'] ?? $defaults['cart']['auto_open'] ),
			'floating_button'        => self::boolean( $cart['floating_button'] ?? $defaults['cart']['floating_button'] ),
			'header_cart'            => self::boolean( $cart['header_cart'] ?? $defaults['cart']['header_cart'] ),
			'coupons'                => self::boolean( $cart['coupons'] ?? $defaults['cart']['coupons'] ),
			'show_shipping'          => self::boolean( $cart['show_shipping'] ?? $defaults['cart']['show_shipping'] ),
			'show_tax'               => self::boolean( $cart['show_tax'] ?? $defaults['cart']['show_tax'] ),
			'show_cart_link'         => self::boolean( $cart['show_cart_link'] ?? $defaults['cart']['show_cart_link'] ),
			'show_continue_shopping' => self::boolean( $cart['show_continue_shopping'] ?? $defaults['cart']['show_continue_shopping'] ),
		);
		$normalized['design'] = array(
			'floating_icon'              => $floating_icon,
			'floating_icon_id'           => $floating_icon_id,
			'floating_icon_url'          => self::media_url( $floating_icon_id ),
			'floating_background'        => self::color( $design['floating_background'] ?? null, $defaults['design']['floating_background'] ),
			'floating_hover'             => self::color( $design['floating_hover'] ?? null, $defaults['design']['floating_hover'] ),
			'floating_icon_color'        => self::color( $design['floating_icon_color'] ?? null, $defaults['design']['floating_icon_color'] ),
			'floating_badge_background'  => self::color( $design['floating_badge_background'] ?? null, $defaults['design']['floating_badge_background'] ),
			'floating_badge_color'       => self::color( $design['floating_badge_color'] ?? null, $defaults['design']['floating_badge_color'] ),
			'floating_size'              => self::bounded_integer( $design['floating_size'] ?? $defaults['design']['floating_size'], 44, 72, 56 ),
			'floating_border_radius'     => self::bounded_integer( $design['floating_border_radius'] ?? $defaults['design']['floating_border_radius'], 0, 50, 50 ),
			'shortcode_icon'             => $shortcode_icon,
			'shortcode_icon_id'          => $shortcode_icon_id,
			'shortcode_icon_url'         => self::media_url( $shortcode_icon_id ),
			'shortcode_show_count'       => self::boolean( $design['shortcode_show_count'] ?? $defaults['design']['shortcode_show_count'] ),
			'shortcode_show_total'       => self::boolean( $design['shortcode_show_total'] ?? $defaults['design']['shortcode_show_total'] ),
			'shortcode_icon_size'        => self::bounded_integer( $design['shortcode_icon_size'] ?? $defaults['design']['shortcode_icon_size'], 16, 64, 24 ),
			'shortcode_text_size'        => self::bounded_integer( $design['shortcode_text_size'] ?? $defaults['design']['shortcode_text_size'], 10, 32, 14 ),
			'shortcode_background'       => self::color( $design['shortcode_background'] ?? null, $defaults['design']['shortcode_background'] ),
			'shortcode_hover'            => self::color( $design['shortcode_hover'] ?? null, $defaults['design']['shortcode_hover'] ),
			'shortcode_icon_color'       => self::color( $design['shortcode_icon_color'] ?? $design['shortcode_text_color'] ?? null, $defaults['design']['shortcode_icon_color'] ),
			'shortcode_border'           => self::color( $design['shortcode_border'] ?? null, $defaults['design']['shortcode_border'] ),
			'shortcode_border_width'     => self::bounded_integer( $design['shortcode_border_width'] ?? $defaults['design']['shortcode_border_width'], 0, 4, 1 ),
			'shortcode_badge_background' => self::color( $design['shortcode_badge_background'] ?? null, $defaults['design']['shortcode_badge_background'] ),
			'shortcode_badge_color'      => self::color( $design['shortcode_badge_color'] ?? null, $defaults['design']['shortcode_badge_color'] ),
			'shortcode_border_radius'    => self::bounded_integer( $design['shortcode_border_radius'] ?? $defaults['design']['shortcode_border_radius'], 0, 32, 8 ),
			'accent'                     => self::color( $design['accent'] ?? null, $defaults['design']['accent'] ),
			'accent_hover'               => self::color( $design['accent_hover'] ?? null, $defaults['design']['accent_hover'] ),
			'background'                 => self::color( $design['background'] ?? null, $defaults['design']['background'] ),
			'text'                       => self::color( $design['text'] ?? null, $defaults['design']['text'] ),
			'muted'                      => self::color( $design['muted'] ?? null, $defaults['design']['muted'] ),
			'border'                     => self::color( $design['border'] ?? null, $defaults['design']['border'] ),
			'success'                    => self::color( $design['success'] ?? null, $defaults['design']['success'] ),
			'danger'                     => self::color( $design['danger'] ?? null, $defaults['design']['danger'] ),
			'border_radius'              => self::bounded_integer( $design['border_radius'] ?? 0, 0, 32, 0 ),
			'overlay_opacity'            => self::bounded_integer( $design['overlay_opacity'] ?? 58, 0, 90, 58 ),
			'empty_image_id'             => self::positive_integer( $design['empty_image_id'] ?? 0 ),
			'empty_image_url'            => self::media_url( self::positive_integer( $design['empty_image_id'] ?? 0 ) ),
		);

		foreach ( array_keys( $defaults['language'] ) as $key ) {
			$normalized['language'][ $key ] = self::text( $language[ $key ] ?? '', 200 );
		}

		$normalized['upsells']               = array(
			'enabled'              => self::boolean( $upsells['enabled'] ?? false ),
			'mode'                 => in_array( $upsells['mode'] ?? '', array( 'upsells', 'cross_sells', 'both' ), true ) ? $upsells['mode'] : 'both',
			'layout'               => in_array( $upsells['layout'] ?? '', array( 'style1', 'style2', 'style3', 'carousel' ), true ) ? $upsells['layout'] : 'style1',
			'placement'            => in_array( $upsells['placement'] ?? '', array( 'before_items', 'after_items', 'before_totals', 'after_checkout' ), true ) ? $upsells['placement'] : 'after_items',
			'heading'              => self::text( $upsells['heading'] ?? $defaults['upsells']['heading'], 120 ),
			'ordering'             => in_array( $upsells['ordering'] ?? '', array( 'relevance', 'name', 'price_asc', 'price_desc' ), true ) ? $upsells['ordering'] : 'relevance',
			'default_product_ids'  => self::id_list( $upsells['default_product_ids'] ?? array(), 20 ),
			'excluded_product_ids' => self::id_list( $upsells['excluded_product_ids'] ?? array(), 50 ),
			'display_limit'        => self::bounded_integer( $upsells['display_limit'] ?? 3, 1, 12, 3 ),
			'always_show_defaults' => self::boolean( $upsells['always_show_defaults'] ?? false ),
		);
		$normalized['rewards']               = array(
			'enabled'            => self::boolean( $rewards['enabled'] ?? false ),
			'calculation_mode'   => in_array( $rewards['calculation_mode'] ?? '', array( 'subtotal', 'total' ), true ) ? $rewards['calculation_mode'] : 'subtotal',
			'progress_design'    => in_array( $rewards['progress_design'] ?? '', array( 'bar', 'steps', 'compact' ), true ) ? $rewards['progress_design'] : 'bar',
			'complete_message'   => self::text( $rewards['complete_message'] ?? $defaults['rewards']['complete_message'], 200 ),
			'allow_gift_removal' => self::boolean( $rewards['allow_gift_removal'] ?? false ),
			'milestones'         => self::reward_milestones( $rewards['milestones'] ?? array() ),
		);
		$normalized['special_addon']         = array(
			'enabled'           => self::boolean( $addon['enabled'] ?? false ),
			'product_id'        => self::positive_integer( $addon['product_id'] ?? 0 ),
			'preselected'       => self::boolean( $addon['preselected'] ?? false ),
			'selection_type'    => in_array( $addon['selection_type'] ?? '', array( 'checkbox', 'toggle' ), true ) ? $addon['selection_type'] : 'checkbox',
			'heading'           => self::text( $addon['heading'] ?? $defaults['special_addon']['heading'], 120 ),
			'description'       => self::text( $addon['description'] ?? '', 250 ),
			'image_enabled'     => self::boolean( $addon['image_enabled'] ?? true ),
			'image_source'      => in_array( $addon['image_source'] ?? '', array( 'product', 'custom' ), true ) ? $addon['image_source'] : 'product',
			'image_id'          => self::positive_integer( $addon['image_id'] ?? 0 ),
			'image_url'         => self::media_url( self::positive_integer( $addon['image_id'] ?? 0 ) ),
			'image_size'        => self::bounded_integer( $addon['image_size'] ?? 56, 32, 96, 56 ),
			'background'        => self::color( $addon['background'] ?? null, $defaults['special_addon']['background'] ),
			'accent'            => self::color( $addon['accent'] ?? null, $defaults['special_addon']['accent'] ),
			'heading_color'     => self::color( $addon['heading_color'] ?? null, $defaults['special_addon']['heading_color'] ),
			'description_color' => self::color( $addon['description_color'] ?? null, $defaults['special_addon']['description_color'] ),
		);
		$normalized[ self::DELETE_DATA_KEY ] = self::boolean( $settings[ self::DELETE_DATA_KEY ] ?? false );

		return $normalized;
	}

	/**
	 * Read a nested settings section.
	 *
	 * @param array<string, mixed> $settings Settings document.
	 * @param string               $key      Section key.
	 * @return array<string, mixed>
	 */
	private static function section( array $settings, string $key ): array {
		return is_array( $settings[ $key ] ?? null ) ? $settings[ $key ] : array();
	}

	/**
	 * Normalize form-style boolean values.
	 *
	 * @param mixed $value Candidate boolean value.
	 */
	private static function boolean( mixed $value ): bool {
		$text = is_scalar( $value ) ? strtolower( (string) $value ) : '';

		return true === $value || 1 === $value || in_array( $text, array( '1', 'yes', 'true', 'on' ), true );
	}

	/**
	 * Normalize a bounded integer.
	 *
	 * @param mixed $value    Candidate numeric value.
	 * @param int   $minimum  Minimum accepted value.
	 * @param int   $maximum  Maximum accepted value.
	 * @param int   $fallback Fallback for non-numeric input.
	 */
	private static function bounded_integer( mixed $value, int $minimum, int $maximum, int $fallback ): int {
		if ( ! is_numeric( $value ) ) {
			return $fallback;
		}

		return max( $minimum, min( $maximum, (int) $value ) );
	}

	/**
	 * Normalize a positive identifier.
	 *
	 * @param mixed $value Candidate identifier.
	 */
	private static function positive_integer( mixed $value ): int {
		return is_numeric( $value ) ? max( 0, (int) $value ) : 0;
	}

	/**
	 * Normalize an identifier list.
	 *
	 * @param mixed $value   Candidate identifier list.
	 * @param int   $maximum Maximum retained identifiers.
	 * @return list<int>
	 */
	private static function id_list( mixed $value, int $maximum ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$identifiers = array_values(
			array_unique(
				array_filter(
					array_map( array( self::class, 'positive_integer' ), $value )
				)
			)
		);

		return array_slice( $identifiers, 0, $maximum );
	}

	/**
	 * Normalize reward milestones into a stable, bounded shape.
	 *
	 * @param mixed $value Candidate milestone list.
	 * @return list<array<string, mixed>>
	 */
	private static function reward_milestones( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$milestones = array();
		$used_ids   = array();
		foreach ( array_slice( $value, 0, 10 ) as $index => $milestone ) {
			if ( ! is_array( $milestone ) ) {
				continue;
			}
			$type = in_array( $milestone['type'] ?? '', array( 'free_shipping', 'coupon', 'gift' ), true ) ? $milestone['type'] : 'free_shipping';
			$id   = sanitize_key( is_scalar( $milestone['id'] ?? null ) ? (string) $milestone['id'] : '' );
			if ( '' === $id || in_array( $id, $used_ids, true ) ) {
				$id = 'milestone-' . ( $index + 1 );
				while ( in_array( $id, $used_ids, true ) ) {
					$id .= '-1';
				}
			}
			$used_ids[]   = $id;
			$milestones[] = array(
				'id'                => $id,
				'enabled'           => self::boolean( $milestone['enabled'] ?? true ),
				'type'              => $type,
				'threshold'         => self::bounded_float( $milestone['threshold'] ?? 0, 0, 1000000000, 0 ),
				'label'             => self::text( $milestone['label'] ?? '', 120 ),
				'pending_message'   => self::text( $milestone['pending_message'] ?? __( 'Spend {{remaining_amount}} more to unlock {{reward}}.', 'starfiniti-cart' ), 250 ),
				'achieved_message'  => self::text( $milestone['achieved_message'] ?? __( '{{reward}} unlocked.', 'starfiniti-cart' ), 250 ),
				'coupon_id'         => self::positive_integer( $milestone['coupon_id'] ?? 0 ),
				'gift_product_id'   => self::positive_integer( $milestone['gift_product_id'] ?? 0 ),
				'gift_variation_id' => self::positive_integer( $milestone['gift_variation_id'] ?? 0 ),
			);
		}

		usort(
			$milestones,
			static fn( array $left, array $right ): int => $left['threshold'] <=> $right['threshold']
		);

		return $milestones;
	}

	/**
	 * Normalize a bounded decimal value.
	 *
	 * @param mixed $value    Candidate numeric value.
	 * @param float $minimum  Minimum accepted value.
	 * @param float $maximum  Maximum accepted value.
	 * @param float $fallback Fallback for non-numeric input.
	 */
	private static function bounded_float( mixed $value, float $minimum, float $maximum, float $fallback ): float {
		if ( ! is_numeric( $value ) ) {
			return $fallback;
		}

		return max( $minimum, min( $maximum, (float) $value ) );
	}

	/**
	 * Normalize a human-readable override.
	 *
	 * @param mixed $value          Candidate text.
	 * @param int   $maximum_length Maximum retained characters.
	 */
	private static function text( mixed $value, int $maximum_length ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$value = trim( wp_strip_all_tags( (string) $value ) );

		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $maximum_length ) : substr( $value, 0, $maximum_length );
	}

	/**
	 * Add a WCAG AA contrast error for a color pair.
	 *
	 * @param array<string, string> $errors     Validation errors.
	 * @param string                $field_path Field receiving the error.
	 * @param mixed                 $foreground Foreground color.
	 * @param mixed                 $background Background color.
	 * @param mixed                 $canvas     Base behind translucent backgrounds.
	 */
	private static function validate_contrast( array &$errors, string $field_path, mixed $foreground, mixed $background, mixed $canvas = '#ffffff' ): void {
		if ( ! self::is_hex_color( $foreground ) || ! self::is_hex_color( $background ) || ! self::is_hex_color( $canvas ) ) {
			return;
		}

		if ( self::contrast_ratio( (string) $foreground, (string) $background, (string) $canvas ) < 4.5 ) {
			$errors[ $field_path ] = __( 'Choose colors with a contrast ratio of at least 4.5:1.', 'starfiniti-cart' );
		}
	}

	/**
	 * Calculate contrast after compositing optional alpha channels.
	 *
	 * @param string $foreground Foreground color.
	 * @param string $background Background color.
	 * @param string $canvas     Base color.
	 */
	private static function contrast_ratio( string $foreground, string $background, string $canvas ): float {
		$white                = array( 1.0, 1.0, 1.0, 1.0 );
		$canvas_rgb           = self::composite_color( self::color_channels( $canvas ), $white );
		$background_rgb       = self::composite_color( self::color_channels( $background ), $canvas_rgb );
		$foreground_rgb       = self::composite_color( self::color_channels( $foreground ), $background_rgb );
		$foreground_luminance = self::relative_luminance( $foreground_rgb );
		$background_luminance = self::relative_luminance( $background_rgb );

		return ( max( $foreground_luminance, $background_luminance ) + 0.05 ) / ( min( $foreground_luminance, $background_luminance ) + 0.05 );
	}

	/**
	 * Parse a validated six- or eight-digit hexadecimal color.
	 *
	 * @param string $color Hex color.
	 * @return array{0: float, 1: float, 2: float, 3: float}
	 */
	private static function color_channels( string $color ): array {
		$hex = substr( $color, 1 );

		return array(
			hexdec( substr( $hex, 0, 2 ) ) / 255,
			hexdec( substr( $hex, 2, 2 ) ) / 255,
			hexdec( substr( $hex, 4, 2 ) ) / 255,
			8 === strlen( $hex ) ? hexdec( substr( $hex, 6, 2 ) ) / 255 : 1.0,
		);
	}

	/**
	 * Composite a foreground RGBA color over an opaque background.
	 *
	 * @param array{0: float, 1: float, 2: float, 3: float} $foreground Foreground channels.
	 * @param array{0: float, 1: float, 2: float, 3: float} $background Background channels.
	 * @return array{0: float, 1: float, 2: float, 3: float}
	 */
	private static function composite_color( array $foreground, array $background ): array {
		$alpha = $foreground[3];

		return array(
			$foreground[0] * $alpha + $background[0] * ( 1 - $alpha ),
			$foreground[1] * $alpha + $background[1] * ( 1 - $alpha ),
			$foreground[2] * $alpha + $background[2] * ( 1 - $alpha ),
			1.0,
		);
	}

	/**
	 * Calculate WCAG relative luminance for an opaque color.
	 *
	 * @param array{0: float, 1: float, 2: float, 3: float} $color RGB channels.
	 */
	private static function relative_luminance( array $color ): float {
		$channels = array_map(
			static fn( float $channel ): float => $channel <= 0.04045 ? $channel / 12.92 : ( ( $channel + 0.055 ) / 1.055 ) ** 2.4,
			array_slice( $color, 0, 3 )
		);

		return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
	}

	/**
	 * Validate a six- or eight-digit hex color, including alpha.
	 *
	 * @param mixed $value Candidate color.
	 */
	private static function is_hex_color( mixed $value ): bool {
		return is_string( $value ) && 1 === preg_match( '/^#[0-9a-fA-F]{6}(?:[0-9a-fA-F]{2})?$/', $value );
	}

	/**
	 * Normalize a color with a known-safe fallback.
	 *
	 * @param mixed  $value    Candidate color.
	 * @param string $fallback Known-safe fallback.
	 */
	private static function color( mixed $value, string $fallback ): string {
		return self::is_hex_color( $value ) ? strtolower( (string) $value ) : $fallback;
	}

	/**
	 * Resolve an attachment URL without trusting a URL from the request.
	 *
	 * @param int $attachment_id WordPress attachment identifier.
	 */
	private static function media_url( int $attachment_id ): string {
		if ( 0 === $attachment_id || ! function_exists( 'wp_get_attachment_image_url' ) ) {
			return '';
		}

		$url = wp_get_attachment_image_url( $attachment_id, 'medium' );

		return is_string( $url ) ? $url : '';
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
