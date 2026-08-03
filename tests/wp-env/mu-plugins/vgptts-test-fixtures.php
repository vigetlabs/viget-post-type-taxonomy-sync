<?php
/**
 * Plugin Name: VGPTTS Test Fixtures (wp-env only)
 * Description: Registers a hierarchical taxonomy for the "page" post type so the plugin's
 *              hierarchical post/taxonomy sync can be smoke-tested locally. Not part of the
 *              shipped plugin — only loaded inside the local wp-env environment.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'init',
	function () {
		register_taxonomy(
			'vgptts_test_section',
			'page',
			[
				'label'        => 'Sections',
				'hierarchical' => true,
				'show_ui'      => true,
				'public'       => true,
				'show_in_rest' => true,
			]
		);
	}
);
