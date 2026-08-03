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
