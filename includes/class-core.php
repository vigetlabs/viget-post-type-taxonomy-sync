<?php
/**
 * Core class
 *
 * @package Viget\PostTypeTaxonomySync
 */

namespace Viget\PostTypeTaxonomySync;

/**
 * Core class
 *
 * @package Viget\PostTypeTaxonomySync
 */
class Core {

	/**
	 * Option name for plugin settings.
	 */
	const OPTION_NAME = 'vgptts_settings';

	/**
	 * Meta key for storing related term ID on a post.
	 */
	const POST_META_KEY = '_vgptts_term_id';

	/**
	 * Meta key for storing related post ID on a term.
	 */
	const TERM_META_KEY = '_vgptts_post_id';

	/**
	 * Instance of this class.
	 *
	 * @var Core|null
	 */
	private static ?Core $instance = null;

	/**
	 * Settings instance.
	 *
	 * @var Settings|null
	 */
	public ?Settings $settings = null;

	/**
	 * Sync instance.
	 *
	 * @var Sync|null
	 */
	public ?Sync $sync = null;

	/**
	 * Admin instance.
	 *
	 * @var Admin|null
	 */
	private ?Admin $admin = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Core
	 */
	public static function get_instance(): Core {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init();
	}

	/**
	 * Initialize the plugin.
	 *
	 * @return void
	 */
	private function init(): void {
		// Load dependencies.
		require_once VGPTTS_PLUGIN_PATH . 'includes/class-settings.php';
		require_once VGPTTS_PLUGIN_PATH . 'includes/class-sync.php';
		require_once VGPTTS_PLUGIN_PATH . 'includes/class-admin.php';
		require_once VGPTTS_PLUGIN_PATH . 'includes/class-github-plugin-updater.php';

		// Initialize dependencies.
		$this->settings = Settings::get_instance();
		$this->sync     = Sync::get_instance();
		$this->admin    = Admin::get_instance();

		// Check for plugin updates from GitHub releases.
		new GitHub_Plugin_Updater( VGPTTS_PLUGIN_FILE, 'vigetlabs', 'viget-post-type-taxonomy-sync' );
	}

	/**
	 * Gets sanitized mappings from the options table.
	 *
	 * @return array
	 */
	public function get_mappings() {
		$settings = $this->settings->get_settings();

		if ( ! $settings ) {
			return [];
		}

		$mappings = $settings['mappings'];
		$result   = [];

		foreach ( $mappings as $mapping ) {
			if ( empty( $mapping['post_type'] ) || empty( $mapping['taxonomy'] ) ) {
				continue;
			}

			$result[] = [
				'post_type' => sanitize_key( $mapping['post_type'] ),
				'taxonomy'  => sanitize_key( $mapping['taxonomy'] ),
			];
		}

		/**
		 * Filters the resolved post type / taxonomy mappings.
		 *
		 * @param array $result The sanitized mappings, each an array with `post_type` and `taxonomy` keys.
		 */
		return apply_filters( 'vgptts_mappings', $result );
	}

	/**
	 * Finds the mapped taxonomy for a given post type.
	 *
	 * @param string $post_type Post type slug.
	 *
	 * @return string|null
	 */
	public function get_taxonomy_for_post_type( $post_type ) {
		$mappings = $this->get_mappings();

		foreach ( $mappings as $mapping ) {
			if ( $mapping['post_type'] === $post_type ) {
				return $mapping['taxonomy'];
			}
		}

		return null;
	}

	/**
	 * Finds the mapped post type for a given taxonomy.
	 *
	 * @param string $taxonomy Taxonomy slug.
	 *
	 * @return string|null
	 */
	public function get_post_type_for_taxonomy( $taxonomy ) {
		$mappings = $this->get_mappings();

		foreach ( $mappings as $mapping ) {
			if ( $mapping['taxonomy'] === $taxonomy ) {
				return $mapping['post_type'];
			}
		}

		return null;
	}

	/**
	 * Gets the post IDs related to a post through its synced taxonomy terms.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy slug.
	 *
	 * @return array
	 */
	public function get_related_post_ids_for_post( $post_id, $taxonomy ) {
		$related_terms = get_the_terms( $post_id, $taxonomy );

		if ( ! $related_terms || is_wp_error( $related_terms ) ) {
			return [];
		}

		$related_post_ids = [];

		foreach ( $related_terms as $related_term ) {
			$related_post_id = get_term_meta( $related_term->term_id, self::TERM_META_KEY, true );
			if ( ! $related_post_id ) {
				continue;
			}

			$related_post_ids[] = (int) $related_post_id;
		}

		return $related_post_ids;
	}

	/**
	 * Gets the post ID for a given term.
	 *
	 * @param int $term_id Term ID.
	 *
	 * @return int|null Post ID or null if not found.
	 */
	public function get_post_id_for_term( $term_id ) {
		$post_id = get_term_meta( $term_id, self::TERM_META_KEY, true );
		if ( ! $post_id ) {
			return null;
		}

		return (int) $post_id;
	}
}
