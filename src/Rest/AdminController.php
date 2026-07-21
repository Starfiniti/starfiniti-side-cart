<?php
/**
 * Versioned administration REST API.
 *
 * @package StarfinitiCart
 */

namespace Starfiniti\Cart\Rest;

use Starfiniti\Cart\Admin\AdminPage;
use Starfiniti\Cart\Settings;
use WC_Coupon;
use WC_Product;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Serves validated settings and authenticated WooCommerce object searches.
 */
final class AdminController {

	/**
	 * REST namespace.
	 */
	public const NAMESPACE = 'starfiniti-cart/v1';

	/**
	 * Register API routes on rest_api_init.
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
	}

	/**
	 * Register versioned routes.
	 */
	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( self::class, 'get_settings' ),
					'permission_callback' => array( self::class, 'can_manage' ),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( self::class, 'update_settings' ),
					'permission_callback' => array( self::class, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/products',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'search_products' ),
				'permission_callback' => array( self::class, 'can_manage' ),
				'args'                => self::search_arguments(),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/coupons',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'search_coupons' ),
				'permission_callback' => array( self::class, 'can_manage' ),
				'args'                => self::search_arguments(),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/relationships/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( self::class, 'get_relationships' ),
					'permission_callback' => array( self::class, 'can_manage_relationships' ),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( self::class, 'update_relationships' ),
					'permission_callback' => array( self::class, 'can_manage_relationships' ),
				),
			)
		);
	}

	/**
	 * Require WooCommerce management permission.
	 */
	public static function can_manage(): bool {
		return current_user_can( AdminPage::CAPABILITY );
	}

	/**
	 * Require WooCommerce management and object-level product edit permission.
	 *
	 * @param WP_REST_Request $request Current REST request.
	 */
	public static function can_manage_relationships( WP_REST_Request $request ): bool {
		$product_id = absint( $request->get_param( 'id' ) );

		return self::can_manage() && $product_id > 0 && current_user_can( 'edit_post', $product_id );
	}

	/**
	 * Return the normalized settings document.
	 */
	public static function get_settings(): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'settings' => Settings::get(),
				'version'  => Settings::SCHEMA_VERSION,
			)
		);
	}

	/**
	 * Validate and persist the complete settings document.
	 *
	 * @param WP_REST_Request $request Authenticated settings request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function update_settings( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$settings = $request->get_param( 'settings' );

		if ( ! is_array( $settings ) ) {
			return new WP_Error(
				'sfcart_invalid_settings',
				__( 'A settings object is required.', 'starfiniti-cart' ),
				array( 'status' => 400 )
			);
		}

		$errors = Settings::validation_errors( $settings );
		if ( array() !== $errors ) {
			return new WP_Error(
				'sfcart_invalid_settings',
				__( 'Some settings are invalid.', 'starfiniti-cart' ),
				array(
					'status' => 400,
					'fields' => $errors,
				)
			);
		}

		return new WP_REST_Response(
			array(
				'settings' => Settings::update( $settings ),
				'version'  => Settings::SCHEMA_VERSION,
			),
			200
		);
	}

	/**
	 * Search products without exposing WooCommerce REST credentials.
	 *
	 * @param WP_REST_Request $request Authenticated product search request.
	 */
	public static function search_products( WP_REST_Request $request ): WP_REST_Response {
		$search  = sanitize_text_field( (string) $request->get_param( 'search' ) );
		$include = self::requested_identifiers( $request );
		$query   = array(
			'limit'   => 20,
			'orderby' => 'name',
			'order'   => 'ASC',
			'return'  => 'objects',
			'status'  => 'publish',
		);

		if ( array() !== $include ) {
			$query['include'] = $include;
		} elseif ( '' !== $search ) {
			$product_ids = get_posts(
				array(
					'fields'           => 'ids',
					'numberposts'      => 20,
					'orderby'          => 'title',
					'order'            => 'ASC',
					'post_status'      => 'publish',
					'post_type'        => 'product',
					's'                => $search,
					'suppress_filters' => false,
				)
			);

			if ( array() === $product_ids ) {
				return new WP_REST_Response( array( 'items' => array() ) );
			}

			$query['include'] = array_map( 'absint', $product_ids );
		}

		$products = wc_get_products( $query );
		$data     = array();

		foreach ( $products as $product ) {
			if ( ! $product instanceof WC_Product ) {
				continue;
			}

			$data[] = self::product_summary( $product );
		}

		return new WP_REST_Response( array( 'items' => $data ) );
	}

	/**
	 * Return native WooCommerce relationships for one managed product.
	 *
	 * @param WP_REST_Request $request Authenticated relationship request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_relationships( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$product = wc_get_product( absint( $request->get_param( 'id' ) ) );
		if ( ! $product instanceof WC_Product || $product->is_type( 'variation' ) ) {
			return new WP_Error(
				'sfcart_product_not_found',
				__( 'That product could not be found.', 'starfiniti-cart' ),
				array( 'status' => 404 )
			);
		}

		return new WP_REST_Response( self::relationship_response( $product ) );
	}

	/**
	 * Persist upsells and cross-sells through WooCommerce product CRUD setters.
	 *
	 * @param WP_REST_Request $request Authenticated relationship request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function update_relationships( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$product = wc_get_product( absint( $request->get_param( 'id' ) ) );
		if ( ! $product instanceof WC_Product || $product->is_type( 'variation' ) ) {
			return new WP_Error(
				'sfcart_product_not_found',
				__( 'That product could not be found.', 'starfiniti-cart' ),
				array( 'status' => 404 )
			);
		}

		$upsell_ids     = self::relationship_identifiers( $request->get_param( 'upsell_ids' ), $product->get_id() );
		$cross_sell_ids = self::relationship_identifiers( $request->get_param( 'cross_sell_ids' ), $product->get_id() );
		$product->set_upsell_ids( $upsell_ids );
		$product->set_cross_sell_ids( $cross_sell_ids );
		$product->save();

		/**
		 * Fires after native product recommendation relationships are updated.
		 *
		 * @param int       $product_id Product identifier.
		 * @param list<int> $upsell_ids Upsell identifiers.
		 * @param list<int> $cross_sell_ids Cross-sell identifiers.
		 */
		do_action( 'sfcart_product_relationships_updated', $product->get_id(), $upsell_ids, $cross_sell_ids );

		return new WP_REST_Response( self::relationship_response( $product ) );
	}

	/**
	 * Search WooCommerce coupons for reward configuration.
	 *
	 * @param WP_REST_Request $request Authenticated coupon search request.
	 */
	public static function search_coupons( WP_REST_Request $request ): WP_REST_Response {
		$search  = sanitize_text_field( (string) $request->get_param( 'search' ) );
		$include = self::requested_identifiers( $request );
		$args    = array(
			'numberposts' => 20,
			'orderby'     => 'title',
			'order'       => 'ASC',
			'post_status' => 'publish',
			'post_type'   => 'shop_coupon',
			's'           => $search,
		);

		if ( array() !== $include ) {
			$args['post__in'] = $include;
		}

		$posts = get_posts( $args );
		$data  = array();

		foreach ( $posts as $post ) {
			$coupon = new WC_Coupon( $post->ID );
			$data[] = array(
				'id'          => $coupon->get_id(),
				'code'        => $coupon->get_code(),
				'type'        => $coupon->get_discount_type(),
				'amount'      => $coupon->get_amount(),
				'description' => $coupon->get_description(),
			);
		}

		return new WP_REST_Response( array( 'items' => $data ) );
	}

	/**
	 * Shared search route arguments.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function search_arguments(): array {
		return array(
			'search'  => array(
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
				'type'              => 'string',
			),
			'include' => array(
				'default' => '',
				'type'    => 'string',
			),
		);
	}

	/**
	 * Parse a comma-separated identifier list.
	 *
	 * @param WP_REST_Request $request Authenticated search request.
	 * @return list<int>
	 */
	private static function requested_identifiers( WP_REST_Request $request ): array {
		$raw = (string) $request->get_param( 'include' );
		$ids = array_values( array_unique( array_filter( array_map( 'absint', explode( ',', $raw ) ) ) ) );

		return array_slice( $ids, 0, 20 );
	}

	/**
	 * Build the shared product search representation.
	 *
	 * @param WC_Product $product Product object.
	 * @return array<string, mixed>
	 */
	private static function product_summary( WC_Product $product ): array {
		$image   = wp_get_attachment_image_url( (int) $product->get_image_id(), 'thumbnail' );
		$charset = get_bloginfo( 'charset' );
		$price   = html_entity_decode(
			wp_strip_all_tags( $product->get_price_html() ),
			ENT_QUOTES | ENT_HTML5,
			'' !== $charset ? $charset : 'UTF-8'
		);

		return array(
			'id'          => $product->get_id(),
			'name'        => $product->get_name(),
			'sku'         => $product->get_sku(),
			'type'        => $product->get_type(),
			'price'       => $price,
			'image'       => is_string( $image ) ? $image : wc_placeholder_img_src( 'thumbnail' ),
			'has_options' => $product->is_type( 'variable' ),
			'in_stock'    => $product->is_in_stock(),
		);
	}

	/**
	 * Return one product and its native relationship summaries.
	 *
	 * @param WC_Product $product Product object.
	 * @return array<string, mixed>
	 */
	private static function relationship_response( WC_Product $product ): array {
		return array(
			'product'     => self::product_summary( $product ),
			'upsells'     => self::summaries_for_ids( $product->get_upsell_ids() ),
			'cross_sells' => self::summaries_for_ids( $product->get_cross_sell_ids() ),
		);
	}

	/**
	 * Load product summaries while retaining stored relationship order.
	 *
	 * @param int[] $identifiers Product identifiers.
	 * @return list<array<string, mixed>>
	 */
	private static function summaries_for_ids( array $identifiers ): array {
		$summaries = array();
		foreach ( $identifiers as $identifier ) {
			$product = wc_get_product( $identifier );
			if ( $product instanceof WC_Product ) {
				$summaries[] = self::product_summary( $product );
			}
		}

		return $summaries;
	}

	/**
	 * Validate relationship identifiers from a REST body.
	 *
	 * @param mixed $value Candidate identifier list.
	 * @param int   $product_id Product being edited.
	 * @return list<int>
	 */
	private static function relationship_identifiers( mixed $value, int $product_id ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$identifiers = array_values( array_unique( array_filter( array_map( 'absint', $value ) ) ) );
		$identifiers = array_values( array_diff( $identifiers, array( $product_id ) ) );
		$identifiers = array_slice( $identifiers, 0, 50 );

		return array_values(
			array_filter(
				$identifiers,
				static function ( int $identifier ): bool {
					$product = wc_get_product( $identifier );

					return $product instanceof WC_Product && ! $product->is_type( 'variation' );
				}
			)
		);
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
