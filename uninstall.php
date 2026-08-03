<?php
/**
 * Uninstall routine for Viget Post Type Taxonomy Sync
 *
 * Removes plugin settings on uninstall. Post/term meta created by the sync
 * (`_vgptts_term_id`, `_vgptts_post_id`) is left in place, since it represents
 * content relationships rather than plugin configuration and removing it could
 * be destructive to unrelated data if the plugin is reinstalled later.
 *
 * @package Viget\PostTypeTaxonomySync
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'vgptts_settings' );
delete_site_option( 'vgptts_settings' );
