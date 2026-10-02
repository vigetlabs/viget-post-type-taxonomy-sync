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
	 * Registers mappings in code through vgptts_registered_mappings and
	 * re-registers Sync's dynamic hooks.
	 *
	 * @param array $mappings Array of ['post_type' => ..., 'taxonomy' => ...] pairs.
	 *
	 * @return void
	 */
	protected function set_registered_mappings( array $mappings ): void {
		remove_all_filters( 'vgptts_registered_mappings' );
		add_filter(
			'vgptts_registered_mappings',
			static fn( array $registered ): array => array_merge( $registered, $mappings )
		);
		vgptts()->sync->register_hooks();
	}

	/**
	 * Resets the mappings option and registered mappings after each test.
	 *
	 * @return void
	 */
	public function tear_down() {
		update_option( Core::OPTION_NAME, [ 'mappings' => [] ] );
		remove_all_filters( 'vgptts_registered_mappings' );
		parent::tear_down();
	}
}
