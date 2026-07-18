<?php
/**
 * Explicit opt-in uninstall cleanup.
 *
 * @package StarfinitiCart
 */

namespace Starfiniti\Cart\Lifecycle;

use Starfiniti\Cart\Analytics\Tables;
use Starfiniti\Cart\Settings;

/**
 * Deletes only Starfiniti-owned data on sites that explicitly opted in.
 */
final class Uninstaller {

	/**
	 * Run uninstall cleanup across the current installation.
	 */
	public static function run(): void {
		if ( ! is_multisite() ) {
			self::maybe_delete_current_site_data();
			return;
		}

		$site_ids = get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		);

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( (int) $site_id );

			try {
				self::maybe_delete_current_site_data();
			} finally {
				restore_current_blog();
			}
		}
	}

	/**
	 * Delete current-site data only after explicit consent.
	 */
	private static function maybe_delete_current_site_data(): void {
		if ( ! Settings::should_delete_data_on_uninstall() ) {
			return;
		}

		delete_option( Installer::PLUGIN_VERSION_OPTION );
		delete_option( Installer::SCHEMA_VERSION_OPTION );
		delete_option( Settings::OPTION_NAME );
		delete_option( 'sfcart_funnelkit_migration_audit' );
		delete_option( 'sfcart_funnelkit_migration_state' );
		Tables::drop();
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
