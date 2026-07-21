<?php
/**
 * Owned administration screen.
 *
 * @package StarfinitiCart
 */

namespace Starfiniti\Cart\Admin;

/**
 * Registers the WooCommerce submenu and its isolated React assets.
 */
final class AdminPage {

	/**
	 * Required capability for every administration surface.
	 */
	public const CAPABILITY = 'manage_woocommerce';

	/**
	 * Stable page slug.
	 */
	public const PAGE_SLUG = 'starfiniti-cart';

	/**
	 * Generated page hook.
	 *
	 * @var string
	 */
	private static string $page_hook = '';

	/**
	 * Register WordPress admin hooks.
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
	}

	/**
	 * Add the settings page beneath WooCommerce.
	 */
	public static function add_menu(): void {
		$page_hook = add_submenu_page(
			'woocommerce',
			__( 'Starfiniti Cart', 'starfiniti-cart' ),
			__( 'Starfiniti Cart', 'starfiniti-cart' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( self::class, 'render' )
		);

		self::$page_hook = is_string( $page_hook ) ? $page_hook : '';
	}

	/**
	 * Enqueue the source-generated application only on its owned page.
	 *
	 * @param string $page_hook Current admin page hook.
	 */
	public static function enqueue_assets( string $page_hook ): void {
		if ( '' === self::$page_hook || self::$page_hook !== $page_hook ) {
			return;
		}

		$asset_path   = SFCART_PLUGIN_DIR . 'build/admin.asset.php';
		$asset        = is_readable( $asset_path ) ? require $asset_path : array();
		$version      = is_array( $asset ) && isset( $asset['version'] ) ? (string) $asset['version'] : SFCART_VERSION;
		$dependencies = is_array( $asset ) && isset( $asset['dependencies'] ) && is_array( $asset['dependencies'] ) ? $asset['dependencies'] : array();

		wp_enqueue_media();
		wp_enqueue_style( 'sfcart-admin', SFCART_PLUGIN_URL . 'build/style-admin.css', array( 'wp-components', 'dashicons' ), SFCART_VERSION );
		wp_enqueue_script( 'sfcart-admin', SFCART_PLUGIN_URL . 'build/admin.js', $dependencies, $version, true );
		wp_add_inline_script(
			'sfcart-admin',
			'window.sfcartAdminConfig = Object.freeze(' . wp_json_encode(
				array(
					'nonce'             => wp_create_nonce( 'wp_rest' ),
					'root'              => esc_url_raw( rest_url() ),
					'version'           => SFCART_VERSION,
					'iconUrl'           => SFCART_PLUGIN_URL . 'assets/images/starfiniti-icon.png',
					'settingsPath'      => '/starfiniti-cart/v1/settings',
					'productsPath'      => '/starfiniti-cart/v1/products',
					'couponsPath'       => '/starfiniti-cart/v1/coupons',
					'relationshipsPath' => '/starfiniti-cart/v1/relationships',
					'analyticsPath'     => '/starfiniti-cart/v1/analytics',
					'migrationPath'     => '/starfiniti-cart/v1/migration/funnelkit',
				)
			) . ');',
			'before'
		);
	}

	/**
	 * Render the application mount point.
	 */
	public static function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to manage Starfiniti Cart.', 'starfiniti-cart' ) );
		}
		?>
		<div class="wrap sfcart-admin-wrap">
			<div id="sfcart-admin-root"><p><?php esc_html_e( 'Loading Starfiniti Cart settings…', 'starfiniti-cart' ); ?></p></div>
		</div>
		<?php
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
