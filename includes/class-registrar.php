<?php
/**
 * Registrar class
 *
 * @package Viget\PostTypeTaxonomySync
 */

namespace Viget\PostTypeTaxonomySync;

/**
 * Registers the taxonomies a mapping asked the plugin to create.
 *
 * @package Viget\PostTypeTaxonomySync
 */
class Registrar {

	/**
	 * Instance of this class.
	 *
	 * @var Registrar|null
	 */
	private static ?Registrar $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Registrar
	 */
	public static function get_instance(): Registrar {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		// The taxonomy takes its labels and hierarchy from the mapped post type, so it is
		// registered the moment that post type appears rather than at a fixed priority we
		// can't guarantee runs after the theme.
		add_action( 'registered_post_type', [ $this, 'register_taxonomy_for_post_type' ], 10, 1 );

		// Same in reverse for an auto-created post type, which mirrors a taxonomy.
		add_action( 'registered_taxonomy', [ $this, 'register_post_type_for_taxonomy' ], 10, 1 );

		// Catch-all for anything registered before these hooks existed, such as core's
		// built-ins, and for mappings whose other side never registers at all.
		add_action( 'init', [ $this, 'register_all' ], 99 );
	}

	/**
	 * Registers the auto-created post type mapped to a taxonomy, as it registers.
	 *
	 * @param string $taxonomy Taxonomy slug.
	 *
	 * @return void
	 */
	public function register_post_type_for_taxonomy( string $taxonomy ): void {
		foreach ( vgptts()->get_mappings() as $mapping ) {
			if ( $mapping['taxonomy'] === $taxonomy ) {
				$this->maybe_register_post_type( $mapping );
			}
		}
	}

	/**
	 * Registers everything still missing for the configured mappings.
	 *
	 * @return void
	 */
	public function register_all(): void {
		foreach ( vgptts()->get_mappings() as $mapping ) {
			$this->maybe_register( $mapping );
			$this->maybe_register_post_type( $mapping );
		}
	}

	/**
	 * Registers a mapping's post type if it asked to be auto-created.
	 *
	 * @param array $mapping Resolved mapping.
	 *
	 * @return void
	 */
	protected function maybe_register_post_type( array $mapping ): void {
		if ( empty( $mapping['post_type_auto'] ) || empty( $mapping['post_type'] ) ) {
			return;
		}

		if ( post_type_exists( $mapping['post_type'] ) ) {
			return;
		}

		$this->register_post_type( $mapping );
	}

	/**
	 * Registers a single auto-created post type.
	 *
	 * Labels and hierarchy come from the mapped taxonomy. When the taxonomy is the source
	 * of truth these posts are a behind-the-scenes mirror, so they stay out of the menus
	 * unless the mapping opts in.
	 *
	 * @param array $mapping Resolved mapping.
	 *
	 * @return void
	 */
	protected function register_post_type( array $mapping ): void {
		$tax_obj = get_taxonomy( $mapping['taxonomy'] );

		$plural   = $tax_obj ? $tax_obj->labels->name : $mapping['taxonomy'];
		$singular = $tax_obj ? $tax_obj->labels->singular_name : $mapping['taxonomy'];

		$args = [
			'labels'       => [
				'name'          => $plural,
				'singular_name' => $singular,
				'menu_name'     => $plural,
			],
			'hierarchical' => $tax_obj ? (bool) $tax_obj->hierarchical : false,
			'public'       => true,
			'show_ui'      => true,
			'show_in_menu' => ! empty( $mapping['show_post_type_in_menus'] ),
			'show_in_rest' => true,
			'supports'     => [ 'title', 'editor', 'thumbnail', 'page-attributes' ],
			'rewrite'      => [ 'slug' => str_replace( '_', '-', $mapping['post_type'] ) ],
		];

		/**
		 * Filters the register_post_type() args for an auto-created post type.
		 *
		 * @param array $args    Post type registration arguments.
		 * @param array $mapping The resolved mapping.
		 */
		$args = apply_filters( 'vgptts_auto_post_type_args', $args, $mapping );

		register_post_type( $mapping['post_type'], $args );
	}

	/**
	 * Registers the auto-created taxonomy mapped to a post type, as it registers.
	 *
	 * @param string $post_type Post type slug.
	 *
	 * @return void
	 */
	public function register_taxonomy_for_post_type( string $post_type ): void {
		foreach ( vgptts()->get_mappings() as $mapping ) {
			if ( $mapping['post_type'] === $post_type ) {
				$this->maybe_register( $mapping );
			}
		}
	}

	/**
	 * Registers a mapping's taxonomy if it asked to be auto-created and nothing claimed the slug.
	 *
	 * @param array $mapping Resolved mapping.
	 *
	 * @return void
	 */
	protected function maybe_register( array $mapping ): void {
		if ( empty( $mapping['taxonomy_auto'] ) || empty( $mapping['taxonomy'] ) ) {
			return;
		}

		// A taxonomy someone else already registered under this slug wins.
		if ( taxonomy_exists( $mapping['taxonomy'] ) ) {
			return;
		}

		$this->register_taxonomy( $mapping );
	}

	/**
	 * Registers a single auto-created taxonomy.
	 *
	 * Labels and hierarchy come from the mapped post type, so the taxonomy reads as
	 * that post type wherever it appears.
	 *
	 * @param array $mapping Resolved mapping.
	 *
	 * @return void
	 */
	protected function register_taxonomy( array $mapping ): void {
		$post_type_obj = get_post_type_object( $mapping['post_type'] );

		$plural   = $post_type_obj ? $post_type_obj->labels->name : $mapping['post_type'];
		$singular = $post_type_obj ? $post_type_obj->labels->singular_name : $mapping['post_type'];

		// Object types are registered even when they don't exist yet: WordPress stores the
		// slug and resolves it once that post type registers.
		$object_types = ! empty( $mapping['attach_to'] ) ? $mapping['attach_to'] : [];

		$args = [
			'labels'            => [
				'name'          => $plural,
				'singular_name' => $singular,
				'menu_name'     => $plural,
			],
			'hierarchical'      => $post_type_obj ? (bool) $post_type_obj->hierarchical : true,
			'public'            => true,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => [ 'slug' => str_replace( '_', '-', $mapping['taxonomy'] ) ],
		];

		/**
		 * Filters the register_taxonomy() args for an auto-created taxonomy.
		 *
		 * @param array  $args     Taxonomy registration arguments.
		 * @param array  $mapping  The resolved mapping.
		 */
		$args = apply_filters( 'vgptts_auto_taxonomy_args', $args, $mapping );

		register_taxonomy( $mapping['taxonomy'], $object_types, $args );
	}
}
