<?php
/**
 * Load the Pkart licensing SDK.
 *
 * Usage in the main plugin file:
 *
 *   require_once __DIR__ . '/includes/licensing/load.php';
 *
 *
 * @package Pkart\LicenseManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-package-config.php';
require_once __DIR__ . '/class-api.php';
require_once __DIR__ . '/class-licensing.php';
require_once __DIR__ . '/class-admin.php';

/**
 * Initialize the Pkart licensing SDK.
 * init() finds the main plugin file from package.json.
 */
\Pkart\LicenseManager\V1\Licensing::init( __FILE__ );

