<?php
/**
 * Sync class
 *
 * @package Viget\PostTypeTaxonomySync
 */

namespace Viget\PostTypeTaxonomySync;

use WP_Post;

/**
 * Sync class
 *
 * @package Viget\PostTypeTaxonomySync
 */
class Sync {

	/**
	 * Instance of this class.
	 *
	 * @var Sync|null
	 */
	private static ?Sync $instance = null;

	/**
	 * Flag used to avoid infinite sync loops.
	 *
	 * @var bool
	 */
	protected bool $is_syncing = false;

	/**
	 * Get the singleton instance.
	 *
	 * @return Sync
	 */
	public static function get_instance(): Sync {
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
	 * Initialize the hooks.
	 *
	 * @return void
	 */
	private function init() {
		add_action( 'init', [ $this, 'register_hooks' ] );
	}

	/**
	 * Registers dynamic hooks for selected post types and taxonomies.
	 *
	 * @return void
	 */
	public function register_hooks() {
		$mappings = vgptts()->get_mappings();

		if ( empty( $mappings ) ) {
			return;
		}

		$registered_post_types = [];
		$has_valid_mapping     = false;

		foreach ( $mappings as $mapping ) {
			$post_type = isset( $mapping['post_type'] ) ? $mapping['post_type'] : '';
			$taxonomy  = isset( $mapping['taxonomy'] ) ? $mapping['taxonomy'] : '';

			if ( ! $post_type || ! post_type_exists( $post_type ) ) {
				continue;
			}

			if ( ! $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			$has_valid_mapping = true;

			if ( ! isset( $registered_post_types[ $post_type ] ) ) {
				$registered_post_types[ $post_type ] = true;

				// Sync when a post is saved.
				add_action( "save_post_{$post_type}", [ $this, 'handle_post_save' ], 10, 3 );
			}
		}

		if ( ! $has_valid_mapping ) {
			return;
		}

		// Sync when a post is deleted.
		add_action( 'before_delete_post', [ $this, 'handle_post_delete' ] );

		// Sync when a term is created or updated. Uses the generic `saved_term` hook (fires for
		// both create and update) rather than the dynamic `created_{$taxonomy}`/`edited_{$taxonomy}`
		// hooks: WordPress core passes different (and version-dependent) extra arguments to those
		// dynamic hooks, which makes reading $taxonomy from the callback signature unreliable.
		// `saved_term` reliably passes ( $term_id, $tt_id, $taxonomy, $update, $args ).
		add_action( 'saved_term', [ $this, 'handle_term_save' ], 10, 3 );

		// Sync when a term is deleted. Must run on `pre_delete_term`, not `delete_term`: WordPress
		// core deletes all term meta (including our post-link meta) before `delete_term` fires,
		// so by then the linked post ID is already gone. `pre_delete_term` fires before any
		// modifications are made and reliably passes ( $term_id, $taxonomy ).
		add_action( 'pre_delete_term', [ $this, 'handle_term_delete' ], 10, 2 );
	}

	/**
	 * Handles post save to create or update the related term.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an existing post being updated.
	 *
	 * @return void
	 */
	public function handle_post_save( $post_id, $post, $update ) {
		unset( $update );

		if ( $this->is_syncing ) {
			return;
		}

		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Only sync published posts; skip drafts, auto-draft, pending, trashed, etc.
		if ( 'publish' !== $post->post_status ) {
			return;
		}

		$taxonomy = vgptts()->get_taxonomy_for_post_type( $post->post_type );

		if ( ! $taxonomy ) {
			return;
		}

		$this->is_syncing = true;

		$this->upsert_term_for_post( $post, $taxonomy );

		$this->is_syncing = false;
	}

	/**
	 * Creates or updates the synced term for a post (and links meta both ways).
	 *
	 * If both the post type and taxonomy are hierarchical, this will also sync the
	 * term's parent to match the post's parent (when the parent has a synced term).
	 *
	 * @param WP_Post $post     Post object.
	 * @param string  $taxonomy Taxonomy slug.
	 *
	 * @return int|null Synced term ID or null on failure.
	 */
	protected function upsert_term_for_post( WP_Post $post, string $taxonomy ): ?int {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return null;
		}

		$post_type_obj = get_post_type_object( $post->post_type );
		$tax_obj       = get_taxonomy( $taxonomy );

		$can_hierarchy_sync = ( $post_type_obj && ! empty( $post_type_obj->hierarchical ) ) && ( $tax_obj && ! empty( $tax_obj->hierarchical ) );

		$parent_term_id = 0;
		if ( $can_hierarchy_sync && ! empty( $post->post_parent ) ) {
			$parent_post = get_post( (int) $post->post_parent );
			if ( $parent_post && $parent_post->post_type === $post->post_type && 'publish' === $parent_post->post_status ) {
				$parent_term_id = (int) $this->upsert_term_for_post( $parent_post, $taxonomy );
			}
		}

		$term_id = (int) get_post_meta( $post->ID, Core::POST_META_KEY, true );

		if ( $term_id && term_exists( $term_id, $taxonomy ) ) {
			$args = [
				'name' => $post->post_title,
				'slug' => $post->post_name,
			];

			if ( $can_hierarchy_sync ) {
				$args['parent'] = $parent_term_id;
			}

			/**
			 * Filters the wp_update_term() args used when syncing a post to its term.
			 *
			 * @param array   $args     Term update arguments.
			 * @param WP_Post $post     The post being synced.
			 * @param string  $taxonomy The taxonomy the term belongs to.
			 */
			$args = apply_filters( 'vgptts_synced_term_args', $args, $post, $taxonomy );

			$updated = wp_update_term( $term_id, $taxonomy, $args );
			if ( is_wp_error( $updated ) ) {
				return null;
			}

			return $term_id;
		}

		$args = [
			'slug' => $post->post_name,
		];
		if ( $can_hierarchy_sync ) {
			$args['parent'] = $parent_term_id;
		}

		/** This filter is documented above. */
		$args = apply_filters( 'vgptts_synced_term_args', $args, $post, $taxonomy );

		$created = wp_insert_term( $post->post_title, $taxonomy, $args );
		if ( is_wp_error( $created ) || empty( $created['term_id'] ) ) {
			return null;
		}

		$term_id = (int) $created['term_id'];
		update_post_meta( $post->ID, Core::POST_META_KEY, $term_id );
		update_term_meta( $term_id, Core::TERM_META_KEY, $post->ID );

		return $term_id;
	}

	/**
	 * Handles post deletion to remove the related term.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	public function handle_post_delete( $post_id ) {
		if ( $this->is_syncing ) {
			return;
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			return;
		}

		$taxonomy = vgptts()->get_taxonomy_for_post_type( $post->post_type );

		if ( ! $taxonomy ) {
			return;
		}

		$term_id = (int) get_post_meta( $post_id, Core::POST_META_KEY, true );

		if ( $term_id && term_exists( $term_id, $taxonomy ) ) {
			$this->is_syncing = true;
			wp_delete_term( $term_id, $taxonomy );
			$this->is_syncing = false;
		}
	}

	/**
	 * Handles term creation or update to create or update the related post.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy slug.
	 *
	 * @return void
	 */
	public function handle_term_save( $term_id, $tt_id, $taxonomy ) {
		unset( $tt_id );

		if ( $this->is_syncing ) {
			return;
		}

		$post_type = vgptts()->get_post_type_for_taxonomy( $taxonomy );

		if ( ! $post_type ) {
			return;
		}

		$term = get_term( $term_id, $taxonomy );

		if ( ! $term || is_wp_error( $term ) ) {
			return;
		}

		$this->is_syncing = true;

		$post_id = (int) get_term_meta( $term_id, Core::TERM_META_KEY, true );

		$post_type_obj      = get_post_type_object( $post_type );
		$tax_obj            = get_taxonomy( $taxonomy );
		$can_hierarchy_sync = ( $post_type_obj && ! empty( $post_type_obj->hierarchical ) ) && ( $tax_obj && ! empty( $tax_obj->hierarchical ) );

		$post_parent = 0;
		if ( $can_hierarchy_sync && ! empty( $term->parent ) ) {
			$parent_post_id = vgptts()->get_post_id_for_term( (int) $term->parent );
			if ( $parent_post_id ) {
				$parent_post = get_post( $parent_post_id );
				if ( $parent_post && $parent_post->post_type === $post_type && 'publish' === $parent_post->post_status ) {
					$post_parent = (int) $parent_post_id;
				}
			}
		}

		if ( $post_id && get_post( $post_id ) ) {
			// Update existing post to match term.
			$args = [
				'ID'          => $post_id,
				'post_title'  => $term->name,
				'post_name'   => $term->slug,
				'post_parent' => $post_parent,
			];

			/**
			 * Filters the wp_insert_post()/wp_update_post() args used when syncing a term to its post.
			 *
			 * @param array  $args      Post insert/update arguments.
			 * @param object $term      The term being synced.
			 * @param string $post_type The post type the post belongs to.
			 */
			$args = apply_filters( 'vgptts_synced_post_args', $args, $term, $post_type );

			wp_update_post( $args );
		} else {
			// Create a new post and link both directions.
			$args = [
				'post_type'   => $post_type,
				'post_title'  => $term->name,
				'post_name'   => $term->slug,
				'post_status' => 'publish',
				'post_parent' => $post_parent,
			];

			/** This filter is documented above. */
			$args = apply_filters( 'vgptts_synced_post_args', $args, $term, $post_type );

			$post_id = wp_insert_post( $args );

			if ( $post_id && ! is_wp_error( $post_id ) ) {
				update_post_meta( $post_id, Core::POST_META_KEY, $term_id );
				update_term_meta( $term_id, Core::TERM_META_KEY, $post_id );
			}
		}

		$this->is_syncing = false;
	}

	/**
	 * Handles term deletion to remove the related post.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy slug.
	 *
	 * @return void
	 */
	public function handle_term_delete( $term_id, $taxonomy ) {
		if ( $this->is_syncing ) {
			return;
		}

		$post_type = vgptts()->get_post_type_for_taxonomy( $taxonomy );

		if ( ! $post_type ) {
			return;
		}

		$post_id = (int) get_term_meta( $term_id, Core::TERM_META_KEY, true );

		if ( $post_id && get_post( $post_id ) ) {
			$this->is_syncing = true;
			wp_delete_post( $post_id, true );
			$this->is_syncing = false;
		}
	}

	/**
	 * Performs a full sync of a mapping: ensures every post has a term and removes orphaned terms.
	 *
	 * @param string $post_type Post type slug.
	 * @param string $taxonomy  Taxonomy slug.
	 *
	 * @return void
	 */
	public function sync_terms( string $post_type, string $taxonomy ): void {
		if ( $this->is_syncing ) {
			return;
		}

		if ( ! post_type_exists( $post_type ) || ! taxonomy_exists( $taxonomy ) ) {
			return;
		}

		$this->is_syncing = true;

		// 1. Sync published posts only (create or update terms).
		$posts = get_posts(
			[
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				'fields'         => 'ids',
			]
		);

		foreach ( $posts as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				continue;
			}
			$this->upsert_term_for_post( $post, $taxonomy );
		}

		// 2. Remove terms that reference a missing, wrong-type, or trashed post.
		$terms = get_terms(
			[
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'fields'     => 'ids',
			]
		);

		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term_id ) {
				$post_id = (int) get_term_meta( $term_id, Core::TERM_META_KEY, true );
				if ( ! $post_id ) {
					continue;
				}

				$post = get_post( $post_id );
				if ( ! $post || $post->post_type !== $post_type || 'trash' === $post->post_status ) {
					wp_delete_term( $term_id, $taxonomy );
				}
			}
		}

		$this->is_syncing = false;
	}
}
