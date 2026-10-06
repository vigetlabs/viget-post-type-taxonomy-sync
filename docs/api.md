# Developer API

Reference for the public methods and filters exposed by Viget Post Type Taxonomy Sync. Everything here lives under the `Viget\PostTypeTaxonomySync` namespace; the `vgptts()` helper returns the shared `Core` instance.

```php
<?php
vgptts()->get_mappings();
```

## `Core`

### `get_mappings(): array`

Returns every active mapping, each an array with `post_type` and `taxonomy` keys: mappings registered in code first, then mappings saved on the settings page. A post type can sync to several taxonomies, but a taxonomy syncs to one post type, so a mapping that reuses a taxonomy already claimed by an earlier mapping is left out (see `get_flagged_mappings()`). The result passes through the `vgptts_mappings` filter.

```php
foreach ( vgptts()->get_mappings() as $mapping ) {
	// $mapping['post_type'], $mapping['taxonomy']
}
```

### `get_registered_mappings(): array`

Returns the sanitized, de-duplicated mappings registered through the `vgptts_registered_mappings` filter.

### `get_saved_mappings(): array`

Returns the sanitized mappings saved on the settings page.

### `get_flagged_mappings(): array`

Returns the mappings left out of `get_mappings()`, each with `post_type`, `taxonomy`, `source` (`registered` or `saved`) and `conflict`, the active mapping that claimed its taxonomy first.

### `find_conflict( array $mapping, array $mappings ): ?array`

Returns the first of `$mappings` that shares a taxonomy with `$mapping`, or `null`. Sharing a post type isn't a conflict.

### `is_overridden( array $mapping, ?array $registered = null ): bool`

Whether a saved mapping shares its taxonomy with a registered mapping, and is therefore inactive.

### `get_taxonomies_for_post_type( string $post_type ): string[]`

Returns every taxonomy mapped to a post type, in mapping order. Empty if none is configured.

### `get_taxonomy_for_post_type( string $post_type ): ?string`

Returns the first taxonomy mapped to a post type, or `null` if none is configured. A post type can map to several, so use `get_taxonomies_for_post_type()` when it might.

### `get_term_id_for_post( int $post_id, string $taxonomy ): ?int`

Returns a post's synced term ID in a taxonomy, or `null` if it has none there.

### `get_post_meta_key( string $taxonomy ): string`

Returns the post meta key that stores a post's synced term ID in a taxonomy: `_vgptts_term_id_{taxonomy}` (`Core::POST_META_KEY` plus the taxonomy). A term stores its post ID under `Core::TERM_META_KEY` (`_vgptts_post_id`).

Before 2.2.0, a post stored a single term ID under `_vgptts_term_id`. Each site moves that data to the per-taxonomy keys once, on its first request after updating (`Upgrade`, tracked in the `vgptts_db_version` option).

### `get_post_type_for_taxonomy( string $taxonomy ): ?string`

Returns the post type slug mapped to a given taxonomy, or `null` if none is configured.

### `get_related_post_ids_for_post( int $post_id, string $taxonomy ): array`

Given a post and a (typically unrelated) taxonomy, returns the post IDs linked — via that taxonomy's synced terms — to the terms assigned to `$post_id`. Useful for surfacing "related" content through a synced taxonomy without writing custom term-meta lookups.

### `get_post_id_for_term( int $term_id ): ?int`

Returns the post ID linked to a given term via the sync, or `null` if the term isn't synced to a post.

## `Sync`

### `sync_terms( string $post_type, string $taxonomy ): void`

Runs a full reconciliation for one mapping (a post type with several taxonomies has one per taxonomy): creates/updates a term for every published post of `$post_type`, and removes any term whose linked post is missing, trashed, or of the wrong type. This is what the settings page's per-row **Sync** button triggers via AJAX (`vgptts_sync_mapping`); call it directly for a WP-CLI command or a scheduled job.

## REST API

All routes are under the `vgptts/v1` namespace and are read-only (`GET`). A post route is readable if the post is publicly viewable or the current user can `read_post` it; a term route is readable if its taxonomy is public or the current user can `assign_terms` on it. Unauthorized or nonexistent lookups return a standard `WP_Error` REST response (404, or 401/403 via `rest_authorization_required_code()`).

### `GET /vgptts/v1/posts/{id}/synced-term`

Returns the term synced to a post: `{ id, taxonomy, name, slug, link, parent }`. Pass `?taxonomy={taxonomy}` to pick one when the post type syncs to several; without it, that case returns a 400 (`vgptts_taxonomy_required`). 404s if the post's type isn't mapped (to that taxonomy, when given), or it doesn't have a synced term yet (e.g. it isn't published).

### `GET /vgptts/v1/terms/{id}/synced-post`

Returns the post synced to a term: `{ id, post_type, status, title, slug, link, parent }`. 404s if the term has no synced post.

### `GET /vgptts/v1/posts/{id}/related?taxonomy={taxonomy}`

Returns an array of posts related to the given post through `$taxonomy` (see `Core::get_related_post_ids_for_post()` above) — i.e. posts whose synced term has been assigned to this post. The `taxonomy` query param is required and must be a registered taxonomy.

```
GET /wp-json/vgptts/v1/posts/42/related?taxonomy=product-line
```

## Filters

### `vgptts_registered_mappings`

Registers mappings in code, for a plugin or theme that owns both the post type and the taxonomy. Registered mappings:

- sync without anything being saved on the settings page;
- show on the settings page as locked rows, with a **Sync** button and no way to edit or remove them;
- take precedence over a saved mapping that uses the same taxonomy. The saved row is flagged as not synced.

A post type can sync to several taxonomies, but a taxonomy syncs to one post type. A registered mapping that reuses a taxonomy claimed by an earlier registered mapping, or that names a post type or taxonomy that isn't registered, shows as a flagged row: struck through, with an ✕ whose tooltip says why it isn't synced.

```php
add_filter(
	'vgptts_registered_mappings',
	function ( array $mappings ): array {
		$mappings[] = [
			'post_type' => 'product',
			'taxonomy'  => 'product-line',
		];
		return $mappings;
	}
);
```

### `vgptts_mappings`

Filters the resolved mappings array, registered and saved, before it's used anywhere else in the plugin. Mappings added here are active but don't appear on the settings page. Use `vgptts_registered_mappings` for that.

```php
add_filter(
	'vgptts_mappings',
	function ( array $mappings ): array {
		$mappings[] = [
			'post_type' => 'product',
			'taxonomy'  => 'product-line',
		];
		return $mappings;
	}
);
```

### `vgptts_synced_term_args`

Filters the `wp_insert_term()` / `wp_update_term()` argument array used when a post is synced to its term.

```php
add_filter(
	'vgptts_synced_term_args',
	function ( array $args, \WP_Post $post, string $taxonomy ): array {
		$args['description'] = $post->post_excerpt;
		return $args;
	},
	10,
	3
);
```

### `vgptts_synced_post_args`

Filters the `wp_insert_post()` / `wp_update_post()` argument array used when a term is synced to its post.

```php
add_filter(
	'vgptts_synced_post_args',
	function ( array $args, $term, string $post_type ): array {
		$args['post_excerpt'] = $term->description;
		return $args;
	},
	10,
	3
);
```
