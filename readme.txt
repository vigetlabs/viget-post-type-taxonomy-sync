=== Viget Post Type Taxonomy Sync ===
Contributors: Viget
Requires at least: 5.9
Tested up to: 7.0
Requires PHP: 8.2
Stable tag: 2.0.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Keeps a post type and a taxonomy in sync automatically, so every post gets a matching term (and vice versa).

== Description ==

Viget Post Type Taxonomy Sync keeps a chosen post type and taxonomy mirrored one-to-one. Publish a post and a matching term is created automatically (and kept in sync on every update); create or edit a term and the matching post follows along. This is useful when a taxonomy needs to double as a lightweight, linkable "profile" for a post (for example, syncing a `product` post type to a `product-line` taxonomy so other content can be tagged with a product without duplicating its data).

= Features =

* Define any number of post type ↔ taxonomy mappings from **Settings → Post/Tax Sync**.
* Automatically creates, updates, and deletes the paired term/post when either side is saved or deleted.
* Supports hierarchical syncing: if both the post type and taxonomy are hierarchical, parent/child relationships are mirrored too.
* A manual "Sync" action per mapping reconciles existing content (backfills missing terms/posts, removes orphaned ones).
* Hides the auto-managed taxonomy's term-management UI (submenu, "Add New Term" controls, block editor panel) so editors don't edit synced terms directly.
* Excludes a post's own synced term from its taxonomy picker, so a post can't be tagged with itself.
* Ships a small set of filters (`vgptts_mappings`, `vgptts_synced_term_args`, `vgptts_synced_post_args`) and a read-only REST API for reading synced relationships — see [docs/api.md](docs/api.md).
* Checks for updates from GitHub releases directly in the WordPress dashboard — no wordpress.org listing required.

= Installation =

1. Upload the plugin to `/wp-content/plugins/viget-post-type-taxonomy-sync/`, or install via Composer (see the [README](https://github.com/vigetlabs/viget-post-type-taxonomy-sync#installation)).
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Navigate to **Settings → Post/Tax Sync** to configure one or more mappings.

= Requirements =

* WordPress 5.9+
* PHP 8.2+

== Changelog ==

= 2.0.0 =
* Renamed the plugin (and all internal identifiers, option/meta keys, text domain) to `viget-post-type-taxonomy-sync` ahead of reuse across multiple sites. The global `PTTS()` helper still works as a deprecated alias for `vgptts()` (triggers `_doing_it_wrong()`) to ease migrating existing sites; everything else is a breaking change with no other backward-compatible aliases.
* Fixed: term-to-post syncing (creating/editing a term) silently did nothing. The plugin relied on WordPress's dynamic `created_{$taxonomy}`/`edited_{$taxonomy}` action hooks, but core passes a different (and version-dependent) value in the argument position this code read as the taxonomy slug — so the taxonomy lookup always failed. Now uses the generic, stable `saved_term` hook.
* Fixed: deleting a synced term never deleted its paired post. WordPress core deletes all term meta (including the post-link meta this plugin stores) before the `delete_term`/`delete_{$taxonomy}` hooks fire, so the linked post ID was already gone by the time the old code ran. Now uses `pre_delete_term`, which fires before any deletion happens.
* Added hierarchical post type/taxonomy syncing: parent posts/terms are now mirrored when both sides are hierarchical.
* Mapping validation now rejects and reports a hierarchical post type mapped to a non-hierarchical taxonomy (both in the settings form and the manual sync AJAX action).
* Manual/bulk sync and the save-post handler now only sync `publish`-status posts (previously included drafts and other statuses).
* Added a CSS-based fallback for hiding synced-taxonomy submenu items in cases `remove_submenu_page()` doesn't cover.
* Added `Core::get_related_post_ids_for_post()` and `Core::get_post_id_for_term()` helper methods.
* Added REST API endpoints for reading synced post/term relationships (see docs/api.md).
* Added a build pipeline (`@wordpress/scripts`), Composer support, a GitHub-releases-based update checker, a versioned release workflow, and a PHPUnit test suite.

= 1.0.1 =
* Added support for hierarchical post type sync.

= 1.0.0 =
* Initial release.

== Credits ==

Developed by [Viget](https://www.viget.com/).
