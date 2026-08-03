# Developer API

Reference for the public methods and filters exposed by Viget Post Type Taxonomy Sync. Everything here lives under the `Viget\PostTypeTaxonomySync` namespace; the `vgptts()` helper returns the shared `Core` instance.

```php
<?php
vgptts()->get_mappings();
```

## `Core`

### `get_mappings(): array`

Returns the sanitized list of configured mappings, each an array with `post_type` and `taxonomy` keys.

```php
foreach ( vgptts()->get_mappings() as $mapping ) {
	// $mapping['post_type'], $mapping['taxonomy']
}
```

### `get_taxonomy_for_post_type( string $post_type ): ?string`

Returns the taxonomy slug mapped to a given post type, or `null` if none is configured.

### `get_post_type_for_taxonomy( string $taxonomy ): ?string`

Returns the post type slug mapped to a given taxonomy, or `null` if none is configured.

### `get_related_post_ids_for_post( int $post_id, string $taxonomy ): array`

Given a post and a (typically unrelated) taxonomy, returns the post IDs linked — via that taxonomy's synced terms — to the terms assigned to `$post_id`. Useful for surfacing "related" content through a synced taxonomy without writing custom term-meta lookups.

### `get_post_id_for_term( int $term_id ): ?int`

Returns the post ID linked to a given term via the sync, or `null` if the term isn't synced to a post.

## `Sync`

### `sync_terms( string $post_type, string $taxonomy ): void`

Runs a full reconciliation for one mapping: creates/updates a term for every published post of `$post_type`, and removes any term whose linked post is missing, trashed, or of the wrong type. This is what the settings page's per-row **Sync** button triggers via AJAX (`vgptts_sync_mapping`); call it directly for a WP-CLI command or a scheduled job.

## REST API

All routes are under the `vgptts/v1` namespace and are read-only (`GET`). A post route is readable if the post is publicly viewable or the current user can `read_post` it; a term route is readable if its taxonomy is public or the current user can `assign_terms` on it. Unauthorized or nonexistent lookups return a standard `WP_Error` REST response (404, or 401/403 via `rest_authorization_required_code()`).

### `GET /vgptts/v1/posts/{id}/synced-term`

Returns the term synced to a post: `{ id, taxonomy, name, slug, link, parent }`. 404s if the post's type isn't mapped, or it doesn't have a synced term yet (e.g. it isn't published).

### `GET /vgptts/v1/terms/{id}/synced-post`

Returns the post synced to a term: `{ id, post_type, status, title, slug, link, parent }`. 404s if the term has no synced post.

### `GET /vgptts/v1/posts/{id}/related?taxonomy={taxonomy}`

Returns an array of posts related to the given post through `$taxonomy` (see `Core::get_related_post_ids_for_post()` above) — i.e. posts whose synced term has been assigned to this post. The `taxonomy` query param is required and must be a registered taxonomy.

```
GET /wp-json/vgptts/v1/posts/42/related?taxonomy=product-line
```

## Filters

### `vgptts_mappings`

Filters the resolved mappings array before it's used anywhere else in the plugin.

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
