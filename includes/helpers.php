<?php
/**
 * Helpers for Viget Post Type Taxonomy Sync
 *
 * @package Viget\PostTypeTaxonomySync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'vgptts' ) ) {
	/**
	 * Return the Viget Post Type Taxonomy Sync core instance.
	 *
	 * @return Viget\PostTypeTaxonomySync\Core
	 */
	function vgptts(): Viget\PostTypeTaxonomySync\Core {
		if ( ! class_exists( Viget\PostTypeTaxonomySync\Core::class ) ) {
			require_once VGPTTS_PLUGIN_PATH . 'includes/class-core.php';
		}
		return Viget\PostTypeTaxonomySync\Core::get_instance();
	}
}

if ( ! function_exists( 'PTTS' ) ) {
	/**
	 * Deprecated alias for vgptts(), kept for sites migrating from the
	 * pre-2.0.0 `post-type-taxonomy-sync` plugin (e.g. Amphenol CIT), whose
	 * theme/mu-plugin code calls `PTTS()` directly.
	 *
	 * @deprecated 2.0.0 Use vgptts() instead.
	 *
	 * @return Viget\PostTypeTaxonomySync\Core
	 */
	function PTTS(): Viget\PostTypeTaxonomySync\Core {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'PTTS() is deprecated; use vgptts() instead.', 'viget-post-type-taxonomy-sync' ), '2.0.0' );
		return vgptts();
	}
}
