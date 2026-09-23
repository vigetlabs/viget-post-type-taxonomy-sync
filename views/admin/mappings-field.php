<?php
/**
 * Mappings field for Viget Post Type Taxonomy Sync
 *
 * @var array  $mappings
 * @var array  $post_types
 * @var array  $taxonomies
 * @var string $auto_value
 * @var array  $source_options
 *
 * @package Viget\PostTypeTaxonomySync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Viget\PostTypeTaxonomySync\Core;
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

/**
 * Renders the "Attach To" post type checkboxes for one row.
 *
 * @param string $name_prefix Field name prefix, e.g. option[mappings][0].
 * @param array  $post_types  Post type objects.
 * @param array  $checked     Selected post type slugs.
 *
 * @return void
 */
$vgptts_render_attach_to = function ( $name_prefix, $post_types, $checked ) {
	foreach ( $post_types as $vgptts_pt ) {
		?>
		<label class="vgptts-attach-option">
			<input type="checkbox"
				name="<?php echo esc_attr( $name_prefix ); ?>[attach_to][]"
				data-name-template="<?php echo esc_attr( $name_prefix ); ?>[attach_to][]"
				value="<?php echo esc_attr( $vgptts_pt->name ); ?>"
				<?php checked( in_array( $vgptts_pt->name, $checked, true ) ); ?>>
			<?php echo esc_html( $vgptts_pt->labels->singular_name ); ?>
		</label>
		<?php
	}
};
?>
<table class="widefat striped" id="vgptts-mappings-table">
	<thead>
		<tr>
			<th><?php esc_html_e( 'Post Type', 'viget-post-type-taxonomy-sync' ); ?></th>
			<th><?php esc_html_e( 'Taxonomy', 'viget-post-type-taxonomy-sync' ); ?></th>
			<th><?php esc_html_e( 'Attach To', 'viget-post-type-taxonomy-sync' ); ?></th>
			<th><?php esc_html_e( 'Source of Truth', 'viget-post-type-taxonomy-sync' ); ?></th>
			<th><?php esc_html_e( 'Actions', 'viget-post-type-taxonomy-sync' ); ?></th>
		</tr>
	</thead>
	<tbody>
	<?php foreach ( $mappings as $index => $mapping ) : ?>
		<?php
		$is_auto      = ! empty( $mapping['taxonomy_auto'] );
		$pt_is_auto   = ! empty( $mapping['post_type_auto'] );
		$pt           = isset( $mapping['post_type'] ) ? $mapping['post_type'] : '';
		$tax          = isset( $mapping['taxonomy'] ) ? $mapping['taxonomy'] : '';
		$attach_to    = isset( $mapping['attach_to'] ) ? (array) $mapping['attach_to'] : [];
		$source       = isset( $mapping['source_of_truth'] ) ? $mapping['source_of_truth'] : Core::SOURCE_POST_TYPE;
		$show_in_menu = ! empty( $mapping['show_post_type_in_menus'] );
		$has_both     = ! empty( $pt ) && ( $is_auto || ! empty( $tax ) );
		$name_prefix  = Settings::OPTION_NAME . '[mappings][' . (string) $index . ']';
		?>
		<tr class="<?php echo $has_both ? 'vgptts-row-saved' : 'vgptts-row-unsaved'; ?>" data-post-type="<?php echo esc_attr( $pt ); ?>" data-taxonomy="<?php echo esc_attr( $tax ); ?>" data-post-type-auto="<?php echo $pt_is_auto ? '1' : '0'; ?>">
			<td>
				<?php if ( $has_both ) : ?>
					<?php if ( $pt_is_auto ) : ?>
						<?php esc_html_e( 'Auto-created', 'viget-post-type-taxonomy-sync' ); ?> (<?php echo esc_html( $pt ); ?>)
						<input type="hidden" name="<?php echo esc_attr( $name_prefix ); ?>[post_type]" value="<?php echo esc_attr( $auto_value ); ?>">
					<?php else : ?>
						<?php echo esc_html( $vgptts_get_post_type_label( $pt ) ); ?> (<?php echo esc_html( $pt ); ?>)
						<input type="hidden" name="<?php echo esc_attr( $name_prefix ); ?>[post_type]" value="<?php echo esc_attr( $pt ); ?>">
					<?php endif; ?>
				<?php else : ?>
					<select name="<?php echo esc_attr( $name_prefix ); ?>[post_type]" class="vgptts-post-type-select">
						<option value=""><?php esc_html_e( 'Select post type', 'viget-post-type-taxonomy-sync' ); ?></option>
						<option value="<?php echo esc_attr( $auto_value ); ?>" <?php selected( $pt_is_auto ); ?>><?php esc_html_e( 'Auto-create for me', 'viget-post-type-taxonomy-sync' ); ?></option>
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
					<?php if ( $is_auto ) : ?>
						<?php esc_html_e( 'Auto-created', 'viget-post-type-taxonomy-sync' ); ?> (<?php echo esc_html( $tax ); ?>)
						<input type="hidden" name="<?php echo esc_attr( $name_prefix ); ?>[taxonomy]" value="<?php echo esc_attr( $auto_value ); ?>">
					<?php else : ?>
						<?php echo esc_html( $vgptts_get_taxonomy_label( $tax ) ); ?> (<?php echo esc_html( $tax ); ?>)
						<input type="hidden" name="<?php echo esc_attr( $name_prefix ); ?>[taxonomy]" value="<?php echo esc_attr( $tax ); ?>">
					<?php endif; ?>
				<?php else : ?>
					<select name="<?php echo esc_attr( $name_prefix ); ?>[taxonomy]" class="vgptts-taxonomy-select">
						<option value=""><?php esc_html_e( 'Select taxonomy', 'viget-post-type-taxonomy-sync' ); ?></option>
						<option value="<?php echo esc_attr( $auto_value ); ?>" <?php selected( $is_auto ); ?>><?php esc_html_e( 'Auto-create for me', 'viget-post-type-taxonomy-sync' ); ?></option>
						<?php foreach ( $taxonomies as $taxonomy ) : ?>
							<option value="<?php echo esc_attr( $taxonomy->name ); ?>" <?php selected( $tax, $taxonomy->name ); ?>>
								<?php echo esc_html( $taxonomy->labels->singular_name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				<?php endif; ?>
			</td>
			<td class="vgptts-attach-cell" <?php echo $is_auto ? '' : 'hidden'; ?>>
				<?php if ( $has_both && ! $is_auto ) : ?>
					<span class="vgptts-attach-na">&mdash;</span>
				<?php else : ?>
					<?php $vgptts_render_attach_to( $name_prefix, $post_types, $attach_to ); ?>
				<?php endif; ?>
			</td>
			<td class="vgptts-source-cell">
				<select name="<?php echo esc_attr( $name_prefix ); ?>[source_of_truth]" class="vgptts-source-select">
					<?php foreach ( $source_options as $vgptts_value => $vgptts_label ) : ?>
						<option value="<?php echo esc_attr( $vgptts_value ); ?>" <?php selected( $source, $vgptts_value ); ?>><?php echo esc_html( $vgptts_label ); ?></option>
					<?php endforeach; ?>
				</select>
				<label class="vgptts-menu-toggle" <?php echo ( $pt_is_auto && Core::SOURCE_TAXONOMY === $source ) ? '' : 'hidden'; ?>>
					<input type="checkbox" name="<?php echo esc_attr( $name_prefix ); ?>[show_post_type_in_menus]" value="1" <?php checked( $show_in_menu ); ?>>
					<?php esc_html_e( 'Show Post Type in Menus', 'viget-post-type-taxonomy-sync' ); ?>
				</label>
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
				<select name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[mappings][__INDEX__][post_type]" class="vgptts-post-type-select" data-name-template="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[mappings][__INDEX__][post_type]">
					<option value=""><?php esc_html_e( 'Select post type', 'viget-post-type-taxonomy-sync' ); ?></option>
					<option value="<?php echo esc_attr( $auto_value ); ?>"><?php esc_html_e( 'Auto-create for me', 'viget-post-type-taxonomy-sync' ); ?></option>
					<?php foreach ( $post_types as $post_type ) : ?>
						<option value="<?php echo esc_attr( $post_type->name ); ?>"><?php echo esc_html( $post_type->labels->singular_name ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
			<td>
				<select name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[mappings][__INDEX__][taxonomy]" class="vgptts-taxonomy-select" data-name-template="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[mappings][__INDEX__][taxonomy]">
					<option value=""><?php esc_html_e( 'Select taxonomy', 'viget-post-type-taxonomy-sync' ); ?></option>
					<option value="<?php echo esc_attr( $auto_value ); ?>"><?php esc_html_e( 'Auto-create for me', 'viget-post-type-taxonomy-sync' ); ?></option>
					<?php foreach ( $taxonomies as $taxonomy ) : ?>
						<option value="<?php echo esc_attr( $taxonomy->name ); ?>"><?php echo esc_html( $taxonomy->labels->singular_name ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
			<td class="vgptts-attach-cell" hidden>
				<?php $vgptts_render_attach_to( Settings::OPTION_NAME . '[mappings][__INDEX__]', $post_types, [] ); ?>
			</td>
			<td class="vgptts-source-cell">
				<select name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[mappings][__INDEX__][source_of_truth]" class="vgptts-source-select" data-name-template="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[mappings][__INDEX__][source_of_truth]">
					<?php foreach ( $source_options as $vgptts_value => $vgptts_label ) : ?>
						<option value="<?php echo esc_attr( $vgptts_value ); ?>"><?php echo esc_html( $vgptts_label ); ?></option>
					<?php endforeach; ?>
				</select>
				<label class="vgptts-menu-toggle" hidden>
					<input type="checkbox" name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[mappings][__INDEX__][show_post_type_in_menus]" value="1" data-name-template="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[mappings][__INDEX__][show_post_type_in_menus]">
					<?php esc_html_e( 'Show Post Type in Menus', 'viget-post-type-taxonomy-sync' ); ?>
				</label>
			</td>
			<td class="vgptts-actions-cell"></td>
		</tr>
	</tbody>
</table>
<p class="description">
	<?php esc_html_e( '"Auto-create for me" lets the plugin register that side for you, so the theme does not have to. Only one side of a mapping can be auto-created. Source of Truth is the side editors manage; the other is kept in sync behind the scenes.', 'viget-post-type-taxonomy-sync' ); ?>
</p>
<p>
	<button type="button" class="button" id="vgptts-add-row">
		<?php esc_html_e( 'Add Mapping', 'viget-post-type-taxonomy-sync' ); ?>
	</button>
</p>
