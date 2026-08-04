<?php
/**
 * Plugin Name:       Viget Post Type Taxonomy Sync
 * Plugin URI:        https://github.com/vigetlabs/viget-post-type-taxonomy-sync
 * Description:       Keeps selected post types and taxonomies in sync, storing related IDs in post and term meta.
 * Version:           2.0.1
 * Requires at least: 5.9
 * Requires PHP:      8.2
 * Author:            Viget
 * Author URI:        https://www.viget.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       viget-post-type-taxonomy-sync
 * Domain Path:       /languages
 *
 * @package Viget\PostTypeTaxonomySync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'VGPTTS_PLUGIN_VERSION', '2.0.1' );
define( 'VGPTTS_PLUGIN_FILE', __FILE__ );
define( 'VGPTTS_PLUGIN_PATH', plugin_dir_path( VGPTTS_PLUGIN_FILE ) );
define( 'VGPTTS_PLUGIN_URL', plugin_dir_url( VGPTTS_PLUGIN_FILE ) );

require_once VGPTTS_PLUGIN_PATH . 'includes/helpers.php';

// Initialize the plugin.
vgptts();
