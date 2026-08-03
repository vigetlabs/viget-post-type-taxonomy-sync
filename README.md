<!-- markdownlint-disable MD033 MD041 -->
<p align="left">
  <img src="https://img.shields.io/badge/WordPress-5.9%2B-21759b?logo=wordpress&logoColor=white" alt="WordPress 5.9+">
  <img src="https://img.shields.io/badge/PHP-8.2%2B-777bb4?logo=php&logoColor=white" alt="PHP 8.2+">
  <img src="https://img.shields.io/github/v/release/vigetlabs/viget-post-type-taxonomy-sync" alt="Latest release">
  <img src="https://img.shields.io/github/actions/workflow/status/vigetlabs/viget-post-type-taxonomy-sync/ci.yaml?branch=main&label=CI" alt="CI status">
  <img src="https://img.shields.io/badge/license-GPL--2.0--or--later-blue" alt="License">
</p>

# Viget Post Type Taxonomy Sync

Keeps a post type and a taxonomy in sync automatically: publish a post and a matching term is created (and kept up to date); create or edit a term and the matching post follows along. Handy when a taxonomy needs to double as a lightweight, linkable "profile" for a post — e.g. syncing a `product` post type to a `product-line` taxonomy so other content can be tagged with a product without duplicating its data.

## Features

- Define any number of post type ↔ taxonomy mappings from **Settings → Post/Tax Sync**.
- Automatically creates, updates, and deletes the paired term/post when either side is saved or deleted.
- Syncs hierarchy: when both the post type and taxonomy are hierarchical, parent/child relationships are mirrored too.
- A manual "Sync" action per mapping backfills missing terms/posts and removes orphaned ones.
- Hides the auto-managed taxonomy's term-management UI (submenu, "Add New Term" controls, block editor panel) so editors don't edit synced terms directly.
- Excludes a post's own synced term from its taxonomy picker, so a post can't be tagged with itself.
- A small set of filters for customizing behavior without forking — see [docs/api.md](docs/api.md).
- Checks for updates from GitHub releases directly in the WordPress dashboard.

## Installation

### Manual

1. Download the [latest release](https://github.com/vigetlabs/viget-post-type-taxonomy-sync/releases/latest) zip.
2. Upload it via **Plugins → Add New → Upload Plugin**, or extract it to `wp-content/plugins/viget-post-type-taxonomy-sync/`.
3. Activate the plugin.

### Composer

```bash
composer require viget/viget-post-type-taxonomy-sync
```

Requires [`composer/installers`](https://github.com/composer/installers) (installed automatically as a dependency) to place the plugin in `wp-content/plugins/`.

## Usage

1. Go to **Settings → Post/Tax Sync**.
2. Add a mapping between a post type and a taxonomy, then save.
3. Publishing a post of that type creates/updates a term of that taxonomy with a matching name and slug (and vice versa).
4. Use the row's **Sync** button at any point to backfill existing content or clean up orphaned terms/posts for that mapping.

Only one taxonomy can be mapped per post type (and vice versa). If both the post type and taxonomy are hierarchical, parent/child structure is mirrored automatically.

## Auto-Updates

This plugin checks GitHub releases every 12 hours and surfaces available updates directly in **Plugins** in wp-admin — no wordpress.org listing required. See [`includes/class-github-plugin-updater.php`](includes/class-github-plugin-updater.php).

Note: shipping a custom update checker is intentionally incompatible with wordpress.org's plugin directory (the [Plugin Check](https://wordpress.org/plugins/plugin-check/) tool flags it as a hard error under `plugin_updater_detected`). If this plugin is ever submitted there, the updater would need to be removed first.

## Hooks

Public methods and filters are documented in [docs/api.md](docs/api.md).

## Development

```bash
npm install        # install JS build tooling
npm run start      # watch mode
npm run build      # production build (writes to build/, which is committed)
npm run lint:js    # eslint
npm run lint:css   # stylelint
composer install   # PHP dependencies (composer/installers)
```

### Local WordPress environment

```bash
npm run env:start   # boots WordPress + this plugin via @wordpress/env (requires Docker)
npm run env:stop
```

### Releasing a new version

Versioning, tagging, and publishing are handled by one command:

```bash
npm run release -- patch   # or: minor | major
```

This bumps `package.json`, syncs the version into the plugin header/constant and `readme.txt` (see `bin/sync-version.js`), commits, tags `vX.Y.Z`, and pushes. Pushing the tag triggers [`.github/workflows/release.yaml`](.github/workflows/release.yaml), which builds the plugin zip and publishes a GitHub release.

## Changelog

See [readme.txt](readme.txt#changelog) for the full version history.

## License

[GPL-2.0-or-later](LICENSE)
