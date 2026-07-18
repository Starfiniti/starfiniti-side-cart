<?php
/**
 * Plugin activation handler.
 *
 * @package StarfinitiCart
 */

namespace Starfiniti\Cart\Lifecycle;

use Starfiniti\Cart\RequirementFailure;
use Starfiniti\Cart\Requirements;
use Starfiniti\Cart\Support\Logger;
use Throwable;

/**
 * Validates dependencies and installs current-site or network data.
 */
final class Activator {

	/**
	 * Validate requirements and install plugin-owned data.
	 *
	 * @param bool $network_wide Whether WordPress is activating across a network.
	 */
	public static function activate( bool $network_wide = false ): void {
		$failure = Requirements::activation_failure( $network_wide );

		if ( $failure instanceof RequirementFailure ) {
			self::abort_activation( $failure->message() );
		}

		try {
			if ( $network_wide && is_multisite() ) {
				self::install_network();
				return;
			}

			Installer::install_or_upgrade();
		} catch ( Throwable $error ) {
			Logger::exception( 'Starfiniti Cart activation failed.', $error );
			self::abort_activation(
				__( 'Starfiniti Cart could not finish activation. Review the WooCommerce logs and try again.', 'starfiniti-cart' )
			);
		}
	}

	/**
	 * Install plugin-owned data on every site during network activation.
	 */
	private static function install_network(): void {
		$site_ids = get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		);

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( (int) $site_id );

			try {
				Installer::install_or_upgrade();
			} finally {
				restore_current_blog();
			}
		}
	}

	/**
	 * Stop activation without changing any other plugin state.
	 *
	 * @param string $message User-facing activation failure.
	 */
	private static function abort_activation( string $message ): void {
		wp_die(
			esc_html( $message ),
			esc_html__( 'Starfiniti Cart activation blocked', 'starfiniti-cart' ),
			array(
				'back_link' => true,
				'response'  => 200,
			)
		);
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
