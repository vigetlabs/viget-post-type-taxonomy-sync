<?php
/**
 * Tests for source of truth and auto-created post types.
 *
 * @package Viget\PostTypeTaxonomySync
 */

use Viget\PostTypeTaxonomySync\Core;
use Viget\PostTypeTaxonomySync\Settings;

/**
 * Tests for the source of truth setting and the post type side of auto-creation.
 */
class SourceOfTruthTest extends VGPTTS_TestCase {

	/**
	 * Post type names are capped at 20 characters, tighter than taxonomies.
	 */
	public function test_auto_post_type_slug_is_truncated() {
		$this->assertSame( 'genre_sync', Core::auto_post_type_slug( 'genre' ) );

		$slug = Core::auto_post_type_slug( str_repeat( 'a', 40 ) );

		$this->assertSame( 20, strlen( $slug ) );
		$this->assertStringEndsWith( '_sync', $slug );
	}

	/**
	 * A mapping needs one real side to derive the other from.
	 */
	public function test_both_sides_auto_is_rejected() {
		$clean = Settings::sanitize_settings(
			[
				'mappings' => [
					[
						'post_type' => Core::AUTO_VALUE,
						'taxonomy'  => Core::AUTO_VALUE,
					],
				],
			]
		);

		$this->assertSame( [], $clean['mappings'] );
	}

	/**
	 * Mappings default to the post type being the source of truth.
	 */
	public function test_source_defaults_to_post_type() {
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

		$this->assertSame( Core::SOURCE_POST_TYPE, vgptts()->get_mappings()[0]['source_of_truth'] );
	}

	/**
	 * An auto-created post type takes its labels from the taxonomy and stays out of the
	 * menus while the taxonomy is the source of truth.
	 */
	public function test_auto_post_type_is_registered_from_taxonomy() {
		register_taxonomy(
			'vgptts_genre',
			'post',
			[
				'labels' => [
					'name'          => 'Genres',
					'singular_name' => 'Genre',
				],
			]
		);

		update_option(
			Core::OPTION_NAME,
			[
				'mappings' => [
					[
						'taxonomy'                => 'vgptts_genre',
						'post_type_auto'          => true,
						'source_of_truth'         => Core::SOURCE_TAXONOMY,
						'show_post_type_in_menus' => false,
					],
				],
			]
		);

		$post_type = Core::auto_post_type_slug( 'vgptts_genre' );

		vgptts()->registrar->register_all();

		$this->assertTrue( post_type_exists( $post_type ) );

		$object = get_post_type_object( $post_type );

		$this->assertSame( 'Genres', $object->labels->name );
		$this->assertFalse( $object->show_in_menu, 'A mirror post type stays out of the menus by default.' );

		unregister_post_type( $post_type );
		unregister_taxonomy( 'vgptts_genre' );
	}

	/**
	 * With the taxonomy as source of truth, a full sync builds posts from terms and drops
	 * posts whose term is gone.
	 */
	public function test_sync_posts_reconciles_from_terms() {
		$this->set_mappings(
			[
				[
					'post_type'       => 'post',
					'taxonomy'        => 'post_tag',
					'source_of_truth' => Core::SOURCE_TAXONOMY,
				],
			]
		);

		$term_id = (int) wp_insert_term( 'Reference', 'post_tag' )['term_id'];
		$post_id = (int) get_term_meta( $term_id, Core::TERM_META_KEY, true );

		$this->assertNotEmpty( $post_id, 'Creating a term should create its post.' );
		$this->assertSame( 'Reference', get_post( $post_id )->post_title );

		// Drop the term without the plugin noticing, then reconcile.
		remove_action( 'pre_delete_term', [ vgptts()->sync, 'handle_term_delete' ], 10 );
		wp_delete_term( $term_id, 'post_tag' );
		add_action( 'pre_delete_term', [ vgptts()->sync, 'handle_term_delete' ], 10, 2 );

		$this->assertNotNull( get_post( $post_id ) );

		vgptts()->sync->sync_posts( 'post', 'post_tag' );

		$this->assertNull( get_post( $post_id ), 'A post whose term is gone should be removed.' );
	}
}
