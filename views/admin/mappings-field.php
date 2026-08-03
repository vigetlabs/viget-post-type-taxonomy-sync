<?php
/**
 * Mappings field for Viget Post Type Taxonomy Sync
 *
 * @var array $mappings
 * @var array $post_types
 * @var array $taxonomies
 *
 * @package Viget\PostTypeTaxonomySync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Viget\PostTypeTaxonomySync\Settings;

$vgptts_get_post_type_label = function ( $slug ) use ( $post_types ) {
	if ( isset( $post_types[ $slug ] ) && isset( $post_types[ $slug ]->labels->singular_name ) ) {
		return $post_types[ $slug ]->labels->singular_name;
	}
	return $slug;
};

$vgptts_get_taxonomy_label = function ( $slug ) use ( $taxonomies ) {
	if ( isset( $taxonomies[ $slug ] ) && isset( $taxonomies[ $slug ]->labels->singular_name ) ) {
		return $taxonomies[ $slug ]->labels->singular_name;
	}
	return $slug;
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
	<?php foreach ( $mappings as $index => $mapping ) : ?>
		<?php
		$has_both = ! empty( $mapping['post_type'] ) && ! empty( $mapping['taxonomy'] );
		$pt       = isset( $mapping['post_type'] ) ? $mapping['post_type'] : '';
		$tax      = isset( $mapping['taxonomy'] ) ? $mapping['taxonomy'] : '';
		?>
		<tr class="<?php echo $has_both ? 'vgptts-row-saved' : 'vgptts-row-unsaved'; ?>" data-post-type="<?php echo esc_attr( $pt ); ?>" data-taxonomy="<?php echo esc_attr( $tax ); ?>">
			<td>
				<?php if ( $has_both ) : ?>
					<?php echo esc_html( $vgptts_get_post_type_label( $pt ) ); ?> (<?php echo esc_html( $pt ); ?>)
					<input type="hidden" name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[mappings][<?php echo esc_attr( (string) $index ); ?>][post_type]" value="<?php echo esc_attr( $pt ); ?>">
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
				<?php if ( $has_both ) : ?>
					<button type="button" class="button vgptts-sync-row" data-post-type="<?php echo esc_attr( $pt ); ?>" data-taxonomy="<?php echo esc_attr( $tax ); ?>">
						<span class="vgptts-sync-label"><?php esc_html_e( 'Sync', 'viget-post-type-taxonomy-sync' ); ?></span>
						<span class="vgptts-sync-spinner" aria-hidden="true"></span>
					</button>
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
