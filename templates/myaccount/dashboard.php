<?php
/**
 * My Account → Dashboard, replaced by the Creative Hub.
 *
 * Overrides WooCommerce's myaccount/dashboard.php via the
 * `woocommerce_locate_template` filter (see CH_Account).
 *
 * @package CreativeHub
 */

defined( 'ABSPATH' ) || exit;

echo do_shortcode( '[creative_hub]' );

/**
 * Keep the standard hook so other plugins that add to the dashboard still fire.
 */
do_action( 'woocommerce_account_dashboard' );
