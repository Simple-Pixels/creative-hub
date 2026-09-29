<?php
/**
 * The link between a Class and the WooCommerce product(s) that unlock it.
 *
 * Only products in the Studio Sessions collection unlock classes; anything
 * else (curated kits, kit classes, …) is sold as an ordinary product.
 *
 * - Meta box on the Class editor to pick "Access products".
 * - Read-only panel on the product editor showing which classes it unlocks.
 *
 * @package CreativeHub
 */

defined( 'ABSPATH' ) || exit;

class CH_Product_Link {

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post_ch_class', array( __CLASS__, 'save_class' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );

		// Keep the reverse index fresh.
		add_action( 'deleted_post', array( __CLASS__, 'on_delete' ) );
		add_action( 'trashed_post', array( __CLASS__, 'on_delete' ) );
		add_action( 'untrashed_post', array( __CLASS__, 'on_delete' ) );

		// A product gaining or losing the Studio Sessions term changes what it unlocks.
		add_action( 'set_object_terms', array( __CLASS__, 'on_product_terms' ), 10, 4 );
	}

	public static function enqueue( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}
		if ( 'ch_class' === $screen->post_type || 'product' === $screen->post_type ) {
			wp_enqueue_script( 'wc-enhanced-select' );
			wp_enqueue_style( 'woocommerce_admin_styles' );
		}
	}

	public static function add_meta_boxes() {
		add_meta_box(
			'ch_access_products',
			__( 'Access — linked products', 'creative-hub' ),
			array( __CLASS__, 'render_class_box' ),
			'ch_class',
			'side',
			'high'
		);

		add_meta_box(
			'ch_unlocks_classes',
			__( 'Creative Hub — classes this product unlocks', 'creative-hub' ),
			array( __CLASS__, 'render_product_box' ),
			'product',
			'side',
			'default'
		);
	}

	public static function render_class_box( $post ) {
		wp_nonce_field( 'ch_save_class', 'ch_class_nonce' );

		$ids = ch_get_linked_product_ids( $post->ID );
		?>
		<p><?php esc_html_e( 'Customers who buy any of these products get access to this class. Only products in the Studio Sessions collection unlock classes.', 'creative-hub' ); ?></p>
		<select
			class="wc-product-search"
			multiple="multiple"
			style="width:100%;"
			id="ch_access_product_ids"
			name="ch_access_product_ids[]"
			data-placeholder="<?php esc_attr_e( 'Search for a product&hellip;', 'creative-hub' ); ?>"
			data-action="woocommerce_json_search_products_and_variations">
			<?php
			foreach ( $ids as $id ) {
				$product = wc_get_product( $id );
				if ( $product ) {
					printf(
						'<option value="%s" selected="selected">%s</option>',
						esc_attr( $id ),
						esc_html( wp_strip_all_tags( $product->get_formatted_name() ) )
					);
				}
			}
			?>
		</select>

		<?php if ( ! empty( $ids ) ) : ?>
			<p style="margin-top:10px;"><strong><?php esc_html_e( 'Studio Sessions check', 'creative-hub' ); ?></strong></p>
			<ul style="margin:0;list-style:disc;padding-left:18px;">
				<?php
				foreach ( $ids as $id ) {
					$product = wc_get_product( $id );
					if ( ! $product ) {
						continue;
					}
					if ( ch_is_studio_session_product( $id ) ) {
						printf( '<li>%s — <em>%s</em></li>', esc_html( $product->get_name() ), esc_html__( 'unlocks this class', 'creative-hub' ) );
					} else {
						printf(
							'<li>%s — <em style="color:#b32d2e;">%s</em></li>',
							esc_html( $product->get_name() ),
							esc_html__( 'not in Studio Sessions, won\'t unlock', 'creative-hub' )
						);
					}
				}
				?>
			</ul>
		<?php endif; ?>
		<?php
	}

	public static function render_product_box( $post ) {
		if ( ! ch_is_studio_session_product( $post->ID ) ) {
			echo '<p>' . esc_html__( 'Only products in the Studio Sessions collection can unlock classes. This product is sold as a normal product.', 'creative-hub' ) . '</p>';
			return;
		}

		$class_ids = ch_get_classes_for_product( $post->ID );

		if ( empty( $class_ids ) ) {
			echo '<p>' . esc_html__( 'This product does not unlock any classes yet. Link it from a Class under "Access — linked products".', 'creative-hub' ) . '</p>';
			return;
		}

		echo '<ul style="margin:0;list-style:disc;padding-left:18px;">';
		foreach ( $class_ids as $class_id ) {
			printf(
				'<li><a href="%s">%s</a></li>',
				esc_url( get_edit_post_link( $class_id ) ),
				esc_html( get_the_title( $class_id ) )
			);
		}
		echo '</ul>';
	}

	public static function save_class( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST['ch_class_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ch_class_nonce'] ) ), 'ch_save_class' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$raw = isset( $_POST['ch_access_product_ids'] ) ? (array) wp_unslash( $_POST['ch_access_product_ids'] ) : array();
		ch_set_class_product_ids( $post_id, $raw );
	}

	public static function on_delete( $post_id ) {
		if ( 'ch_class' === get_post_type( $post_id ) ) {
			ch_rebuild_product_class_index();
		}
	}

	public static function on_product_terms( $object_id, $terms, $tt_ids, $taxonomy ) {
		if ( 'product' === get_post_type( $object_id ) && in_array( $taxonomy, ch_get_studio_session_taxonomies(), true ) ) {
			ch_rebuild_product_class_index();
		}
	}
}
