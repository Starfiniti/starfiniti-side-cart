<?php
/**
 * Runtime and activation requirements.
 *
 * @package StarfinitiCart
 */

namespace Starfiniti\Cart;

/**
 * Validates the supported platform and conflicting plugin state.
 */
final class Requirements {

	/**
	 * Official WooCommerce plugin basename.
	 */
	private const WOOCOMMERCE_BASENAME = 'woocommerce/woocommerce.php';

	/**
	 * FunnelKit Cart plugin basename used only for activation conflict detection.
	 */
	private const CONFLICTING_CART_BASENAME = 'cart-for-woocommerce/plugin.php';

	/**
	 * Return the first runtime requirement failure, if any.
	 */
	public static function runtime_failure(): ?RequirementFailure {
		$platform_failure = self::platform_failure();

		if ( null !== $platform_failure ) {
			return $platform_failure;
		}

		if ( self::is_conflicting_cart_active() ) {
			return RequirementFailure::cart_conflict();
		}

		if ( ! defined( 'WC_VERSION' ) || ! class_exists( 'WooCommerce' ) ) {
			return RequirementFailure::woocommerce_missing();
		}

		if ( version_compare( WC_VERSION, Plugin::MINIMUM_WOOCOMMERCE, '<' ) ) {
			return RequirementFailure::version( 'WooCommerce', Plugin::MINIMUM_WOOCOMMERCE, WC_VERSION );
		}

		return null;
	}

	/**
	 * Return the first activation failure, including network-activation rules.
	 *
	 * @param bool $network_wide Whether activation applies to a multisite network.
	 */
	public static function activation_failure( bool $network_wide ): ?RequirementFailure {
		$platform_failure = self::platform_failure();

		if ( null !== $platform_failure ) {
			return $platform_failure;
		}

		if ( self::is_conflicting_cart_active( $network_wide ) ) {
			return RequirementFailure::cart_conflict();
		}

		if ( ! self::is_woocommerce_active( $network_wide ) || ! defined( 'WC_VERSION' ) ) {
			return RequirementFailure::woocommerce_missing();
		}

		if ( version_compare( WC_VERSION, Plugin::MINIMUM_WOOCOMMERCE, '<' ) ) {
			return RequirementFailure::version( 'WooCommerce', Plugin::MINIMUM_WOOCOMMERCE, WC_VERSION );
		}

		return null;
	}

	/**
	 * Validate the PHP and WordPress version floors.
	 */
	private static function platform_failure(): ?RequirementFailure {
		$current_wordpress = isset( $GLOBALS['wp_version'] ) ? (string) $GLOBALS['wp_version'] : '0';

		if ( version_compare( PHP_VERSION, Plugin::MINIMUM_PHP, '<' ) ) {
			return RequirementFailure::version( 'PHP', Plugin::MINIMUM_PHP, PHP_VERSION );
		}

		if ( version_compare( $current_wordpress, Plugin::MINIMUM_WORDPRESS, '<' ) ) {
			return RequirementFailure::version( 'WordPress', Plugin::MINIMUM_WORDPRESS, $current_wordpress );
		}

		return null;
	}

	/**
	 * Check whether WooCommerce is active in the required scope.
	 *
	 * @param bool $network_wide Whether activation applies to a multisite network.
	 */
	private static function is_woocommerce_active( bool $network_wide ): bool {
		if ( $network_wide && is_multisite() ) {
			return self::is_network_plugin_active( self::WOOCOMMERCE_BASENAME );
		}

		return class_exists( 'WooCommerce' ) || self::is_current_site_plugin_active( self::WOOCOMMERCE_BASENAME );
	}

	/**
	 * Detect an active FunnelKit Cart without loading or invoking its runtime.
	 *
	 * @param bool $network_wide Whether to inspect every site in a network.
	 */
	private static function is_conflicting_cart_active( bool $network_wide = false ): bool {
		if ( class_exists( 'FKCart\\Plugin', false ) ) {
			return true;
		}

		if ( self::is_current_site_plugin_active( self::CONFLICTING_CART_BASENAME ) ) {
			return true;
		}

		if ( ! $network_wide || ! is_multisite() ) {
			return false;
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
				if ( self::is_current_site_plugin_active( self::CONFLICTING_CART_BASENAME ) ) {
					return true;
				}
			} finally {
				restore_current_blog();
			}
		}

		return false;
	}

	/**
	 * Check a plugin basename against the current site and network active lists.
	 *
	 * @param string $plugin_basename Plugin basename to find.
	 * @phpstan-impure
	 */
	private static function is_current_site_plugin_active( string $plugin_basename ): bool {
		$active_plugins = get_option( 'active_plugins', array() );

		if ( is_array( $active_plugins ) && in_array( $plugin_basename, $active_plugins, true ) ) {
			return true;
		}

		return is_multisite() && self::is_network_plugin_active( $plugin_basename );
	}

	/**
	 * Check a plugin basename against the network active list.
	 *
	 * @param string $plugin_basename Plugin basename to find.
	 */
	private static function is_network_plugin_active( string $plugin_basename ): bool {
		$active_network_plugins = get_site_option( 'active_sitewide_plugins', array() );

		return is_array( $active_network_plugins ) && isset( $active_network_plugins[ $plugin_basename ] );
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
