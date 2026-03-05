<?php
/**
 * Settings class for Post Type Taxonomy Sync
 *
 * @package PostTypeTaxonomySync
 */

namespace PostTypeTaxonomySync;

class Settings {

	/**
	 * Page slug for plugin settings.
	 */
	const PAGE_SLUG = 'ptts-settings';

	/**
	 * Option name for plugin settings.
	 */
	const OPTION_NAME = 'ptts_settings';

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
		add_action( 'wp_ajax_ptts_sync_mapping', [ $this, 'handle_ajax_sync_mapping' ] );
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
		wp_register_script(
			'ptts-mappings-field',
			PTTS_PLUGIN_URL . 'assets/scripts/mappings-field.js',
			[],
			PTTS_PLUGIN_VERSION,
			[ 'in_footer' => true ]
		);

		wp_register_style(
			'ptts-admin-styles',
			PTTS_PLUGIN_URL . 'assets/styles/admin.css',
			[],
			PTTS_PLUGIN_VERSION
		);
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
			'ptts_main_section',
			esc_html__( 'Sync Mappings', 'post-type-taxonomy-sync' ),
			'__return_false',
			self::PAGE_SLUG
		);

		add_settings_field(
			'ptts_mappings_field',
			esc_html__( 'Mappings', 'post-type-taxonomy-sync' ),
			[ $this, 'render_mappings_field' ],
			self::PAGE_SLUG,
			'ptts_main_section'
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
			esc_html__( 'Post Type Taxonomy Sync', 'post-type-taxonomy-sync' ),
			esc_html__( 'Post/Tax Sync', 'post-type-taxonomy-sync' ),
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

		wp_enqueue_script( 'ptts-mappings-field' );
		wp_enqueue_style( 'ptts-admin-styles' );

		wp_localize_script(
			'ptts-mappings-field',
			'pttsMappings',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ptts_sync_mapping' ),
			]
		);

		require PTTS_PLUGIN_PATH . 'views/admin/settings.php';
	}

	/**
	 * Handles AJAX request to sync a mapping (post type ↔ taxonomy).
	 *
	 * @return void
	 */
	public function handle_ajax_sync_mapping(): void {
		check_ajax_referer( 'ptts_sync_mapping', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'post-type-taxonomy-sync' ) ], 403 );
		}

		$post_type = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';
		$taxonomy  = isset( $_POST['taxonomy'] ) ? sanitize_key( wp_unslash( $_POST['taxonomy'] ) ) : '';

		if ( ! $post_type || ! $taxonomy || ! post_type_exists( $post_type ) || ! taxonomy_exists( $taxonomy ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid post type or taxonomy.', 'post-type-taxonomy-sync' ) ], 400 );
		}

		PTTS()->sync->sync_terms( $post_type, $taxonomy );

		wp_send_json_success( [ 'message' => __( 'Sync completed.', 'post-type-taxonomy-sync' ) ] );
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

		require PTTS_PLUGIN_PATH . 'views/admin/mappings-field.php';
	}
}
