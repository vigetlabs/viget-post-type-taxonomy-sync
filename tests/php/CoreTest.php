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
	 * Registered mappings are active with nothing saved, and come before saved mappings.
	 */
	public function test_get_mappings_puts_registered_mappings_first() {
		$this->set_mappings(
			[
				[
					'post_type' => 'post',
					'taxonomy'  => 'post_tag',
				],
			]
		);
		$this->set_registered_mappings(
			[
				[
					'post_type' => 'page',
					'taxonomy'  => 'category',
				],
			]
		);

		$this->assertSame(
			[
				[
					'post_type' => 'page',
					'taxonomy'  => 'category',
				],
				[
					'post_type' => 'post',
					'taxonomy'  => 'post_tag',
				],
			],
			vgptts()->get_mappings()
		);
		$this->assertSame( 'category', vgptts()->get_taxonomy_for_post_type( 'page' ) );
	}

	/**
	 * A saved mapping sharing a taxonomy with a registered mapping is dropped. Sharing a post type is fine.
	 */
	public function test_registered_mapping_overrides_saved_mapping() {
		$this->set_mappings(
			[
				[
					'post_type' => 'page',
					'taxonomy'  => 'post_tag',
				],
				[
					'post_type' => 'post',
					'taxonomy'  => 'category',
				],
				[
					'post_type' => 'post',
					'taxonomy'  => 'post_format',
				],
			]
		);
		$this->set_registered_mappings(
			[
				[
					'post_type' => 'page',
					'taxonomy'  => 'category',
				],
			]
		);

		$this->assertSame(
			[
				[
					'post_type' => 'page',
					'taxonomy'  => 'category',
				],
				[
					'post_type' => 'page',
					'taxonomy'  => 'post_tag',
				],
				[
					'post_type' => 'post',
					'taxonomy'  => 'post_format',
				],
			],
			vgptts()->get_mappings()
		);
		$this->assertTrue(
			vgptts()->is_overridden(
				[
					'post_type' => 'post',
					'taxonomy'  => 'category',
				]
			)
		);
		$this->assertFalse(
			vgptts()->is_overridden(
				[
					'post_type' => 'post',
					'taxonomy'  => 'post_format',
				]
			)
		);
	}

	/**
	 * get_registered_mappings() drops incomplete entries, sanitizes slugs and removes duplicates.
	 */
	public function test_get_registered_mappings_sanitizes_and_dedupes() {
		$this->set_registered_mappings(
			[
				[
					'post_type' => ' Page ',
					'taxonomy'  => 'Category',
				],
				[
					'post_type' => 'page',
					'taxonomy'  => 'category',
				],
				[ 'post_type' => 'post' ],
				'not-a-mapping',
			]
		);

		$this->assertSame(
			[
				[
					'post_type' => 'page',
					'taxonomy'  => 'category',
				],
			],
			vgptts()->get_registered_mappings()
		);
	}

	/**
	 * A registered mapping syncs on save, with nothing saved on the settings page.
	 */
	public function test_registered_mapping_syncs_on_save() {
		$this->set_registered_mappings(
			[
				[
					'post_type' => 'page',
					'taxonomy'  => 'category',
				],
			]
		);

		$page_id = self::factory()->post->create(
			[
				'post_type'   => 'page',
				'post_title'  => 'Registered Mapping Page',
				'post_status' => 'publish',
			]
		);

		$term = get_term_by( 'name', 'Registered Mapping Page', 'category' );

		$this->assertInstanceOf( WP_Term::class, $term );
		$this->assertSame( $page_id, vgptts()->get_post_id_for_term( $term->term_id ) );
	}

	/**
	 * is_overridden() matches a registered mapping's taxonomy, not its post type.
	 */
	public function test_is_overridden() {
		$registered = [
			[
				'post_type' => 'post',
				'taxonomy'  => 'category',
			],
		];

		$this->assertFalse(
			vgptts()->is_overridden(
				[
					'post_type' => 'post',
					'taxonomy'  => 'post_tag',
				],
				$registered
			)
		);
		$this->assertTrue(
			vgptts()->is_overridden(
				[
					'post_type' => 'page',
					'taxonomy'  => 'category',
				],
				$registered
			)
		);
		$this->assertFalse(
			vgptts()->is_overridden(
				[
					'post_type' => 'page',
					'taxonomy'  => 'post_tag',
				],
				$registered
			)
		);
	}

	/**
	 * A registered mapping that reuses a taxonomy claimed by an earlier one is flagged and left out.
	 */
	public function test_registered_mapping_sharing_taxonomy_is_flagged() {
		$this->set_registered_mappings(
			[
				[
					'post_type' => 'post',
					'taxonomy'  => 'category',
				],
				[
					'post_type' => 'page',
					'taxonomy'  => 'category',
				],
			]
		);

		$this->assertSame(
			[
				[
					'post_type' => 'post',
					'taxonomy'  => 'category',
				],
			],
			vgptts()->get_mappings()
		);
		$this->assertSame(
			[
				[
					'post_type' => 'page',
					'taxonomy'  => 'category',
					'source'    => 'registered',
					'conflict'  => [
						'post_type' => 'post',
						'taxonomy'  => 'category',
					],
				],
			],
			vgptts()->get_flagged_mappings()
		);
	}

	/**
	 * A post type can sync to several taxonomies, so registered mappings can share one.
	 */
	public function test_registered_mappings_can_share_a_post_type() {
		$this->set_registered_mappings(
			[
				[
					'post_type' => 'post',
					'taxonomy'  => 'category',
				],
				[
					'post_type' => 'post',
					'taxonomy'  => 'post_tag',
				],
			]
		);

		$this->assertCount( 2, vgptts()->get_mappings() );
		$this->assertSame( [], vgptts()->get_flagged_mappings() );
		$this->assertSame( [ 'category', 'post_tag' ], vgptts()->get_taxonomies_for_post_type( 'post' ) );
		$this->assertSame( 'category', vgptts()->get_taxonomy_for_post_type( 'post' ) );
	}

	/**
	 * Saved mappings can share a post type too, alongside a registered one.
	 */
	public function test_saved_mapping_can_share_a_registered_post_type() {
		$this->set_registered_mappings(
			[
				[
					'post_type' => 'post',
					'taxonomy'  => 'category',
				],
			]
		);
		$this->set_mappings(
			[
				[
					'post_type' => 'post',
					'taxonomy'  => 'post_tag',
				],
			]
		);

		$this->assertSame( [ 'category', 'post_tag' ], vgptts()->get_taxonomies_for_post_type( 'post' ) );
		$this->assertSame( [], vgptts()->get_flagged_mappings() );
	}

	/**
	 * Saved mappings follow the same rule: the first to claim a taxonomy wins.
	 */
	public function test_saved_mapping_sharing_taxonomy_is_flagged() {
		$this->set_mappings(
			[
				[
					'post_type' => 'post',
					'taxonomy'  => 'category',
				],
				[
					'post_type' => 'page',
					'taxonomy'  => 'category',
				],
			]
		);

		$this->assertSame( 'post', vgptts()->get_post_type_for_taxonomy( 'category' ) );
		$this->assertCount( 1, vgptts()->get_mappings() );
		$this->assertSame( 'saved', vgptts()->get_flagged_mappings()[0]['source'] );
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
		$other_term_id = (int) get_post_meta( $other_post_id, vgptts()->get_post_meta_key( 'post_tag' ), true );

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
		$term_id = (int) get_post_meta( $post_id, vgptts()->get_post_meta_key( 'post_tag' ), true );

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
