<?php
/**
 * Core class
 *
 * @package PostTypeTaxonomySync
 */

namespace PostTypeTaxonomySync;

/**
 * Core class
 *
 * @package PostTypeTaxonomySync
 */
class Core {

	/**
	 * Option name for plugin settings.
	 */
	const OPTION_NAME = 'ptts_settings';

	/**
	 * Meta key for storing related term ID on a post.
	 */
	const POST_META_KEY = '_ptts_term_id';

	/**
	 * Meta key for storing related post ID on a term.
	 */
	const TERM_META_KEY = '_ptts_post_id';

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
		require_once PTTS_PLUGIN_PATH . 'inc/class-settings.php';
		require_once PTTS_PLUGIN_PATH . 'inc/class-sync.php';
		require_once PTTS_PLUGIN_PATH . 'inc/class-admin.php';

		// Initialize dependencies.
		$this->settings = Settings::get_instance();
		$this->sync     = Sync::get_instance();
		$this->admin    = Admin::get_instance();
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
            if ( empty( $mapping['post_type'] ) || empty( $mapping['taxonomy'] ) ) {
                continue;
            }

            $result[] = [
                'post_type' => sanitize_key( $mapping['post_type'] ),
                'taxonomy'  => sanitize_key( $mapping['taxonomy'] ),
            ];
        }

        return $result;
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
}
