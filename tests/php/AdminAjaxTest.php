<?php
/**
 * Tests for Admin's wp_ajax_add-tag interception.
 *
 * Uses WP_Ajax_UnitTestCase rather than the shared VGPTTS_TestCase (plain
 * WP_UnitTestCase) because it needs that base class's wp_die_ajax_handler
 * wiring to safely catch wp_send_json_error()'s wp_die() call as an
 * exception instead of actually terminating the test run.
 *
 * @package Viget\PostTypeTaxonomySync
 */

use Viget\PostTypeTaxonomySync\Core;

/**
 * Tests for Admin's wp_ajax_add-tag interception.
 */
class AdminAjaxTest extends WP_Ajax_UnitTestCase {

	/**
	 * Resets the mappings option after each test.
	 */
	public function tear_down() {
		update_option( Core::OPTION_NAME, [ 'mappings' => [] ] );
		parent::tear_down();
	}

	/**
	 * The quick-add "add-tag" ajax action is blocked for a synced taxonomy.
	 */
	public function test_add_tag_ajax_is_blocked_for_synced_taxonomy() {
		update_option(
			Core::OPTION_NAME,
			[
				'mappings' => [
					[
						'post_type' => 'post',
						'taxonomy'  => 'post_tag',
					],
				],
			]
		);
		vgptts()->sync->register_hooks();

		$_POST['taxonomy'] = 'post_tag';

		// wp_send_json_error() echoes a JSON body before calling wp_die(), so
		// WP_Ajax_UnitTestCase treats this as "normal termination with output" and
		// throws WPAjaxDieContinueException (not ...DieStopException, which is only
		// for a die with no prior output) — capturing the body into _last_response.
		try {
			$this->_handleAjax( 'add-tag' );
			$this->fail( 'Expected WPAjaxDieContinueException was not thrown.' );
		} catch ( WPAjaxDieContinueException $exception ) {
			$response = json_decode( $this->_last_response, true );
		}

		$this->assertFalse( $response['success'] );
		$this->assertSame(
			'This taxonomy is managed automatically and does not allow adding new terms here.',
			$response['data']['message']
		);
	}
}
