<?php
/**
 * REST API endpoints
 *
 * @package Viget\PostTypeTaxonomySync
 */

namespace Viget\PostTypeTaxonomySync;

use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Term;

/**
 * Registers read-only REST API routes for synced post/term relationships.
 *
 * @package Viget\PostTypeTaxonomySync
 */
class REST {

	/**
	 * REST route namespace.
	 */
	const ROUTE_NAMESPACE = 'vgptts/v1';

	/**
	 * Instance of this class.
	 *
	 * @var REST|null
	 */
	private static ?REST $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return REST
	 */
	public static function get_instance(): REST {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Registers the REST routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/posts/(?P<id>\d+)/synced-term',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_synced_term_for_post' ],
				'permission_callback' => [ $this, 'post_permissions_check' ],
				'args'                => [
					'id' => [
						'required'          => true,
						'validate_callback' => static function ( $value ) {
							return is_numeric( $value );
						},
					],
				],
			]
		);

		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/terms/(?P<id>\d+)/synced-post',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_synced_post_for_term' ],
				'permission_callback' => [ $this, 'term_permissions_check' ],
				'args'                => [
					'id' => [
						'required'          => true,
						'validate_callback' => static function ( $value ) {
							return is_numeric( $value );
						},
					],
				],
			]
		);

		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/posts/(?P<id>\d+)/related',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_related_posts' ],
				'permission_callback' => [ $this, 'post_permissions_check' ],
				'args'                => [
					'id'       => [
						'required'          => true,
						'validate_callback' => static function ( $value ) {
							return is_numeric( $value );
						},
					],
					'taxonomy' => [
						'required'          => true,
						'validate_callback' => static function ( $value ) {
							return is_string( $value ) && taxonomy_exists( $value );
						},
					],
				],
			]
		);
	}

	/**
	 * Permission check for routes keyed by a post ID.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return true|WP_Error
	 */
	public function post_permissions_check( WP_REST_Request $request ) {
		$post = get_post( (int) $request['id'] );

		if ( ! $post ) {
			return new WP_Error(
				'vgptts_post_not_found',
				__( 'Post not found.', 'viget-post-type-taxonomy-sync' ),
				[ 'status' => 404 ]
			);
		}

		if ( is_post_publicly_viewable( $post ) || current_user_can( 'read_post', $post->ID ) ) {
			return true;
		}

		return new WP_Error(
			'vgptts_cannot_read_post',
			__( 'Sorry, you are not allowed to view this post.', 'viget-post-type-taxonomy-sync' ),
			[ 'status' => rest_authorization_required_code() ]
		);
	}

	/**
	 * Permission check for routes keyed by a term ID.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return true|WP_Error
	 */
	public function term_permissions_check( WP_REST_Request $request ) {
		$term = get_term( (int) $request['id'] );

		if ( ! $term || is_wp_error( $term ) ) {
			return new WP_Error(
				'vgptts_term_not_found',
				__( 'Term not found.', 'viget-post-type-taxonomy-sync' ),
				[ 'status' => 404 ]
			);
		}

		$tax_obj = get_taxonomy( $term->taxonomy );

		if ( $tax_obj && ( ! empty( $tax_obj->public ) || current_user_can( $tax_obj->cap->assign_terms ) ) ) {
			return true;
		}

		return new WP_Error(
			'vgptts_cannot_read_term',
			__( 'Sorry, you are not allowed to view this term.', 'viget-post-type-taxonomy-sync' ),
			[ 'status' => rest_authorization_required_code() ]
		);
	}

	/**
	 * Returns the term synced to a post.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_synced_term_for_post( WP_REST_Request $request ) {
		$post_id = (int) $request['id'];
		$post    = get_post( $post_id );

		$taxonomy = vgptts()->get_taxonomy_for_post_type( $post->post_type );

		if ( ! $taxonomy ) {
			return new WP_Error(
				'vgptts_no_mapping',
				__( 'This post type is not mapped to a taxonomy.', 'viget-post-type-taxonomy-sync' ),
				[ 'status' => 404 ]
			);
		}

		$term_id = (int) get_post_meta( $post_id, Core::POST_META_KEY, true );
		$term    = $term_id ? get_term( $term_id, $taxonomy ) : null;

		if ( ! $term || is_wp_error( $term ) ) {
			return new WP_Error(
				'vgptts_no_synced_term',
				__( 'This post does not have a synced term yet.', 'viget-post-type-taxonomy-sync' ),
				[ 'status' => 404 ]
			);
		}

		return new WP_REST_Response( self::prepare_term_response( $term ) );
	}

	/**
	 * Returns the post synced to a term.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_synced_post_for_term( WP_REST_Request $request ) {
		$term_id = (int) $request['id'];
		$post_id = vgptts()->get_post_id_for_term( $term_id );
		$post    = $post_id ? get_post( $post_id ) : null;

		if ( ! $post ) {
			return new WP_Error(
				'vgptts_no_synced_post',
				__( 'This term does not have a synced post.', 'viget-post-type-taxonomy-sync' ),
				[ 'status' => 404 ]
			);
		}

		if ( ! is_post_publicly_viewable( $post ) && ! current_user_can( 'read_post', $post->ID ) ) {
			return new WP_Error(
				'vgptts_cannot_read_post',
				__( 'Sorry, you are not allowed to view this post.', 'viget-post-type-taxonomy-sync' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		return new WP_REST_Response( self::prepare_post_response( $post ) );
	}

	/**
	 * Returns posts related to a post through a taxonomy synced to another post type.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response
	 */
	public function get_related_posts( WP_REST_Request $request ) {
		$post_id  = (int) $request['id'];
		$taxonomy = (string) $request['taxonomy'];

		$related_post_ids = vgptts()->get_related_post_ids_for_post( $post_id, $taxonomy );

		$posts = [];

		foreach ( $related_post_ids as $related_post_id ) {
			$related_post = get_post( $related_post_id );

			if ( ! $related_post ) {
				continue;
			}

			if ( ! is_post_publicly_viewable( $related_post ) && ! current_user_can( 'read_post', $related_post->ID ) ) {
				continue;
			}

			$posts[] = self::prepare_post_response( $related_post );
		}

		return new WP_REST_Response( $posts );
	}

	/**
	 * Prepares a post for inclusion in a REST response.
	 *
	 * @param WP_Post $post Post object.
	 *
	 * @return array
	 */
	protected static function prepare_post_response( WP_Post $post ): array {
		return [
			'id'        => $post->ID,
			'post_type' => $post->post_type,
			'status'    => $post->post_status,
			'title'     => get_the_title( $post ),
			'slug'      => $post->post_name,
			'link'      => get_permalink( $post ),
			'parent'    => (int) $post->post_parent,
		];
	}

	/**
	 * Prepares a term for inclusion in a REST response.
	 *
	 * @param WP_Term $term Term object.
	 *
	 * @return array
	 */
	protected static function prepare_term_response( WP_Term $term ): array {
		return [
			'id'       => $term->term_id,
			'taxonomy' => $term->taxonomy,
			'name'     => $term->name,
			'slug'     => $term->slug,
			'link'     => get_term_link( $term ),
			'parent'   => (int) $term->parent,
		];
	}
}
