<?php
/**
 * Tests for the Settings class.
 *
 * @package Viget\PostTypeTaxonomySync
 */

use Viget\PostTypeTaxonomySync\Settings;

/**
 * Tests for the Settings class.
 */
class SettingsTest extends VGPTTS_TestCase {

	/**
	 * get_settings() returns the mappings default when unset.
	 */
	public function test_get_settings_defaults_to_empty_mappings() {
		delete_option( Settings::OPTION_NAME );

		$this->assertSame( [ 'mappings' => [] ], vgptts()->settings->get_settings() );
	}

	/**
	 * sanitize_settings() drops entries missing a post_type or taxonomy.
	 */
	public function test_sanitize_settings_drops_incomplete_entries() {
		$sanitized = Settings::sanitize_settings(
			[
				'mappings' => [
					[ 'post_type' => 'post' ],
					[ 'taxonomy' => 'post_tag' ],
				],
			]
		);

		$this->assertSame( [ 'mappings' => [] ], $sanitized );
	}

	/**
	 * sanitize_settings() drops entries referencing a nonexistent post type or taxonomy.
	 */
	public function test_sanitize_settings_drops_nonexistent_post_type_or_taxonomy() {
		$sanitized = Settings::sanitize_settings(
			[
				'mappings' => [
					[
						'post_type' => 'does_not_exist',
						'taxonomy'  => 'post_tag',
					],
					[
						'post_type' => 'post',
						'taxonomy'  => 'does_not_exist',
					],
				],
			]
		);

		$this->assertSame( [ 'mappings' => [] ], $sanitized );
	}

	/**
	 * sanitize_settings() rejects a hierarchical post type mapped to a non-hierarchical taxonomy.
	 */
	public function test_sanitize_settings_rejects_hierarchical_mismatch() {
		// 'page' is hierarchical, 'post_tag' is not.
		$sanitized = Settings::sanitize_settings(
			[
				'mappings' => [
					[
						'post_type' => 'page',
						'taxonomy'  => 'post_tag',
					],
				],
			]
		);

		$this->assertSame( [ 'mappings' => [] ], $sanitized );
	}

	/**
	 * sanitize_settings() accepts a non-hierarchical post type mapped to a hierarchical taxonomy.
	 */
	public function test_sanitize_settings_accepts_nonhierarchical_post_type_to_hierarchical_taxonomy() {
		// 'post' is non-hierarchical, 'category' is hierarchical: allowed.
		$sanitized = Settings::sanitize_settings(
			[
				'mappings' => [
					[
						'post_type' => 'post',
						'taxonomy'  => 'category',
					],
				],
			]
		);

		$this->assertSame(
			[
				'mappings' => [
					[
						'post_type' => 'post',
						'taxonomy'  => 'category',
					],
				],
			],
			$sanitized
		);
	}

	/**
	 * sanitize_settings() accepts a valid hierarchical/hierarchical pair.
	 */
	public function test_sanitize_settings_accepts_hierarchical_pair() {
		register_taxonomy( 'vgptts_test_tax', 'page', [ 'hierarchical' => true ] );

		$sanitized = Settings::sanitize_settings(
			[
				'mappings' => [
					[
						'post_type' => 'page',
						'taxonomy'  => 'vgptts_test_tax',
					],
				],
			]
		);

		$this->assertSame(
			[
				'mappings' => [
					[
						'post_type' => 'page',
						'taxonomy'  => 'vgptts_test_tax',
					],
				],
			],
			$sanitized
		);

		unregister_taxonomy( 'vgptts_test_tax' );
	}

	/**
	 * sanitize_settings() sanitizes post_type/taxonomy slugs.
	 */
	public function test_sanitize_settings_sanitizes_slugs() {
		$sanitized = Settings::sanitize_settings(
			[
				'mappings' => [
					[
						'post_type' => ' Post ',
						'taxonomy'  => ' Post_Tag ',
					],
				],
			]
		);

		$this->assertSame( 'post', $sanitized['mappings'][0]['post_type'] );
		$this->assertSame( 'post_tag', $sanitized['mappings'][0]['taxonomy'] );
	}
}
