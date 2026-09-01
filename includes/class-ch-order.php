<?php
/**
 * Order confirmation ("thank you") screen: point the customer at the class(es)
 * they just bought.
 *
 * @package CreativeHub
 */

defined( 'ABSPATH' ) || exit;

class CH_Order {

	public static function init() {
		add_action( 'woocommerce_thankyou', array( __CLASS__, 'thankyou' ), 15 );
	}

	/**
	 * Class IDs unlocked by the products in an order.
	 *
	 * @param WC_Order $order Order object.
	 * @return int[]
	 */
	protected static function class_ids_for_order( $order ) {
		$class_ids = array();

		foreach ( $order->get_items() as $item ) {
			if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
				continue;
			}
			foreach ( ch_get_classes_for_product( $item->get_product_id() ) as $class_id ) {
				if ( 'publish' === get_post_status( $class_id ) ) {
					$class_ids[] = (int) $class_id;
				}
			}
		}

		return array_values( array_unique( $class_ids ) );
	}

	public static function thankyou( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$class_ids = self::class_ids_for_order( $order );
		if ( empty( $class_ids ) ) {
			return;
		}

		CH_Assets::enqueue_front();

		$unlocked = $order->has_status( ch_get_access_statuses() );
		$is_guest = 0 === (int) $order->get_customer_id();
		$hub_url  = ch_get_hub_url();
		$many     = count( $class_ids ) > 1;
		$noun     = $many ? __( 'classes', 'creative-hub' ) : __( 'class', 'creative-hub' );

		// Guests have no account for access to attach to. Point them at registration
		// with the same email — WooCommerce links their past orders on sign-up.
		if ( $is_guest ) {
			$account_url = wc_get_page_permalink( 'myaccount' );
			if ( ! $account_url ) {
				$account_url = home_url( '/my-account/' );
			}
			?>
			<section class="ch-scope ch-order-classes">
				<h2 class="ch-order-classes__heading">
					<?php echo esc_html( $many ? __( 'One step to open your classes', 'creative-hub' ) : __( 'One step to open your class', 'creative-hub' ) ); ?>
				</h2>
				<p>
					<?php
					printf(
						/* translators: 1: "class" or "classes", 2: customer email address */
						esc_html__( 'Create an account with %2$s to open your %1$s and keep it in your Creative Hub.', 'creative-hub' ),
						esc_html( $noun ),
						esc_html( $order->get_billing_email() )
					);
					?>
				</p>
				<ul class="ch-order-classes__list">
					<?php foreach ( $class_ids as $class_id ) : ?>
						<li><?php echo esc_html( get_the_title( $class_id ) ); ?></li>
					<?php endforeach; ?>
				</ul>
				<p class="ch-order-classes__cta">
					<a class="ch-btn" href="<?php echo esc_url( $account_url ); ?>">
						<?php esc_html_e( 'Create my account', 'creative-hub' ); ?>
					</a>
				</p>
			</section>
			<?php
			return;
		}
		?>
		<section class="ch-scope ch-order-classes">
			<h2 class="ch-order-classes__heading">
				<?php
				echo $unlocked
					? esc_html( $many ? __( 'Your classes are ready', 'creative-hub' ) : __( 'Your class is ready', 'creative-hub' ) )
					: esc_html( $many ? __( 'Your classes are on the way', 'creative-hub' ) : __( 'Your class is on the way', 'creative-hub' ) );
				?>
			</h2>

			<?php if ( $unlocked ) : ?>
				<p>
					<?php
					/* translators: %s: "class" or "classes" */
					printf( esc_html__( 'You can start straight away. Your %s is also saved in your Creative Hub, so you can always come back to it.', 'creative-hub' ), esc_html( $noun ) );
					?>
				</p>
				<ul class="ch-order-classes__list">
					<?php foreach ( $class_ids as $class_id ) : ?>
						<li>
							<a class="ch-link" href="<?php echo esc_url( get_permalink( $class_id ) ); ?>">
								<?php echo esc_html( get_the_title( $class_id ) ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p>
					<?php
					/* translators: %s: "class" or "classes" */
					printf( esc_html__( 'As soon as your payment is confirmed, your %s unlocks in your Creative Hub:', 'creative-hub' ), esc_html( $noun ) );
					?>
				</p>
				<ul class="ch-order-classes__list">
					<?php foreach ( $class_ids as $class_id ) : ?>
						<li><?php echo esc_html( get_the_title( $class_id ) ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<p class="ch-order-classes__cta">
				<a class="ch-btn" href="<?php echo esc_url( $hub_url ); ?>">
					<?php esc_html_e( 'Go to my Creative Hub', 'creative-hub' ); ?>
				</a>
			</p>
		</section>
		<?php
	}
}
