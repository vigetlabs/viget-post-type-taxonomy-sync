<?php
/**
 * Mappings field for Viget Post Type Taxonomy Sync
 *
 * @var array $mappings
 * @var array $registered Registered rows from Settings::get_registered_rows().
 * @var array $flagged
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
?>
<table class="widefat striped" id="vgptts-mappings-table">
	<thead>
		<tr>
			<th><?php esc_html_e( 'Post Type', 'viget-post-type-taxonomy-sync' ); ?></th>
			<th><?php esc_html_e( 'Taxonomy', 'viget-post-type-taxonomy-sync' ); ?></th>
			<th><?php esc_html_e( 'Actions', 'viget-post-type-taxonomy-sync' ); ?></th>
			<th class="vgptts-icon-cell"><span class="screen-reader-text"><?php esc_html_e( 'Status', 'viget-post-type-taxonomy-sync' ); ?></span></th>
		</tr>
	</thead>
	<tbody>
	<?php foreach ( $registered as $mapping ) : ?>
		<?php
		$vgptts_tip  = $mapping['note'] ?? $vgptts_registered_note;
		$vgptts_icon = $mapping['note'] ? 'dashicons-no-alt' : 'dashicons-lock';
		?>
		<tr class="vgptts-row-registered<?php echo $mapping['note'] ? ' vgptts-row-flagged' : ''; ?>" data-post-type="<?php echo esc_attr( $mapping['post_type'] ); ?>" data-taxonomy="<?php echo esc_attr( $mapping['taxonomy'] ); ?>">
			<td>
				<span class="vgptts-mapping-label"><?php echo esc_html( Settings::get_post_type_label( $mapping['post_type'] ) ); ?> (<?php echo esc_html( $mapping['post_type'] ); ?>)</span>
			</td>
			<td>
				<span class="vgptts-mapping-label"><?php echo esc_html( Settings::get_taxonomy_label( $mapping['taxonomy'] ) ); ?> (<?php echo esc_html( $mapping['taxonomy'] ); ?>)</span>
			</td>
			<td class="vgptts-actions-cell">
				<?php if ( ! $mapping['note'] ) : ?>
					<button type="button" class="button vgptts-sync-row" data-post-type="<?php echo esc_attr( $mapping['post_type'] ); ?>" data-taxonomy="<?php echo esc_attr( $mapping['taxonomy'] ); ?>">
						<span class="vgptts-sync-label"><?php esc_html_e( 'Sync', 'viget-post-type-taxonomy-sync' ); ?></span>
						<span class="vgptts-sync-spinner" aria-hidden="true"></span>
					</button>
				<?php endif; ?>
			</td>
			<td class="vgptts-icon-cell">
				<?php if ( function_exists( 'wp_get_tooltip' ) ) : ?>
					<?php
					echo wp_get_tooltip(
						$vgptts_tip,
						[
							'icon'  => $vgptts_icon,
							'class' => 'vgptts-registered-icon',
						]
					);
					?>
				<?php else : ?>
					<span class="dashicons <?php echo esc_attr( $vgptts_icon ); ?> vgptts-registered-icon" title="<?php echo esc_attr( $vgptts_tip ); ?>" aria-hidden="true"></span>
					<span class="screen-reader-text"><?php echo esc_html( $vgptts_tip ); ?></span>
				<?php endif; ?>
			</td>
		</tr>
	<?php endforeach; ?>
	<?php foreach ( $mappings as $index => $mapping ) : ?>
		<?php
		$has_both   = ! empty( $mapping['post_type'] ) && ! empty( $mapping['taxonomy'] );
		$pt         = isset( $mapping['post_type'] ) ? $mapping['post_type'] : '';
		$tax        = isset( $mapping['taxonomy'] ) ? $mapping['taxonomy'] : '';
		$note       = $has_both ? Settings::get_flag_note(
			[
				'post_type' => sanitize_key( $pt ),
				'taxonomy'  => sanitize_key( $tax ),
			],
			'saved',
			$flagged
		) : null;
		$overridden = null !== $note;
		$row_class  = $has_both ? 'vgptts-row-saved' : 'vgptts-row-unsaved';
		?>
		<tr class="<?php echo esc_attr( $row_class . ( $overridden ? ' vgptts-row-overridden' : '' ) ); ?>" data-post-type="<?php echo esc_attr( $pt ); ?>" data-taxonomy="<?php echo esc_attr( $tax ); ?>">
			<td>
				<?php if ( $has_both ) : ?>
					<?php echo esc_html( Settings::get_post_type_label( $pt ) ); ?> (<?php echo esc_html( $pt ); ?>)
					<input type="hidden" name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[mappings][<?php echo esc_attr( (string) $index ); ?>][post_type]" value="<?php echo esc_attr( $pt ); ?>">
					<?php if ( $overridden ) : ?>
						<span class="vgptts-row-note"><?php echo esc_html( $note ); ?></span>
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
					<?php echo esc_html( Settings::get_taxonomy_label( $tax ) ); ?> (<?php echo esc_html( $tax ); ?>)
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
			<td class="vgptts-icon-cell">
				<?php if ( ! $has_both ) : ?>
					<button type="button" class="button-link vgptts-discard-row" aria-label="<?php esc_attr_e( 'Remove unsaved mapping', 'viget-post-type-taxonomy-sync' ); ?>">
						<span class="dashicons dashicons-minus" aria-hidden="true"></span>
					</button>
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
			<td class="vgptts-icon-cell">
				<button type="button" class="button-link vgptts-discard-row" aria-label="<?php esc_attr_e( 'Remove unsaved mapping', 'viget-post-type-taxonomy-sync' ); ?>">
					<span class="dashicons dashicons-minus" aria-hidden="true"></span>
				</button>
			</td>
		</tr>
	</tbody>
</table>
<p>
	<button type="button" class="button" id="vgptts-add-row">
		<?php esc_html_e( 'Add Mapping', 'viget-post-type-taxonomy-sync' ); ?>
	</button>
</p>
