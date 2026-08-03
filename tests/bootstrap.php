<?php
/**
 * PHPUnit bootstrap file.
 *
 * Loads the WordPress core test suite (via WP_TESTS_DIR, provided by the wp-env
 * "tests-cli"/"tests-wordpress" services) and this plugin.
 *
 * @package Viget\PostTypeTaxonomySync
 */

// Composer's autoloader (yoast/phpunit-polyfills, etc.).
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain CLI text output, not HTML.
	echo "Could not find {$_tests_dir}/includes/functions.php, have you run the WP core test suite installer?" . PHP_EOL;
	exit( 1 );
}

require_once $_tests_dir . '/includes/functions.php';

// Simulates an admin-context request so is_admin()-gated logic (e.g. Admin's
// term-list/menu filtering) can be exercised in tests. Must be defined before
// the plugin loads below: Admin::init() checks is_admin() at load time.
if ( ! defined( 'WP_ADMIN' ) ) {
	define( 'WP_ADMIN', true );
}

/**
 * Loads the plugin under test.
 *
 * @return void
 */
function _vgptts_manually_load_plugin() {
	require dirname( __DIR__ ) . '/viget-post-type-taxonomy-sync.php';
}
tests_add_filter( 'muplugins_loaded', '_vgptts_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';

// Loaded after WP_UnitTestCase is available, before any suite files reference it.
require __DIR__ . '/VGPTTS_TestCase.php';
