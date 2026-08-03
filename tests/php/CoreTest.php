<?php
/**
 * Tests for the Core class.
 *
 * @package Viget\PostTypeTaxonomySync
 */

use Viget\PostTypeTaxonomySync\Core;

/**
 * Tests for the Core class.
 */
class CoreTest extends VGPTTS_TestCase {

	/**
	 * vgptts() returns a singleton Core instance.
	 */
	public function test_vgptts_returns_singleton() {
		$this->assertInstanceOf( Core::class, vgptts() );
		$this->assertSame( vgptts(), vgptts() );
	}

	/**
	 * get_mappings() drops entries missing a post_type or taxonomy.
	 */
	public function test_get_mappings_filters_invalid_entries() {
		update_option(
			Core::OPTION_NAME,
			[
				'mappings' => [
					[
						'post_type' => 'post',
						'taxonomy'  => 'post_tag',
					],
					[ 'post_type' => 'post' ],
					[ 'taxonomy' => 'post_tag' ],
					[
						'post_type' => '',
						'taxonomy'  => '',
					],
				],
			]
		);

		$this->assertSame(
			[
				[
					'post_type' => 'post',
					'taxonomy'  => 'post_tag',
				],
			],
			vgptts()->get_mappings()
		);
	}

	/**
	 * get_mappings() sanitizes post_type/taxonomy values with sanitize_key().
	 */
	public function test_get_mappings_sanitizes_values() {
		update_option(
			Core::OPTION_NAME,
			[
				'mappings' => [
					[
						'post_type' => ' Post ',
						'taxonomy'  => ' Post_Tag ',
					],
				],
			]
		);

		$mappings = vgptts()->get_mappings();

		$this->assertSame( 'post', $mappings[0]['post_type'] );
		$this->assertSame( 'post_tag', $mappings[0]['taxonomy'] );
	}

	/**
	 * The vgptts_mappings filter can modify the resolved mappings.
	 */
	public function test_get_mappings_applies_filter() {
		update_option( Core::OPTION_NAME, [ 'mappings' => [] ] );

		$inject = static function ( array $mappings ): array {
			$mappings[] = [
				'post_type' => 'page',
				'taxonomy'  => 'category',
			];
			return $mappings;
		};
		add_filter( 'vgptts_mappings', $inject );

		$this->assertSame(
			[
				[
					'post_type' => 'page',
					'taxonomy'  => 'category',
				],
			],
			vgptts()->get_mappings()
		);

		remove_filter( 'vgptts_mappings', $inject );
	}

	/**
	 * get_taxonomy_for_post_type()/get_post_type_for_taxonomy() resolve both directions.
	 */
	public function test_taxonomy_and_post_type_lookups() {
		$this->set_mappings(
			[
				[
					'post_type' => 'post',
					'taxonomy'  => 'post_tag',
				],
			]
		);

		$this->assertSame( 'post_tag', vgptts()->get_taxonomy_for_post_type( 'post' ) );
		$this->assertSame( 'post', vgptts()->get_post_type_for_taxonomy( 'post_tag' ) );
		$this->assertNull( vgptts()->get_taxonomy_for_post_type( 'page' ) );
		$this->assertNull( vgptts()->get_post_type_for_taxonomy( 'category' ) );
	}

	/**
	 * get_related_post_ids_for_post() resolves posts linked via a term assigned to the given post.
	 */
	public function test_get_related_post_ids_for_post() {
		$this->set_mappings(
			[
				[
					'post_type' => 'post',
					'taxonomy'  => 'post_tag',
				],
			]
		);

		$other_post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$other_term_id = (int) get_post_meta( $other_post_id, Core::POST_META_KEY, true );

		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		wp_set_post_terms( $post_id, [ $other_term_id ], 'post_tag' );

		$this->assertSame(
			[ $other_post_id ],
			vgptts()->get_related_post_ids_for_post( $post_id, 'post_tag' )
		);
	}

	/**
	 * get_related_post_ids_for_post() returns an empty array when there are no terms.
	 */
	public function test_get_related_post_ids_for_post_returns_empty_array_when_untagged() {
		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );

		$this->assertSame( [], vgptts()->get_related_post_ids_for_post( $post_id, 'post_tag' ) );
	}

	/**
	 * get_post_id_for_term() resolves the post linked to a term.
	 */
	public function test_get_post_id_for_term() {
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

		$this->assertSame( $post_id, vgptts()->get_post_id_for_term( $term_id ) );
	}

	/**
	 * get_post_id_for_term() returns null for a term with no linked post.
	 */
	public function test_get_post_id_for_term_returns_null_when_unlinked() {
		$term_id = self::factory()->term->create( [ 'taxonomy' => 'post_tag' ] );

		$this->assertNull( vgptts()->get_post_id_for_term( $term_id ) );
	}

	/**
	 * The deprecated PTTS() alias triggers _doing_it_wrong() and delegates to vgptts().
	 */
	public function test_deprecated_ptts_alias_triggers_doing_it_wrong() {
		$this->setExpectedIncorrectUsage( 'PTTS' );

		$this->assertSame( vgptts(), PTTS() );
	}
}
