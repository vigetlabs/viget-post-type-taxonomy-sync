<?php
/**
 * Tests for Settings' remove-mapping ajax action.
 *
 * Uses WP_Ajax_UnitTestCase to catch wp_send_json_*()'s wp_die() as an exception.
 *
 * @package Viget\PostTypeTaxonomySync
 */

use Viget\PostTypeTaxonomySync\Core;

/**
 * Tests for Settings' remove-mapping ajax action.
 */
class SettingsAjaxTest extends WP_Ajax_UnitTestCase {

	/**
	 * Saves two mappings and sets up an administrator request.
	 */
	public function set_up() {
		parent::set_up();

		update_option(
			Core::OPTION_NAME,
			[
				'mappings' => [
					[
						'post_type' => 'post',
						'taxonomy'  => 'post_tag',
					],
					[
						'post_type' => 'page',
						'taxonomy'  => 'category',
					],
				],
			]
		);

		$this->_setRole( 'administrator' );
		$_POST['nonce'] = wp_create_nonce( 'vgptts_remove_mapping' );
	}

	/**
	 * Resets the mappings option after each test.
	 */
	public function tear_down() {
		update_option( Core::OPTION_NAME, [ 'mappings' => [] ] );
		parent::tear_down();
	}

	/**
	 * Removing a saved mapping deletes it from the option and keeps the rest.
	 */
	public function test_remove_mapping_deletes_saved_mapping() {
		$response = $this->remove( 'post', 'post_tag' );

		$this->assertTrue( $response['success'] );
		$this->assertSame( 'Mapping removed: Post → Tag.', $response['data']['message'] );
		$this->assertSame(
			[
				[
					'post_type' => 'page',
					'taxonomy'  => 'category',
				],
			],
			get_option( Core::OPTION_NAME )['mappings']
		);
	}

	/**
	 * A mapping that isn't saved returns an error and changes nothing.
	 */
	public function test_remove_mapping_rejects_unknown_mapping() {
		$response = $this->remove( 'post', 'category' );

		$this->assertFalse( $response['success'] );
		$this->assertCount( 2, get_option( Core::OPTION_NAME )['mappings'] );
	}

	/**
	 * Users without manage_options can't remove mappings.
	 */
	public function test_remove_mapping_requires_manage_options() {
		$this->_setRole( 'editor' );
		$_POST['nonce'] = wp_create_nonce( 'vgptts_remove_mapping' );

		$response = $this->remove( 'post', 'post_tag' );

		$this->assertFalse( $response['success'] );
		$this->assertCount( 2, get_option( Core::OPTION_NAME )['mappings'] );
	}

	/**
	 * A bad nonce stops the request before anything changes.
	 */
	public function test_remove_mapping_requires_nonce() {
		$_POST['nonce'] = 'bad';

		try {
			$this->_handleAjax( 'vgptts_remove_mapping' );
			$this->fail( 'Expected WPAjaxDieStopException was not thrown.' );
		} catch ( WPAjaxDieStopException $exception ) {
			$this->assertSame( '-1', $exception->getMessage() );
		}

		$this->assertCount( 2, get_option( Core::OPTION_NAME )['mappings'] );
	}

	/**
	 * Calls the remove-mapping action and returns the decoded response.
	 *
	 * @param string $post_type Post type slug.
	 * @param string $taxonomy  Taxonomy slug.
	 *
	 * @return array
	 */
	private function remove( string $post_type, string $taxonomy ): array {
		$_POST['post_type'] = $post_type;
		$_POST['taxonomy']  = $taxonomy;

		try {
			$this->_handleAjax( 'vgptts_remove_mapping' );
			$this->fail( 'Expected WPAjaxDieContinueException was not thrown.' );
		} catch ( WPAjaxDieContinueException $exception ) {
			unset( $exception );
		}

		return json_decode( $this->_last_response, true );
	}
}
