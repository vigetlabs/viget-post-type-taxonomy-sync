<?php
/**
 * Tests for the REST class.
 *
 * @package Viget\PostTypeTaxonomySync
 */

use Viget\PostTypeTaxonomySync\Core;
use Viget\PostTypeTaxonomySync\REST;

/**
 * Tests for the REST class.
 */
class RestTest extends WP_Test_REST_TestCase {

	/**
	 * The REST server.
	 *
	 * @var WP_REST_Server
	 */
	protected $server;

	/**
	 * Boots a fresh REST server for each test, matching core's own REST test convention.
	 */
	public function set_up() {
		parent::set_up();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init', $this->server );
	}

	/**
	 * Tears down the REST server and resets mappings.
	 */
	public function tear_down() {
		global $wp_rest_server;
		$wp_rest_server = null;

		update_option( Core::OPTION_NAME, [ 'mappings' => [] ] );

		parent::tear_down();
	}

	/**
	 * Sets the plugin's mappings and re-registers Sync's dynamic hooks.
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
	 * GET /posts/{id}/synced-term returns the term synced to a post.
	 */
	public function test_get_synced_term_for_post_returns_term() {
		$this->set_mappings(
			[
				[
					'post_type' => 'post',
					'taxonomy'  => 'post_tag',
				],
			]
		);

		$post_id = self::factory()->post->create(
			[
				'post_status' => 'publish',
				'post_title'  => 'Synced Post',
			]
		);
		$term_id = (int) get_post_meta( $post_id, Core::POST_META_KEY, true );

		$request  = new WP_REST_Request( 'GET', "/vgptts/v1/posts/{$post_id}/synced-term" );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $term_id, $data['id'] );
		$this->assertSame( 'post_tag', $data['taxonomy'] );
		$this->assertSame( 'Synced Post', $data['name'] );
	}

	/**
	 * GET /posts/{id}/synced-term 404s when the post type isn't mapped.
	 */
	public function test_get_synced_term_for_post_404_when_not_mapped() {
		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );

		$request  = new WP_REST_Request( 'GET', "/vgptts/v1/posts/{$post_id}/synced-term" );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'vgptts_no_mapping', $response, 404 );
	}

	/**
	 * GET /posts/{id}/synced-term 404s for a nonexistent post.
	 */
	public function test_get_synced_term_for_post_404_when_post_missing() {
		$request  = new WP_REST_Request( 'GET', '/vgptts/v1/posts/999999/synced-term' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'vgptts_post_not_found', $response, 404 );
	}

	/**
	 * GET /terms/{id}/synced-post returns the post synced to a term.
	 */
	public function test_get_synced_post_for_term_returns_post() {
		$this->set_mappings(
			[
				[
					'post_type' => 'post',
					'taxonomy'  => 'post_tag',
				],
			]
		);

		$term    = wp_insert_term( 'Synced Term', 'post_tag' );
		$post_id = (int) get_term_meta( $term['term_id'], Core::TERM_META_KEY, true );

		$request  = new WP_REST_Request( 'GET', "/vgptts/v1/terms/{$term['term_id']}/synced-post" );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $post_id, $data['id'] );
		$this->assertSame( 'post', $data['post_type'] );
		$this->assertSame( 'Synced Term', $data['title'] );
	}

	/**
	 * GET /terms/{id}/synced-post 404s when the term has no synced post.
	 */
	public function test_get_synced_post_for_term_404_when_unlinked() {
		$term_id = self::factory()->term->create( [ 'taxonomy' => 'post_tag' ] );

		$request  = new WP_REST_Request( 'GET', "/vgptts/v1/terms/{$term_id}/synced-post" );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'vgptts_no_synced_post', $response, 404 );
	}

	/**
	 * GET /terms/{id}/synced-post 404s for a nonexistent term.
	 */
	public function test_get_synced_post_for_term_404_when_term_missing() {
		$request  = new WP_REST_Request( 'GET', '/vgptts/v1/terms/999999/synced-post' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'vgptts_term_not_found', $response, 404 );
	}

	/**
	 * GET /posts/{id}/related returns posts linked via a term assigned to the given post.
	 */
	public function test_get_related_posts_returns_related_posts() {
		$this->set_mappings(
			[
				[
					'post_type' => 'post',
					'taxonomy'  => 'post_tag',
				],
			]
		);

		$other_post_id = self::factory()->post->create(
			[
				'post_status' => 'publish',
				'post_title'  => 'Related Post',
			]
		);
		$other_term_id = (int) get_post_meta( $other_post_id, Core::POST_META_KEY, true );

		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		wp_set_post_terms( $post_id, [ $other_term_id ], 'post_tag' );

		$request = new WP_REST_Request( 'GET', "/vgptts/v1/posts/{$post_id}/related" );
		$request->set_param( 'taxonomy', 'post_tag' );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 1, $data );
		$this->assertSame( $other_post_id, $data[0]['id'] );
		$this->assertSame( 'Related Post', $data[0]['title'] );
	}

	/**
	 * GET /posts/{id}/related requires the taxonomy query param.
	 */
	public function test_get_related_posts_requires_taxonomy_param() {
		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );

		$request  = new WP_REST_Request( 'GET', "/vgptts/v1/posts/{$post_id}/related" );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
	}

	/**
	 * GET /posts/{id}/related rejects a nonexistent taxonomy.
	 */
	public function test_get_related_posts_rejects_invalid_taxonomy() {
		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );

		$request = new WP_REST_Request( 'GET', "/vgptts/v1/posts/{$post_id}/related" );
		$request->set_param( 'taxonomy', 'does_not_exist' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
	}

	/**
	 * A logged-out request cannot read the synced term for a private post.
	 */
	public function test_get_synced_term_for_post_denies_anonymous_access_to_private_post() {
		$this->set_mappings(
			[
				[
					'post_type' => 'post',
					'taxonomy'  => 'post_tag',
				],
			]
		);

		$post_id = self::factory()->post->create( [ 'post_status' => 'private' ] );

		wp_set_current_user( 0 );

		$request  = new WP_REST_Request( 'GET', "/vgptts/v1/posts/{$post_id}/synced-term" );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'vgptts_cannot_read_post', $response, 401 );
	}

	/**
	 * An authorized user can read the synced term for a private post they can edit.
	 */
	public function test_get_synced_term_for_post_allows_editor_access_to_private_post() {
		$this->set_mappings(
			[
				[
					'post_type' => 'post',
					'taxonomy'  => 'post_tag',
				],
			]
		);

		$editor  = self::factory()->user->create( [ 'role' => 'editor' ] );
		$post_id = self::factory()->post->create(
			[
				'post_status' => 'private',
				'post_author' => $editor,
			]
		);

		wp_set_current_user( $editor );

		$request  = new WP_REST_Request( 'GET', "/vgptts/v1/posts/{$post_id}/synced-term" );
		$response = $this->server->dispatch( $request );

		// A private post with no synced term yet still 404s, but on the *term*, not the permission check.
		$this->assertErrorResponse( 'vgptts_no_synced_term', $response, 404 );
	}
}
