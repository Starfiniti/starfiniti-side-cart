<?php
/**
 * Authenticated analytics REST API.
 *
 * @package StarfinitiCart
 */

namespace Starfiniti\Cart\Rest;

use Starfiniti\Cart\Analytics\CsvExporter;
use Starfiniti\Cart\Analytics\Reports;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Serves the owned analytics dashboard and exports.
 */
final class AnalyticsController {

	/** Register API routes and raw CSV serving hook. */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
		add_filter( 'rest_pre_serve_request', array( self::class, 'serve_csv' ), 10, 4 );
	}

	/** Register analytics routes. */
	public static function register_routes(): void {
		$routes = array(
			'/analytics/overview'    => 'overview',
			'/analytics/conversions' => 'conversions',
			'/analytics/popular'     => 'popular',
			'/analytics/performance' => 'performance',
			'/analytics/filters'     => 'filter_options',
			'/analytics/export'      => 'export',
		);

		foreach ( $routes as $route => $method ) {
			register_rest_route(
				AdminController::NAMESPACE,
				$route,
				array(
					'methods'             => 'GET',
					'callback'            => array( self::class, $method ),
					'permission_callback' => array( AdminController::class, 'can_manage' ),
					'args'                => self::arguments(),
				)
			);
		}
	}

	/**
	 * Return overview metrics.
	 *
	 * @param WP_REST_Request $request Analytics request.
	 */
	public static function overview( WP_REST_Request $request ): WP_REST_Response {
		$filters = Reports::filters( $request );
		return new WP_REST_Response(
			array(
				'filters'  => $filters,
				'overview' => Reports::overview( $filters ),
			)
		);
	}

	/**
	 * Return recent conversions.
	 *
	 * @param WP_REST_Request $request Analytics request.
	 */
	public static function conversions( WP_REST_Request $request ): WP_REST_Response {
		$filters = Reports::filters( $request );
		return new WP_REST_Response(
			array(
				'filters'     => $filters,
				'conversions' => Reports::conversions( $filters ),
			)
		);
	}

	/**
	 * Return product performance.
	 *
	 * @param WP_REST_Request $request Analytics request.
	 */
	public static function popular( WP_REST_Request $request ): WP_REST_Response {
		$filters = Reports::filters( $request );
		return new WP_REST_Response(
			array(
				'filters' => $filters,
				'items'   => Reports::popular( $filters ),
			)
		);
	}

	/**
	 * Return daily series.
	 *
	 * @param WP_REST_Request $request Analytics request.
	 */
	public static function performance( WP_REST_Request $request ): WP_REST_Response {
		$filters = Reports::filters( $request );
		return new WP_REST_Response(
			array(
				'filters' => $filters,
				'series'  => Reports::performance( $filters ),
			)
		);
	}

	/**
	 * Return available filter values.
	 */
	public static function filter_options(): WP_REST_Response {
		return new WP_REST_Response( Reports::filter_options() );
	}

	/**
	 * Return a marker response whose body is streamed by serve_csv().
	 *
	 * @param WP_REST_Request $request Analytics request.
	 */
	public static function export( WP_REST_Request $request ): WP_REST_Response {
		$filters  = Reports::filters( $request );
		$response = new WP_REST_Response( $filters );
		$response->header( 'Content-Type', 'text/csv; charset=utf-8' );
		$response->header( 'Content-Disposition', 'attachment; filename="starfiniti-cart-analytics.csv"' );
		$response->header( 'X-Starfiniti-Cart-CSV', '1' );

		return $response;
	}

	/**
	 * Serve CSV responses without JSON encoding.
	 *
	 * @param bool             $served Whether the response was served.
	 * @param WP_REST_Response $result Response object.
	 * @param WP_REST_Request  $request Request object.
	 * @param WP_REST_Server   $server REST server.
	 */
	public static function serve_csv( bool $served, WP_REST_Response $result, WP_REST_Request $request, WP_REST_Server $server ): bool {
		unset( $server );
		$headers = $result->get_headers();
		if (
			'/starfiniti-cart/v1/analytics/export' !== $request->get_route()
			|| '1' !== ( $headers['X-Starfiniti-Cart-CSV'] ?? '' )
		) {
			return $served;
		}

		$data = $result->get_data();
		CsvExporter::output( is_array( $data ) ? $data : array() );
		return true;
	}

	/**
	 * Shared report query arguments.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function arguments(): array {
		return array(
			'from'       => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'to'         => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'type'       => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_key',
			),
			'product_id' => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'coupon'     => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'currency'   => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/** Prevent construction. */
	private function __construct() {
	}
}
