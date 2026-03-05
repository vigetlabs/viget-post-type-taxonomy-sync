<?php
/**
 * Plugin Name:       Post Type Taxonomy Sync
 * Description:       Keeps selected post types and taxonomies in sync, storing related IDs in post and term meta.
 * Version:           1.0.0
 * Author:            Viget
 * Author URI:        https://www.viget.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       post-type-taxonomy-sync
 * Domain Path:       /languages
 *
 * @package PostTypeTaxonomySync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PTTS_PLUGIN_VERSION', '1.0.0' );
define( 'PTTS_PLUGIN_FILE', __FILE__ );
define( 'PTTS_PLUGIN_PATH', plugin_dir_path( PTTS_PLUGIN_FILE ) );
define( 'PTTS_PLUGIN_URL', plugin_dir_url( PTTS_PLUGIN_FILE ) );

require_once PTTS_PLUGIN_PATH . 'inc/helpers.php';

// Initialize the plugin.
PTTS();
