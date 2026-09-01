<?php
/**
 * Front-end stylesheet loading.
 *
 * The site's Elementor kit already provides Manrope and the heading/body styles,
 * so this sheet only styles the plugin's own components (cards, buttons, teaser).
 *
 * @package CreativeHub
 */

defined( 'ABSPATH' ) || exit;

class CH_Assets {

	/** @var bool */
	protected static $needed = false;

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'auto_enqueue' ), 20 );
	}

	public static function register() {
		wp_register_style( 'creative-hub', CH_URL . 'assets/css/creative-hub.css', array(), CH_VERSION );
	}

	/**
	 * Load automatically on single class pages, the account area, and any post
	 * that contains one of the hub shortcodes.
	 */
	public static function auto_enqueue() {
		if ( is_singular( 'ch_class' ) || is_post_type_archive( 'ch_class' ) || is_account_page() || is_order_received_page() ) {
			self::enqueue_front();
			return;
		}

		if ( is_singular() ) {
			$post = get_post();
			if ( $post && has_shortcode( (string) $post->post_content, 'creative_hub' ) ) {
				self::enqueue_front();
				return;
			}
			foreach ( array( 'ch_my_classes', 'ch_my_downloads', 'ch_class_products', 'ch_access_notice', 'ch_locked', 'ch_teaser' ) as $tag ) {
				if ( $post && has_shortcode( (string) $post->post_content, $tag ) ) {
					self::enqueue_front();
					return;
				}
			}
		}
	}

	public static function enqueue_front() {
		if ( ! wp_style_is( 'creative-hub', 'registered' ) ) {
			self::register();
		}
		wp_enqueue_style( 'creative-hub' );
	}
}
