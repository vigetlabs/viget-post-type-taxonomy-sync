<?php
/**
 * Shared base test case.
 *
 * @package Viget\PostTypeTaxonomySync
 */

use Viget\PostTypeTaxonomySync\Core;

/**
 * Shared base test case with helpers for configuring sync mappings.
 */
abstract class VGPTTS_TestCase extends WP_UnitTestCase {

	/**
	 * Sets the plugin's mappings option and re-registers Sync's dynamic hooks
	 * so the new mappings take effect within the same request/test.
	 *
	 * @param array $mappings Array of ['post_type' => ..., 'taxonomy' => ...] pairs.
	 *
	 * @return void
	 */
	protected function set_mappings( array $mappings ): void {
		update_option( Core::OPTION_NAME, [ 'mappings' => $mappings ] );
		vgptts()->sync->register_hooks();
	}

	/**
	 * Resets the mappings option after each test.
	 *
	 * @return void
	 */
	public function tear_down() {
		update_option( Core::OPTION_NAME, [ 'mappings' => [] ] );
		parent::tear_down();
	}
}
