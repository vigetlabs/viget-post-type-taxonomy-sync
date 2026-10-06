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
	 * Prefix of the post meta key that stores a post's synced term ID, one per taxonomy. See get_post_meta_key().
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
	 * REST instance.
	 *
	 * @var REST|null
	 */
	private ?REST $rest = null;

	/**
	 * Upgrade instance.
	 *
	 * @var Upgrade|null
	 */
	private ?Upgrade $upgrade = null;

	/**
	 * Unpublished term IDs per taxonomy, for this request.
	 *
	 * @var array<string, int[]>
	 */
	private array $unpublished_term_ids = [];

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
		require_once VGPTTS_PLUGIN_PATH . 'includes/class-rest.php';
		require_once VGPTTS_PLUGIN_PATH . 'includes/class-upgrade.php';
		require_once VGPTTS_PLUGIN_PATH . 'includes/class-github-plugin-updater.php';

		// Initialize dependencies.
		$this->settings = Settings::get_instance();
		$this->sync     = Sync::get_instance();
		$this->admin    = Admin::get_instance();
		$this->rest     = REST::get_instance();
		$this->upgrade  = Upgrade::get_instance();

		// Check for plugin updates from GitHub releases.
		new GitHub_Plugin_Updater( VGPTTS_PLUGIN_FILE, 'vigetlabs', 'viget-post-type-taxonomy-sync' );
	}

	/**
	 * Gets all active mappings: registered in code first, then saved on the settings page.
	 *
	 * A post type can sync to several taxonomies, but a taxonomy syncs to one post
	 * type, so a mapping that reuses a taxonomy already claimed by an earlier
	 * mapping is left out. See get_flagged_mappings().
	 *
	 * @return array
	 */
	public function get_mappings() {
		$result = $this->resolve_mappings()['active'];

		/**
		 * Filters the resolved post type / taxonomy mappings.
		 *
		 * @param array $result The sanitized mappings, each an array with `post_type` and `taxonomy` keys.
		 */
		return apply_filters( 'vgptts_mappings', $result );
	}

	/**
	 * Gets mappings left out of get_mappings() because an earlier mapping claimed their taxonomy.
	 *
	 * Each has `post_type` and `taxonomy` keys, plus `source` (`registered` or `saved`)
	 * and `conflict`, the active mapping that claimed it.
	 *
	 * @return array
	 */
	public function get_flagged_mappings(): array {
		return $this->resolve_mappings()['flagged'];
	}

	/**
	 * Splits registered and saved mappings into active and flagged, first claim wins.
	 *
	 * @return array{active: array, flagged: array}
	 */
	private function resolve_mappings(): array {
		$active  = [];
		$flagged = [];
		$sources = [
			'registered' => $this->get_registered_mappings(),
			'saved'      => $this->get_saved_mappings(),
		];

		foreach ( $sources as $source => $mappings ) {
			foreach ( $mappings as $mapping ) {
				$conflict = $this->find_conflict( $mapping, $active );

				if ( $conflict ) {
					$flagged[] = $mapping + [
						'source'   => $source,
						'conflict' => $conflict,
					];
				} else {
					$active[] = $mapping;
				}
			}
		}

		return [
			'active'  => $active,
			'flagged' => $flagged,
		];
	}

	/**
	 * Finds the first mapping that shares a taxonomy with the given one.
	 *
	 * A taxonomy syncs to one post type. A post type can sync to several taxonomies.
	 *
	 * @param array $mapping  Mapping with `post_type` and `taxonomy` keys.
	 * @param array $mappings Mappings to search.
	 *
	 * @return array|null
	 */
	public function find_conflict( array $mapping, array $mappings ): ?array {
		foreach ( $mappings as $claimed ) {
			if ( $claimed['taxonomy'] === $mapping['taxonomy'] ) {
				return $claimed;
			}
		}

		return null;
	}

	/**
	 * Gets sanitized mappings saved on the settings page.
	 *
	 * @return array
	 */
	public function get_saved_mappings(): array {
		$settings = $this->settings->get_settings();

		if ( ! $settings || empty( $settings['mappings'] ) ) {
			return [];
		}

		return $this->sanitize_mappings( (array) $settings['mappings'] );
	}

	/**
	 * Gets sanitized mappings registered in code.
	 *
	 * Registered mappings show on the settings page as locked rows that can be
	 * synced but not edited or removed.
	 *
	 * @return array
	 */
	public function get_registered_mappings(): array {
		/**
		 * Filters the mappings registered in code.
		 *
		 * @param array $mappings Mappings, each an array with `post_type` and `taxonomy` keys.
		 */
		$mappings = apply_filters( 'vgptts_registered_mappings', [] );

		return $this->sanitize_mappings( (array) $mappings );
	}

	/**
	 * Whether a saved mapping is overridden by a registered mapping for the same taxonomy.
	 *
	 * @param array      $mapping    Mapping with `post_type` and `taxonomy` keys.
	 * @param array|null $registered Registered mappings. Defaults to get_registered_mappings().
	 *
	 * @return bool
	 */
	public function is_overridden( array $mapping, ?array $registered = null ): bool {
		return null !== $this->find_conflict( $mapping, $registered ?? $this->get_registered_mappings() );
	}

	/**
	 * Drops incomplete and duplicate mappings, and sanitizes slugs.
	 *
	 * @param array $mappings Raw mappings.
	 *
	 * @return array
	 */
	private function sanitize_mappings( array $mappings ): array {
		$result = [];

		foreach ( $mappings as $mapping ) {
			if ( ! \is_array( $mapping ) || empty( $mapping['post_type'] ) || empty( $mapping['taxonomy'] ) ) {
				continue;
			}

			$sanitized = [
				'post_type' => sanitize_key( $mapping['post_type'] ),
				'taxonomy'  => sanitize_key( $mapping['taxonomy'] ),
			];

			if ( ! \in_array( $sanitized, $result, true ) ) {
				$result[] = $sanitized;
			}
		}

		return $result;
	}

	/**
	 * Finds the taxonomies mapped to a given post type, in mapping order.
	 *
	 * @param string $post_type Post type slug.
	 *
	 * @return string[]
	 */
	public function get_taxonomies_for_post_type( string $post_type ): array {
		$taxonomies = [];

		foreach ( $this->get_mappings() as $mapping ) {
			if ( $mapping['post_type'] === $post_type ) {
				$taxonomies[] = $mapping['taxonomy'];
			}
		}

		return array_values( array_unique( $taxonomies ) );
	}

	/**
	 * Finds the first taxonomy mapped to a given post type.
	 *
	 * A post type can map to several taxonomies. Use get_taxonomies_for_post_type() for all of them.
	 *
	 * @param string $post_type Post type slug.
	 *
	 * @return string|null
	 */
	public function get_taxonomy_for_post_type( $post_type ) {
		return $this->get_taxonomies_for_post_type( (string) $post_type )[0] ?? null;
	}

	/**
	 * Gets the post meta key that stores a post's synced term ID in a taxonomy.
	 *
	 * @param string $taxonomy Taxonomy slug.
	 *
	 * @return string
	 */
	public function get_post_meta_key( string $taxonomy ): string {
		return self::POST_META_KEY . '_' . $taxonomy;
	}

	/**
	 * Gets a post's synced term ID in a taxonomy.
	 *
	 * Until Upgrade has moved a site's data to per-taxonomy keys, the term may
	 * still be under the old single key, so that's checked too.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy slug.
	 *
	 * @return int|null Term ID or null if the post has no synced term in that taxonomy.
	 */
	public function get_term_id_for_post( int $post_id, string $taxonomy ): ?int {
		$term_id = (int) get_post_meta( $post_id, $this->get_post_meta_key( $taxonomy ), true );

		if ( ! $term_id && ! Upgrade::is_current() ) {
			$legacy_id = (int) get_post_meta( $post_id, self::POST_META_KEY, true );
			$term      = $legacy_id ? get_term( $legacy_id ) : null;
			$term_id   = $term instanceof \WP_Term && $term->taxonomy === $taxonomy ? $legacy_id : 0;
		}

		return $term_id ? $term_id : null;
	}

	/**
	 * Gets the IDs of a synced taxonomy's terms whose post isn't published.
	 *
	 * A trashed or unpublished post keeps its term, so restoring it brings back
	 * every relationship. The term is only removed when the post is deleted.
	 *
	 * @param string $taxonomy Taxonomy slug.
	 *
	 * @return int[]
	 */
	public function get_unpublished_term_ids( string $taxonomy ): array {
		$post_type = $this->get_post_type_for_taxonomy( $taxonomy );

		if ( ! $post_type ) {
			return [];
		}

		// Recomputed whenever any post changes.
		$cache_key = $taxonomy . ':' . wp_cache_get_last_changed( 'posts' );

		if ( isset( $this->unpublished_term_ids[ $cache_key ] ) ) {
			return $this->unpublished_term_ids[ $cache_key ];
		}

		$meta_query = [ [ 'key' => $this->get_post_meta_key( $taxonomy ) ] ];

		if ( ! Upgrade::is_current() ) {
			$meta_query[]           = [ 'key' => self::POST_META_KEY ];
			$meta_query['relation'] = 'OR';
		}

		$post_ids = get_posts(
			[
				'post_type'        => $post_type,
				'post_status'      => array_values( array_diff( get_post_stati(), [ 'publish', 'auto-draft', 'inherit' ] ) ),
				'posts_per_page'   => -1,
				'no_found_rows'    => true,
				'fields'           => 'ids',
				'meta_query'       => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Only posts linked to a term.
				'suppress_filters' => true,
			]
		);

		$term_ids = array_filter( array_map( fn( $post_id ) => (int) $this->get_term_id_for_post( (int) $post_id, $taxonomy ), $post_ids ) );

		$this->unpublished_term_ids[ $cache_key ] = array_values( array_unique( $term_ids ) );

		return $this->unpublished_term_ids[ $cache_key ];
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
	 * Gets the published post IDs related to a post through its synced taxonomy terms.
	 *
	 * A related post that's trashed or unpublished is left out until it's published again.
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
			if ( ! $related_post_id || 'publish' !== get_post_status( (int) $related_post_id ) ) {
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
