<?php
/**
 * Authenticated migration REST API.
 *
 * @package StarfinitiCart
 */

namespace Starfiniti\Cart\Rest;

use Starfiniti\Cart\Migration\FunnelKitMigrator;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Serves the guided FunnelKit Cart migration flow.
 */
final class MigrationController {

	/** Register API routes on rest_api_init. */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
	}

	/** Register versioned routes. */
	public static function register_routes(): void {
		register_rest_route(
			AdminController::NAMESPACE,
			'/migration/funnelkit/preview',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'preview' ),
				'permission_callback' => array( AdminController::class, 'can_manage' ),
			)
		);

		register_rest_route(
			AdminController::NAMESPACE,
			'/migration/funnelkit/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'status' ),
				'permission_callback' => array( AdminController::class, 'can_manage' ),
			)
		);

		register_rest_route(
			AdminController::NAMESPACE,
			'/migration/funnelkit/run',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'run' ),
				'permission_callback' => array( AdminController::class, 'can_manage' ),
				'args'                => array(
					'confirmed' => array(
						'required'          => true,
						'type'              => 'boolean',
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
				),
			)
		);
	}

	/** Return the migration preview. */
	public static function preview(): WP_REST_Response {
		return new WP_REST_Response( FunnelKitMigrator::preview() );
	}

	/** Return migration status and audit history. */
	public static function status(): WP_REST_Response {
		return new WP_REST_Response( FunnelKitMigrator::status() );
	}

	/**
	 * Run a confirmed migration.
	 *
	 * @param WP_REST_Request $request Migration request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function run( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( true !== $request->get_param( 'confirmed' ) ) {
			return new WP_Error(
				'sfcart_migration_confirmation_required',
				__( 'Confirm that legacy data should be copied into Starfiniti Cart before running migration.', 'starfiniti-cart' ),
				array( 'status' => 400 )
			);
		}

		return new WP_REST_Response( FunnelKitMigrator::run() );
	}

	/** Prevent construction. */
	private function __construct() {
	}
}
