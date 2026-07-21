<?php
/**
 * Reversible plugin deactivation cleanup.
 *
 * @package StarfinitiCart
 */

namespace Starfiniti\Cart\Lifecycle;

use Starfiniti\Cart\Analytics\Privacy;

/** Clears background jobs without removing settings or analytics. */
final class Deactivator {

	/**
	 * Clear plugin cron jobs for the current site or an entire network.
	 *
	 * @param bool $network_wide Whether the plugin was network-deactivated.
	 */
	public static function deactivate( bool $network_wide = false ): void {
		if ( ! $network_wide || ! is_multisite() ) {
			wp_clear_scheduled_hook( Privacy::CRON_HOOK );
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
				wp_clear_scheduled_hook( Privacy::CRON_HOOK );
			} finally {
				restore_current_blog();
			}
		}
	}

	/** Prevent construction. */
	private function __construct() {
	}
}
