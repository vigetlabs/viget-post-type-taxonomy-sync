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

		// Catch-all for post types that registered before this hook existed, such as core's
		// built-ins, and for mappings whose post type never registers at all.
		add_action( 'init', [ $this, 'register_taxonomies' ], 99 );
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
	 * Registers a taxonomy for every mapping flagged as auto-created.
	 *
	 * @return void
	 */
	public function register_taxonomies(): void {
		foreach ( vgptts()->get_mappings() as $mapping ) {
			$this->maybe_register( $mapping );
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
