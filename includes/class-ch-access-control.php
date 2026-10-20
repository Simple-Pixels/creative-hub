<?php
/**
 * Gates single Class pages by purchase.
 *
 * Entitled visitors get the normal (Elementor / theme) single view.
 * Everyone else is sent to the configured Access page, or — if none is set —
 * shown the teaser template (the class's own Elementor content is bypassed).
 * Customers are never redirected to wp-login.php.
 *
 * @package CreativeHub
 */

defined( 'ABSPATH' ) || exit;

class CH_Access_Control {

	/** @var bool */
	protected static $showing_teaser = false;

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'guard' ) );

		// The teaser is loaded through the normal template step, never printed from
		// inside template_redirect: Elementor (and so its header/footer, mini-cart
		// and elementorFrontendConfig) only finishes initialising after our
		// template_redirect callback has run.
		add_filter( 'template_include', array( __CLASS__, 'teaser_template' ), 99999 );
	}

	/**
	 * Swap in the teaser template when the guard decided this visitor is locked out.
	 *
	 * @param string $template Template WordPress resolved.
	 * @return string
	 */
	public static function teaser_template( $template ) {
		if ( ! self::$showing_teaser ) {
			return $template;
		}

		$override = locate_template( array( 'creative-hub/teaser.php' ) );
		return $override ? $override : CH_PATH . 'templates/teaser.php';
	}

	public static function guard() {
		if ( ! is_singular( 'ch_class' ) ) {
			return;
		}
		if ( ! ch_get_setting( 'auto_gate', 1 ) ) {
			return;
		}

		$class_id = get_queried_object_id();

		if ( ch_user_can_access_class( $class_id ) ) {
			return;
		}

		// Preferred: send everyone without access to the configured Access page,
		// passing the class they tried to reach so the notice can show buy links.
		$access_page_id = absint( ch_get_setting( 'access_page_id', 0 ) );
		if ( $access_page_id && 'publish' === get_post_status( $access_page_id ) && (int) $access_page_id !== (int) $class_id ) {
			$redirect = add_query_arg( 'ch_class', $class_id, get_permalink( $access_page_id ) );
			/**
			 * Filter the URL a no-access visitor is redirected to.
			 *
			 * @param string $redirect
			 * @param int    $class_id
			 */
			$redirect = apply_filters( 'ch_no_access_redirect', $redirect, $class_id );
			nocache_headers();
			wp_safe_redirect( $redirect );
			exit;
		}

		// Fallback (no Access page set): show the teaser to everyone, logged in or
		// not. The teaser itself offers a log-in link. We never bounce a customer
		// to wp-login.php.
		$logged_out_redirect = apply_filters( 'ch_locked_logged_out_redirect', '', $class_id );
		if ( ! is_user_logged_in() && $logged_out_redirect ) {
			wp_safe_redirect( $logged_out_redirect );
			exit;
		}

		self::$showing_teaser = true;

		status_header( 200 );
		nocache_headers();
	}

	/**
	 * @return bool
	 */
	public static function is_showing_teaser() {
		return self::$showing_teaser;
	}
}
