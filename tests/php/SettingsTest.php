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

	/**
	 * sanitize_settings() doesn't save a mapping that's registered in code.
	 */
	public function test_sanitize_settings_drops_registered_mapping() {
		$this->set_registered_mappings(
			[
				[
					'post_type' => 'page',
					'taxonomy'  => 'category',
				],
			]
		);

		$sanitized = Settings::sanitize_settings(
			[
				'mappings' => [
					[
						'post_type' => 'page',
						'taxonomy'  => 'category',
					],
					[
						'post_type' => 'post',
						'taxonomy'  => 'post_tag',
					],
				],
			]
		);

		$this->assertSame(
			[
				'mappings' => [
					[
						'post_type' => 'post',
						'taxonomy'  => 'post_tag',
					],
				],
			],
			$sanitized
		);
	}

	/**
	 * A registered mapping renders as a locked row: Sync, but no inputs and no Remove.
	 */
	public function test_render_mappings_field_locks_registered_mapping() {
		$this->set_registered_mappings(
			[
				[
					'post_type' => 'page',
					'taxonomy'  => 'category',
				],
			]
		);

		$row = $this->get_rendered_row( 'vgptts-row-registered' );

		$this->assertStringContainsString( 'data-post-type="page"', $row );
		$this->assertStringContainsString( 'dashicons-lock', $row );
		$this->assertStringContainsString( 'Registered in code', $row );
		$this->assertStringContainsString( 'vgptts-sync-row', $row );
		$this->assertStringNotContainsString( 'vgptts-remove-row', $row );
		$this->assertStringNotContainsString( '<input', $row );
		$this->assertStringNotContainsString( '<select', $row );
	}

	/**
	 * A saved mapping overridden by a registered one is flagged, keeps Remove and loses Sync.
	 */
	public function test_render_mappings_field_flags_overridden_saved_mapping() {
		$this->set_mappings(
			[
				[
					'post_type' => 'post',
					'taxonomy'  => 'category',
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

		$row = $this->get_rendered_row( 'vgptts-row-overridden' );

		$this->assertStringContainsString( 'data-post-type="post"', $row );
		$this->assertStringContainsString( 'vgptts-remove-row', $row );
		$this->assertStringNotContainsString( 'vgptts-sync-row', $row );
	}

	/**
	 * Renders the mappings field and returns the first row with the given class.
	 *
	 * @param string $class_name Row class.
	 *
	 * @return string
	 */
	private function get_rendered_row( string $class_name ): string {
		ob_start();
		vgptts()->settings->render_mappings_field();
		$html = (string) ob_get_clean();

		$this->assertMatchesRegularExpression( '/<tr class="[^"]*\b' . preg_quote( $class_name, '/' ) . '\b[^"]*".*?<\/tr>/s', $html );
		preg_match( '/<tr class="[^"]*\b' . preg_quote( $class_name, '/' ) . '\b[^"]*".*?<\/tr>/s', $html, $matches );

		return $matches[0];
	}
}
