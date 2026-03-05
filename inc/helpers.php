<?php
/**
 * Helpers for Post Type Taxonomy Sync
 *
 * @package PostTypeTaxonomySync
 */

if ( ! function_exists( 'PTTS' ) ) {
	/**
	 * Return the Post Type Taxonomy Sync core instance.
	 *
	 * @return PostTypeTaxonomySync\Core
	 */
	function PTTS(): PostTypeTaxonomySync\Core {
		if ( ! class_exists( PostTypeTaxonomySync\Core::class ) ) {
			require_once PTTS_PLUGIN_PATH . '/inc/class-core.php';
		}
		return PostTypeTaxonomySync\Core::get_instance();
	}
}
