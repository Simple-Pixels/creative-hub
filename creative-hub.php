<?php
/**
 * Plugin Name:       Creative Hub
 * Description:        Post-purchase members area for online classes. Adds a "Classes" content type (Elementor-editable), links classes to WooCommerce products, gates class pages by purchase, and gives customers a tidy view of what they own.
 * Version:           0.4.0
 * Requires at least:  6.2
 * Requires PHP:       7.4
 * Author:            Scrapping Clearly
 * Text Domain:       creative-hub
 * WC requires at least: 7.0
 * WC tested up to:   9.3
 *
 * @package CreativeHub
 */

defined( 'ABSPATH' ) || exit;

define( 'CH_VERSION', '0.4.0' );
define( 'CH_FILE', __FILE__ );
define( 'CH_PATH', plugin_dir_path( __FILE__ ) );
define( 'CH_URL', plugin_dir_url( __FILE__ ) );

/**
 * Declare HPOS (High-Performance Order Storage) compatibility.
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', CH_FILE, true );
		}
	}
);

/**
 * Boot the plugin once all plugins are loaded, so WooCommerce is available.
 */
add_action(
	'plugins_loaded',
	function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-error"><p><strong>Creative Hub</strong> needs WooCommerce to be installed and active.</p></div>';
				}
			);
			return;
		}

		require_once CH_PATH . 'includes/helpers.php';
		require_once CH_PATH . 'includes/class-ch-post-types.php';
		require_once CH_PATH . 'includes/class-ch-product-link.php';
		require_once CH_PATH . 'includes/class-ch-entitlements.php';
		require_once CH_PATH . 'includes/class-ch-access-control.php';
		require_once CH_PATH . 'includes/class-ch-shortcodes.php';
		require_once CH_PATH . 'includes/class-ch-account.php';
		require_once CH_PATH . 'includes/class-ch-order.php';
		require_once CH_PATH . 'includes/class-ch-elementor.php';
		require_once CH_PATH . 'includes/class-ch-settings.php';
		require_once CH_PATH . 'includes/class-ch-assets.php';

		CH_Post_Types::init();
		CH_Product_Link::init();
		CH_Entitlements::instance();
		CH_Access_Control::init();
		CH_Shortcodes::init();
		CH_Account::init();
		CH_Order::init();
		CH_Elementor::init();
		CH_Settings::init();
		CH_Assets::init();

		load_plugin_textdomain( 'creative-hub', false, dirname( plugin_basename( CH_FILE ) ) . '/languages' );
	}
);

/**
 * When the plugin version changes, re-register rewrite rules and flush once,
 * so slug changes take effect without the admin visiting Settings → Permalinks.
 */
add_action(
	'admin_init',
	function () {
		if ( get_option( 'ch_version' ) === CH_VERSION ) {
			return;
		}
		if ( class_exists( 'CH_Post_Types' ) ) {
			CH_Post_Types::register_post_type();
			CH_Post_Types::register_taxonomy();
			CH_Account::register_endpoint();
			flush_rewrite_rules();
		}
		update_option( 'ch_version', CH_VERSION );
	}
);

/* -------------------------------------------------------------------------
 * Activation / deactivation
 * ---------------------------------------------------------------------- */

register_activation_hook( CH_FILE, 'ch_activate' );
register_deactivation_hook( CH_FILE, 'ch_deactivate' );

function ch_activate() {
	require_once CH_PATH . 'includes/helpers.php';
	require_once CH_PATH . 'includes/class-ch-post-types.php';
	require_once CH_PATH . 'includes/class-ch-account.php';

	CH_Post_Types::register_post_type();
	CH_Post_Types::register_taxonomy();
	CH_Account::register_endpoint();

	// Default settings.
	add_option(
		'ch_settings',
		array(
			'access_statuses'  => array( 'processing', 'completed' ),
			'auto_gate'        => 1,
			'admin_preview'    => 1,
			'buy_button_text'  => 'Get this class',
			'locked_heading'   => 'This class is waiting for you',
			'locked_message'   => 'Purchase this class to unlock the full lesson, videos and downloads. Your access appears here the moment your payment goes through.',
			'hub_page_id'      => 0,
			'access_page_id'   => 0,
		)
	);

	update_option( 'ch_version', CH_VERSION );

	// Register "Classes" with Elementor's editable post types.
	$cpt_support = get_option( 'elementor_cpt_support' );
	if ( ! is_array( $cpt_support ) ) {
		$cpt_support = array( 'page', 'post' );
	}
	if ( ! in_array( 'ch_class', $cpt_support, true ) ) {
		$cpt_support[] = 'ch_class';
		update_option( 'elementor_cpt_support', $cpt_support );
	}

	// Seed the class-type terms.
	foreach ( array( 'Online Class', 'Kit Class', 'Free Resource' ) as $term ) {
		if ( ! term_exists( $term, 'ch_class_type' ) ) {
			wp_insert_term( $term, 'ch_class_type' );
		}
	}

	flush_rewrite_rules();
}

function ch_deactivate() {
	flush_rewrite_rules();
}
