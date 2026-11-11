<?php
/**
 * Tests for dismissing a hierarchy warning over AJAX.
 *
 * @package Viget\PostTypeTaxonomySync
 */

use Viget\PostTypeTaxonomySync\Admin;
use Viget\PostTypeTaxonomySync\Core;

/**
 * Tests for dismissing a hierarchy warning over AJAX.
 */
class HierarchyAjaxTest extends WP_Ajax_UnitTestCase {

	/**
	 * Resets the mappings option after each test.
	 */
	public function tear_down() {
		update_option( Core::OPTION_NAME, [ 'mappings' => [] ] );
		parent::tear_down();
	}

	/**
	 * Posts a dismissal and returns the decoded response.
	 *
	 * @param string $keys Comma-separated warning keys.
	 *
	 * @return array
	 */
	private function dismiss( string $keys ): array {
		$this->_setRole( 'administrator' );
		update_option(
			Core::OPTION_NAME,
			[
				'mappings' => [
					[
						'post_type' => 'post',
						'taxonomy'  => 'category',
					],
				],
			]
		);

		$_POST['nonce'] = wp_create_nonce( 'vgptts_dismiss_hierarchy_warning' );
		$_POST['keys']  = $keys;

		try {
			$this->_handleAjax( 'vgptts_dismiss_hierarchy_warning' );
		} catch ( WPAjaxDieContinueException $exception ) {
			unset( $exception );
		}

		return json_decode( $this->_last_response, true );
	}

	/**
	 * Dismissing the warning stores its current keys for the current user, once.
	 */
	public function test_dismiss_hierarchy_warning() {
		$this->assertTrue( $this->dismiss( 'post:category,post:category' )['success'] );
		$this->assertSame( [ 'post:category' ], get_user_meta( get_current_user_id(), Admin::DISMISSED_WARNINGS_META_KEY, true ) );
	}

	/**
	 * Keys that aren't a current warning are ignored.
	 */
	public function test_dismiss_ignores_unknown_keys() {
		$this->assertFalse( $this->dismiss( 'page:post_tag,anything:else' )['success'] );
		$this->assertSame( '', get_user_meta( get_current_user_id(), Admin::DISMISSED_WARNINGS_META_KEY, true ) );
	}
}
