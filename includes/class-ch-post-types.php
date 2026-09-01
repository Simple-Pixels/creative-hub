<?php
/**
 * Registers the "Classes" post type and its "Class type" taxonomy.
 *
 * @package CreativeHub
 */

defined( 'ABSPATH' ) || exit;

class CH_Post_Types {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'init', array( __CLASS__, 'register_taxonomy' ) );

		add_filter( 'manage_ch_class_posts_columns', array( __CLASS__, 'admin_columns' ) );
		add_action( 'manage_ch_class_posts_custom_column', array( __CLASS__, 'admin_column_content' ), 10, 2 );
	}

	public static function register_post_type() {
		$labels = array(
			'name'               => __( 'Classes', 'creative-hub' ),
			'singular_name'      => __( 'Class', 'creative-hub' ),
			'add_new'            => __( 'Add New', 'creative-hub' ),
			'add_new_item'       => __( 'Add New Class', 'creative-hub' ),
			'edit_item'          => __( 'Edit Class', 'creative-hub' ),
			'new_item'           => __( 'New Class', 'creative-hub' ),
			'view_item'          => __( 'View Class', 'creative-hub' ),
			'view_items'         => __( 'View Classes', 'creative-hub' ),
			'search_items'       => __( 'Search Classes', 'creative-hub' ),
			'not_found'          => __( 'No classes found.', 'creative-hub' ),
			'not_found_in_trash' => __( 'No classes found in Trash.', 'creative-hub' ),
			'all_items'          => __( 'All Classes', 'creative-hub' ),
			'menu_name'          => __( 'Classes', 'creative-hub' ),
		);

		register_post_type(
			'ch_class',
			array(
				'labels'             => $labels,
				'public'             => true,
				'show_in_rest'       => true, // Required for Gutenberg + Elementor editing.
				'menu_icon'          => 'dashicons-welcome-learn-more',
				'menu_position'      => 26,
				'has_archive'        => 'classes',
				'rewrite'            => array(
					'slug'       => 'classes',
					'with_front' => false,
				),
				'hierarchical'       => false,
				'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'author', 'custom-fields', 'revisions', 'page-attributes' ),
				'capability_type'    => 'post',
			)
		);
	}

	public static function register_taxonomy() {
		$labels = array(
			'name'          => __( 'Class Types', 'creative-hub' ),
			'singular_name' => __( 'Class Type', 'creative-hub' ),
			'all_items'     => __( 'All Class Types', 'creative-hub' ),
			'edit_item'     => __( 'Edit Class Type', 'creative-hub' ),
			'view_item'     => __( 'View Class Type', 'creative-hub' ),
			'add_new_item'  => __( 'Add New Class Type', 'creative-hub' ),
			'new_item_name' => __( 'New Class Type Name', 'creative-hub' ),
			'menu_name'     => __( 'Class Types', 'creative-hub' ),
		);

		register_taxonomy(
			'ch_class_type',
			'ch_class',
			array(
				'labels'            => $labels,
				'public'            => true,
				'hierarchical'      => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => array( 'slug' => 'class-type' ),
			)
		);
	}

	/* ---------------- Admin list table ---------------- */

	public static function admin_columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['ch_products'] = __( 'Access products', 'creative-hub' );
			}
		}
		return $new;
	}

	public static function admin_column_content( $column, $post_id ) {
		if ( 'ch_products' !== $column ) {
			return;
		}

		$ids = ch_get_class_product_ids( $post_id );
		if ( empty( $ids ) ) {
			echo '<span style="color:#b32d2e;">' . esc_html__( 'Not linked — page is locked for everyone', 'creative-hub' ) . '</span>';
			return;
		}

		$names = array();
		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( $product ) {
				$badge   = $product->needs_shipping() ? ' <em>(kit)</em>' : '';
				$names[] = '<a href="' . esc_url( get_edit_post_link( $id ) ) . '">' . esc_html( $product->get_name() ) . '</a>' . $badge;
			}
		}
		echo wp_kses_post( implode( '<br>', $names ) );
	}
}
