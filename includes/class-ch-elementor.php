<?php
/**
 * Elementor compatibility.
 *
 * Ensures "Classes" is registered as an Elementor-editable post type so the
 * "Edit with Elementor" button appears, and Theme Builder can target it.
 *
 * @package CreativeHub
 */

defined( 'ABSPATH' ) || exit;

class CH_Elementor {

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'ensure_cpt_support' ) );
	}

	/**
	 * Elementor stores its editable post types in the `elementor_cpt_support`
	 * option. Make sure `ch_class` is in there.
	 */
	public static function ensure_cpt_support() {
		if ( ! did_action( 'elementor/loaded' ) && ! defined( 'ELEMENTOR_VERSION' ) ) {
			return;
		}

		$support = get_option( 'elementor_cpt_support' );
		if ( ! is_array( $support ) ) {
			$support = array( 'page', 'post' );
		}
		if ( ! in_array( 'ch_class', $support, true ) ) {
			$support[] = 'ch_class';
			update_option( 'elementor_cpt_support', $support );
		}
	}
}
