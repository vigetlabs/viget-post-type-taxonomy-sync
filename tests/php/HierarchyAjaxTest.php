<?php
/**
 * Tests for dismissing a hierarchy warning over AJAX.
 *
 * @package Viget\PostTypeTaxonomySync
 */

use Viget\PostTypeTaxonomySync\Admin;

/**
 * Tests for dismissing a hierarchy warning over AJAX.
 */
class HierarchyAjaxTest extends WP_Ajax_UnitTestCase {

	/**
	 * Dismissing a warning stores its key for the current user.
	 */
	public function test_dismiss_hierarchy_warning() {
		$this->_setRole( 'administrator' );

		$_POST['nonce'] = wp_create_nonce( 'vgptts_dismiss_hierarchy_warning' );
		$_POST['key']   = 'post:category';

		try {
			$this->_handleAjax( 'vgptts_dismiss_hierarchy_warning' );
		} catch ( WPAjaxDieContinueException $exception ) {
			unset( $exception );
		}

		$this->assertTrue( json_decode( $this->_last_response, true )['success'] );
		$this->assertSame( [ 'post:category' ], get_user_meta( get_current_user_id(), Admin::DISMISSED_WARNINGS_META_KEY, true ) );
	}
}
