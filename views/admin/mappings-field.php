<?php
/**
 * Mappings field for Viget Post Type Taxonomy Sync
 *
 * @var array $mappings
 * @var array $registered
 * @var array $post_types
 * @var array $taxonomies
 *
 * @package Viget\PostTypeTaxonomySync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Viget\PostTypeTaxonomySync\Settings;

// Shown in a tooltip on registered rows. wp_get_tooltip() is WordPress 7.1+, so older versions fall back to a title.
$vgptts_registered_note = __( 'Registered in code', 'viget-post-type-taxonomy-sync' );

// Registered mappings can use post types and taxonomies the dropdowns don't list, so fall back to the registered objects.
$vgptts_get_post_type_label = function ( $slug ) use ( $post_types ) {
	$object = $post_types[ $slug ] ?? get_post_type_object( $slug );
	return $object->labels->singular_name ?? $slug;
};

$vgptts_get_taxonomy_label = function ( $slug ) use ( $taxonomies ) {
	$object = $taxonomies[ $slug ] ?? get_taxonomy( $slug );
	return $object ? $object->labels->singular_name : $slug;
};
?>
<table class="widefat striped" id="vgptts-mappings-table">
	<thead>
		<tr>
			<th><?php esc_html_e( 'Post Type', 'viget-post-type-taxonomy-sync' ); ?></th>
			<th><?php esc_html_e( 'Taxonomy', 'viget-post-type-taxonomy-sync' ); ?></th>
			<th><?php esc_html_e( 'Actions', 'viget-post-type-taxonomy-sync' ); ?></th>
		</tr>
	</thead>
	<tbody>
	<?php foreach ( $registered as $mapping ) : ?>
		<tr class="vgptts-row-registered" data-post-type="<?php echo esc_attr( $mapping['post_type'] ); ?>" data-taxonomy="<?php echo esc_attr( $mapping['taxonomy'] ); ?>">
			<td>
				<span class="vgptts-registered-label">
				<?php echo esc_html( $vgptts_get_post_type_label( $mapping['post_type'] ) ); ?> (<?php echo esc_html( $mapping['post_type'] ); ?>)
				<?php if ( function_exists( 'wp_get_tooltip' ) ) : ?>
					<?php
					echo wp_get_tooltip( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the tooltip markup.
						$vgptts_registered_note,
						[
							'icon'  => 'dashicons-lock',
							'class' => 'vgptts-registered-icon',
						]
					);
					?>
				<?php else : ?>
					<span class="dashicons dashicons-lock vgptts-registered-icon" title="<?php echo esc_attr( $vgptts_registered_note ); ?>" aria-hidden="true"></span>
					<span class="screen-reader-text"><?php echo esc_html( $vgptts_registered_note ); ?></span>
				<?php endif; ?>
				</span>
			</td>
			<td>
				<?php echo esc_html( $vgptts_get_taxonomy_label( $mapping['taxonomy'] ) ); ?> (<?php echo esc_html( $mapping['taxonomy'] ); ?>)
			</td>
			<td class="vgptts-actions-cell">
				<button type="button" class="button vgptts-sync-row" data-post-type="<?php echo esc_attr( $mapping['post_type'] ); ?>" data-taxonomy="<?php echo esc_attr( $mapping['taxonomy'] ); ?>">
					<span class="vgptts-sync-label"><?php esc_html_e( 'Sync', 'viget-post-type-taxonomy-sync' ); ?></span>
					<span class="vgptts-sync-spinner" aria-hidden="true"></span>
				</button>
			</td>
		</tr>
	<?php endforeach; ?>
	<?php foreach ( $mappings as $index => $mapping ) : ?>
		<?php
		$has_both   = ! empty( $mapping['post_type'] ) && ! empty( $mapping['taxonomy'] );
		$pt         = isset( $mapping['post_type'] ) ? $mapping['post_type'] : '';
		$tax        = isset( $mapping['taxonomy'] ) ? $mapping['taxonomy'] : '';
		$overridden = $has_both && vgptts()->is_overridden(
			[
				'post_type' => sanitize_key( $pt ),
				'taxonomy'  => sanitize_key( $tax ),
			],
			$registered
		);
		$row_class  = $has_both ? 'vgptts-row-saved' : 'vgptts-row-unsaved';
		?>
		<tr class="<?php echo esc_attr( $row_class . ( $overridden ? ' vgptts-row-overridden' : '' ) ); ?>" data-post-type="<?php echo esc_attr( $pt ); ?>" data-taxonomy="<?php echo esc_attr( $tax ); ?>">
			<td>
				<?php if ( $has_both ) : ?>
					<?php echo esc_html( $vgptts_get_post_type_label( $pt ) ); ?> (<?php echo esc_html( $pt ); ?>)
					<input type="hidden" name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[mappings][<?php echo esc_attr( (string) $index ); ?>][post_type]" value="<?php echo esc_attr( $pt ); ?>">
					<?php if ( $overridden ) : ?>
						<span class="vgptts-row-note"><?php esc_html_e( 'Not synced: a mapping registered in code uses this post type or taxonomy.', 'viget-post-type-taxonomy-sync' ); ?></span>
					<?php endif; ?>
				<?php else : ?>
					<select name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[mappings][<?php echo esc_attr( (string) $index ); ?>][post_type]">
						<option value=""><?php esc_html_e( 'Select post type', 'viget-post-type-taxonomy-sync' ); ?></option>
						<?php foreach ( $post_types as $post_type ) : ?>
							<option value="<?php echo esc_attr( $post_type->name ); ?>" <?php selected( $pt, $post_type->name ); ?>>
								<?php echo esc_html( $post_type->labels->singular_name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				<?php endif; ?>
			</td>
			<td>
				<?php if ( $has_both ) : ?>
					<?php echo esc_html( $vgptts_get_taxonomy_label( $tax ) ); ?> (<?php echo esc_html( $tax ); ?>)
					<input type="hidden" name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[mappings][<?php echo esc_attr( (string) $index ); ?>][taxonomy]" value="<?php echo esc_attr( $tax ); ?>">
				<?php else : ?>
					<select name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[mappings][<?php echo esc_attr( (string) $index ); ?>][taxonomy]">
						<option value=""><?php esc_html_e( 'Select taxonomy', 'viget-post-type-taxonomy-sync' ); ?></option>
						<?php foreach ( $taxonomies as $taxonomy ) : ?>
							<option value="<?php echo esc_attr( $taxonomy->name ); ?>" <?php selected( $tax, $taxonomy->name ); ?>>
								<?php echo esc_html( $taxonomy->labels->singular_name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				<?php endif; ?>
			</td>
			<td class="vgptts-actions-cell">
				<?php if ( $has_both && ! $overridden ) : ?>
					<button type="button" class="button vgptts-sync-row" data-post-type="<?php echo esc_attr( $pt ); ?>" data-taxonomy="<?php echo esc_attr( $tax ); ?>">
						<span class="vgptts-sync-label"><?php esc_html_e( 'Sync', 'viget-post-type-taxonomy-sync' ); ?></span>
						<span class="vgptts-sync-spinner" aria-hidden="true"></span>
					</button>
				<?php endif; ?>
				<?php if ( $has_both ) : ?>
					<button type="button" class="button vgptts-remove-row"><?php esc_html_e( 'Remove', 'viget-post-type-taxonomy-sync' ); ?></button>
				<?php endif; ?>
			</td>
		</tr>
	<?php endforeach; ?>
		<tr id="vgptts-row-template" class="vgptts-row-template vgptts-row-unsaved" style="display: none;" data-post-type="" data-taxonomy="">
			<td>
				<select name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[mappings][__INDEX__][post_type]" data-name-template="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[mappings][__INDEX__][post_type]">
					<option value=""><?php esc_html_e( 'Select post type', 'viget-post-type-taxonomy-sync' ); ?></option>
					<?php foreach ( $post_types as $post_type ) : ?>
						<option value="<?php echo esc_attr( $post_type->name ); ?>"><?php echo esc_html( $post_type->labels->singular_name ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
			<td>
				<select name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[mappings][__INDEX__][taxonomy]" data-name-template="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[mappings][__INDEX__][taxonomy]">
					<option value=""><?php esc_html_e( 'Select taxonomy', 'viget-post-type-taxonomy-sync' ); ?></option>
					<?php foreach ( $taxonomies as $taxonomy ) : ?>
						<option value="<?php echo esc_attr( $taxonomy->name ); ?>"><?php echo esc_html( $taxonomy->labels->singular_name ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
			<td class="vgptts-actions-cell"></td>
		</tr>
	</tbody>
</table>
<p>
	<button type="button" class="button" id="vgptts-add-row">
		<?php esc_html_e( 'Add Mapping', 'viget-post-type-taxonomy-sync' ); ?>
	</button>
</p>
