<?php
/**
 * Shared helper functions. Safe to call from anywhere after `plugins_loaded`.
 *
 * @package CreativeHub
 */

defined( 'ABSPATH' ) || exit;

/**
 * Read a single setting from the `ch_settings` option array.
 *
 * @param string $key     Setting key.
 * @param mixed  $default  Fallback value.
 * @return mixed
 */
function ch_get_setting( $key, $default = '' ) {
	$settings = get_option( 'ch_settings', array() );
	return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
}

/**
 * Order statuses (without the `wc-` prefix) that grant class access.
 *
 * @return string[]
 */
function ch_get_access_statuses() {
	$statuses = ch_get_setting( 'access_statuses', array( 'processing', 'completed' ) );
	if ( empty( $statuses ) || ! is_array( $statuses ) ) {
		$statuses = array( 'processing', 'completed' );
	}
	/**
	 * Filter the order statuses that unlock class content.
	 *
	 * @param string[] $statuses Status slugs without the `wc-` prefix.
	 */
	return apply_filters( 'ch_access_statuses', array_values( $statuses ) );
}

/**
 * Taxonomies checked for the Studio Sessions term. The store may file it
 * under its "collection" taxonomy, product tags or product categories.
 *
 * @return string[]
 */
function ch_get_studio_session_taxonomies() {
	return (array) apply_filters( 'ch_studio_session_taxonomies', array( 'collection', 'product_tag', 'product_cat' ) );
}

/**
 * Is this product a Studio Session, i.e. one that can unlock classes?
 * Products in other collections (curated-kits, kit-classes, …) behave as
 * ordinary WooCommerce products.
 *
 * @param int $product_id Product or variation ID.
 * @return bool
 */
function ch_is_studio_session_product( $product_id ) {
	$product_id = absint( $product_id );
	if ( ! $product_id ) {
		return false;
	}

	// Variations carry no terms of their own — check the parent.
	$parent_id = wp_get_post_parent_id( $product_id );
	if ( $parent_id && 'product_variation' === get_post_type( $product_id ) ) {
		$product_id = $parent_id;
	}

	$term = apply_filters( 'ch_studio_session_term', 'studio-sessions' );

	foreach ( ch_get_studio_session_taxonomies() as $taxonomy ) {
		if ( taxonomy_exists( $taxonomy ) && has_term( $term, $taxonomy, $product_id ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Every product linked to a class in the editor, whether or not it is
 * currently a Studio Session. Use ch_get_class_product_ids() for access.
 *
 * @param int $class_id Class post ID.
 * @return int[]
 */
function ch_get_linked_product_ids( $class_id ) {
	$ids = get_post_meta( $class_id, '_ch_access_product_ids', true );
	$ids = is_array( $ids ) ? array_map( 'absint', $ids ) : array();
	return array_values( array_filter( array_unique( $ids ) ) );
}

/**
 * Product IDs that unlock a given class — linked products that are Studio Sessions.
 *
 * @param int $class_id Class post ID.
 * @return int[]
 */
function ch_get_class_product_ids( $class_id ) {
	$ids = array_values( array_filter( ch_get_linked_product_ids( $class_id ), 'ch_is_studio_session_product' ) );

	/**
	 * Filter the products that unlock a class.
	 *
	 * @param int[] $ids      Product IDs.
	 * @param int   $class_id Class post ID.
	 */
	return apply_filters( 'ch_class_product_ids', $ids, $class_id );
}

/**
 * Persist the product IDs that unlock a class and refresh the reverse index.
 *
 * @param int   $class_id    Class post ID.
 * @param int[] $product_ids Product IDs.
 */
function ch_set_class_product_ids( $class_id, $product_ids ) {
	$product_ids = array_values( array_filter( array_unique( array_map( 'absint', (array) $product_ids ) ) ) );
	update_post_meta( $class_id, '_ch_access_product_ids', $product_ids );
	ch_rebuild_product_class_index();
}

/**
 * Reverse lookup: product ID => [ class IDs it unlocks ].
 * Cached in an option and rebuilt whenever a class is saved.
 *
 * @return array<int,int[]>
 */
function ch_get_product_class_index() {
	$index = get_option( 'ch_product_class_index', null );
	if ( ! is_array( $index ) ) {
		$index = ch_rebuild_product_class_index();
	}
	return $index;
}

/**
 * Rebuild and store the reverse index.
 *
 * @return array<int,int[]>
 */
function ch_rebuild_product_class_index() {
	$classes = get_posts(
		array(
			'post_type'      => 'ch_class',
			'post_status'    => array( 'publish', 'private', 'draft', 'pending', 'future' ),
			'numberposts'    => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);

	$index = array();
	foreach ( $classes as $class_id ) {
		foreach ( ch_get_class_product_ids( $class_id ) as $product_id ) {
			$index[ $product_id ][] = (int) $class_id;
		}
	}

	update_option( 'ch_product_class_index', $index, false );
	return $index;
}

/**
 * Classes unlocked by a given product.
 *
 * @param int $product_id Product ID (parent product, not variation).
 * @return int[]
 */
function ch_get_classes_for_product( $product_id ) {
	if ( ! ch_is_studio_session_product( $product_id ) ) {
		return array();
	}
	$index = ch_get_product_class_index();
	return isset( $index[ $product_id ] ) ? array_values( array_unique( $index[ $product_id ] ) ) : array();
}

/**
 * Can this user access this class?
 *
 * @param int      $class_id Class post ID.
 * @param int|null $user_id  Defaults to the current user.
 * @return bool
 */
function ch_user_can_access_class( $class_id, $user_id = null ) {
	$class_id = absint( $class_id );
	$user_id  = is_null( $user_id ) ? get_current_user_id() : absint( $user_id );

	$can = null;

	// Shop managers / editors can preview locked pages.
	if ( ch_get_setting( 'admin_preview', 1 ) && $user_id && user_can( $user_id, 'edit_others_pages' ) ) {
		$can = true;
	}

	if ( is_null( $can ) ) {
		if ( ! $user_id ) {
			$can = false;
		} else {
			$linked = ch_get_class_product_ids( $class_id );
			if ( empty( $linked ) ) {
				$can = false;
			} else {
				$purchased = CH_Entitlements::instance()->get_purchased_product_ids( $user_id );
				$can       = (bool) array_intersect( $linked, $purchased );
			}
		}
	}

	/**
	 * Filter the final access decision for a class.
	 *
	 * @param bool $can      Whether access is granted.
	 * @param int  $class_id Class post ID.
	 * @param int  $user_id  User ID (0 when logged out).
	 */
	return (bool) apply_filters( 'ch_user_can_access_class', $can, $class_id, $user_id );
}

/**
 * All class IDs a user currently has access to.
 *
 * @param int|null $user_id Defaults to the current user.
 * @return int[]
 */
function ch_get_user_class_ids( $user_id = null ) {
	$user_id = is_null( $user_id ) ? get_current_user_id() : absint( $user_id );
	if ( ! $user_id ) {
		return array();
	}

	$purchased = CH_Entitlements::instance()->get_purchased_product_ids( $user_id );
	$index     = ch_get_product_class_index();

	$class_ids = array();
	foreach ( $purchased as $product_id ) {
		if ( isset( $index[ $product_id ] ) ) {
			$class_ids = array_merge( $class_ids, $index[ $product_id ] );
		}
	}

	$class_ids = array_values( array_unique( $class_ids ) );

	return apply_filters( 'ch_user_class_ids', $class_ids, $user_id );
}

/**
 * URL of the Creative Hub: a dedicated page if one is set, otherwise the
 * My Account dashboard (which now shows the hub).
 *
 * @return string
 */
function ch_get_hub_url() {
	$page_id = absint( ch_get_setting( 'hub_page_id', 0 ) );
	if ( $page_id && 'publish' === get_post_status( $page_id ) ) {
		return get_permalink( $page_id );
	}
	$account = wc_get_page_permalink( 'myaccount' );
	return $account ? $account : home_url( '/my-account/' );
}

/**
 * Render a template file, allowing a theme override in `yourtheme/creative-hub/`.
 *
 * @param string $name Template file name, e.g. "teaser.php".
 * @param array  $args Variables to extract into scope.
 */
function ch_get_template( $name, $args = array() ) {
	$override = locate_template( array( 'creative-hub/' . $name ) );
	$file     = $override ? $override : CH_PATH . 'templates/' . $name;

	if ( ! file_exists( $file ) ) {
		return;
	}

	if ( $args ) {
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		extract( $args );
	}
	include $file;
}
