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
	 * Select value meaning "register this taxonomy for me".
	 */
	const AUTO_VALUE = '__vgptts_auto__';

	/**
	 * Suffix appended to a slug to build its auto-created counterpart.
	 */
	const AUTO_TAXONOMY_SUFFIX = '_sync';

	/**
	 * Source of truth values. The side named here is the one editors manage.
	 */
	const SOURCE_POST_TYPE = 'post_type';
	const SOURCE_TAXONOMY  = 'taxonomy';

	/**
	 * Builds the post type slug for a mapping whose post type is auto-created.
	 *
	 * Post type names are capped at 20 characters, tighter than taxonomies.
	 *
	 * @param string $taxonomy Taxonomy slug.
	 *
	 * @return string
	 */
	public static function auto_post_type_slug( string $taxonomy ): string {
		$taxonomy = sanitize_key( $taxonomy );

		if ( ! $taxonomy ) {
			return '';
		}

		$max_base = 20 - strlen( self::AUTO_TAXONOMY_SUFFIX );

		return substr( $taxonomy, 0, $max_base ) . self::AUTO_TAXONOMY_SUFFIX;
	}

	/**
	 * Builds the taxonomy slug for an auto-created mapping.
	 *
	 * Taxonomy names are capped at 32 characters, so the post type slug is trimmed
	 * to make room for the suffix.
	 *
	 * @param string $post_type Post type slug.
	 *
	 * @return string
	 */
	public static function auto_taxonomy_slug( string $post_type ): string {
		$post_type = sanitize_key( $post_type );

		if ( ! $post_type ) {
			return '';
		}

		$max_base = 32 - strlen( self::AUTO_TAXONOMY_SUFFIX );

		return substr( $post_type, 0, $max_base ) . self::AUTO_TAXONOMY_SUFFIX;
	}

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
	 * Registrar instance.
	 *
	 * @var Registrar|null
	 */
	public ?Registrar $registrar = null;

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
	 * REST instance.
	 *
	 * @var REST|null
	 */
	private ?REST $rest = null;

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
		require_once VGPTTS_PLUGIN_PATH . 'includes/class-registrar.php';
		require_once VGPTTS_PLUGIN_PATH . 'includes/class-sync.php';
		require_once VGPTTS_PLUGIN_PATH . 'includes/class-admin.php';
		require_once VGPTTS_PLUGIN_PATH . 'includes/class-rest.php';
		require_once VGPTTS_PLUGIN_PATH . 'includes/class-github-plugin-updater.php';

		// Initialize dependencies.
		$this->settings  = Settings::get_instance();
		$this->registrar = Registrar::get_instance();
		$this->sync      = Sync::get_instance();
		$this->admin     = Admin::get_instance();
		$this->rest      = REST::get_instance();

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
			$taxonomy_auto  = ! empty( $mapping['taxonomy_auto'] );
			$post_type_auto = ! empty( $mapping['post_type_auto'] );

			// Only one side can be auto-created: the other is what its slug is derived from.
			if ( $taxonomy_auto && $post_type_auto ) {
				continue;
			}

			$post_type = sanitize_key( isset( $mapping['post_type'] ) ? $mapping['post_type'] : '' );
			$taxonomy  = sanitize_key( isset( $mapping['taxonomy'] ) ? $mapping['taxonomy'] : '' );

			// An auto-created slug is resolved here rather than stored, keeping one source
			// of truth for it.
			if ( $taxonomy_auto ) {
				$taxonomy = self::auto_taxonomy_slug( $post_type );
			} elseif ( $post_type_auto ) {
				$post_type = self::auto_post_type_slug( $taxonomy );
			}

			if ( ! $post_type || ! $taxonomy ) {
				continue;
			}

			$source = isset( $mapping['source_of_truth'] ) && self::SOURCE_TAXONOMY === $mapping['source_of_truth']
				? self::SOURCE_TAXONOMY
				: self::SOURCE_POST_TYPE;

			$result[] = [
				'post_type'               => $post_type,
				'taxonomy'                => $taxonomy,
				'taxonomy_auto'           => $taxonomy_auto,
				'post_type_auto'          => $post_type_auto,
				'attach_to'               => $taxonomy_auto ? array_map( 'sanitize_key', (array) ( $mapping['attach_to'] ?? [] ) ) : [],
				'source_of_truth'         => $source,
				// Only meaningful for an auto-created post type the editor does not manage.
				'show_post_type_in_menus' => $post_type_auto && self::SOURCE_TAXONOMY === $source
					? ! empty( $mapping['show_post_type_in_menus'] )
					: true,
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
