<?php
/**
 * Tests for the Sync class.
 *
 * @package Viget\PostTypeTaxonomySync
 */

use Viget\PostTypeTaxonomySync\Core;

/**
 * Tests for the Sync class.
 */
class SyncTest extends VGPTTS_TestCase {

	/**
	 * Set up: register a hierarchical taxonomy for the "page" post type, mirroring what
	 * the plugin needs to exercise its hierarchical-sync branch (core ships no
	 * hierarchical taxonomy attached to "page" by default).
	 */
	public function set_up() {
		parent::set_up();
		register_taxonomy( 'vgptts_section', 'page', [ 'hierarchical' => true ] );
	}

	/**
	 * Tear down: unregister the test taxonomy.
	 */
	public function tear_down() {
		unregister_taxonomy( 'vgptts_section' );
		parent::tear_down();
	}

	/**
	 * Publishing a post creates a matching term and links both directions.
	 */
	public function test_post_save_creates_term() {
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
				'post_title'  => 'Sync Me',
			]
		);

		$term_id = (int) get_post_meta( $post_id, Core::POST_META_KEY, true );
		$this->assertNotEmpty( $term_id );

		$term = get_term( $term_id, 'post_tag' );
		$this->assertSame( 'Sync Me', $term->name );
		$this->assertSame( (string) $post_id, get_term_meta( $term_id, Core::TERM_META_KEY, true ) );
	}

	/**
	 * Updating a post's title updates the linked term's name.
	 */
	public function test_post_save_updates_existing_term() {
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
				'post_title'  => 'Original',
			]
		);
		$term_id = (int) get_post_meta( $post_id, Core::POST_META_KEY, true );

		wp_update_post(
			[
				'ID'         => $post_id,
				'post_title' => 'Updated',
			]
		);

		$this->assertSame( 'Updated', get_term( $term_id, 'post_tag' )->name );
	}

	/**
	 * Draft posts do not get a synced term.
	 */
	public function test_post_save_skips_non_publish_status() {
		$this->set_mappings(
			[
				[
					'post_type' => 'post',
					'taxonomy'  => 'post_tag',
				],
			]
		);

		$post_id = self::factory()->post->create( [ 'post_status' => 'draft' ] );

		$this->assertEmpty( get_post_meta( $post_id, Core::POST_META_KEY, true ) );
	}

	/**
	 * Deleting a post removes its linked term.
	 */
	public function test_post_delete_removes_term() {
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

		wp_delete_post( $post_id, true );

		$this->assertNull( get_term( $term_id, 'post_tag' ) );
	}

	/**
	 * Regression test: creating a term creates a matching post and links both directions.
	 *
	 * WordPress core's `created_{$taxonomy}` hook does not reliably pass the taxonomy
	 * slug as its third argument across versions (it may pass the term args array
	 * instead). Sync must use the generic `saved_term` hook instead, which does.
	 */
	public function test_term_save_creates_post() {
		$this->set_mappings(
			[
				[
					'post_type' => 'post',
					'taxonomy'  => 'post_tag',
				],
			]
		);

		$term    = wp_insert_term( 'New Term', 'post_tag' );
		$term_id = $term['term_id'];

		$post_id = (int) get_term_meta( $term_id, Core::TERM_META_KEY, true );
		$this->assertNotEmpty( $post_id, 'Term creation should sync a post; check Sync::register_hooks() uses saved_term.' );

		$post = get_post( $post_id );
		$this->assertSame( 'New Term', $post->post_title );
		$this->assertSame( 'post', $post->post_type );
		$this->assertSame( 'publish', $post->post_status );
		$this->assertSame( $term_id, (int) get_post_meta( $post_id, Core::POST_META_KEY, true ) );
	}

	/**
	 * Editing a term updates the linked post's title.
	 */
	public function test_term_save_updates_existing_post() {
		$this->set_mappings(
			[
				[
					'post_type' => 'post',
					'taxonomy'  => 'post_tag',
				],
			]
		);

		$term    = wp_insert_term( 'Original Term', 'post_tag' );
		$term_id = $term['term_id'];
		$post_id = (int) get_term_meta( $term_id, Core::TERM_META_KEY, true );

		wp_update_term( $term_id, 'post_tag', [ 'name' => 'Renamed Term' ] );

		$this->assertSame( 'Renamed Term', get_post( $post_id )->post_title );
	}

	/**
	 * Regression test: deleting a term removes the linked post.
	 *
	 * WordPress core deletes all term meta (including the post-link meta this plugin
	 * stores) before the `delete_term`/`delete_{$taxonomy}` hooks fire, so by then the
	 * linked post ID is already gone. Sync must use `pre_delete_term` instead, which
	 * fires before any deletion happens.
	 */
	public function test_term_delete_removes_post() {
		$this->set_mappings(
			[
				[
					'post_type' => 'post',
					'taxonomy'  => 'post_tag',
				],
			]
		);

		$term    = wp_insert_term( 'To Delete', 'post_tag' );
		$term_id = $term['term_id'];
		$post_id = (int) get_term_meta( $term_id, Core::TERM_META_KEY, true );
		$this->assertNotEmpty( $post_id );

		wp_delete_term( $term_id, 'post_tag' );

		$this->assertNull( get_post( $post_id ), 'Deleting the term should delete its linked post; check Sync uses pre_delete_term.' );
	}

	/**
	 * A child page's synced term is created under the parent page's synced term.
	 */
	public function test_hierarchical_post_parent_syncs_to_term_parent() {
		$this->set_mappings(
			[
				[
					'post_type' => 'page',
					'taxonomy'  => 'vgptts_section',
				],
			]
		);

		$parent_id = self::factory()->post->create(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
			]
		);
		$child_id  = self::factory()->post->create(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_parent' => $parent_id,
			]
		);

		$parent_term_id = (int) get_post_meta( $parent_id, Core::POST_META_KEY, true );
		$child_term_id  = (int) get_post_meta( $child_id, Core::POST_META_KEY, true );

		$this->assertSame( $parent_term_id, get_term( $child_term_id, 'vgptts_section' )->parent );
	}

	/**
	 * A child term's synced post is created as a child of the parent term's synced post.
	 */
	public function test_hierarchical_term_parent_syncs_to_post_parent() {
		$this->set_mappings(
			[
				[
					'post_type' => 'page',
					'taxonomy'  => 'vgptts_section',
				],
			]
		);

		$parent_term = wp_insert_term( 'Parent Term', 'vgptts_section' );
		$child_term  = wp_insert_term( 'Child Term', 'vgptts_section', [ 'parent' => $parent_term['term_id'] ] );

		$parent_post_id = (int) get_term_meta( $parent_term['term_id'], Core::TERM_META_KEY, true );
		$child_post_id  = (int) get_term_meta( $child_term['term_id'], Core::TERM_META_KEY, true );

		$this->assertSame( $parent_post_id, get_post( $child_post_id )->post_parent );
	}

	/**
	 * sync_terms() backfills a term for a post that predates the mapping.
	 */
	public function test_sync_terms_backfills_missing_terms() {
		// No mapping registered yet: this post won't get an automatic term.
		$post_id = self::factory()->post->create(
			[
				'post_status' => 'publish',
				'post_title'  => 'Preexisting',
			]
		);
		$this->assertEmpty( get_post_meta( $post_id, Core::POST_META_KEY, true ) );

		$this->set_mappings(
			[
				[
					'post_type' => 'post',
					'taxonomy'  => 'post_tag',
				],
			]
		);
		vgptts()->sync->sync_terms( 'post', 'post_tag' );

		$term_id = (int) get_post_meta( $post_id, Core::POST_META_KEY, true );
		$this->assertNotEmpty( $term_id );
		$this->assertSame( 'Preexisting', get_term( $term_id, 'post_tag' )->name );
	}

	/**
	 * sync_terms() removes a term whose linked post no longer exists.
	 */
	public function test_sync_terms_removes_orphaned_terms() {
		global $wpdb;

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

		// Simulate the post having been removed by something other than this plugin
		// (bypassing wp_delete_post(), which would otherwise clean up the term itself).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Intentional: simulating data loss outside the plugin's own hooks.
		$wpdb->delete( $wpdb->posts, [ 'ID' => $post_id ] );
		clean_post_cache( $post_id );

		vgptts()->sync->sync_terms( 'post', 'post_tag' );

		$this->assertNull( get_term( $term_id, 'post_tag' ) );
	}

	/**
	 * The vgptts_synced_term_args filter can modify term creation/update args.
	 */
	public function test_vgptts_synced_term_args_filter() {
		$this->set_mappings(
			[
				[
					'post_type' => 'post',
					'taxonomy'  => 'post_tag',
				],
			]
		);

		$add_description = static function ( array $args ): array {
			$args['description'] = 'Injected by filter';
			return $args;
		};
		add_filter( 'vgptts_synced_term_args', $add_description );

		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$term_id = (int) get_post_meta( $post_id, Core::POST_META_KEY, true );

		$this->assertSame( 'Injected by filter', get_term( $term_id, 'post_tag' )->description );

		remove_filter( 'vgptts_synced_term_args', $add_description );
	}

	/**
	 * The vgptts_synced_post_args filter can modify post creation/update args.
	 */
	public function test_vgptts_synced_post_args_filter() {
		$this->set_mappings(
			[
				[
					'post_type' => 'post',
					'taxonomy'  => 'post_tag',
				],
			]
		);

		$add_excerpt = static function ( array $args ): array {
			$args['post_excerpt'] = 'Injected by filter';
			return $args;
		};
		add_filter( 'vgptts_synced_post_args', $add_excerpt );

		$term    = wp_insert_term( 'Filtered Term', 'post_tag' );
		$post_id = (int) get_term_meta( $term['term_id'], Core::TERM_META_KEY, true );

		$this->assertSame( 'Injected by filter', get_post( $post_id )->post_excerpt );

		remove_filter( 'vgptts_synced_post_args', $add_excerpt );
	}
}
