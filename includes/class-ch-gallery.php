<?php
/**
 * [ch_gallery] — image gallery: large crossfading square image with thumbnails
 * underneath, hover arrows, a counter, and a lightbox with zoom when the main
 * image is clicked.
 *
 * The featured image is the first slide; the product's gallery images follow.
 * Products in the Curated Kits collection get the curated-kit image (Classes →
 * Settings → Curated kits) appended at the end.
 *
 * Works on a product, or on a Class (using the class's featured image and its
 * linked product's gallery). Pass product_id="123" to point at a specific product.
 * Optional: fit="contain|cover" and ratio="1/1" (width/height).
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
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_early' ), 20 );
	}

	/**
	 * The shortcode usually sits in an Elementor template, which can't be seen
	 * from here. Load the (small) stylesheet in the head on the pages it is used
	 * on, so the gallery never renders unstyled while the page loads.
	 */
	public static function enqueue_early() {
		/**
		 * Filter whether the gallery stylesheet loads in the head on products and classes.
		 *
		 * @param bool $load Default true.
		 */
		if ( is_singular( array( 'product', 'ch_class' ) ) && apply_filters( 'ch_gallery_early_enqueue', true ) ) {
			wp_enqueue_style( 'creative-hub-gallery' );
		}
	}

	public static function register() {
		wp_register_style( 'creative-hub-gallery', CH_URL . 'assets/css/creative-hub-gallery.css', array(), CH_VERSION );
		wp_register_script( 'creative-hub-gallery', CH_URL . 'assets/js/creative-hub-gallery.js', array(), CH_VERSION, true );
	}

	/**
	 * Text the lightbox script needs, so it can be translated.
	 *
	 * @return array<string,string>
	 */
	protected static function labels() {
		return array(
			'open'    => __( 'View image full screen', 'creative-hub' ),
			'viewer'  => __( 'Image viewer', 'creative-hub' ),
			'close'   => __( 'Close', 'creative-hub' ),
			'prev'    => __( 'Previous image', 'creative-hub' ),
			'next'    => __( 'Next image', 'creative-hub' ),
			'zoomIn'  => __( 'Zoom in', 'creative-hub' ),
			'zoomOut' => __( 'Zoom out', 'creative-hub' ),
		);
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
		$atts = shortcode_atts(
			array(
				'product_id' => 0,
				'fit'        => 'contain',
				'ratio'      => '1/1',
			),
			$atts,
			'ch_gallery'
		);

		list( $main_post_id, $product ) = self::resolve_source( absint( $atts['product_id'] ) );
		if ( ! $main_post_id ) {
			return '';
		}

		$fit   = 'cover' === strtolower( (string) $atts['fit'] ) ? 'cover' : 'contain';
		$ratio = preg_match( '#^\d+(\.\d+)?\s*/\s*\d+(\.\d+)?$#', trim( (string) $atts['ratio'] ) ) ? trim( (string) $atts['ratio'] ) : '1/1';

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
		 * @param array           $items   Image descriptions (src, srcset, thumb, alt, width, height).
		 * @param int             $post_id Post supplying the main image.
		 * @param WC_Product|null $product Product supplying the gallery, if any.
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

		// Needed even for a single image: it powers the lightbox.
		wp_enqueue_script( 'creative-hub-gallery' );

		ob_start();
		?>
		<div
			id="<?php echo esc_attr( 'ch-gallery-' . self::$instances ); ?>"
			class="ch-scope ch-gallery<?php echo $multi ? '' : ' is-single'; ?>"
			style="--ch-gal-ratio: <?php echo esc_attr( $ratio ); ?>; --ch-gal-fit: <?php echo esc_attr( $fit ); ?>;"
			data-ch-gallery
			data-ch-labels="<?php echo esc_attr( wp_json_encode( self::labels() ) ); ?>"
			role="region"
			aria-roledescription="carousel"
			aria-label="<?php echo esc_attr( sprintf( /* translators: %s: item title */ __( '%s images', 'creative-hub' ), $alt_fallback ) ); ?>">

			<div class="ch-gallery__stage">
				<?php foreach ( $items as $i => $item ) : ?>
					<?php
					$alt = '' !== $item['alt'] ? $item['alt'] : $alt_fallback;
					?>
					<figure class="ch-gallery__slide<?php echo 0 === $i ? ' is-active' : ''; ?>"
						role="group" aria-roledescription="slide"
						aria-label="<?php echo esc_attr( sprintf( /* translators: 1: slide number, 2: slide count */ __( '%1$d of %2$d', 'creative-hub' ), $i + 1, $count ) ); ?>"
						<?php echo 0 === $i ? '' : 'aria-hidden="true"'; ?>>
						<img
							class="ch-gallery__img"
							src="<?php echo esc_url( $item['src'] ); ?>"
							data-full="<?php echo esc_url( ! empty( $item['full'] ) ? $item['full'] : $item['src'] ); ?>"
							<?php if ( ! empty( $item['srcset'] ) ) : ?>
								srcset="<?php echo esc_attr( $item['srcset'] ); ?>"
								sizes="(max-width: 767px) 100vw, 800px"
							<?php endif; ?>
							<?php if ( ! empty( $item['width'] ) && ! empty( $item['height'] ) ) : ?>
								width="<?php echo esc_attr( $item['width'] ); ?>"
								height="<?php echo esc_attr( $item['height'] ); ?>"
							<?php endif; ?>
							alt="<?php echo esc_attr( $alt ); ?>"
							loading="<?php echo 0 === $i ? 'eager' : 'lazy'; ?>"
							decoding="async">
					</figure>
				<?php endforeach; ?>

				<?php if ( $multi ) : ?>
					<button type="button" class="ch-gallery__nav ch-gallery__nav--prev" data-ch-gal-prev aria-label="<?php esc_attr_e( 'Previous image', 'creative-hub' ); ?>">
						<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false"><path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
					</button>
					<button type="button" class="ch-gallery__nav ch-gallery__nav--next" data-ch-gal-next aria-label="<?php esc_attr_e( 'Next image', 'creative-hub' ); ?>">
						<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false"><path d="M9 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
					</button>
					<span class="ch-gallery__count" aria-live="polite"><span data-ch-gal-current>1</span> / <?php echo esc_html( $count ); ?></span>
				<?php endif; ?>
			</div>

			<?php if ( $multi ) : ?>
				<div class="ch-gallery__thumbs" role="tablist" aria-label="<?php esc_attr_e( 'Choose image', 'creative-hub' ); ?>">
					<?php foreach ( $items as $i => $item ) : ?>
						<button type="button"
							class="ch-gallery__thumb<?php echo 0 === $i ? ' is-active' : ''; ?>"
							role="tab"
							aria-selected="<?php echo 0 === $i ? 'true' : 'false'; ?>"
							aria-label="<?php echo esc_attr( sprintf( /* translators: %d: image number */ __( 'Show image %d', 'creative-hub' ), $i + 1 ) ); ?>"
							data-index="<?php echo esc_attr( $i ); ?>">
							<img class="ch-gallery__thumb-img" src="<?php echo esc_url( $item['thumb'] ); ?>" alt="" loading="lazy" decoding="async">
						</button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
