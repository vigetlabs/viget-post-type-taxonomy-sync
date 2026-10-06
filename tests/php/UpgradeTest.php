<?php
/**
 * Tests for the Upgrade class.
 *
 * @package Viget\PostTypeTaxonomySync
 */

use Viget\PostTypeTaxonomySync\Core;
use Viget\PostTypeTaxonomySync\Upgrade;

/**
 * Tests for the Upgrade class.
 */
class UpgradeTest extends VGPTTS_TestCase {

	/**
	 * Start each test as a site that hasn't upgraded yet.
	 */
	public function set_up() {
		parent::set_up();
		delete_option( Upgrade::OPTION_NAME );
	}

	/**
	 * Create a post linked to a term the way 2.1 stored it: one term ID under the old key.
	 *
	 * @param string $taxonomy Taxonomy slug.
	 *
	 * @return array{0: int, 1: int} Post ID and term ID.
	 */
	private function legacy_link( string $taxonomy ): array {
		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$term_id = self::factory()->term->create( [ 'taxonomy' => $taxonomy ] );

		add_post_meta( $post_id, Core::POST_META_KEY, $term_id );
		add_term_meta( $term_id, Core::TERM_META_KEY, $post_id );

		return [ $post_id, $term_id ];
	}

	/**
	 * The upgrade moves each post's term ID to its taxonomy's key, drops the old key, and records the version.
	 */
	public function test_moves_term_ids_to_taxonomy_keys() {
		list( $tag_post, $tag_id ) = $this->legacy_link( 'post_tag' );
		list( $cat_post, $cat_id ) = $this->legacy_link( 'category' );

		Upgrade::get_instance()->maybe_upgrade();

		$this->assertSame( (string) $tag_id, get_post_meta( $tag_post, vgptts()->get_post_meta_key( 'post_tag' ), true ) );
		$this->assertSame( (string) $cat_id, get_post_meta( $cat_post, vgptts()->get_post_meta_key( 'category' ), true ) );
		$this->assertSame( '', get_post_meta( $tag_post, Core::POST_META_KEY, true ) );
		$this->assertSame( '', get_post_meta( $cat_post, Core::POST_META_KEY, true ) );
		$this->assertTrue( Upgrade::is_current() );
	}

	/**
	 * A term ID whose term is gone is dropped instead of moved.
	 */
	public function test_drops_term_ids_for_missing_terms() {
		list( $post_id, $term_id ) = $this->legacy_link( 'post_tag' );
		wp_delete_term( $term_id, 'post_tag' );

		Upgrade::get_instance()->maybe_upgrade();

		$this->assertSame( '', get_post_meta( $post_id, Core::POST_META_KEY, true ) );
		$this->assertSame( '', get_post_meta( $post_id, vgptts()->get_post_meta_key( 'post_tag' ), true ) );
	}

	/**
	 * Running it again changes nothing, and it doesn't run at all once the site is current.
	 */
	public function test_is_idempotent() {
		list( $post_id, $term_id ) = $this->legacy_link( 'post_tag' );

		Upgrade::get_instance()->move_term_ids_to_taxonomy_keys();
		Upgrade::get_instance()->move_term_ids_to_taxonomy_keys();

		$this->assertSame( [ (string) $term_id ], get_post_meta( $post_id, vgptts()->get_post_meta_key( 'post_tag' ), false ), 'One value, not a duplicate.' );

		update_option( Upgrade::OPTION_NAME, Upgrade::DB_VERSION );
		add_post_meta( $post_id, Core::POST_META_KEY, $term_id );

		Upgrade::get_instance()->maybe_upgrade();

		$this->assertSame( (string) $term_id, get_post_meta( $post_id, Core::POST_META_KEY, true ), 'A current site skips the upgrade.' );
	}

	/**
	 * Before the upgrade runs, reads and syncs still find the term under the old key.
	 */
	public function test_unmigrated_data_still_syncs() {
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
				'post_status' => 'draft',
				'post_title'  => 'Old Data',
			]
		);
		$term_id = self::factory()->term->create(
			[
				'taxonomy' => 'post_tag',
				'name'     => 'Old Data',
			]
		);
		add_post_meta( $post_id, Core::POST_META_KEY, $term_id );

		$this->assertSame( $term_id, vgptts()->get_term_id_for_post( $post_id, 'post_tag' ) );
		$this->assertNull( vgptts()->get_term_id_for_post( $post_id, 'category' ), 'The old key only counts for its own taxonomy.' );

		wp_update_post(
			[
				'ID'          => $post_id,
				'post_status' => 'publish',
				'post_title'  => 'Renamed',
			]
		);

		$this->assertSame( 'Renamed', get_term( $term_id, 'post_tag' )->name, 'The existing term is updated, not duplicated.' );
		$this->assertSame( (string) $term_id, get_post_meta( $post_id, vgptts()->get_post_meta_key( 'post_tag' ), true ) );
	}

	/**
	 * Each site on a network upgrades its own data and version.
	 *
	 * @group ms-required
	 */
	public function test_upgrades_each_site_separately() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite only.' );
		}

		$site_id = self::factory()->blog->create();

		list( $main_post, $main_term ) = $this->legacy_link( 'post_tag' );

		switch_to_blog( $site_id );
		list( $site_post, $site_term ) = $this->legacy_link( 'post_tag' );
		delete_option( Upgrade::OPTION_NAME );
		restore_current_blog();

		Upgrade::get_instance()->maybe_upgrade();

		$this->assertSame( (string) $main_term, get_post_meta( $main_post, vgptts()->get_post_meta_key( 'post_tag' ), true ) );

		switch_to_blog( $site_id );
		$this->assertFalse( Upgrade::is_current() );
		$this->assertSame( (string) $site_term, get_post_meta( $site_post, Core::POST_META_KEY, true ) );

		Upgrade::get_instance()->maybe_upgrade();

		$this->assertSame( (string) $site_term, get_post_meta( $site_post, vgptts()->get_post_meta_key( 'post_tag' ), true ) );
		$this->assertTrue( Upgrade::is_current() );
		restore_current_blog();
	}
}
