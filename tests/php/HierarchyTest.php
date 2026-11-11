<?php
/**
 * Tests for mappings whose post type and taxonomy disagree on hierarchy.
 *
 * @package Viget\PostTypeTaxonomySync
 */

use Viget\PostTypeTaxonomySync\Admin;
use Viget\PostTypeTaxonomySync\Settings;

/**
 * Tests for mappings whose post type and taxonomy disagree on hierarchy.
 */
class HierarchyTest extends VGPTTS_TestCase {

	/**
	 * Hierarchical post type to non-hierarchical taxonomy.
	 *
	 * @var array
	 */
	const PAUSED = [
		'post_type' => 'page',
		'taxonomy'  => 'post_tag',
	];

	/**
	 * Non-hierarchical post type to hierarchical taxonomy.
	 *
	 * @var array
	 */
	const WARNING = [
		'post_type' => 'post',
		'taxonomy'  => 'category',
	];

	/**
	 * Logs in an administrator.
	 */
	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
	}

	/**
	 * Removes the vgptts_mappings filter.
	 */
	public function tear_down() {
		remove_all_filters( 'vgptts_mappings' );
		unregister_taxonomy( 'vgptts_flip' );
		parent::tear_down();
	}

	/**
	 * Creates a published page.
	 *
	 * @param string $title Page title.
	 *
	 * @return int
	 */
	private function create_page( string $title ): int {
		return self::factory()->post->create(
			[
				'post_type'   => 'page',
				'post_title'  => $title,
				'post_status' => 'publish',
			]
		);
	}

	/**
	 * Renders the admin notices.
	 *
	 * @return string
	 */
	private function render_notices(): string {
		ob_start();
		Admin::get_instance()->render_hierarchy_notices();
		return (string) ob_get_clean();
	}

	/**
	 * get_hierarchy_status() covers every combination.
	 */
	public function test_get_hierarchy_status() {
		register_taxonomy( 'vgptts_flip', 'page', [ 'hierarchical' => true ] );

		$this->assertSame( 'paused', vgptts()->get_hierarchy_status( self::PAUSED ) );
		$this->assertSame( 'warning', vgptts()->get_hierarchy_status( self::WARNING ) );
		$this->assertNull(
			vgptts()->get_hierarchy_status(
				[
					'post_type' => 'page',
					'taxonomy'  => 'vgptts_flip',
				]
			)
		);
		$this->assertNull(
			vgptts()->get_hierarchy_status(
				[
					'post_type' => 'post',
					'taxonomy'  => 'post_tag',
				]
			)
		);
		$this->assertNull(
			vgptts()->get_hierarchy_status(
				[
					'post_type' => 'not_registered',
					'taxonomy'  => 'post_tag',
				]
			)
		);
	}

	/**
	 * A registered mapping from a hierarchical post type to a flat taxonomy is paused and doesn't sync.
	 */
	public function test_registered_paused_mapping_does_not_sync() {
		$this->set_registered_mappings( [ self::PAUSED ] );
		$this->create_page( 'Paused Registered Page' );

		$this->assertSame( [], vgptts()->get_mappings() );
		$this->assertSame( [ self::PAUSED ], vgptts()->get_paused_mappings() );
		$this->assertFalse( get_term_by( 'name', 'Paused Registered Page', 'post_tag' ) );
	}

	/**
	 * A mapping added through vgptts_mappings is paused too.
	 */
	public function test_filtered_paused_mapping_does_not_sync() {
		add_filter( 'vgptts_mappings', static fn( array $mappings ): array => array_merge( $mappings, [ self::PAUSED ] ) );
		vgptts()->sync->register_hooks();
		$this->create_page( 'Paused Filtered Page' );

		$this->assertSame( [ self::PAUSED ], vgptts()->get_paused_mappings() );
		$this->assertFalse( get_term_by( 'name', 'Paused Filtered Page', 'post_tag' ) );
	}

	/**
	 * A saved mapping pauses when its taxonomy is re-registered as non-hierarchical, and resumes when it's fixed.
	 */
	public function test_saved_mapping_pauses_and_resumes_with_registration() {
		$mapping = [
			'post_type' => 'page',
			'taxonomy'  => 'vgptts_flip',
		];

		register_taxonomy( 'vgptts_flip', 'page', [ 'hierarchical' => false ] );
		$this->set_mappings( [ $mapping ] );
		$this->create_page( 'Paused Saved Page' );

		$this->assertSame( [ $mapping ], vgptts()->get_paused_mappings() );
		$this->assertFalse( get_term_by( 'name', 'Paused Saved Page', 'vgptts_flip' ) );

		vgptts()->sync->sync_terms( 'page', 'vgptts_flip' );
		$this->assertFalse( get_term_by( 'name', 'Paused Saved Page', 'vgptts_flip' ) );

		register_taxonomy( 'vgptts_flip', 'page', [ 'hierarchical' => true ] );
		vgptts()->sync->register_hooks();
		$this->create_page( 'Resumed Saved Page' );

		$this->assertSame( [], vgptts()->get_paused_mappings() );
		$this->assertInstanceOf( WP_Term::class, get_term_by( 'name', 'Resumed Saved Page', 'vgptts_flip' ) );
	}

	/**
	 * Saving the settings page keeps an already-saved mapping that has since paused.
	 */
	public function test_sanitize_settings_keeps_saved_paused_mapping() {
		update_option( Settings::OPTION_NAME, [ 'mappings' => [ self::PAUSED ] ] );

		$this->assertSame( [ 'mappings' => [ self::PAUSED ] ], Settings::sanitize_settings( [ 'mappings' => [ self::PAUSED ] ] ) );
	}

	/**
	 * A non-hierarchical post type to a hierarchical taxonomy still syncs, with a warning.
	 */
	public function test_warning_mapping_still_syncs() {
		$this->set_mappings( [ self::WARNING ] );

		self::factory()->post->create(
			[
				'post_title'  => 'Warning Post',
				'post_status' => 'publish',
			]
		);

		$this->assertSame( [ self::WARNING ], vgptts()->get_mappings() );
		$this->assertSame( [ self::WARNING ], vgptts()->get_hierarchy_warnings() );
		$this->assertInstanceOf( WP_Term::class, get_term_by( 'name', 'Warning Post', 'category' ) );
	}

	/**
	 * A paused mapping shows a non-dismissible error, and a warning mapping a dismissible warning.
	 */
	public function test_notices() {
		$this->set_registered_mappings( [ self::PAUSED ] );
		$this->set_mappings( [ self::WARNING ] );

		$output = $this->render_notices();

		$this->assertMatchesRegularExpression( '/class="notice notice-error"><p><strong>Post Type Taxonomy Sync:<\/strong> syncing Page with Tag is paused/', $output );
		$this->assertStringContainsString( 'notice-warning is-dismissible vgptts-hierarchy-warning" data-key="post:category"', $output );
	}

	/**
	 * A dismissed warning stays hidden, and comes back once the mapping matches and then drifts again.
	 */
	public function test_dismissed_warning_resets_when_mapping_matches() {
		$mapping = [
			'post_type' => 'post',
			'taxonomy'  => 'vgptts_flip',
		];

		register_taxonomy( 'vgptts_flip', 'post', [ 'hierarchical' => true ] );
		$this->set_mappings( [ $mapping ] );
		update_user_meta( get_current_user_id(), Admin::DISMISSED_WARNINGS_META_KEY, [ 'post:vgptts_flip' ] );

		$this->assertStringNotContainsString( 'vgptts-hierarchy-warning', $this->render_notices() );

		register_taxonomy( 'vgptts_flip', 'post', [ 'hierarchical' => false ] );
		$this->render_notices();
		register_taxonomy( 'vgptts_flip', 'post', [ 'hierarchical' => true ] );

		$this->assertStringContainsString( 'vgptts-hierarchy-warning', $this->render_notices() );
	}

	/**
	 * Notices are only shown to users who can manage options.
	 */
	public function test_notices_require_manage_options() {
		$this->set_registered_mappings( [ self::PAUSED ] );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );

		$this->assertSame( '', $this->render_notices() );
	}

	/**
	 * Registered and saved paused rows say why they're paused and drop the Sync button.
	 */
	public function test_paused_rows_on_settings_page() {
		$this->set_registered_mappings( [ self::PAUSED ] );

		$rows = vgptts()->settings->get_registered_rows();

		$this->assertTrue( $rows[0]['paused'] );
		$this->assertSame( 'Paused: Page is hierarchical but Tag is not. Make Tag hierarchical to resume syncing.', $rows[0]['note'] );

		$this->set_registered_mappings( [] );
		$this->set_mappings( [ self::PAUSED ] );

		ob_start();
		vgptts()->settings->render_mappings_field();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'vgptts-row-saved vgptts-row-paused', $output );
		$this->assertStringNotContainsString( 'vgptts-sync-row" data-post-type="page"', $output );
		$this->assertStringContainsString( 'vgptts-remove-row', $output );
	}
}
