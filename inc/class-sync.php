<?php
/**
 * Sync class
 *
 * @package PostTypeTaxonomySync
 */

namespace PostTypeTaxonomySync;

use WP_Post;

/**
 * Sync class
 *
 * @package PostTypeTaxonomySync
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
		$mappings = PTTS()->get_mappings();

		if ( empty( $mappings ) ) {
			return;
		}

		foreach ( $mappings as $mapping ) {
			$post_type = isset( $mapping['post_type'] ) ? $mapping['post_type'] : '';
			$taxonomy  = isset( $mapping['taxonomy'] ) ? $mapping['taxonomy'] : '';

			if ( ! $post_type || ! post_type_exists( $post_type ) ) {
				continue;
			}

			if ( ! $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			// Sync when a post is saved.
			add_action( "save_post_{$post_type}", [ $this, 'handle_post_save' ], 10, 3 );

			// Sync when a post is deleted.
			add_action( 'before_delete_post', [ $this, 'handle_post_delete' ] );

			// Sync when a term is created or updated.
			add_action( "created_{$taxonomy}", [ $this, 'handle_term_save' ], 10, 3 );
			add_action( "edited_{$taxonomy}", [ $this, 'handle_term_save' ], 10, 3 );

			// Sync when a term is deleted.
			add_action( "delete_{$taxonomy}", [ $this, 'handle_term_delete' ], 10, 4 );
		}
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
		if ( $this->is_syncing ) {
			return;
		}

		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( 'trash' === $post->post_status ) {
			return;
		}

		$taxonomy = PTTS()->get_taxonomy_for_post_type( $post->post_type );

		if ( ! $taxonomy ) {
			return;
		}

		$this->is_syncing = true;

		$term_id = (int) get_post_meta( $post_id, Core::POST_META_KEY, true );

		if ( $term_id && term_exists( $term_id, $taxonomy ) ) {
			// Update existing term to match post.
			wp_update_term(
				$term_id,
				$taxonomy,
				[
					'name' => $post->post_title,
					'slug' => $post->post_name,
				]
			);
		} else {
			// Create a new term and link both directions.
			$created = wp_insert_term(
				$post->post_title,
				$taxonomy,
				[
					'slug' => $post->post_name,
				]
			);

			if ( ! is_wp_error( $created ) && ! empty( $created['term_id'] ) ) {
				$term_id = (int) $created['term_id'];

				update_post_meta( $post_id, Core::POST_META_KEY, $term_id );
				update_term_meta( $term_id, Core::TERM_META_KEY, $post_id );
			}
		}

		$this->is_syncing = false;
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

		$taxonomy = PTTS()->get_taxonomy_for_post_type( $post->post_type );

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

		$post_type = PTTS()->get_post_type_for_taxonomy( $taxonomy );

		if ( ! $post_type ) {
			return;
		}

		$term = get_term( $term_id, $taxonomy );

		if ( ! $term || is_wp_error( $term ) ) {
			return;
		}

		$this->is_syncing = true;

		$post_id = (int) get_term_meta( $term_id, Core::TERM_META_KEY, true );

		if ( $post_id && get_post( $post_id ) ) {
			// Update existing post to match term.
			wp_update_post(
				[
					'ID'         => $post_id,
					'post_title' => $term->name,
					'post_name'  => $term->slug,
				]
			);
		} else {
			// Create a new post and link both directions.
			$post_id = wp_insert_post(
				[
					'post_type'   => $post_type,
					'post_title'  => $term->name,
					'post_name'   => $term->slug,
					'post_status' => 'publish',
				]
			);

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
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @param object $deleted_term Deleted term object.
	 *
	 * @return void
	 */
	public function handle_term_delete( $term_id, $tt_id, $taxonomy, $deleted_term ) {
		unset( $tt_id, $deleted_term );

		if ( $this->is_syncing ) {
			return;
		}

		$post_type = PTTS()->get_post_type_for_taxonomy( $taxonomy );

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

		// 1. Sync all posts of this type to terms (create or update).
		$posts = get_posts(
			[
				'post_type'      => $post_type,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				'fields'         => 'ids',
			]
		);

		foreach ( $posts as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post || 'trash' === $post->post_status ) {
				continue;
			}

			$term_id = (int) get_post_meta( $post_id, Core::POST_META_KEY, true );

			if ( $term_id && term_exists( $term_id, $taxonomy ) ) {
				wp_update_term(
					$term_id,
					$taxonomy,
					[
						'name' => $post->post_title,
						'slug' => $post->post_name,
					]
				);
			} else {
				$created = wp_insert_term(
					$post->post_title,
					$taxonomy,
					[ 'slug' => $post->post_name ]
				);

				if ( ! is_wp_error( $created ) && ! empty( $created['term_id'] ) ) {
					$term_id = (int) $created['term_id'];
					update_post_meta( $post_id, Core::POST_META_KEY, $term_id );
					update_term_meta( $term_id, Core::TERM_META_KEY, $post_id );
				}
			}
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
