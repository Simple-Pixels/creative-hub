<?php
/**
 * [ch_gallery] — coverflow image gallery.
 *
 * The featured image is the main slide; the product's gallery images follow,
 * with thumbnails underneath. Products in the Curated Kits collection get the
 * curated-kit image (Classes → Settings → Curated kits) appended at the end.
 *
 * Works on a product, or on a Class (using the class's featured image and its
 * linked product's gallery). Pass product_id="123" to point at a specific product.
 *
 * @package CreativeHub
 */

defined( 'ABSPATH' ) || exit;

class CH_Gallery {

	/** @var int */
	protected static $instances = 0;

	public static function init() {
		add_shortcode( 'ch_gallery', array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		wp_register_style( 'creative-hub-gallery', CH_URL . 'assets/css/creative-hub-gallery.css', array(), CH_VERSION );
		wp_register_script( 'creative-hub-gallery', CH_URL . 'assets/js/creative-hub-gallery.js', array(), CH_VERSION, true );
	}

	/**
	 * Work out which post supplies the main image and which product supplies the
	 * rest of the gallery.
	 *
	 * @param int $product_id Explicit product ID from the shortcode, or 0.
	 * @return array{0:int,1:WC_Product|null} Main-image post ID and product.
	 */
	protected static function resolve_source( $product_id ) {
		if ( $product_id ) {
			$product = wc_get_product( $product_id );
			return array( $product ? $product->get_id() : 0, $product ? $product : null );
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return array( 0, null );
		}

		$type = get_post_type( $post_id );

		if ( 'product' === $type ) {
			$product = wc_get_product( $post_id );
			return array( $post_id, $product ? $product : null );
		}

		if ( 'ch_class' === $type ) {
			$product = null;
			foreach ( ch_get_linked_product_ids( $post_id ) as $linked_id ) {
				$candidate = wc_get_product( $linked_id );
				if ( $candidate && 'publish' === $candidate->get_status() ) {
					$product = $candidate;
					break;
				}
			}
			return array( $post_id, $product );
		}

		return array( 0, null );
	}

	public static function render( $atts ) {
		$atts = shortcode_atts( array( 'product_id' => 0 ), $atts, 'ch_gallery' );

		list( $main_post_id, $product ) = self::resolve_source( absint( $atts['product_id'] ) );
		if ( ! $main_post_id ) {
			return '';
		}

		// Featured image first, then the product's own image and gallery.
		$attachment_ids = array( (int) get_post_thumbnail_id( $main_post_id ) );
		if ( $product ) {
			$attachment_ids[] = (int) $product->get_image_id();
			$attachment_ids   = array_merge( $attachment_ids, array_map( 'intval', $product->get_gallery_image_ids() ) );
		}
		$attachment_ids = array_values( array_unique( array_filter( $attachment_ids ) ) );

		$alt_fallback = get_the_title( $main_post_id );
		$items        = array();
		foreach ( $attachment_ids as $attachment_id ) {
			$item = ch_image_item( $attachment_id );
			if ( $item ) {
				$items[] = $item;
			}
		}

		// Curated kits always end with the kit image.
		if ( $product && ch_is_curated_kit_product( $product->get_id() ) ) {
			$kit = ch_get_curated_kit_image();
			if ( $kit ) {
				$already = wp_list_pluck( $items, 'src' );
				if ( ! in_array( $kit['src'], $already, true ) ) {
					$items[] = $kit;
				}
			}
		}

		/**
		 * Filter the images shown by [ch_gallery].
		 *
		 * @param array           $items      Image descriptions (src, srcset, thumb, alt, width, height).
		 * @param int             $post_id    Post supplying the main image.
		 * @param WC_Product|null $product    Product supplying the gallery, if any.
		 */
		$items = apply_filters( 'ch_gallery_items', $items, $main_post_id, $product );

		if ( empty( $items ) ) {
			return '';
		}

		if ( ! wp_style_is( 'creative-hub-gallery', 'registered' ) ) {
			self::register();
		}
		wp_enqueue_style( 'creative-hub-gallery' );

		$count = count( $items );
		$multi = $count > 1;
		++self::$instances;
		$uid = 'ch-gallery-' . self::$instances;

		if ( $multi ) {
			wp_enqueue_script( 'creative-hub-gallery' );
		}

		ob_start();
		?>
		<div class="ch-scope ch-gallery<?php echo $multi ? '' : ' ch-gallery--single'; ?>" id="<?php echo esc_attr( $uid ); ?>" data-ch-gallery role="region" aria-roledescription="carousel" aria-label="<?php esc_attr_e( 'Image gallery', 'creative-hub' ); ?>">
			<div class="ch-gallery__stage"<?php echo $multi ? ' tabindex="0"' : ''; ?>>
				<ul class="ch-gallery__track">
					<?php foreach ( $items as $i => $item ) : ?>
						<?php
						$alt = '' !== $item['alt'] ? $item['alt'] : sprintf(
							/* translators: 1: item title, 2: image number */
							__( '%1$s — image %2$d', 'creative-hub' ),
							$alt_fallback,
							$i + 1
						);
						?>
						<li class="ch-gallery__slide<?php echo 0 === $i ? ' is-active' : ''; ?>" data-index="<?php echo esc_attr( $i ); ?>" role="group" aria-roledescription="slide" aria-label="<?php echo esc_attr( sprintf( /* translators: 1: slide number, 2: slide count */ __( '%1$d of %2$d', 'creative-hub' ), $i + 1, $count ) ); ?>">
							<img
								src="<?php echo esc_url( $item['src'] ); ?>"
								<?php if ( ! empty( $item['srcset'] ) ) : ?>
									srcset="<?php echo esc_attr( $item['srcset'] ); ?>"
									sizes="(max-width: 640px) 78vw, 800px"
								<?php endif; ?>
								<?php if ( ! empty( $item['width'] ) && ! empty( $item['height'] ) ) : ?>
									width="<?php echo esc_attr( $item['width'] ); ?>"
									height="<?php echo esc_attr( $item['height'] ); ?>"
								<?php endif; ?>
								alt="<?php echo esc_attr( $alt ); ?>"
								loading="<?php echo $i < 3 ? 'eager' : 'lazy'; ?>"
								decoding="async"
								draggable="false">
						</li>
					<?php endforeach; ?>
				</ul>

				<?php if ( $multi ) : ?>
					<button type="button" class="ch-gallery__nav ch-gallery__nav--prev" aria-label="<?php esc_attr_e( 'Previous image', 'creative-hub' ); ?>">
						<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false"><path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
					</button>
					<button type="button" class="ch-gallery__nav ch-gallery__nav--next" aria-label="<?php esc_attr_e( 'Next image', 'creative-hub' ); ?>">
						<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false"><path d="M9 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
					</button>
				<?php endif; ?>
			</div>

			<?php if ( $multi ) : ?>
				<div class="ch-gallery__thumbs">
					<?php foreach ( $items as $i => $item ) : ?>
						<button type="button" class="ch-gallery__thumb<?php echo 0 === $i ? ' is-active' : ''; ?>" data-index="<?php echo esc_attr( $i ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: image number */ __( 'Show image %d', 'creative-hub' ), $i + 1 ) ); ?>"<?php echo 0 === $i ? ' aria-current="true"' : ''; ?>>
							<img src="<?php echo esc_url( $item['thumb'] ); ?>" alt="" loading="lazy" decoding="async" draggable="false">
						</button>
					<?php endforeach; ?>
				</div>
				<p class="ch-gallery__status" aria-live="polite"></p>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
