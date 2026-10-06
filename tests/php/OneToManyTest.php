<?php
/**
 * Tests for one post type syncing to several taxonomies.
 *
 * @package Viget\PostTypeTaxonomySync
 */

use Viget\PostTypeTaxonomySync\Admin;
use Viget\PostTypeTaxonomySync\Core;
use Viget\PostTypeTaxonomySync\Settings;

/**
 * Tests for one post type syncing to several taxonomies.
 */
class OneToManyTest extends VGPTTS_TestCase {

	/**
	 * Map posts to both tags and categories.
	 */
	public function set_up() {
		parent::set_up();

		$this->set_mappings(
			[
				[
					'post_type' => 'post',
					'taxonomy'  => 'post_tag',
				],
				[
					'post_type' => 'post',
					'taxonomy'  => 'category',
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
	 * A post's synced term ID in a taxonomy.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy slug.
	 *
	 * @return int
	 */
	private function term_id( int $post_id, string $taxonomy ): int {
		return (int) get_post_meta( $post_id, vgptts()->get_post_meta_key( $taxonomy ), true );
	}

	/**
	 * Publishing a post creates a term in each of its taxonomies, stored under per-taxonomy keys.
	 */
	public function test_post_save_creates_a_term_in_each_taxonomy() {
		$post_id = self::factory()->post->create(
			[
				'post_status' => 'publish',
				'post_title'  => 'Both Ways',
			]
		);

		foreach ( [ 'post_tag', 'category' ] as $taxonomy ) {
			$term = get_term( $this->term_id( $post_id, $taxonomy ), $taxonomy );

			$this->assertInstanceOf( WP_Term::class, $term, $taxonomy );
			$this->assertSame( 'Both Ways', $term->name );
			$this->assertSame( (string) $post_id, get_term_meta( $term->term_id, Core::TERM_META_KEY, true ) );
		}

		$this->assertSame( '', get_post_meta( $post_id, Core::POST_META_KEY, true ) );
	}

	/**
	 * Renaming a post renames its term in each taxonomy.
	 */
	public function test_post_rename_updates_each_term() {
		$post_id = self::factory()->post->create(
			[
				'post_status' => 'publish',
				'post_title'  => 'Before',
			]
		);

		wp_update_post(
			[
				'ID'         => $post_id,
				'post_title' => 'After',
				'post_name'  => 'after',
			]
		);

		foreach ( [ 'post_tag', 'category' ] as $taxonomy ) {
			$term = get_term( $this->term_id( $post_id, $taxonomy ), $taxonomy );
			$this->assertSame( 'After', $term->name, $taxonomy );
			$this->assertSame( 'after', $term->slug, $taxonomy );
		}
	}

	/**
	 * Deleting a post removes its term in each taxonomy.
	 */
	public function test_post_delete_removes_each_term() {
		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$tag_id  = $this->term_id( $post_id, 'post_tag' );
		$cat_id  = $this->term_id( $post_id, 'category' );

		wp_delete_post( $post_id, true );

		$this->assertNull( term_exists( $tag_id, 'post_tag' ) );
		$this->assertNull( term_exists( $cat_id, 'category' ) );
	}

	/**
	 * A full sync removes a trashed post's term from each mapping it runs on.
	 */
	public function test_sync_terms_removes_a_trashed_posts_terms_in_each_taxonomy() {
		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$tag_id  = $this->term_id( $post_id, 'post_tag' );
		$cat_id  = $this->term_id( $post_id, 'category' );

		wp_trash_post( $post_id );

		vgptts()->sync->sync_terms( 'post', 'post_tag' );
		vgptts()->sync->sync_terms( 'post', 'category' );

		$this->assertNull( term_exists( $tag_id, 'post_tag' ) );
		$this->assertNull( term_exists( $cat_id, 'category' ) );
	}

	/**
	 * A full sync backfills each mapping on its own.
	 */
	public function test_sync_terms_backfills_each_mapping() {
		$this->set_mappings( [] );
		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );

		vgptts()->sync->sync_terms( 'post', 'post_tag' );
		$this->assertNotEmpty( $this->term_id( $post_id, 'post_tag' ) );
		$this->assertEmpty( $this->term_id( $post_id, 'category' ) );

		vgptts()->sync->sync_terms( 'post', 'category' );
		$this->assertNotEmpty( $this->term_id( $post_id, 'category' ) );
	}

	/**
	 * A term created in one taxonomy creates its post, which then gets a term in the other.
	 */
	public function test_term_save_creates_post_with_a_term_in_the_other_taxonomy() {
		$tag_id  = self::factory()->term->create(
			[
				'taxonomy' => 'post_tag',
				'name'     => 'From A Tag',
			]
		);
		$post_id = vgptts()->get_post_id_for_term( $tag_id );

		$this->assertNotNull( $post_id );
		$this->assertSame( $tag_id, $this->term_id( $post_id, 'post_tag' ) );
		$this->assertSame( 'From A Tag', get_term( $this->term_id( $post_id, 'category' ), 'category' )->name );
	}

	/**
	 * Renaming a term renames its post and the post's term in the other taxonomy.
	 */
	public function test_term_rename_updates_post_and_other_term() {
		$post_id = self::factory()->post->create(
			[
				'post_status' => 'publish',
				'post_title'  => 'Original',
			]
		);

		wp_update_term(
			$this->term_id( $post_id, 'post_tag' ),
			'post_tag',
			[
				'name' => 'Renamed',
				'slug' => 'renamed',
			]
		);

		$this->assertSame( 'Renamed', get_post( $post_id )->post_title );
		$this->assertSame( 'Renamed', get_term( $this->term_id( $post_id, 'category' ), 'category' )->name );
	}

	/**
	 * Deleting a term deletes its post and the post's term in the other taxonomy.
	 */
	public function test_term_delete_removes_post_and_other_term() {
		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$cat_id  = $this->term_id( $post_id, 'category' );

		wp_delete_term( $this->term_id( $post_id, 'post_tag' ), 'post_tag' );

		$this->assertNull( get_post( $post_id ) );
		$this->assertNull( term_exists( $cat_id, 'category' ) );
	}

	/**
	 * get_term_id_for_post() reads the per-taxonomy key, and returns null when there's no term.
	 */
	public function test_get_term_id_for_post() {
		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );

		$this->assertSame( $this->term_id( $post_id, 'category' ), vgptts()->get_term_id_for_post( $post_id, 'category' ) );
		$this->assertNull( vgptts()->get_term_id_for_post( $post_id, 'post_format' ) );
	}

	/**
	 * Admin hides Add New and excludes the post's own term in each of its synced taxonomies.
	 */
	public function test_admin_excludes_own_term_in_each_taxonomy() {
		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$admin   = Admin::get_instance();

		$method = new ReflectionMethod( Admin::class, 'get_synced_taxonomies_for_post_type' );
		$method->setAccessible( true );
		$this->assertSame( [ 'post_tag', 'category' ], $method->invoke( $admin, 'post' ) );

		set_current_screen( 'post' );
		$_GET['post'] = $post_id;

		$args = $admin->exclude_synced_term_from_terms_list( [], [ 'post_tag', 'category' ] );

		$this->assertEqualsCanonicalizing( [ $this->term_id( $post_id, 'post_tag' ), $this->term_id( $post_id, 'category' ) ], $args['exclude'] );

		$_SERVER['HTTP_REFERER'] = admin_url( "post.php?post={$post_id}&action=edit" );

		$tags = $admin->exclude_synced_term_in_rest_terms_query( [], new WP_REST_Request( 'GET', '/wp/v2/tags' ) );
		$cats = $admin->exclude_synced_term_in_rest_terms_query( [], new WP_REST_Request( 'GET', '/wp/v2/categories' ) );

		$this->assertSame( [ $this->term_id( $post_id, 'post_tag' ) ], array_values( $tags['exclude'] ) );
		$this->assertSame( [ $this->term_id( $post_id, 'category' ) ], array_values( $cats['exclude'] ) );
	}

	/**
	 * The editor's create-term links are removed for each synced taxonomy.
	 */
	public function test_create_term_links_removed_for_each_taxonomy() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		do_action( 'rest_api_init', rest_get_server() );

		$request = new WP_REST_Request( 'GET', rest_get_route_for_post( self::factory()->post->create() ) );
		$request->set_param( 'context', 'edit' );
		$links = array_keys( rest_get_server()->dispatch( $request )->get_links() );

		$this->assertNotContains( 'https://api.w.org/action-create-tags', $links );
		$this->assertNotContains( 'https://api.w.org/action-create-categories', $links );
	}

	/**
	 * sanitize_settings() saves two mappings for the same post type, and still rejects a reused taxonomy.
	 */
	public function test_sanitize_settings_allows_a_shared_post_type() {
		$this->set_mappings( [] );

		$sanitized = Settings::sanitize_settings(
			[
				'mappings' => [
					[
						'post_type' => 'post',
						'taxonomy'  => 'post_tag',
					],
					[
						'post_type' => 'post',
						'taxonomy'  => 'category',
					],
					[
						'post_type' => 'page',
						'taxonomy'  => 'category',
					],
				],
			]
		);

		$this->assertSame( [ 'post_tag', 'category' ], wp_list_pluck( $sanitized['mappings'], 'taxonomy' ) );
		$this->assertSame( [ 'post', 'post' ], wp_list_pluck( $sanitized['mappings'], 'post_type' ) );

		$errors = wp_list_pluck( get_settings_errors( Settings::OPTION_NAME ), 'message', 'code' );
		$this->assertSame( 'Mapping "page" to "category" was not saved. "category" already syncs with "post".', $errors['vgptts_conflict_page_category'] );
	}

	/**
	 * The settings page renders both mappings for the post type as synced rows with Sync buttons.
	 */
	public function test_settings_page_renders_each_mapping_for_a_post_type() {
		ob_start();
		vgptts()->settings->render_mappings_field();
		$html = (string) ob_get_clean();

		$this->assertSame( 2, preg_match_all( '/<tr class="vgptts-row-saved"/', $html ) );
		$this->assertSame( 2, preg_match_all( '/vgptts-sync-row" data-post-type="post"/', $html ) );
		$this->assertStringNotContainsString( 'vgptts-row-overridden', $html );
	}
}
