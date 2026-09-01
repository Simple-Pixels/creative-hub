<?php
/**
 * Front-end shortcodes for the hub and for gating content inside Elementor.
 *
 * [creative_hub]        Full hub: my classes + my retreats + my downloads + link to orders.
 * [ch_my_classes]       Grid of the classes the current user can access.
 * [ch_my_retreats]      Grid of retreat products the current user has bought.
 * [ch_my_downloads]     The customer's downloadable files, grouped by class.
 * [ch_class_products]   Buy options for the current (or a given) class.
 * [ch_access_notice]    "You don't have access" notice + buy links + login link.
 *                       Put this on the Access page. Reads ?ch_class=123 from the URL.
 * [ch_locked] … [/ch_locked]   Show inner content only to entitled users.
 * [ch_teaser] … [/ch_teaser]   Show inner content only to non-entitled users.
 *
 * @package CreativeHub
 */

defined( 'ABSPATH' ) || exit;

class CH_Shortcodes {

	public static function init() {
		add_shortcode( 'creative_hub', array( __CLASS__, 'hub' ) );
		add_shortcode( 'ch_my_classes', array( __CLASS__, 'my_classes' ) );
		add_shortcode( 'ch_my_retreats', array( __CLASS__, 'my_retreats' ) );
		add_shortcode( 'ch_my_downloads', array( __CLASS__, 'my_downloads' ) );
		add_shortcode( 'ch_class_products', array( __CLASS__, 'class_products' ) );
		add_shortcode( 'ch_access_notice', array( __CLASS__, 'access_notice' ) );
		add_shortcode( 'ch_locked', array( __CLASS__, 'locked' ) );
		add_shortcode( 'ch_teaser', array( __CLASS__, 'teaser' ) );
	}

	/* --------------------------------------------------------------------- */

	public static function hub( $atts ) {
		CH_Assets::enqueue_front();

		if ( ! is_user_logged_in() ) {
			return self::login_prompt();
		}

		$user = wp_get_current_user();

		ob_start();
		echo '<div class="ch-scope ch-hub">';
		printf(
			'<h2 class="ch-hub__welcome">%s</h2>',
			esc_html( sprintf( __( 'Welcome back, %s', 'creative-hub' ), $user->display_name ) )
		);
		echo '<p class="ch-hub__intro">' . esc_html__( 'Everything you have bought lives here. No digging through emails.', 'creative-hub' ) . '</p>';

		echo '<section class="ch-hub__section"><h3>' . esc_html__( 'My classes', 'creative-hub' ) . '</h3>';
		echo self::my_classes( array( 'wrap' => 0 ) ); // phpcs:ignore
		echo '</section>';

		echo '<section class="ch-hub__section"><h3>' . esc_html__( 'My retreats', 'creative-hub' ) . '</h3>';
		echo self::my_retreats( array( 'wrap' => 0 ) ); // phpcs:ignore
		echo '</section>';

		echo '<section class="ch-hub__section"><h3>' . esc_html__( 'My downloads', 'creative-hub' ) . '</h3>';
		echo self::my_downloads( array( 'wrap' => 0 ) ); // phpcs:ignore
		echo '</section>';

		printf(
			'<p class="ch-hub__orders"><a class="ch-link" href="%s">%s</a></p>',
			esc_url( wc_get_account_endpoint_url( 'orders' ) ),
			esc_html__( 'View full order history ›', 'creative-hub' )
		);

		echo '</div>';
		return ob_get_clean();
	}

	/* --------------------------------------------------------------------- */

	public static function my_classes( $atts ) {
		CH_Assets::enqueue_front();

		$atts = shortcode_atts( array( 'wrap' => 1 ), $atts, 'ch_my_classes' );

		if ( ! is_user_logged_in() ) {
			return self::login_prompt();
		}

		$class_ids = ch_get_user_class_ids();

		ob_start();
		if ( $atts['wrap'] ) {
			echo '<div class="ch-scope">';
		}

		if ( empty( $class_ids ) ) {
			echo '<p class="ch-empty">' . esc_html__( 'No classes yet. Once you buy one it appears here straight away.', 'creative-hub' ) . '</p>';
		} else {
			$query = new WP_Query(
				array(
					'post_type'      => 'ch_class',
					'post__in'       => $class_ids,
					'orderby'        => 'title',
					'order'          => 'ASC',
					'posts_per_page' => -1,
					'no_found_rows'  => true,
				)
			);

			echo '<div class="ch-grid">';
			while ( $query->have_posts() ) {
				$query->the_post();
				$thumb = get_the_post_thumbnail( get_the_ID(), 'medium_large' );
				?>
				<article class="ch-card">
					<a class="ch-card__media" href="<?php the_permalink(); ?>">
						<?php
						echo $thumb // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-generated <img>.
							? $thumb // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							: '<span class="ch-card__placeholder"></span>';
						?>
					</a>
					<div class="ch-card__body">
						<h4 class="ch-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h4>
						<a class="ch-btn ch-card__cta" href="<?php the_permalink(); ?>"><?php esc_html_e( 'Open class', 'creative-hub' ); ?></a>
					</div>
				</article>
				<?php
			}
			echo '</div>';
			wp_reset_postdata();
		}

		if ( $atts['wrap'] ) {
			echo '</div>';
		}
		return ob_get_clean();
	}

	/* --------------------------------------------------------------------- */

	public static function my_retreats( $atts ) {
		CH_Assets::enqueue_front();

		$atts = shortcode_atts( array( 'wrap' => 1 ), $atts, 'ch_my_retreats' );

		if ( ! is_user_logged_in() ) {
			return self::login_prompt();
		}

		/**
		 * Retreats are ordinary products in a product taxonomy term. Defaults
		 * match the store's "collection" taxonomy and its "retreats" term.
		 */
		$taxonomy = apply_filters( 'ch_retreat_taxonomy', 'collection' );
		$term     = apply_filters( 'ch_retreat_term', 'retreats' );

		$purchased   = CH_Entitlements::instance()->get_purchased_product_ids( get_current_user_id() );
		$retreat_ids = array();

		foreach ( $purchased as $pid ) {
			$product = wc_get_product( $pid );
			if ( ! $product ) {
				continue;
			}
			$parent_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
			if ( isset( $retreat_ids[ $parent_id ] ) ) {
				continue;
			}
			if ( taxonomy_exists( $taxonomy ) && has_term( $term, $taxonomy, $parent_id ) ) {
				$retreat_ids[ $parent_id ] = true;
			}
		}
		$retreat_ids = array_keys( $retreat_ids );

		ob_start();
		if ( $atts['wrap'] ) {
			echo '<div class="ch-scope">';
		}

		if ( empty( $retreat_ids ) ) {
			echo '<p class="ch-empty">' . esc_html__( 'No retreat bookings yet.', 'creative-hub' ) . '</p>';
		} else {
			echo '<div class="ch-grid">';
			foreach ( $retreat_ids as $rid ) {
				$product = wc_get_product( $rid );
				if ( $product && 'publish' === $product->get_status() ) {
					echo self::render_product_card( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
			}
			echo '</div>';
		}

		if ( $atts['wrap'] ) {
			echo '</div>';
		}
		return ob_get_clean();
	}

	protected static function render_product_card( $product ) {
		$permalink = get_permalink( $product->get_id() );
		$image     = $product->get_image( 'medium_large' );

		ob_start();
		?>
		<article class="ch-card">
			<a class="ch-card__media" href="<?php echo esc_url( $permalink ); ?>">
				<?php echo wp_kses_post( $image ); ?>
			</a>
			<div class="ch-card__body">
				<h4 class="ch-card__title">
					<a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $product->get_name() ); ?></a>
				</h4>
				<a class="ch-btn ch-card__cta" href="<?php echo esc_url( $permalink ); ?>">
					<?php esc_html_e( 'View retreat', 'creative-hub' ); ?>
				</a>
			</div>
		</article>
		<?php
		return ob_get_clean();
	}

	/* --------------------------------------------------------------------- */

	public static function my_downloads( $atts ) {
		CH_Assets::enqueue_front();

		$atts = shortcode_atts( array( 'wrap' => 1 ), $atts, 'ch_my_downloads' );

		if ( ! is_user_logged_in() ) {
			return self::login_prompt();
		}

		$downloads = wc_get_customer_available_downloads( get_current_user_id() );

		ob_start();
		if ( $atts['wrap'] ) {
			echo '<div class="ch-scope">';
		}

		if ( empty( $downloads ) ) {
			echo '<p class="ch-empty">' . esc_html__( 'No downloads yet.', 'creative-hub' ) . '</p>';
		} else {
			// Group by product.
			$groups = array();
			foreach ( $downloads as $download ) {
				$groups[ $download['product_id'] ]['name']    = $download['product_name'];
				$groups[ $download['product_id'] ]['files'][] = $download;
			}

			echo '<div class="ch-downloads">';
			foreach ( $groups as $product_id => $group ) {
				$class_ids = ch_get_classes_for_product( $product_id );
				$heading   = $class_ids ? get_the_title( $class_ids[0] ) : $group['name'];
				?>
				<div class="ch-downloads__group">
					<h4><?php echo esc_html( $heading ); ?></h4>
					<ul>
						<?php foreach ( $group['files'] as $file ) : ?>
							<li>
								<a class="ch-link" href="<?php echo esc_url( $file['download_url'] ); ?>">
									<?php echo esc_html( $file['download_name'] ); ?>
								</a>
								<?php if ( ! is_null( $file['downloads_remaining'] ) && '' !== $file['downloads_remaining'] ) : ?>
									<span class="ch-downloads__meta">
										<?php
										/* translators: %s: number of downloads left */
										echo esc_html( sprintf( __( '%s left', 'creative-hub' ), $file['downloads_remaining'] ) );
										?>
									</span>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
				<?php
			}
			echo '</div>';
		}

		if ( $atts['wrap'] ) {
			echo '</div>';
		}
		return ob_get_clean();
	}

	/* --------------------------------------------------------------------- */

	public static function class_products( $atts ) {
		CH_Assets::enqueue_front();

		$atts     = shortcode_atts( array( 'class_id' => get_the_ID() ), $atts, 'ch_class_products' );
		$class_id = absint( $atts['class_id'] );
		$ids      = ch_get_class_product_ids( $class_id );

		if ( empty( $ids ) ) {
			return '';
		}

		$button_text = ch_get_setting( 'buy_button_text', __( 'Get this class', 'creative-hub' ) );

		ob_start();
		echo '<div class="ch-scope ch-buy">';
		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product || ! $product->is_purchasable() || 'publish' !== get_post_status( $id ) ) {
				continue;
			}
			$label = $product->needs_shipping()
				? __( 'With posted kit', 'creative-hub' )
				: __( 'Online access', 'creative-hub' );
			?>
			<div class="ch-buy__option">
				<div class="ch-buy__info">
					<p class="ch-buy__label"><?php echo esc_html( $label ); ?></p>
					<p class="ch-buy__price"><?php echo wp_kses_post( $product->get_price_html() ); ?></p>
				</div>
				<a class="ch-btn" href="<?php echo esc_url( $product->add_to_cart_url() ); ?>">
					<?php echo esc_html( $button_text ); ?>
				</a>
			</div>
			<?php
		}
		echo '</div>';
		return ob_get_clean();
	}

	/* --------------------------------------------------------------------- */

	public static function access_notice( $atts ) {
		CH_Assets::enqueue_front();

		$requested = isset( $_GET['ch_class'] ) ? absint( wp_unslash( $_GET['ch_class'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$atts     = shortcode_atts( array( 'class_id' => $requested ), $atts, 'ch_access_notice' );
		$class_id = absint( $atts['class_id'] );

		if ( $class_id && 'ch_class' !== get_post_type( $class_id ) ) {
			$class_id = 0;
		}

		$login_url = wc_get_page_permalink( 'myaccount' );
		if ( ! $login_url ) {
			$login_url = home_url( '/my-account/' );
		}

		ob_start();
		echo '<div class="ch-scope ch-access">';

		// If they now have access (e.g. just logged in), send them straight in.
		if ( $class_id && ch_user_can_access_class( $class_id ) ) {
			printf(
				'<h2 class="ch-access__heading">%s</h2><p><a class="ch-btn" href="%s">%s</a></p>',
				esc_html__( 'You have access to this class.', 'creative-hub' ),
				esc_url( get_permalink( $class_id ) ),
				esc_html__( 'Open the class', 'creative-hub' )
			);
			echo '</div>';
			return ob_get_clean();
		}

		echo '<h2 class="ch-access__heading">' . esc_html__( "It looks like you don't have access", 'creative-hub' ) . '</h2>';

		$products_html = $class_id ? self::class_products( array( 'class_id' => $class_id ) ) : '';

		if ( $products_html ) {
			echo '<p class="ch-access__lead">' . esc_html__( 'To get access, buy this class:', 'creative-hub' ) . '</p>';
			echo $products_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from wc_get_product() output above.
		} elseif ( $class_id ) {
			printf(
				'<p class="ch-access__lead">%s</p>',
				esc_html__( "This class isn't available to buy at the moment.", 'creative-hub' )
			);
		} else {
			printf(
				'<p class="ch-access__lead"><a class="ch-link" href="%s">%s</a></p>',
				esc_url( get_post_type_archive_link( 'ch_class' ) ),
				esc_html__( 'Browse the classes', 'creative-hub' )
			);
		}

		printf(
			'<p class="ch-access__login">%s <a class="ch-link" href="%s">%s</a></p>',
			esc_html__( 'Already bought it?', 'creative-hub' ),
			esc_url( $login_url ),
			esc_html__( 'Log into your account to continue.', 'creative-hub' )
		);

		echo '</div>';
		return ob_get_clean();
	}

	/* --------------------------------------------------------------------- */

	public static function locked( $atts, $content = '' ) {
		$atts     = shortcode_atts( array( 'class_id' => get_the_ID() ), $atts, 'ch_locked' );
		return ch_user_can_access_class( absint( $atts['class_id'] ) ) ? do_shortcode( $content ) : '';
	}

	public static function teaser( $atts, $content = '' ) {
		$atts = shortcode_atts( array( 'class_id' => get_the_ID() ), $atts, 'ch_teaser' );
		return ch_user_can_access_class( absint( $atts['class_id'] ) ) ? '' : do_shortcode( $content );
	}

	/* --------------------------------------------------------------------- */

	protected static function login_prompt() {
		$account_url = wc_get_page_permalink( 'myaccount' );
		if ( ! $account_url ) {
			$account_url = home_url( '/my-account/' );
		}
		return sprintf(
			'<div class="ch-scope"><p class="ch-empty">%s <a class="ch-link" href="%s">%s</a></p></div>',
			esc_html__( 'Please log in to see your Creative Hub.', 'creative-hub' ),
			esc_url( $account_url ),
			esc_html__( 'Log in', 'creative-hub' )
		);
	}
}
