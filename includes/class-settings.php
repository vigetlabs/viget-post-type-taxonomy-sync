<?php
/**
 * Settings class for Viget Post Type Taxonomy Sync
 *
 * @package Viget\PostTypeTaxonomySync
 */

namespace Viget\PostTypeTaxonomySync;

/**
 * Settings class
 *
 * @package Viget\PostTypeTaxonomySync
 */
class Settings {

	/**
	 * Page slug for plugin settings.
	 */
	const PAGE_SLUG = 'vgptts-settings';

	/**
	 * Option name for plugin settings.
	 */
	const OPTION_NAME = 'vgptts_settings';

	/**
	 * Instance of this class.
	 *
	 * @var Settings|null
	 */
	private static ?Settings $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Settings
	 */
	public static function get_instance(): Settings {
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
	 * Initialize the settings.
	 *
	 * @return void
	 */
	private function init() {
		add_action( 'admin_menu', [ $this, 'register_settings_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'register_admin_assets' ] );
		add_action( 'wp_ajax_vgptts_sync_mapping', [ $this, 'handle_ajax_sync_mapping' ] );
	}

	/**
	 * Gets the plugin settings.
	 *
	 * @return array
	 */
	public function get_settings(): array {
		$defaults = [
			'mappings' => [],
		];
		return get_option( self::OPTION_NAME, $defaults );
	}

	/**
	 * Registers the admin assets.
	 *
	 * @return void
	 */
	public function register_admin_assets() {
		$script_asset_file = VGPTTS_PLUGIN_PATH . 'build/admin.asset.php';
		$script_asset      = file_exists( $script_asset_file )
			? require $script_asset_file
			: [
				'dependencies' => [],
				'version'      => VGPTTS_PLUGIN_VERSION,
			];

		wp_register_script(
			'vgptts-mappings-field',
			VGPTTS_PLUGIN_URL . 'build/admin.js',
			$script_asset['dependencies'],
			$script_asset['version'],
			[ 'in_footer' => true ]
		);

		$style_path = VGPTTS_PLUGIN_PATH . 'build/admin-style.css';

		wp_register_style(
			'vgptts-admin-styles',
			VGPTTS_PLUGIN_URL . 'build/admin-style.css',
			[],
			file_exists( $style_path ) ? filemtime( $style_path ) : VGPTTS_PLUGIN_VERSION
		);
		wp_style_add_data( 'vgptts-admin-styles', 'rtl', 'replace' );
	}

	/**
	 * Registers the plugin settings using the Settings API.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			self::OPTION_NAME,
			self::OPTION_NAME,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_settings' ],
				'default'           => [],
			]
		);

		add_settings_section(
			'vgptts_main_section',
			esc_html__( 'Sync Mappings', 'viget-post-type-taxonomy-sync' ),
			'__return_false',
			self::PAGE_SLUG
		);

		add_settings_field(
			'vgptts_mappings_field',
			esc_html__( 'Mappings', 'viget-post-type-taxonomy-sync' ),
			[ $this, 'render_mappings_field' ],
			self::PAGE_SLUG,
			'vgptts_main_section'
		);
	}

	/**
	 * Sanitizes plugin settings before saving.
	 *
	 * @param array $input Raw input.
	 *
	 * @return array
	 */
	public static function sanitize_settings( $input ) {
		$sanitized = [
			'mappings' => [],
		];

		if ( empty( $input['mappings'] ) || ! \is_array( $input['mappings'] ) ) {
			return $sanitized;
		}

		foreach ( $input['mappings'] as $mapping ) {
			if ( empty( $mapping['post_type'] ) || empty( $mapping['taxonomy'] ) ) {
				continue;
			}

			$post_type = sanitize_key( $mapping['post_type'] );
			$taxonomy  = sanitize_key( $mapping['taxonomy'] );

			if ( ! post_type_exists( $post_type ) || ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			$post_type_obj = get_post_type_object( $post_type );
			$tax_obj       = get_taxonomy( $taxonomy );

			$post_type_is_hierarchical = ( $post_type_obj && ! empty( $post_type_obj->hierarchical ) );
			$tax_is_hierarchical       = ( $tax_obj && ! empty( $tax_obj->hierarchical ) );

			if ( $post_type_is_hierarchical && ! $tax_is_hierarchical ) {
				add_settings_error(
					self::OPTION_NAME,
					"vgptts_hierarchy_mismatch_{$post_type}_{$taxonomy}",
					sprintf(
						/* translators: 1: post type slug, 2: taxonomy slug */
						__( 'Cannot sync hierarchical post type "%1$s" to non-hierarchical taxonomy "%2$s". Please choose a hierarchical taxonomy.', 'viget-post-type-taxonomy-sync' ),
						$post_type,
						$taxonomy
					),
					'error'
				);
				continue;
			}

			$sanitized['mappings'][] = [
				'post_type' => $post_type,
				'taxonomy'  => $taxonomy,
			];
		}

		return $sanitized;
	}

	/**
	 * Registers the settings page.
	 *
	 * @return void
	 */
	public function register_settings_page() {
		add_options_page(
			esc_html__( 'Post Type Taxonomy Sync', 'viget-post-type-taxonomy-sync' ),
			esc_html__( 'Post/Tax Sync', 'viget-post-type-taxonomy-sync' ),
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_settings_page' ]
		);
	}

	/**
	 * Renders the settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_enqueue_script( 'vgptts-mappings-field' );
		wp_enqueue_style( 'vgptts-admin-styles' );

		wp_localize_script(
			'vgptts-mappings-field',
			'vgpttsMappings',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'vgptts_sync_mapping' ),
			]
		);

		require VGPTTS_PLUGIN_PATH . 'views/admin/settings.php';
	}

	/**
	 * Handles AJAX request to sync a mapping (post type ↔ taxonomy).
	 *
	 * @return void
	 */
	public function handle_ajax_sync_mapping(): void {
		check_ajax_referer( 'vgptts_sync_mapping', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'viget-post-type-taxonomy-sync' ) ], 403 );
		}

		$post_type = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';
		$taxonomy  = isset( $_POST['taxonomy'] ) ? sanitize_key( wp_unslash( $_POST['taxonomy'] ) ) : '';

		if ( ! $post_type || ! $taxonomy || ! post_type_exists( $post_type ) || ! taxonomy_exists( $taxonomy ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid post type or taxonomy.', 'viget-post-type-taxonomy-sync' ) ], 400 );
		}

		$post_type_obj = get_post_type_object( $post_type );
		$tax_obj       = get_taxonomy( $taxonomy );

		$post_type_is_hierarchical = ( $post_type_obj && ! empty( $post_type_obj->hierarchical ) );
		$tax_is_hierarchical       = ( $tax_obj && ! empty( $tax_obj->hierarchical ) );

		if ( $post_type_is_hierarchical && ! $tax_is_hierarchical ) {
			wp_send_json_error(
				[
					'message' => __( 'Cannot sync a hierarchical post type to a non-hierarchical taxonomy. Choose a hierarchical taxonomy.', 'viget-post-type-taxonomy-sync' ),
				],
				400
			);
		}

		vgptts()->sync->sync_terms( $post_type, $taxonomy );

		wp_send_json_success( [ 'message' => __( 'Sync completed.', 'viget-post-type-taxonomy-sync' ) ] );
	}

	/**
	 * Renders the mappings field.
	 *
	 * @return void
	 */
	public function render_mappings_field() {
		$settings = $this->get_settings();
		$mappings = $settings['mappings'];

		$post_types = get_post_types(
			[
				'show_ui' => true,
				'public'  => true,
			],
			'objects'
		);

		$taxonomies = get_taxonomies(
			[
				'show_ui' => true,
				'public'  => true,
			],
			'objects'
		);

		// Ensure there is at least one row.
		if ( empty( $mappings ) ) {
			$mappings[] = [
				'post_type' => '',
				'taxonomy'  => '',
			];
		}

		require VGPTTS_PLUGIN_PATH . 'views/admin/mappings-field.php';
	}
}
