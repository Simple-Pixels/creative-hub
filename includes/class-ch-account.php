<?php
/**
 * Puts the Creative Hub on the WooCommerce My Account **Dashboard** tab,
 * replacing the default "Hello / From your account dashboard…" text.
 *
 * @package CreativeHub
 */

defined( 'ABSPATH' ) || exit;

class CH_Account {

	public static function init() {
		add_filter( 'woocommerce_locate_template', array( __CLASS__, 'dashboard_template' ), 10, 3 );
	}

	/**
	 * Swap WooCommerce's myaccount/dashboard.php for ours.
	 *
	 * @param string $template      Resolved template path.
	 * @param string $template_name Requested template, e.g. "myaccount/dashboard.php".
	 * @param string $template_path Theme template dir (usually "woocommerce/").
	 * @return string
	 */
	public static function dashboard_template( $template, $template_name, $template_path ) {
		if ( 'myaccount/dashboard.php' !== $template_name ) {
			return $template;
		}

		$custom = CH_PATH . 'templates/myaccount/dashboard.php';
		return file_exists( $custom ) ? $custom : $template;
	}

	/**
	 * Backwards-compatible endpoint registration (called from activation /
	 * version-upgrade). The dedicated "Creative Hub" tab was removed in 0.4.0;
	 * this is a no-op kept so the older calls don't fatal.
	 */
	public static function register_endpoint() {}
}
