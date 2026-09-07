<?php
/**
 * Tests for auto-created taxonomies.
 *
 * @package Viget\PostTypeTaxonomySync
 */

use Viget\PostTypeTaxonomySync\Core;
use Viget\PostTypeTaxonomySync\Settings;

/**
 * Tests for the Registrar class and the settings that drive it.
 */
class RegistrarTest extends VGPTTS_TestCase {

	/**
	 * The derived slug is the post type plus the suffix.
	 */
	public function test_auto_taxonomy_slug() {
		$this->assertSame( 'book_sync', Core::auto_taxonomy_slug( 'book' ) );
		$this->assertSame( '', Core::auto_taxonomy_slug( '' ) );
	}

	/**
	 * Taxonomy names are capped at 32 characters.
	 */
	public function test_auto_taxonomy_slug_is_truncated() {
		$slug = Core::auto_taxonomy_slug( str_repeat( 'a', 40 ) );

		$this->assertSame( 32, strlen( $slug ) );
		$this->assertStringEndsWith( '_sync', $slug );
	}

	/**
	 * An auto mapping is stored as a flag, not a slug, and only real post types stick.
	 */
	public function test_sanitize_settings_stores_auto_mapping() {
		$clean = Settings::sanitize_settings(
			[
				'mappings' => [
					[
						'post_type' => 'post',
						'taxonomy'  => Core::AUTO_VALUE,
						'attach_to' => [ 'page', 'no_such_type' ],
					],
				],
			]
		);

		$this->assertCount( 1, $clean['mappings'] );
		$this->assertTrue( $clean['mappings'][0]['taxonomy_auto'] );
		$this->assertSame( [ 'page' ], $clean['mappings'][0]['attach_to'] );
		$this->assertArrayNotHasKey( 'taxonomy', $clean['mappings'][0] );
	}

	/**
	 * get_mappings() resolves the slug for an auto mapping so callers see a normal mapping.
	 */
	public function test_get_mappings_resolves_auto_slug() {
		update_option(
			Core::OPTION_NAME,
			[
				'mappings' => [
					[
						'post_type'     => 'post',
						'taxonomy_auto' => true,
						'attach_to'     => [ 'page' ],
					],
				],
			]
		);

		$mappings = vgptts()->get_mappings();

		$this->assertSame( 'post_sync', $mappings[0]['taxonomy'] );
		$this->assertSame( 'post', vgptts()->get_post_type_for_taxonomy( 'post_sync' ) );
	}

	/**
	 * Registering the mapped post type registers its taxonomy, with the post type's labels.
	 */
	public function test_taxonomy_is_registered_with_the_post_type() {
		update_option(
			Core::OPTION_NAME,
			[
				'mappings' => [
					[
						'post_type'     => 'vgptts_auto_cpt',
						'taxonomy_auto' => true,
						'attach_to'     => [ 'post' ],
					],
				],
			]
		);

		$this->assertFalse( taxonomy_exists( 'vgptts_auto_cpt_sync' ) );

		register_post_type(
			'vgptts_auto_cpt',
			[
				'public'       => true,
				'hierarchical' => true,
				'labels'       => [
					'name'          => 'Widgets',
					'singular_name' => 'Widget',
				],
			]
		);

		$this->assertTrue( taxonomy_exists( 'vgptts_auto_cpt_sync' ) );

		$taxonomy = get_taxonomy( 'vgptts_auto_cpt_sync' );

		$this->assertSame( 'Widgets', $taxonomy->labels->name );
		$this->assertSame( 'Widget', $taxonomy->labels->singular_name );
		$this->assertTrue( $taxonomy->hierarchical, 'Hierarchy should follow the post type.' );
		$this->assertContains( 'post', $taxonomy->object_type );

		unregister_taxonomy( 'vgptts_auto_cpt_sync' );
		unregister_post_type( 'vgptts_auto_cpt' );
	}

	/**
	 * A taxonomy the theme already registered under that slug is left alone.
	 */
	public function test_existing_taxonomy_is_not_overwritten() {
		register_taxonomy( 'vgptts_taken_sync', 'post', [ 'labels' => [ 'name' => 'Theme Owned' ] ] );

		update_option(
			Core::OPTION_NAME,
			[
				'mappings' => [
					[
						'post_type'     => 'vgptts_taken',
						'taxonomy_auto' => true,
					],
				],
			]
		);

		register_post_type( 'vgptts_taken', [ 'public' => true ] );

		$this->assertSame( 'Theme Owned', get_taxonomy( 'vgptts_taken_sync' )->labels->name );

		unregister_post_type( 'vgptts_taken' );
		unregister_taxonomy( 'vgptts_taken_sync' );
	}
}
