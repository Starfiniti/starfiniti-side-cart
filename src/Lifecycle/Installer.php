<?php
/**
 * Versioned installation and migration runner.
 *
 * @package StarfinitiCart
 */

namespace Starfiniti\Cart\Lifecycle;

use Starfiniti\Cart\Analytics\Tables;
use Starfiniti\Cart\Settings;
use Starfiniti\Cart\Support\Logger;

/**
 * Applies idempotent Starfiniti-owned schema and option migrations.
 */
final class Installer {

	/**
	 * Current plugin-owned schema version.
	 */
	public const CURRENT_SCHEMA_VERSION = '12';

	/**
	 * Stored code version option.
	 */
	public const PLUGIN_VERSION_OPTION = 'sfcart_version';

	/**
	 * Stored schema version option.
	 */
	public const SCHEMA_VERSION_OPTION = 'sfcart_db_version';

	/**
	 * Run installation or upgrade work for the current site.
	 */
	public static function install_or_upgrade(): void {
		$installed_schema = get_option( self::SCHEMA_VERSION_OPTION, '0' );
		$installed_schema = is_scalar( $installed_schema ) ? (string) $installed_schema : '0';
		$installed_plugin = get_option( self::PLUGIN_VERSION_OPTION, '' );
		$installed_plugin = is_scalar( $installed_plugin ) ? (string) $installed_plugin : '';
		$stored_settings  = get_option( Settings::OPTION_NAME, false );

		if (
			self::CURRENT_SCHEMA_VERSION === $installed_schema
			&& SFCART_VERSION === $installed_plugin
			&& is_array( $stored_settings )
		) {
			return;
		}

		if ( ! preg_match( '/^\d+(?:\.\d+)*$/', $installed_schema ) ) {
			$installed_schema = '0';
		}

		if ( version_compare( $installed_schema, '1', '<' ) ) {
			self::migrate_to_1();
		}

		if ( version_compare( $installed_schema, '2', '<' ) ) {
			self::migrate_to_2();
		}

		if ( version_compare( $installed_schema, '3', '<' ) ) {
			self::migrate_to_3();
		}

		if ( version_compare( $installed_schema, '4', '<' ) ) {
			self::migrate_to_4();
		}

		if ( version_compare( $installed_schema, '5', '<' ) ) {
			self::migrate_to_5();
		}

		if ( version_compare( $installed_schema, '6', '<' ) ) {
			self::migrate_to_6();
		}

		if ( version_compare( $installed_schema, '7', '<' ) ) {
			self::migrate_to_7();
		}

		if ( version_compare( $installed_schema, '8', '<' ) ) {
			self::migrate_to_8();
		}

		if ( version_compare( $installed_schema, '9', '<' ) ) {
			self::migrate_to_9();
		}

		if ( version_compare( $installed_schema, '10', '<' ) ) {
			self::migrate_to_10();
		}

		if ( version_compare( $installed_schema, '11', '<' ) ) {
			self::migrate_to_11();
		}

		if ( version_compare( $installed_schema, '12', '<' ) ) {
			self::migrate_to_12();
		}

		Settings::ensure_defaults();
		if ( SFCART_VERSION !== $installed_plugin ) {
			update_option( self::PLUGIN_VERSION_OPTION, SFCART_VERSION, false );
		}
	}

	/**
	 * Establish the first Starfiniti-owned settings schema.
	 */
	private static function migrate_to_1(): void {
		Settings::ensure_defaults();
		update_option( self::SCHEMA_VERSION_OPTION, '1', false );

		Logger::info(
			'Starfiniti Cart migration completed.',
			array(
				'schema_version' => '1',
			)
		);

		/**
		 * Fires after a Starfiniti Cart migration completes for the current site.
		 *
		 * @param string $schema_version Completed schema version.
		 */
		do_action( 'sfcart_migration_completed', '1' );
	}

	/**
	 * Expand the owned document into the validated administration schema.
	 */
	private static function migrate_to_2(): void {
		Settings::ensure_defaults();
		update_option( self::SCHEMA_VERSION_OPTION, '2', false );

		Logger::info(
			'Starfiniti Cart migration completed.',
			array( 'schema_version' => '2' )
		);

		/**
		 * Fires after a Starfiniti Cart migration completes for the current site.
		 *
		 * @param string $schema_version Completed schema version.
		 */
		do_action( 'sfcart_migration_completed', '2' );
	}

	/**
	 * Add owned recommendation presentation and filtering settings.
	 */
	private static function migrate_to_3(): void {
		Settings::ensure_defaults();
		update_option( self::SCHEMA_VERSION_OPTION, '3', false );

		Logger::info(
			'Starfiniti Cart migration completed.',
			array( 'schema_version' => '3' )
		);

		/**
		 * Fires after a Starfiniti Cart migration completes for the current site.
		 *
		 * @param string $schema_version Completed schema version.
		 */
		do_action( 'sfcart_migration_completed', '3' );
	}

	/**
	 * Add the owned threshold reward settings document.
	 */
	private static function migrate_to_4(): void {
		Settings::ensure_defaults();
		update_option( self::SCHEMA_VERSION_OPTION, '4', false );

		Logger::info(
			'Starfiniti Cart migration completed.',
			array( 'schema_version' => '4' )
		);

		/** This action is documented in migrate_to_1(). */
		do_action( 'sfcart_migration_completed', '4' );
	}

	/** Add the owned special add-on presentation and selection schema. */
	private static function migrate_to_5(): void {
		Settings::ensure_defaults();
		update_option( self::SCHEMA_VERSION_OPTION, '5', false );

		Logger::info(
			'Starfiniti Cart migration completed.',
			array( 'schema_version' => '5' )
		);

		/** This action is documented in migrate_to_1(). */
		do_action( 'sfcart_migration_completed', '5' );
	}

	/** Add owned analytics conversion ledger tables. */
	private static function migrate_to_6(): void {
		Tables::install();
		Settings::ensure_defaults();
		update_option( self::SCHEMA_VERSION_OPTION, '6', false );

		Logger::info(
			'Starfiniti Cart migration completed.',
			array( 'schema_version' => '6' )
		);

		/** This action is documented in migrate_to_1(). */
		do_action( 'sfcart_migration_completed', '6' );
	}

	/** Add guided legacy-cart migration state storage and ensure analytics tables exist. */
	private static function migrate_to_7(): void {
		Tables::install();
		Settings::ensure_defaults();
		add_option( 'sfcart_funnelkit_migration_audit', array(), '', false );
		add_option( 'sfcart_funnelkit_migration_state', array(), '', false );
		update_option( self::SCHEMA_VERSION_OPTION, '7', false );

		Logger::info(
			'Starfiniti Cart migration completed.',
			array( 'schema_version' => '7' )
		);

		/** This action is documented in migrate_to_1(). */
		do_action( 'sfcart_migration_completed', '7' );
	}

	/** Add anonymous cart-session events for cart-focused funnel reporting. */
	private static function migrate_to_8(): void {
		Tables::install();
		update_option( self::SCHEMA_VERSION_OPTION, '8', false );

		Logger::info(
			'Starfiniti Cart migration completed.',
			array( 'schema_version' => '8' )
		);

		/** This action is documented in migrate_to_1(). */
		do_action( 'sfcart_migration_completed', '8' );
	}

	/** Add owned cart-icon settings and normalize existing design documents. */
	private static function migrate_to_9(): void {
		Settings::ensure_defaults();
		update_option( self::SCHEMA_VERSION_OPTION, '9', false );

		Logger::info(
			'Starfiniti Cart migration completed.',
			array( 'schema_version' => '9' )
		);

		/** This action is documented in migrate_to_1(). */
		do_action( 'sfcart_migration_completed', '9' );
	}

	/** Add independently configurable recommendation layout and placement. */
	private static function migrate_to_10(): void {
		Settings::ensure_defaults();
		update_option( self::SCHEMA_VERSION_OPTION, '10', false );

		Logger::info(
			'Starfiniti Cart migration completed.',
			array( 'schema_version' => '10' )
		);

		/** This action is documented in migrate_to_1(). */
		do_action( 'sfcart_migration_completed', '10' );
	}

	/** Add carousel recommendations and independently configurable continue-shopping links. */
	private static function migrate_to_11(): void {
		Settings::ensure_defaults();
		update_option( self::SCHEMA_VERSION_OPTION, '11', false );

		Logger::info(
			'Starfiniti Cart migration completed.',
			array( 'schema_version' => '11' )
		);

		/** This action is documented in migrate_to_1(). */
		do_action( 'sfcart_migration_completed', '11' );
	}

	/** Add bounded analytics retention and WordPress privacy integration defaults. */
	private static function migrate_to_12(): void {
		Settings::ensure_defaults();
		update_option( self::SCHEMA_VERSION_OPTION, '12', false );

		Logger::info(
			'Starfiniti Cart migration completed.',
			array( 'schema_version' => '12' )
		);

		/** This action is documented in migrate_to_1(). */
		do_action( 'sfcart_migration_completed', '12' );
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
