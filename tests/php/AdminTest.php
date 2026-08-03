<?php
/**
 * Tests for the Admin class.
 *
 * @package Viget\PostTypeTaxonomySync
 */

use Viget\PostTypeTaxonomySync\Admin;
use Viget\PostTypeTaxonomySync\Core;

/**
 * Tests for the Admin class.
 */
class AdminTest extends VGPTTS_TestCase {

	/**
	 * Calls a protected/private Admin method via reflection.
	 *
	 * @param string $method Method name.
	 * @param array  $args   Arguments to pass.
	 *
	 * @return mixed
	 */
	protected function call_admin_method( string $method, array $args = [] ) {
		$reflection = new ReflectionMethod( Admin::class, $method );
		$reflection->setAccessible( true );
		return $reflection->invokeArgs( $this->get_admin_instance(), $args );
	}

	/**
	 * Core doesn't expose its private Admin instance publicly, so fetch it via reflection too.
	 *
	 * @return Admin
	 */
	protected function get_admin_instance(): Admin {
		return Admin::get_instance();
	}

	/**
	 * Cleans up superglobals/filters that individual tests set, including in
	 * cases where an expected exception interrupted the test before it could
	 * clean up after itself.
	 */
	public function tear_down() {
		unset( $_POST['taxonomy'], $_GET['post'], $_SERVER['HTTP_REFERER'] );
		parent::tear_down();
	}

	/**
	 * get_synced_taxonomies_for_post_type() returns only taxonomies mapped to the given post type.
	 */
	public function test_get_synced_taxonomies_for_post_type() {
		$this->set_mappings(
			[
				[
					'post_type' => 'post',
					'taxonomy'  => 'post_tag',
				],
				[
					'post_type' => 'page',
					'taxonomy'  => 'category',
				],
			]
		);

		$this->assertSame( [ 'post_tag' ], $this->call_admin_method( 'get_synced_taxonomies_for_post_type', [ 'post' ] ) );
		$this->assertSame( [], $this->call_admin_method( 'get_synced_taxonomies_for_post_type', [ 'attachment' ] ) );
	}

	/**
	 * is_synced_taxonomy() reflects whether a taxonomy is currently mapped.
	 */
	public function test_is_synced_taxonomy() {
		$this->set_mappings(
			[
				[
					'post_type' => 'post',
					'taxonomy'  => 'post_tag',
				],
			]
		);

		$this->assertTrue( $this->call_admin_method( 'is_synced_taxonomy', [ 'post_tag' ] ) );
		$this->assertFalse( $this->call_admin_method( 'is_synced_taxonomy', [ 'category' ] ) );
	}

	/**
	 * get_post_id_from_referer() extracts the post ID from an admin edit-screen referer URL.
	 */
	public function test_get_post_id_from_referer() {
		$_SERVER['HTTP_REFERER'] = admin_url( 'post.php?post=42&action=edit' );

		$this->assertSame( 42, $this->call_admin_method( 'get_post_id_from_referer' ) );

		unset( $_SERVER['HTTP_REFERER'] );
		$this->assertNull( $this->call_admin_method( 'get_post_id_from_referer' ) );
	}

	/**
	 * get_taxonomy_from_rest_route() resolves the taxonomy from a REST route's final segment.
	 */
	public function test_get_taxonomy_from_rest_route_matches_taxonomy_name() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/post_tag' );

		$this->assertSame( 'post_tag', $this->call_admin_method( 'get_taxonomy_from_rest_route', [ $request ] ) );
	}

	/**
	 * get_taxonomy_from_rest_route() falls back to matching a taxonomy's rest_base.
	 */
	public function test_get_taxonomy_from_rest_route_matches_rest_base() {
		register_taxonomy(
			'vgptts_rest_tax',
			'post',
			[
				'show_in_rest' => true,
				'rest_base'    => 'vgptts-custom-base',
			]
		);

		$request = new WP_REST_Request( 'GET', '/wp/v2/vgptts-custom-base' );

		$this->assertSame( 'vgptts_rest_tax', $this->call_admin_method( 'get_taxonomy_from_rest_route', [ $request ] ) );

		unregister_taxonomy( 'vgptts_rest_tax' );
	}

	/**
	 * maybe_block_ajax_add_tag_for_synced_taxonomy() is a no-op for a non-synced taxonomy.
	 *
	 * (The blocking case is covered by AdminAjaxTest, which needs WP_Ajax_UnitTestCase's
	 * die-handler wiring rather than this class's plain WP_UnitTestCase base.)
	 */
	public function test_maybe_block_ajax_add_tag_for_synced_taxonomy_ignores_unsynced_taxonomy() {
		$this->set_mappings( [] );
		$_POST['taxonomy'] = 'post_tag';

		// Should not throw, since post_tag is not a synced taxonomy in this test.
		$this->get_admin_instance()->maybe_block_ajax_add_tag_for_synced_taxonomy();
		unset( $_POST['taxonomy'] );

		$this->assertTrue( true );
	}

	/**
	 * exclude_synced_term_from_terms_list() excludes the current post's own synced term.
	 */
	public function test_exclude_synced_term_from_terms_list_excludes_own_term() {
		$this->set_mappings(
			[
				[
					'post_type' => 'post',
					'taxonomy'  => 'post_tag',
				],
			]
		);

		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$term_id = (int) get_post_meta( $post_id, Core::POST_META_KEY, true );

		set_current_screen( 'post' );
		$_GET['post'] = $post_id;

		$args = $this->get_admin_instance()->exclude_synced_term_from_terms_list( [], [ 'post_tag' ] );

		unset( $_GET['post'] );
		set_current_screen( 'front' );

		$this->assertContains( $term_id, $args['exclude'] );
	}

	/**
	 * exclude_synced_term_in_rest_terms_query() excludes the current post's own synced term.
	 */
	public function test_exclude_synced_term_in_rest_terms_query_excludes_own_term() {
		$this->set_mappings(
			[
				[
					'post_type' => 'post',
					'taxonomy'  => 'post_tag',
				],
			]
		);

		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$term_id = (int) get_post_meta( $post_id, Core::POST_META_KEY, true );

		$_SERVER['HTTP_REFERER'] = admin_url( "post.php?post={$post_id}&action=edit" );
		$request                 = new WP_REST_Request( 'GET', '/wp/v2/tags' );

		$args = $this->get_admin_instance()->exclude_synced_term_in_rest_terms_query( [], $request );

		unset( $_SERVER['HTTP_REFERER'] );

		$this->assertContains( $term_id, $args['exclude'] );
	}
}
