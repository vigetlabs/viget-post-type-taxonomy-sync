<?php
/**
 * Upgrade class
 *
 * @package Viget\PostTypeTaxonomySync
 */

namespace Viget\PostTypeTaxonomySync;

/**
 * Moves each site's data forward when the plugin updates.
 *
 * Runs once per site, on that site's first request after the update, and
 * records the version it reached so it doesn't run again.
 *
 * @package Viget\PostTypeTaxonomySync
 */
class Upgrade {

	/**
	 * Option that stores the data version a site is on.
	 */
	const OPTION_NAME = 'vgptts_db_version';

	/**
	 * Upgrade methods, keyed by the data version each one moves a site to.
	 * To add one, add a method and a new entry with the next version.
	 */
	const UPGRADES = [
		2 => 'move_term_ids_to_taxonomy_keys', // 2.2.0: synced term ID per taxonomy.
	];

	/**
	 * Instance of this class.
	 *
	 * @var Upgrade|null
	 */
	private static ?Upgrade $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Upgrade
	 */
	public static function get_instance(): Upgrade {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'init', [ $this, 'maybe_upgrade' ], 20 );
	}

	/**
	 * The data version the current site is on.
	 *
	 * @return int
	 */
	public static function get_version(): int {
		return (int) get_option( self::OPTION_NAME, 0 );
	}

	/**
	 * Whether the current site's data is on the latest version.
	 *
	 * @return bool
	 */
	public static function is_current(): bool {
		return self::get_version() >= array_key_last( self::UPGRADES );
	}

	/**
	 * Whether the current site stores synced term IDs per taxonomy (version 2).
	 *
	 * @return bool
	 */
	public static function has_term_keys(): bool {
		return self::get_version() >= 2;
	}

	/**
	 * Runs any upgrades the current site hasn't had yet, in order.
	 *
	 * @return void
	 */
	public function maybe_upgrade(): void {
		$version = self::get_version();

		foreach ( self::UPGRADES as $to => $method ) {
			if ( $version >= $to ) {
				continue;
			}

			$this->$method();

			update_option( self::OPTION_NAME, $to );
		}
	}

	/**
	 * Moves each post's synced term ID from the single `_vgptts_term_id` key to a per-taxonomy key.
	 *
	 * The taxonomy comes from the term itself, so this works whether or not the
	 * taxonomy is registered yet. A term ID whose term no longer exists is dropped.
	 * Safe to run more than once: it only touches posts that still have the old key.
	 *
	 * @return void
	 */
	public function move_term_ids_to_taxonomy_keys(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time migration over every post with the old key.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.post_id, pm.meta_value AS term_id, tt.taxonomy
				FROM {$wpdb->postmeta} pm
				LEFT JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = pm.meta_value
				WHERE pm.meta_key = %s",
				Core::POST_META_KEY
			)
		);

		foreach ( $rows as $row ) {
			if ( $row->taxonomy ) {
				update_post_meta( (int) $row->post_id, vgptts()->get_post_meta_key( $row->taxonomy ), (int) $row->term_id );
			}

			delete_post_meta( (int) $row->post_id, Core::POST_META_KEY );
		}
	}
}
