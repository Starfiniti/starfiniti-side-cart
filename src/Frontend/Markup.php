<?php
/**
 * Owned side-cart markup.
 *
 * @package StarfinitiCart
 */

namespace Starfiniti\Cart\Frontend;

use Starfiniti\Cart\Gateway\ExpressButtons;
use Starfiniti\Cart\Settings;

/**
 * Produces the drawer shell and reusable cart toggles.
 */
final class Markup {

	/**
	 * Render a reusable cart toggle button.
	 *
	 * @param string               $class_name Additional CSS class.
	 * @param string               $label    Visible toggle label.
	 * @param array<string, mixed> $settings Normalized settings document.
	 */
	public static function toggle( string $class_name = '', string $label = '', array $settings = array() ): string {
		$settings = array() !== $settings ? $settings : Settings::get();
		$label    = '' !== trim( $label ) ? $label : self::language( $settings, 'open_cart', __( 'Open cart', 'starfiniti-cart' ) );
		$count    = self::cart_count();
		$variant  = str_contains( $class_name, 'sfcart-floating-toggle' ) ? 'floating' : 'shortcode';
		$style    = self::trigger_style( $variant, $settings );
		$design   = $settings['design'];
		/* translators: %d: number of items in the cart. */
		$count_label  = sprintf( _n( '%d item', '%d items', $count, 'starfiniti-cart' ), $count );
		$show_count   = 'floating' === $variant || true === $design['shortcode_show_count'];
		$show_total   = 'shortcode' === $variant && true === $design['shortcode_show_total'];
		$count_markup = $show_count
			? sprintf( '<span class="sfcart-toggle__count" data-sfcart-count aria-label="%1$s">%2$d</span>', esc_attr( $count_label ), $count )
			: '';
		$icon_markup  = self::cart_icon( $settings, $variant );
		if ( 'shortcode' === $variant ) {
			$icon_markup .= $count_markup;
			$count_markup = '';
		}
		$total_markup = $show_total
			? sprintf( '<span class="sfcart-toggle__subtotal" data-sfcart-trigger-subtotal>%s</span>', esc_html( self::cart_subtotal() ) )
			: '';
		$label_markup = 'floating' === $variant
			? sprintf( '<span class="sfcart-toggle__label">%s</span>', esc_html( $label ) )
			: '';

		return sprintf(
			'<button type="button" class="sfcart-toggle %1$s" style="%2$s" data-sfcart-toggle aria-label="%3$s" aria-controls="sfcart-drawer" aria-haspopup="dialog"><span class="sfcart-toggle__icon" aria-hidden="true">%4$s</span>%5$s%6$s%7$s</button>',
			esc_attr( $class_name ),
			esc_attr( $style ),
			esc_attr( $label ),
			$icon_markup,
			$label_markup,
			$count_markup,
			$total_markup
		);
	}

	/**
	 * Render the fixed floating toggle.
	 *
	 * @param array<string, mixed> $settings Normalized settings document.
	 */
	public static function floating_toggle( array $settings = array() ): string {
		$settings = array() !== $settings ? $settings : Settings::get();
		$position = 'left' === $settings['cart']['position'] ? 'left' : 'right';
		$label    = self::language( $settings, 'open_cart', __( 'Open cart', 'starfiniti-cart' ) );

		return self::toggle( 'sfcart-floating-toggle sfcart-floating-toggle--' . $position, $label, $settings );
	}

	/**
	 * Render the single drawer shell populated by frontend JavaScript.
	 *
	 * @param array<string, mixed> $settings Normalized settings document.
	 */
	public static function drawer( array $settings = array() ): string {
		$settings         = array() !== $settings ? $settings : Settings::get();
		$position         = 'left' === $settings['cart']['position'] ? 'left' : 'right';
		$style            = self::design_style( $settings );
		$image            = (string) $settings['design']['empty_image_url'];
		$upsell_placement = (string) ( $settings['upsells']['placement'] ?? 'after_items' );
		$view_cart_label  = self::language( $settings, 'view_cart', __( 'View cart', 'starfiniti-cart' ) );
		$calculation_note = trim( (string) ( $settings['language']['calculation_note'] ?? '' ) );
		$totals           = Settings::cart_totals_visibility( $settings['cart'] );
		$totals_hidden    = ! in_array( true, $totals, true );
		ob_start();
		?>
		<div class="sfcart-root sfcart-root--<?php echo esc_attr( $position ); ?>" id="sfcart-drawer" data-sfcart-root style="<?php echo esc_attr( $style ); ?>" hidden>
			<div class="sfcart-overlay" data-sfcart-close aria-hidden="true"></div>
			<section class="sfcart-drawer" role="dialog" aria-modal="true" aria-labelledby="sfcart-drawer-title" tabindex="-1">
				<header class="sfcart-header">
					<div>
						<h2 class="sfcart-title" id="sfcart-drawer-title"><?php echo esc_html( self::language( $settings, 'title', __( 'Your cart', 'starfiniti-cart' ) ) ); ?></h2>
						<p class="sfcart-header-count" data-sfcart-header-count></p>
					</div>
					<button type="button" class="sfcart-icon-button sfcart-close" data-sfcart-close aria-label="<?php echo esc_attr( self::language( $settings, 'close', __( 'Close cart', 'starfiniti-cart' ) ) ); ?>">
						<span aria-hidden="true">&times;</span>
					</button>
				</header>

				<div class="sfcart-notices" data-sfcart-notices role="status" aria-live="polite" aria-atomic="true"></div>

				<div class="sfcart-body" data-sfcart-body>
					<div class="sfcart-loading" data-sfcart-loading role="status">
						<span class="sfcart-spinner" aria-hidden="true"></span>
						<span><?php echo esc_html( self::language( $settings, 'loading', __( 'Loading your cart…', 'starfiniti-cart' ) ) ); ?></span>
					</div>

					<div class="sfcart-empty" data-sfcart-empty hidden>
						<?php if ( '' !== $image ) : ?>
							<img class="sfcart-empty__image" src="<?php echo esc_url( $image ); ?>" alt="">
						<?php else : ?>
							<span class="sfcart-empty__icon" aria-hidden="true"><?php echo self::cart_icon( $settings, 'floating' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped owned icon markup. ?></span>
						<?php endif; ?>
						<h3><?php echo esc_html( self::language( $settings, 'empty_title', __( 'Your cart is empty', 'starfiniti-cart' ) ) ); ?></h3>
						<p><?php echo esc_html( self::language( $settings, 'empty_message', __( 'Add something you love and it will appear here.', 'starfiniti-cart' ) ) ); ?></p>
						<a class="sfcart-secondary-button" data-sfcart-shop-link href="#" <?php echo empty( $settings['cart']['show_continue_shopping'] ) ? 'hidden' : ''; ?>><?php echo esc_html( self::language( $settings, 'continue_shopping', __( 'Continue shopping', 'starfiniti-cart' ) ) ); ?></a>
					</div>

					<section class="sfcart-rewards" data-sfcart-rewards aria-label="<?php esc_attr_e( 'Cart rewards', 'starfiniti-cart' ); ?>" hidden>
						<p class="sfcart-rewards__message" data-sfcart-rewards-message role="status" aria-live="polite"></p>
						<div data-sfcart-rewards-visual></div>
					</section>
					<?php if ( 'before_items' === $upsell_placement ) : ?>
						<?php echo self::recommendations_section(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Owned static markup. ?>
					<?php endif; ?>
					<ul class="sfcart-items" data-sfcart-items aria-label="<?php esc_attr_e( 'Cart items', 'starfiniti-cart' ); ?>"></ul>
					<section class="sfcart-special-addon" data-sfcart-special-addon aria-label="<?php esc_attr_e( 'Special add-on', 'starfiniti-cart' ); ?>" hidden></section>
					<?php if ( 'after_items' === $upsell_placement ) : ?>
						<?php echo self::recommendations_section(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Owned static markup. ?>
					<?php endif; ?>
				</div>

				<footer class="sfcart-footer" data-sfcart-footer hidden>
					<?php if ( 'before_totals' === $upsell_placement ) : ?>
						<?php echo self::recommendations_section(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Owned static markup. ?>
					<?php endif; ?>
					<form class="sfcart-coupon" data-sfcart-coupon-form>
						<label for="sfcart-coupon-code"><?php echo esc_html( self::language( $settings, 'coupon_code', __( 'Coupon code', 'starfiniti-cart' ) ) ); ?></label>
						<div class="sfcart-coupon__controls">
							<input class="sfcart-coupon__input" id="sfcart-coupon-code" name="coupon_code" type="text" autocomplete="off" placeholder="<?php echo esc_attr( self::language( $settings, 'coupon_code', __( 'Coupon code', 'starfiniti-cart' ) ) ); ?>" required>
							<button type="submit" class="sfcart-secondary-button"><?php echo esc_html( self::language( $settings, 'apply_coupon', __( 'Apply', 'starfiniti-cart' ) ) ); ?></button>
						</div>
					</form>

					<div class="sfcart-coupons" data-sfcart-coupons></div>
					<dl class="sfcart-totals" data-sfcart-totals <?php echo $totals_hidden ? 'hidden' : ''; ?>>
						<div class="sfcart-total-row" data-sfcart-subtotal-row <?php echo $totals['subtotal'] ? '' : 'hidden'; ?>>
							<dt><?php esc_html_e( 'Subtotal', 'woocommerce' ); ?></dt>
							<dd data-sfcart-subtotal></dd>
						</div>
						<div class="sfcart-total-row" data-sfcart-shipping-row <?php echo $totals['shipping'] ? '' : 'hidden'; ?>>
							<dt><?php esc_html_e( 'Shipping', 'woocommerce' ); ?></dt>
							<dd data-sfcart-shipping></dd>
						</div>
						<div class="sfcart-total-row" data-sfcart-tax-row <?php echo $totals['tax'] ? '' : 'hidden'; ?>>
							<dt><?php esc_html_e( 'Tax', 'woocommerce' ); ?></dt>
							<dd data-sfcart-tax></dd>
						</div>
						<div class="sfcart-total-row sfcart-total-row--grand" data-sfcart-total-row <?php echo $totals['total'] ? '' : 'hidden'; ?>>
							<dt><?php esc_html_e( 'Total', 'woocommerce' ); ?></dt>
							<dd data-sfcart-total></dd>
						</div>
					</dl>

					<a class="sfcart-checkout-button" data-sfcart-checkout href="#"><?php echo esc_html( self::language( $settings, 'checkout', __( 'Proceed to checkout', 'starfiniti-cart' ) ) ); ?></a>
					<?php self::express_buttons(); ?>
					<?php if ( 'after_checkout' === $upsell_placement ) : ?>
						<?php echo self::recommendations_section(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Owned static markup. ?>
					<?php endif; ?>
					<div class="sfcart-footer-links" data-sfcart-footer-links>
						<a class="sfcart-cart-link" data-sfcart-cart-link href="#" <?php echo empty( $settings['cart']['show_cart_link'] ) ? 'hidden' : ''; ?>><?php echo esc_html( $view_cart_label ); ?></a>
						<a class="sfcart-continue-shopping" data-sfcart-continue-shopping href="#" <?php echo empty( $settings['cart']['show_continue_shopping'] ) ? 'hidden' : ''; ?>><?php echo esc_html( self::language( $settings, 'continue_shopping', __( 'Continue shopping', 'starfiniti-cart' ) ) ); ?></a>
					</div>
					<?php if ( '' !== $calculation_note ) : ?>
						<p class="sfcart-calculation-note"><?php echo esc_html( $calculation_note ); ?></p>
					<?php endif; ?>
				</footer>
			</section>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/** Render only controls supplied by a supported official gateway adapter. */
	private static function express_buttons(): void {
		if ( false === has_action( ExpressButtons::RENDER_ACTION ) ) {
			return;
		}
		?>
		<div class="sfcart-express-buttons" data-sfcart-express-buttons aria-label="<?php esc_attr_e( 'Express checkout', 'starfiniti-cart' ); ?>">
			<?php do_action( ExpressButtons::RENDER_ACTION ); ?>
		</div>
		<?php
	}

	/**
	 * Return current server-side cart count when the session is available.
	 */
	public static function cart_count(): int {
		return null !== WC()->cart ? (int) WC()->cart->get_cart_contents_count() : 0;
	}

	/** Return the current formatted cart subtotal for header-cart triggers. */
	public static function cart_subtotal(): string {
		return null !== WC()->cart ? wp_strip_all_tags( (string) WC()->cart->get_cart_subtotal() ) : '';
	}

	/**
	 * Return the configured cart icon placeholder or custom image.
	 *
	 * Preset icons use bundled Lucide SVG assets without a JavaScript dependency.
	 * Custom URLs are derived from owned attachment IDs in Settings::sanitize().
	 *
	 * @param array<string, mixed> $settings Normalized settings document.
	 * @param string               $variant  Floating or shortcode icon context.
	 */
	public static function cart_icon( array $settings = array(), string $variant = 'floating' ): string {
		$settings = array() !== $settings ? $settings : Settings::get();
		$design   = is_array( $settings['design'] ?? null ) ? $settings['design'] : array();
		$prefix   = 'shortcode' === $variant ? 'shortcode' : 'floating';
		$icon     = is_string( $design[ $prefix . '_icon' ] ?? null ) ? $design[ $prefix . '_icon' ] : 'shopping-cart';
		$url      = is_string( $design[ $prefix . '_icon_url' ] ?? null ) ? $design[ $prefix . '_icon_url' ] : '';

		if ( 'custom' === $icon && '' !== $url ) {
			return sprintf( '<img class="sfcart-cart-icon__image" src="%s" alt="">', esc_url( $url ) );
		}

		if ( ! in_array( $icon, array( 'shopping-cart', 'shopping-bag', 'shopping-basket', 'baggage-claim' ), true ) ) {
			$icon = 'shopping-cart';
		}

		$icon_url = SFCART_PLUGIN_URL . 'assets/icons/' . $icon . '.svg';

		return sprintf(
			'<span class="sfcart-cart-icon__preset" style="--sfcart-cart-icon-url:url(%s)"></span>',
			esc_url( $icon_url )
		);
	}

	/** Return the single recommendation region at its configured drawer position. */
	private static function recommendations_section(): string {
		return '<section class="sfcart-recommendations" data-sfcart-recommendations aria-labelledby="sfcart-recommendations-title" hidden>'
			. '<div class="sfcart-recommendations__header">'
			. '<h3 id="sfcart-recommendations-title" data-sfcart-recommendations-heading></h3>'
			. '<div class="sfcart-recommendations__navigation" data-sfcart-recommendations-navigation hidden>'
			. '<button type="button" data-sfcart-carousel-previous><span aria-hidden="true"></span></button>'
			. '<button type="button" data-sfcart-carousel-next><span aria-hidden="true"></span></button>'
			. '</div>'
			. '</div>'
			. '<div data-sfcart-recommendations-items></div>'
			. '</section>';
	}

	/**
	 * Build safe custom properties from normalized design settings.
	 *
	 * @param array<string, mixed> $settings Complete settings document.
	 */
	private static function design_style( array $settings ): string {
		$design = $settings['design'];

		return sprintf(
			'--sfcart-accent:%1$s;--sfcart-accent-hover:%2$s;--sfcart-accent-text:%3$s;--sfcart-accent-hover-text:%4$s;--sfcart-background:%5$s;--sfcart-text:%6$s;--sfcart-muted:%7$s;--sfcart-border:%8$s;--sfcart-success:%9$s;--sfcart-danger:%10$s;--sfcart-width:%11$dpx;--sfcart-radius:%12$dpx;--sfcart-overlay-opacity:%13$d%%;',
			$design['accent'],
			$design['accent_hover'],
			Settings::readable_text_color( $design['accent'], $design['background'] ),
			Settings::readable_text_color( $design['accent_hover'], $design['background'] ),
			$design['background'],
			$design['text'],
			$design['muted'],
			$design['border'],
			$design['success'],
			$design['danger'],
			$settings['cart']['width'],
			$design['border_radius'],
			$design['overlay_opacity']
		);
	}

	/**
	 * Build isolated custom properties for floating and shortcode triggers.
	 *
	 * @param string               $variant Trigger variant.
	 * @param array<string, mixed> $settings Normalized settings.
	 */
	private static function trigger_style( string $variant, array $settings ): string {
		$design = $settings['design'];

		if ( 'floating' === $variant ) {
			return sprintf(
				'--sfcart-trigger-background:%1$s;--sfcart-trigger-hover:%2$s;--sfcart-trigger-text:%3$s;--sfcart-trigger-badge:%4$s;--sfcart-trigger-badge-text:%5$s;--sfcart-trigger-size:%6$dpx;--sfcart-trigger-radius:%7$d%%;',
				$design['floating_background'],
				$design['floating_hover'],
				$design['floating_icon_color'],
				$design['floating_badge_background'],
				$design['floating_badge_color'],
				$design['floating_size'],
				$design['floating_border_radius']
			);
		}

		return sprintf(
			'--sfcart-trigger-background:%1$s;--sfcart-trigger-hover:%2$s;--sfcart-trigger-text:%3$s;--sfcart-trigger-border:%4$s;--sfcart-trigger-border-width:%5$dpx;--sfcart-trigger-badge:%6$s;--sfcart-trigger-badge-text:%7$s;--sfcart-trigger-radius:%8$dpx;--sfcart-trigger-icon-size:%9$dpx;--sfcart-trigger-text-size:%10$dpx;',
			$design['shortcode_background'],
			$design['shortcode_hover'],
			$design['shortcode_icon_color'],
			$design['shortcode_border'],
			$design['shortcode_border_width'],
			$design['shortcode_badge_background'],
			$design['shortcode_badge_color'],
			$design['shortcode_border_radius'],
			$design['shortcode_icon_size'],
			$design['shortcode_text_size']
		);
	}

	/**
	 * Resolve a normalized language override.
	 *
	 * @param array<string, mixed> $settings Complete settings document.
	 * @param string               $key      Override key.
	 * @param string               $fallback Translated fallback.
	 */
	private static function language( array $settings, string $key, string $fallback ): string {
		$value = $settings['language'][ $key ] ?? '';

		return is_string( $value ) && '' !== $value ? $value : $fallback;
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
