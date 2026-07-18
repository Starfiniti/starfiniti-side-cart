<?php
/**
 * Starfiniti Cart uninstall handler.
 *
 * Plugin data is deliberately retained unless delete_data_on_uninstall was
 * explicitly enabled in the sfcart_settings option.
 *
 * @package StarfinitiCart
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/src/Settings.php';
require_once __DIR__ . '/src/Lifecycle/Installer.php';
require_once __DIR__ . '/src/Lifecycle/Uninstaller.php';

Starfiniti\Cart\Lifecycle\Uninstaller::run();
