<?php
/**
 * Runs when the plugin is deleted from wp-admin.
 * Removes plugin options only. Classes and their content are left untouched.
 *
 * @package CreativeHub
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'ch_settings' );
delete_option( 'ch_product_class_index' );

// Clear per-user entitlement caches.
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_ch\_purchased\_%' OR option_name LIKE '\_transient\_timeout\_ch\_purchased\_%'" );
