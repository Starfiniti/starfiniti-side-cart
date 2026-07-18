<?php
/**
 * Plugin Name:       Starfiniti Cart for WooCommerce
 * Plugin URI:        https://starfiniti.com/
 * Description:       A standalone side cart for WooCommerce.
 * Version:           1.4.0
 * Update URI:        https://github.com/Starfiniti/starfiniti-side-cart
 * Requires at least: 6.6
 * Requires PHP:      8.1
 * Requires Plugins:  woocommerce
 * Author:            Starfiniti
 * Author URI:        https://starfiniti.com/
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       starfiniti-cart
 * Domain Path:       /languages
 * WC requires at least: 9.0
 * WC tested up to:      10.8
 *
 * @package StarfinitiCart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SFCART_VERSION', '1.4.0' );
define( 'SFCART_PLUGIN_FILE', __FILE__ );
define( 'SFCART_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SFCART_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

$sfcart_autoloader = SFCART_PLUGIN_DIR . 'vendor/autoload.php';

if ( is_readable( $sfcart_autoloader ) ) {
	require_once $sfcart_autoloader;
} else {
	spl_autoload_register(
		static function ( string $class_name ): void {
			$namespace = 'Starfiniti\\Cart\\';

			if ( 0 !== strpos( $class_name, $namespace ) ) {
				return;
			}

			$relative_class = substr( $class_name, strlen( $namespace ) );
			$class_file     = SFCART_PLUGIN_DIR . 'src/' . str_replace( '\\', '/', $relative_class ) . '.php';

			if ( is_readable( $class_file ) ) {
				require_once $class_file;
			}
		}
	);
}

register_activation_hook(
	SFCART_PLUGIN_FILE,
	array( Starfiniti\Cart\Lifecycle\Activator::class, 'activate' )
);

Starfiniti\Cart\Plugin::instance()->boot();
