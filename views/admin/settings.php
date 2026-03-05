<?php
/**
 * Settings page for Post Type Taxonomy Sync
 *
 * @package PostTypeTaxonomySync
 */

use PostTypeTaxonomySync\Settings;
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Post Type Taxonomy Sync', 'post-type-taxonomy-sync' ); ?></h1>
	<form method="post" action="<?php echo admin_url( 'options.php' ); ?>">
		<?php
		settings_fields( Settings::OPTION_NAME );
		do_settings_sections( Settings::PAGE_SLUG );
		submit_button();
		?>
	</form>
</div>
