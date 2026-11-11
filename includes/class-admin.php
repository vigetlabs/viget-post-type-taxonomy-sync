<?php
/**
 * Admin class
 *
 * @package Viget\PostTypeTaxonomySync
 */

namespace Viget\PostTypeTaxonomySync;

/**
 * Admin class
 *
 * @package Viget\PostTypeTaxonomySync
 */
class Admin {

	/**
	 * User meta key for dismissed hierarchy warnings.
	 */
	const DISMISSED_WARNINGS_META_KEY = 'vgptts_dismissed_hierarchy_warnings';

	/**
	 * Instance of this class.
	 *
	 * @var Admin|null
	 */
	private static ?Admin $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Admin
	 */
	public static function get_instance(): Admin {
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
		add_action( 'rest_api_init', [ $this, 'register_rest_terms_exclude_filter' ] );

		if ( ! is_admin() ) {
			return;
		}

		add_action( 'admin_menu', [ $this, 'hide_synced_taxonomy_submenus' ], 999 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_hide_synced_taxonomy_submenu_css' ], 10 );
		add_action( 'admin_enqueue_scripts', [ $this, 'maybe_hide_synced_taxonomy_metabox_add_new_ui' ] );
		add_action( 'enqueue_block_editor_assets', [ $this, 'maybe_hide_block_editor_add_term_ui' ] );
		add_action( 'wp_ajax_add-tag', [ $this, 'maybe_block_ajax_add_tag_for_synced_taxonomy' ], 0 );
		add_filter( 'get_terms_args', [ $this, 'exclude_synced_term_from_terms_list' ], 10, 2 );
		add_action( 'admin_notices', [ $this, 'render_hierarchy_notices' ] );
		add_action( 'wp_ajax_vgptts_dismiss_hierarchy_warning', [ $this, 'handle_ajax_dismiss_hierarchy_warning' ] );
	}

	/**
	 * Get mapped taxonomies for a given post type.
	 *
	 * @param string $post_type Post type slug.
	 *
	 * @return string[]
	 */
	protected function get_synced_taxonomies_for_post_type( string $post_type ): array {
		$result   = [];
		$mappings = vgptts()->get_mappings();

		foreach ( $mappings as $mapping ) {
			$mapped_post_type = isset( $mapping['post_type'] ) ? sanitize_key( $mapping['post_type'] ) : '';
			$taxonomy         = isset( $mapping['taxonomy'] ) ? sanitize_key( $mapping['taxonomy'] ) : '';

			if ( ! $mapped_post_type || ! $taxonomy ) {
				continue;
			}

			if ( $mapped_post_type !== $post_type ) {
				continue;
			}

			$result[] = $taxonomy;
		}

		return array_values( array_unique( $result ) );
	}

	/**
	 * Whether a taxonomy is configured to sync.
	 *
	 * @param string $taxonomy Taxonomy slug.
	 *
	 * @return bool
	 */
	protected function is_synced_taxonomy( string $taxonomy ): bool {
		return ! empty( vgptts()->get_post_type_for_taxonomy( $taxonomy ) );
	}

	/**
	 * Removes taxonomy term-management submenu items for mapped taxonomies.
	 *
	 * @return void
	 */
	public function hide_synced_taxonomy_submenus(): void {
		$mappings = vgptts()->get_mappings();

		if ( empty( $mappings ) ) {
			return;
		}

		foreach ( $mappings as $mapping ) {
			$post_type = isset( $mapping['post_type'] ) ? sanitize_key( $mapping['post_type'] ) : '';
			$taxonomy  = isset( $mapping['taxonomy'] ) ? sanitize_key( $mapping['taxonomy'] ) : '';

			if ( ! $post_type || ! post_type_exists( $post_type ) ) {
				continue;
			}

			if ( ! $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			// Remove from the mapped post type menu...
			$parent_map = [];
			$parent_map[ ( 'post' === $post_type ) ? 'edit.php' : "edit.php?post_type={$post_type}" ] = $post_type;

			// ...and from any other post type menus the taxonomy is registered to.
			$tax_obj = get_taxonomy( $taxonomy );
			if ( $tax_obj && ! empty( $tax_obj->object_type ) && \is_array( $tax_obj->object_type ) ) {
				foreach ( $tax_obj->object_type as $obj_post_type ) {
					$obj_post_type = sanitize_key( (string) $obj_post_type );
					if ( ! $obj_post_type || ! post_type_exists( $obj_post_type ) ) {
						continue;
					}
					$parent_map[ ( 'post' === $obj_post_type ) ? 'edit.php' : "edit.php?post_type={$obj_post_type}" ] = $obj_post_type;
				}
			}

			foreach ( $parent_map as $parent_slug => $parent_post_type ) {
				// Most menus include the post_type param.
				remove_submenu_page( $parent_slug, "edit-tags.php?taxonomy={$taxonomy}&post_type={$parent_post_type}" );

				// Fallback (some taxonomies don't include post_type in the slug).
				remove_submenu_page( $parent_slug, "edit-tags.php?taxonomy={$taxonomy}" );
			}
		}
	}

	/**
	 * Hides synced taxonomy submenu items via CSS (fallback if remove_submenu_page does not apply).
	 *
	 * @return void
	 */
	public function enqueue_hide_synced_taxonomy_submenu_css(): void {
		$mappings = vgptts()->get_mappings();
		if ( empty( $mappings ) ) {
			return;
		}

		$rules = [];
		foreach ( $mappings as $mapping ) {
			$taxonomy = isset( $mapping['taxonomy'] ) ? sanitize_key( $mapping['taxonomy'] ) : '';
			if ( ! $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			$rules[] = \sprintf(
				'ul.wp-submenu>li:has(a[href*="edit-tags.php?taxonomy=%s"]){display:none!important;}',
				esc_attr( $taxonomy )
			);
		}

		if ( empty( $rules ) ) {
			return;
		}

		$css = implode( "\n", $rules );
		if ( ! wp_style_is( 'vgptts-hide-submenu', 'registered' ) ) {
			wp_register_style( 'vgptts-hide-submenu', false, [], VGPTTS_PLUGIN_VERSION );
		}
		wp_enqueue_style( 'vgptts-hide-submenu' );
		wp_add_inline_style( 'vgptts-hide-submenu', $css );
	}

	/**
	 * Hide the "Add New Term" UI in taxonomy meta boxes for synced taxonomies.
	 *
	 * @param string $hook_suffix Admin page hook.
	 *
	 * @return void
	 */
	public function maybe_hide_synced_taxonomy_metabox_add_new_ui( string $hook_suffix ): void {
		if ( ! \in_array( $hook_suffix, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || empty( $screen->post_type ) ) {
			return;
		}

		$taxonomies = $this->get_synced_taxonomies_for_post_type( $screen->post_type );

		if ( empty( $taxonomies ) ) {
			return;
		}

		$css_parts = [];
		foreach ( $taxonomies as $taxonomy ) {
			// Hierarchical taxonomy meta box (categories-style).
			$css_parts[] = "#{$taxonomy}div .category-add{display:none !important;}";

			// Non-hierarchical taxonomy meta box (tags-style).
			$css_parts[] = "#tagsdiv-{$taxonomy} .jaxtag{display:none !important;}";
			$css_parts[] = "#tagsdiv-{$taxonomy} .tagadd{display:none !important;}";
		}

		$css = implode( "\n", $css_parts );
		if ( ! wp_style_is( 'vgptts-admin-inline', 'registered' ) ) {
			wp_register_style( 'vgptts-admin-inline', false, [], VGPTTS_PLUGIN_VERSION );
		}
		wp_enqueue_style( 'vgptts-admin-inline' );
		wp_add_inline_style( 'vgptts-admin-inline', $css );
	}

	/**
	 * Hide the "Add New Term" button in the block editor taxonomy panel for synced taxonomies.
	 *
	 * @return void
	 */
	public function maybe_hide_block_editor_add_term_ui(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || empty( $screen->post_type ) ) {
			return;
		}

		$taxonomies = $this->get_synced_taxonomies_for_post_type( $screen->post_type );

		if ( empty( $taxonomies ) ) {
			return;
		}

		$css = '.editor-post-taxonomies__hierarchical-terms-add{display:none !important;}';
		if ( ! wp_style_is( 'vgptts-block-editor-inline', 'registered' ) ) {
			wp_register_style( 'vgptts-block-editor-inline', false, [], VGPTTS_PLUGIN_VERSION );
		}
		wp_enqueue_style( 'vgptts-block-editor-inline' );
		wp_add_inline_style( 'vgptts-block-editor-inline', $css );
	}

	/**
	 * Blocks AJAX term creation from the post editor for synced taxonomies.
	 *
	 * Note: This only affects the admin-ajax endpoint used by the taxonomy meta box quick-add.
	 *
	 * @return void
	 */
	public function maybe_block_ajax_add_tag_for_synced_taxonomy(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read-only decision to short-circuit; WP core's own wp_ajax_add_tag() (priority 10) still verifies the nonce before creating anything.
		$taxonomy = isset( $_POST['taxonomy'] ) ? sanitize_key( wp_unslash( $_POST['taxonomy'] ) ) : '';

		if ( ! $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
			return;
		}

		if ( ! $this->is_synced_taxonomy( $taxonomy ) ) {
			return;
		}

		wp_send_json_error(
			[
				'message' => __( 'This taxonomy is managed automatically and does not allow adding new terms here.', 'viget-post-type-taxonomy-sync' ),
			],
			403
		);
	}

	/**
	 * Registers the REST API filter so the block editor excludes the current post's synced term.
	 *
	 * @return void
	 */
	public function register_rest_terms_exclude_filter(): void {
		$mappings = vgptts()->get_mappings();
		if ( empty( $mappings ) ) {
			return;
		}

		foreach ( $mappings as $mapping ) {
			$taxonomy = isset( $mapping['taxonomy'] ) ? sanitize_key( $mapping['taxonomy'] ) : '';
			if ( ! $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			add_filter( "rest_{$taxonomy}_query", [ $this, 'exclude_synced_term_in_rest_terms_query' ], 10, 2 );
		}
	}

	/**
	 * Excludes the current post's synced term from REST terms listing (block editor).
	 *
	 * When the block editor fetches terms, the request Referer is the post edit URL.
	 * We parse the post ID and exclude that post's synced term from the response.
	 *
	 * @param array            $prepared_args Arguments for the term query.
	 * @param \WP_REST_Request $request       REST request.
	 *
	 * @return array Modified arguments.
	 */
	public function exclude_synced_term_in_rest_terms_query( array $prepared_args, \WP_REST_Request $request ): array {
		$post_id = $this->get_post_id_from_referer();
		if ( ! $post_id ) {
			return $prepared_args;
		}

		$post = get_post( $post_id );
		if ( ! $post || ! $post->post_type ) {
			return $prepared_args;
		}

		$taxonomy_for_type = vgptts()->get_taxonomy_for_post_type( $post->post_type );
		if ( ! $taxonomy_for_type ) {
			return $prepared_args;
		}

		$request_taxonomy = $this->get_taxonomy_from_rest_route( $request );
		if ( $request_taxonomy !== $taxonomy_for_type ) {
			return $prepared_args;
		}

		$synced_term_id = (int) get_post_meta( $post_id, Core::POST_META_KEY, true );
		if ( $synced_term_id <= 0 ) {
			return $prepared_args;
		}

		$exclude                  = isset( $prepared_args['exclude'] ) ? (array) $prepared_args['exclude'] : [];
		$exclude[]                = $synced_term_id;
		$prepared_args['exclude'] = array_unique( array_filter( $exclude ) );

		return $prepared_args;
	}

	/**
	 * Gets the post ID from the HTTP Referer when editing in admin (e.g. block editor).
	 *
	 * @return int|null Post ID or null if not found.
	 */
	protected function get_post_id_from_referer(): ?int {
		$referer = isset( $_SERVER['HTTP_REFERER'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		if ( ! is_string( $referer ) || '' === $referer ) {
			return null;
		}

		$query = wp_parse_url( $referer, PHP_URL_QUERY );
		if ( ! is_string( $query ) ) {
			return null;
		}

		parse_str( $query, $params );
		if ( empty( $params['post'] ) ) {
			return null;
		}

		$post_id = (int) $params['post'];
		return $post_id > 0 ? $post_id : null;
	}

	/**
	 * Gets the taxonomy slug from the REST request route (e.g. /wp/v2/product -> product).
	 *
	 * The route segment may be the taxonomy name or its rest_base.
	 *
	 * @param \WP_REST_Request $request REST request.
	 *
	 * @return string Taxonomy slug or empty string.
	 */
	protected function get_taxonomy_from_rest_route( \WP_REST_Request $request ): string {
		$route = $request->get_route();
		if ( ! is_string( $route ) || '' === $route ) {
			return '';
		}

		$route = trim( $route, '/' );
		$parts = explode( '/', $route );
		$slug  = end( $parts );

		if ( '' === $slug ) {
			return '';
		}

		if ( taxonomy_exists( $slug ) ) {
			return $slug;
		}

		$taxonomies = get_taxonomies( [ 'show_in_rest' => true ], 'objects' );
		foreach ( $taxonomies as $taxonomy => $tax_obj ) {
			$rest_base = ! empty( $tax_obj->rest_base ) ? $tax_obj->rest_base : $tax_obj->name;
			if ( $rest_base === $slug ) {
				return $taxonomy;
			}
		}

		return '';
	}

	/**
	 * Excludes the current post's synced term from the taxonomy terms list in the sidebar.
	 *
	 * When editing a post whose post type is synced to a taxonomy, the term that represents
	 * this post is hidden from the taxonomy meta box so the post cannot be associated with
	 * its own synced term.
	 *
	 * @param array $args       Array of get_terms() arguments.
	 * @param array $taxonomies Array of taxonomy names being queried.
	 *
	 * @return array Modified arguments.
	 */
	public function exclude_synced_term_from_terms_list( array $args, array $taxonomies ): array {
		if ( ! is_admin() ) {
			return $args;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || empty( $screen->post_type ) || 'post' !== $screen->base ) {
			return $args;
		}

		$post_id = null;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter of an admin term query; no state is changed.
		if ( ! empty( $_GET['post'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter of an admin term query; no state is changed.
			$post_id = (int) $_GET['post'];
		} elseif ( isset( $GLOBALS['post'] ) && $GLOBALS['post'] instanceof \WP_Post ) {
			$post_id = (int) $GLOBALS['post']->ID;
		}
		if ( ! $post_id ) {
			return $args;
		}

		$post = get_post( $post_id );
		if ( ! $post || ! $post->post_type ) {
			return $args;
		}

		$taxonomy_for_type = vgptts()->get_taxonomy_for_post_type( $post->post_type );
		if ( ! $taxonomy_for_type || ! \in_array( $taxonomy_for_type, $taxonomies, true ) ) {
			return $args;
		}

		$synced_term_id = (int) get_post_meta( $post_id, Core::POST_META_KEY, true );
		if ( $synced_term_id <= 0 ) {
			return $args;
		}

		$exclude         = isset( $args['exclude'] ) ? (array) $args['exclude'] : [];
		$exclude[]       = $synced_term_id;
		$args['exclude'] = array_unique( array_filter( $exclude ) );

		return $args;
	}

	/**
	 * Shows one error listing paused mappings, and one dismissible warning listing mappings that sync without parents.
	 *
	 * @return void
	 */
	public function render_hierarchy_notices(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$paused = vgptts()->get_paused_mappings();

		if ( $paused ) {
			printf(
				'<div class="notice notice-error"><p>%s</p>%s</div>',
				wp_kses_post(
					sprintf(
						/* translators: %s: settings page link */
						__( '<strong>Post Type Taxonomy Sync:</strong> these mappings are paused because the post type is hierarchical and the taxonomy is not. Make the taxonomy hierarchical, or remove the mapping in %s.', 'viget-post-type-taxonomy-sync' ),
						sprintf(
							'<a href="%s">%s</a>',
							esc_url( admin_url( 'options-general.php?page=' . Settings::PAGE_SLUG ) ),
							esc_html__( 'Post/Tax Sync settings', 'viget-post-type-taxonomy-sync' )
						)
					)
				),
				self::get_mapping_list( $paused ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in get_mapping_list().
			);
		}

		$dismissed = $this->prune_dismissed_warnings( get_current_user_id() );
		$warnings  = array_values(
			array_filter(
				vgptts()->get_hierarchy_warnings(),
				static fn( array $mapping ): bool => ! \in_array( self::get_warning_key( $mapping ), $dismissed, true )
			)
		);

		if ( ! $warnings ) {
			return;
		}

		printf(
			'<div class="notice notice-warning is-dismissible vgptts-hierarchy-warning" data-keys="%s" data-nonce="%s"><p>%s</p>%s</div>',
			esc_attr( implode( ',', array_map( [ self::class, 'get_warning_key' ], $warnings ) ) ),
			esc_attr( wp_create_nonce( 'vgptts_dismiss_hierarchy_warning' ) ),
			wp_kses_post( __( '<strong>Post Type Taxonomy Sync:</strong> these taxonomies are hierarchical but their post types are not. Syncing still works, but parent/child relationships won\'t sync.', 'viget-post-type-taxonomy-sync' ) ),
			self::get_mapping_list( $warnings ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in get_mapping_list().
		);

		wp_print_inline_script_tag(
			"document.addEventListener( 'click', function ( event ) {
				var button = event.target.closest( '.vgptts-hierarchy-warning .notice-dismiss' );
				if ( ! button ) {
					return;
				}
				var notice = button.closest( '.vgptts-hierarchy-warning' );
				var body = new URLSearchParams( { action: 'vgptts_dismiss_hierarchy_warning', keys: notice.dataset.keys, nonce: notice.dataset.nonce } );
				window.fetch( window.ajaxurl, { method: 'POST', credentials: 'same-origin', body: body } );
			} );"
		);
	}

	/**
	 * Forgets a user's dismissed warnings whose mapping was removed or now matches, and returns the rest.
	 *
	 * A mapping whose post type or taxonomy isn't registered on this request keeps its dismissal.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return string[]
	 */
	private function prune_dismissed_warnings( int $user_id ): array {
		$dismissed = array_values( array_filter( (array) get_user_meta( $user_id, self::DISMISSED_WARNINGS_META_KEY, true ) ) );
		$mappings  = [];

		foreach ( vgptts()->get_mappings() as $mapping ) {
			$mappings[ self::get_warning_key( $mapping ) ] = $mapping;
		}

		$remaining = array_values(
			array_filter(
				$dismissed,
				static function ( $key ) use ( $mappings ): bool {
					$mapping = $mappings[ $key ] ?? null;

					if ( ! $mapping ) {
						return false;
					}

					$registered = post_type_exists( $mapping['post_type'] ) && taxonomy_exists( $mapping['taxonomy'] );

					return ! $registered || Core::HIERARCHY_WARNING === vgptts()->get_hierarchy_status( $mapping );
				}
			)
		);

		if ( $remaining !== $dismissed ) {
			update_user_meta( $user_id, self::DISMISSED_WARNINGS_META_KEY, $remaining );
		}

		return $remaining;
	}

	/**
	 * Gets an escaped list of mappings for a notice.
	 *
	 * @param array $mappings Mappings with `post_type` and `taxonomy` keys.
	 *
	 * @return string
	 */
	private static function get_mapping_list( array $mappings ): string {
		$items = '';

		foreach ( $mappings as $mapping ) {
			$items .= sprintf(
				'<li>%s (%s) &rarr; %s (%s)</li>',
				esc_html( Settings::get_post_type_label( $mapping['post_type'] ) ),
				esc_html( $mapping['post_type'] ),
				esc_html( Settings::get_taxonomy_label( $mapping['taxonomy'] ) ),
				esc_html( $mapping['taxonomy'] )
			);
		}

		return '<ul class="ul-disc">' . $items . '</ul>';
	}

	/**
	 * Remembers that the current user dismissed the hierarchy warning for the mappings it listed.
	 *
	 * @return void
	 */
	public function handle_ajax_dismiss_hierarchy_warning(): void {
		check_ajax_referer( 'vgptts_dismiss_hierarchy_warning', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'viget-post-type-taxonomy-sync' ) ], 403 );
		}

		$keys    = isset( $_POST['keys'] ) ? explode( ',', sanitize_text_field( wp_unslash( $_POST['keys'] ) ) ) : [];
		$current = array_map( [ self::class, 'get_warning_key' ], vgptts()->get_hierarchy_warnings() );

		// Only current warnings, so the stored list can't grow past the mappings themselves.
		$keys = array_intersect( $keys, $current );

		if ( ! $keys ) {
			wp_send_json_error( [ 'message' => __( 'Missing warning.', 'viget-post-type-taxonomy-sync' ) ], 400 );
		}

		$user_id   = get_current_user_id();
		$dismissed = array_filter( (array) get_user_meta( $user_id, self::DISMISSED_WARNINGS_META_KEY, true ) );

		update_user_meta( $user_id, self::DISMISSED_WARNINGS_META_KEY, array_values( array_unique( array_merge( $dismissed, $keys ) ) ) );

		wp_send_json_success();
	}

	/**
	 * Gets the key a hierarchy warning is dismissed under.
	 *
	 * @param array $mapping Mapping with `post_type` and `taxonomy` keys.
	 *
	 * @return string
	 */
	public static function get_warning_key( array $mapping ): string {
		return $mapping['post_type'] . ':' . $mapping['taxonomy'];
	}
}
