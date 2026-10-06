<?php
/**
 * Tests for trashed and unpublished synced posts.
 *
 * @package Viget\PostTypeTaxonomySync
 */

/**
 * A trashed or unpublished post keeps its term and relationships, hidden until it's published again.
 */
class UnpublishedPostsTest extends VGPTTS_TestCase {

	/**
	 * Map pages to tags, so tagging a post relates it to a page.
	 */
	public function set_up() {
		parent::set_up();

		$this->set_mappings(
			[
				[
					'post_type' => 'page',
					'taxonomy'  => 'post_tag',
				],
			]
		);
	}

	/**
	 * Clean up the request state the admin tests set.
	 */
	public function tear_down() {
		unset( $_GET['post'], $_SERVER['HTTP_REFERER'] );
		set_current_screen( 'front' );
		parent::tear_down();
	}

	/**
	 * Create a published page and return its synced tag ID.
	 *
	 * @param int|null $page_id Set to the new page's ID.
	 *
	 * @return int
	 */
	private function page_tag( ?int &$page_id = null ): int {
		$page_id = self::factory()->post->create(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
			]
		);

		return (int) vgptts()->get_term_id_for_post( $page_id, 'post_tag' );
	}

	/**
	 * Trashing a page keeps its tag and the posts related to it, and restoring brings it back.
	 */
	public function test_trashing_keeps_the_term_and_relationships() {
		$tag_id  = $this->page_tag( $page_id );
		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		wp_set_object_terms( $post_id, [ $tag_id ], 'post_tag' );

		wp_trash_post( $page_id );

		$this->assertNotNull( term_exists( $tag_id, 'post_tag' ) );
		$this->assertTrue( has_term( $tag_id, 'post_tag', $post_id ) );
		$this->assertSame( [], vgptts()->get_related_post_ids_for_post( $post_id, 'post_tag' ) );

		wp_untrash_post( $page_id );
		wp_publish_post( $page_id );

		$this->assertSame( $tag_id, (int) vgptts()->get_term_id_for_post( $page_id, 'post_tag' ) );
		$this->assertSame( [ $page_id ], vgptts()->get_related_post_ids_for_post( $post_id, 'post_tag' ) );
	}

	/**
	 * Deleting a trashed page permanently removes its tag.
	 */
	public function test_deleting_removes_the_term() {
		$tag_id = $this->page_tag( $page_id );

		wp_trash_post( $page_id );
		wp_delete_post( $page_id, true );

		$this->assertNull( term_exists( $tag_id, 'post_tag' ) );
	}

	/**
	 * Trashed and unpublished pages' tags are listed as unpublished, published ones aren't.
	 */
	public function test_get_unpublished_term_ids() {
		$published = $this->page_tag();
		$trashed   = $this->page_tag( $trashed_page );
		$draft     = $this->page_tag( $draft_page );

		wp_trash_post( $trashed_page );
		wp_update_post(
			[
				'ID'          => $draft_page,
				'post_status' => 'draft',
			]
		);

		$ids = vgptts()->get_unpublished_term_ids( 'post_tag' );

		$this->assertEqualsCanonicalizing( [ $trashed, $draft ], $ids );
		$this->assertNotContains( $published, $ids );
		$this->assertSame( [], vgptts()->get_unpublished_term_ids( 'category' ) );
	}

	/**
	 * The block editor's term list leaves out a trashed page's tag, unless the post already has it.
	 */
	public function test_rest_terms_query_hides_unpublished_terms_unless_assigned() {
		$trashed = $this->page_tag( $page_id );
		$visible = $this->page_tag();
		wp_trash_post( $page_id );

		$request = new WP_REST_Request( 'GET', '/wp/v2/tags' );
		$admin   = \Viget\PostTypeTaxonomySync\Admin::get_instance();

		$args = $admin->exclude_synced_term_in_rest_terms_query( [], $request );
		$this->assertContains( $trashed, $args['exclude'] );
		$this->assertNotContains( $visible, $args['exclude'] );

		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		wp_set_object_terms( $post_id, [ $trashed ], 'post_tag' );
		$_SERVER['HTTP_REFERER'] = admin_url( "post.php?post={$post_id}&action=edit" );

		$args = $admin->exclude_synced_term_in_rest_terms_query( [], $request );
		$this->assertNotContains( $trashed, $args['exclude'] ?? [] );
	}

	/**
	 * The classic meta box leaves out a trashed page's tag, unless the post already has it.
	 */
	public function test_terms_list_hides_unpublished_terms_unless_assigned() {
		$trashed = $this->page_tag( $page_id );
		wp_trash_post( $page_id );

		$other  = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$tagged = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		wp_set_object_terms( $tagged, [ $trashed ], 'post_tag' );

		$admin = \Viget\PostTypeTaxonomySync\Admin::get_instance();
		set_current_screen( 'post' );

		$_GET['post'] = $other;
		$this->assertContains( $trashed, $admin->exclude_synced_term_from_terms_list( [], [ 'post_tag' ] )['exclude'] );

		$_GET['post'] = $tagged;
		$this->assertNotContains( $trashed, $admin->exclude_synced_term_from_terms_list( [], [ 'post_tag' ] )['exclude'] ?? [] );
	}
}
