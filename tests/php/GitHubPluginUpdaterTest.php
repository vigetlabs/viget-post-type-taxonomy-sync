<?php
/**
 * Tests for the GitHub_Plugin_Updater class.
 *
 * @package Viget\PostTypeTaxonomySync
 */

use Viget\PostTypeTaxonomySync\GitHub_Plugin_Updater;

/**
 * Tests for the GitHub_Plugin_Updater class.
 *
 * Uses a distinct fake owner/repo (rather than the real vigetlabs/... pair Core
 * already wires up) so this test's mocked HTTP responses and transient cache
 * can't collide with the plugin's real, already-registered updater instance.
 */
class GitHubPluginUpdaterTest extends WP_UnitTestCase {

	/**
	 * The plugin's basename, e.g. "post-type-taxonomy-sync/viget-post-type-taxonomy-sync.php".
	 *
	 * @var string
	 */
	protected $plugin_basename;

	/**
	 * Canned GitHub API response body used by most tests, as a mutable array.
	 *
	 * @var array
	 */
	protected $release;

	/**
	 * The mocked HTTP response's status code.
	 *
	 * @var int
	 */
	protected $http_status = 200;

	/**
	 * Sets up a canned release payload and mocks wp_remote_get().
	 */
	public function set_up() {
		parent::set_up();

		$this->plugin_basename = plugin_basename( VGPTTS_PLUGIN_FILE );

		$this->release = [
			'tag_name'     => 'v9.9.9',
			'html_url'     => 'https://github.com/vigetlabs/fake-repo-test/releases/tag/v9.9.9',
			'published_at' => '2026-01-01T00:00:00Z',
			'body'         => "Fixed a bug.\nSee [the docs](https://example.com) for details.",
			'author'       => [
				'login'    => 'someone',
				'html_url' => 'https://github.com/someone',
			],
			'assets'       => [
				[
					'name'                 => 'fake-repo-test.zip',
					'content_type'         => 'application/zip',
					'browser_download_url' => 'https://github.com/vigetlabs/fake-repo-test/releases/download/v9.9.9/fake-repo-test.zip',
				],
			],
		];

		add_filter( 'pre_http_request', [ $this, 'mock_http_request' ], 10, 3 );
	}

	/**
	 * Removes the HTTP mock and any transients it may have set.
	 */
	public function tear_down() {
		remove_filter( 'pre_http_request', [ $this, 'mock_http_request' ] );
		delete_site_transient( 'vgptts_github_updater_' . md5( 'vigetlabs/fake-repo-test' ) );
		delete_site_transient( 'vgptts_github_updater_' . md5( 'vigetlabs/fake-repo-test' ) . '_error' );

		parent::tear_down();
	}

	/**
	 * Intercepts wp_remote_get() calls to the GitHub releases API.
	 *
	 * @param false|array|WP_Error $preempt Short-circuit value.
	 * @param array                $args    HTTP request args (unused).
	 * @param string               $url     Request URL.
	 *
	 * @return false|array
	 */
	public function mock_http_request( $preempt, $args, $url ) {
		if ( ! str_contains( $url, 'api.github.com/repos/vigetlabs/fake-repo-test/releases/latest' ) ) {
			return $preempt;
		}

		return [
			'response' => [ 'code' => $this->http_status ],
			'body'     => wp_json_encode( $this->release ),
		];
	}

	/**
	 * Builds a fresh updater instance pointed at the fake repo.
	 *
	 * @return GitHub_Plugin_Updater
	 */
	protected function make_updater(): GitHub_Plugin_Updater {
		return new GitHub_Plugin_Updater( VGPTTS_PLUGIN_FILE, 'vigetlabs', 'fake-repo-test' );
	}

	/**
	 * A fake update_plugins transient with the plugin already in the "checked" list.
	 *
	 * @return object
	 */
	protected function make_transient(): object {
		return (object) [
			'checked'   => [ $this->plugin_basename => VGPTTS_PLUGIN_VERSION ],
			'response'  => [],
			'no_update' => [],
		];
	}

	/**
	 * check_for_update() adds a response entry when a newer release exists.
	 */
	public function test_check_for_update_detects_newer_version() {
		$updater   = $this->make_updater();
		$transient = $updater->check_for_update( $this->make_transient() );

		$this->assertArrayHasKey( $this->plugin_basename, $transient->response );
		$this->assertSame( '9.9.9', $transient->response[ $this->plugin_basename ]->new_version );
		$this->assertSame(
			'https://github.com/vigetlabs/fake-repo-test/releases/download/v9.9.9/fake-repo-test.zip',
			$transient->response[ $this->plugin_basename ]->package
		);
	}

	/**
	 * check_for_update() reports no_update when already on the latest version.
	 */
	public function test_check_for_update_reports_no_update_when_current() {
		$this->release['tag_name'] = 'v' . VGPTTS_PLUGIN_VERSION;

		$updater   = $this->make_updater();
		$transient = $updater->check_for_update( $this->make_transient() );

		$this->assertArrayHasKey( $this->plugin_basename, $transient->no_update );
		$this->assertArrayNotHasKey( $this->plugin_basename, $transient->response );
	}

	/**
	 * check_for_update() ignores a transient the plugin hasn't been "checked" in.
	 */
	public function test_check_for_update_ignores_transient_without_checked_data() {
		$updater = $this->make_updater();

		$empty_transient = (object) [];
		$result          = $updater->check_for_update( $empty_transient );

		$this->assertSame( $empty_transient, $result );
	}

	/**
	 * check_for_update() falls back gracefully when the GitHub API returns an error.
	 */
	public function test_check_for_update_handles_api_error() {
		$this->http_status = 404;

		$updater   = $this->make_updater();
		$transient = $updater->check_for_update( $this->make_transient() );

		$this->assertArrayNotHasKey( $this->plugin_basename, $transient->response );
		$this->assertArrayNotHasKey( $this->plugin_basename, $transient->no_update );
	}

	/**
	 * plugin_info() returns release details and a rendered changelog for a matching slug.
	 */
	public function test_plugin_info_returns_release_details() {
		$updater = $this->make_updater();

		$args       = new stdClass();
		$args->slug = 'fake-repo-test';
		$result     = $updater->plugin_info( false, 'plugin_information', $args );

		$this->assertIsObject( $result );
		$this->assertSame( '9.9.9', $result->version );
		$this->assertSame( 'someone', $result->author );
		$this->assertStringContainsString( 'Fixed a bug.', $result->sections['changelog'] );
		$this->assertStringContainsString( '<a href="https://example.com"', $result->sections['changelog'] );
	}

	/**
	 * plugin_info() passes through when the action or slug doesn't match.
	 */
	public function test_plugin_info_passes_through_for_other_requests() {
		$updater = $this->make_updater();

		$this->assertFalse( $updater->plugin_info( false, 'query_plugins', new stdClass() ) );

		$args       = new stdClass();
		$args->slug = 'some-other-plugin';
		$this->assertFalse( $updater->plugin_info( false, 'plugin_information', $args ) );
	}

	/**
	 * get_release_info() (called indirectly via check_for_update()) caches across calls.
	 */
	public function test_release_info_is_cached_in_a_transient() {
		$updater = $this->make_updater();
		$updater->check_for_update( $this->make_transient() );

		$this->http_status = 500; // If a second HTTP call were made, this would surface as no update info.
		$transient         = $updater->check_for_update( $this->make_transient() );

		$this->assertArrayHasKey( $this->plugin_basename, $transient->response );
	}

	/**
	 * format_changelog() escapes HTML and converts markdown links, via Reflection
	 * since the method is private.
	 */
	public function test_format_changelog_escapes_html_and_converts_links() {
		$updater    = $this->make_updater();
		$reflection = new ReflectionMethod( GitHub_Plugin_Updater::class, 'format_changelog' );
		$reflection->setAccessible( true );

		$formatted = $reflection->invoke( $updater, "<script>alert(1)</script>\n[a link](https://example.com/x)" );

		$this->assertStringNotContainsString( '<script>', $formatted );
		$this->assertStringContainsString( '<a href="https://example.com/x" target="_blank" rel="noopener noreferrer">a link</a>', $formatted );
	}

	/**
	 * format_changelog() falls back to a placeholder message for empty input.
	 */
	public function test_format_changelog_handles_empty_changelog() {
		$updater    = $this->make_updater();
		$reflection = new ReflectionMethod( GitHub_Plugin_Updater::class, 'format_changelog' );
		$reflection->setAccessible( true );

		$this->assertSame( 'No changelog available.', $reflection->invoke( $updater, '' ) );
	}
}
