<?php
/**
 * Settings page for Viget Post Type Taxonomy Sync
 *
 * @package Viget\PostTypeTaxonomySync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Viget\PostTypeTaxonomySync\Settings;
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Post Type Taxonomy Sync', 'viget-post-type-taxonomy-sync' ); ?></h1>
	<?php settings_errors( Settings::OPTION_NAME ); ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
		<?php
		settings_fields( Settings::OPTION_NAME );
		do_settings_sections( Settings::PAGE_SLUG );
		submit_button();
		?>
	</form>
</div>
