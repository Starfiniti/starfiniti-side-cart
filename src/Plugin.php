<?php
/**
 * Main plugin bootstrap.
 *
 * @package StarfinitiCart
 */

namespace Starfiniti\Cart;

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use Starfiniti\Cart\AddOn\SpecialAddOn;
use Starfiniti\Cart\Admin\AdminPage;
use Starfiniti\Cart\Ajax\CartController;
use Starfiniti\Cart\Analytics\Recorder;
use Starfiniti\Cart\Blocks\CartToggleBlock;
use Starfiniti\Cart\Compatibility\CompatibilityManager;
use Starfiniti\Cart\Frontend\Frontend;
use Starfiniti\Cart\Gateway\ExpressButtons;
use Starfiniti\Cart\Integration\StoreApiExtension;
use Starfiniti\Cart\Lifecycle\Installer;
use Starfiniti\Cart\Recommendations\Attribution;
use Starfiniti\Cart\Rewards\RewardEngine;
use Starfiniti\Cart\Rest\AdminController;
use Starfiniti\Cart\Rest\AnalyticsController;
use Starfiniti\Cart\Rest\MigrationController;
use Starfiniti\Cart\Support\Logger;
use Starfiniti\Cart\Updates\GitHubUpdater;
use Throwable;

/**
 * Coordinates the standalone plugin lifecycle.
 */
final class Plugin {

	/**
	 * Minimum supported PHP version.
	 */
	public const MINIMUM_PHP = '8.1';

	/**
	 * Minimum supported WordPress version.
	 */
	public const MINIMUM_WORDPRESS = '6.6';

	/**
	 * Minimum supported WooCommerce version.
	 */
	public const MINIMUM_WOOCOMMERCE = '9.0';

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Whether runtime initialization completed.
	 *
	 * @var bool
	 */
	private bool $ready = false;

	/**
	 * Unmet runtime requirement.
	 *
	 * @var RequirementFailure|null
	 */
	private ?RequirementFailure $requirement_failure = null;

	/**
	 * Whether installation or migration failed.
	 *
	 * @var bool
	 */
	private bool $initialization_failed = false;

	/**
	 * Return the singleton plugin instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register lifecycle hooks.
	 */
	public function boot(): void {
		GitHubUpdater::register();
		add_action( 'before_woocommerce_init', array( $this, 'declare_woocommerce_compatibility' ) );
		add_action( 'plugins_loaded', array( $this, 'initialize' ), 20 );
		add_action( 'init', array( $this, 'load_textdomain' ), 0 );
	}

	/**
	 * Declare compatibility with WooCommerce High-Performance Order Storage.
	 */
	public function declare_woocommerce_compatibility(): void {
		if ( ! class_exists( FeaturesUtil::class ) ) {
			return;
		}

		try {
			FeaturesUtil::declare_compatibility( 'custom_order_tables', SFCART_PLUGIN_FILE, true );
		} catch ( Throwable $error ) {
			Logger::exception( 'Starfiniti Cart could not declare HPOS compatibility.', $error );
		}
	}

	/**
	 * Initialize the plugin after dependencies have loaded.
	 */
	public function initialize(): void {
		$this->requirement_failure = Requirements::runtime_failure();

		if ( $this->requirement_failure instanceof RequirementFailure ) {
			add_action( 'admin_notices', array( $this, 'render_requirement_notice' ) );

			/**
			 * Fires when Starfiniti Cart refuses to initialize due to requirements.
			 *
			 * @param string $failure_code Stable failure code.
			 */
			do_action( 'sfcart_requirements_failed', $this->requirement_failure->code() );
			return;
		}

		try {
			Installer::install_or_upgrade();
			AdminPage::register();
			AdminController::register();
			AnalyticsController::register();
			MigrationController::register();
			CompatibilityManager::register();
			CartController::register();
			ExpressButtons::register();
			Frontend::register();
			CartToggleBlock::register();
			StoreApiExtension::register();
			Attribution::register();
			RewardEngine::register();
			SpecialAddOn::register();
			Recorder::register();
		} catch ( Throwable $error ) {
			$this->initialization_failed = true;
			Logger::exception( 'Starfiniti Cart initialization failed.', $error );
			add_action( 'admin_notices', array( $this, 'render_initialization_notice' ) );

			/**
			 * Fires after a caught Starfiniti Cart initialization failure.
			 *
			 * @param Throwable $error Caught initialization error.
			 */
			do_action( 'sfcart_initialization_failed', $error );
			return;
		}

		$this->ready = true;

		/**
		 * Fires after Starfiniti Cart has validated dependencies and migrations.
		 *
		 * @param Plugin $plugin Main plugin instance.
		 */
		do_action( 'sfcart_loaded', $this );
	}

	/**
	 * Load translations from the plugin languages directory.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'starfiniti-cart',
			false,
			dirname( plugin_basename( SFCART_PLUGIN_FILE ) ) . '/languages'
		);
	}

	/**
	 * Report whether runtime initialization completed.
	 */
	public function is_ready(): bool {
		return $this->ready;
	}

	/**
	 * Render the current dependency or conflict notice.
	 */
	public function render_requirement_notice(): void {
		if ( ! $this->requirement_failure instanceof RequirementFailure ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html( $this->requirement_failure->message() )
		);
	}

	/**
	 * Render a generic caught-initialization failure notice.
	 */
	public function render_initialization_notice(): void {
		if ( ! $this->initialization_failed ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'Starfiniti Cart could not initialize safely. Review the WooCommerce logs for details.', 'starfiniti-cart' )
		);
	}

	/**
	 * Prevent direct construction.
	 */
	private function __construct() {
	}
}
