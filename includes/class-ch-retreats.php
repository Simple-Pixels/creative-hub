<?php
/**
 * Retreat bookings for the hub.
 *
 * A retreat is an ordinary product in the `collection` → `retreats` term. Each
 * one a customer has bought becomes a booking card showing payment progress and
 * a "Pay balance" link, read from "Deposits & Partial Payments for WooCommerce
 * Pro" (Acowebs) when that plugin is in use. Everything the cards need beyond
 * plain WooCommerce is optional, so they degrade to name + status when the
 * plugin's data isn't there.
 *
 * @package CreativeHub
 */

defined( 'ABSPATH' ) || exit;

class CH_Retreats {

	/**
	 * Order statuses whose retreats appear as bookings. A deposit-paid booking
	 * sits in "Partially Paid", which is not an access-granting status.
	 *
	 * @return string[] Slugs without the wc- prefix.
	 */
	public static function statuses() {
		$statuses = array_merge( ch_get_access_statuses(), array( 'partially-paid', 'on-hold' ) );

		/**
		 * Filter which order statuses count as a retreat booking.
		 *
		 * @param string[] $statuses Status slugs without the wc- prefix.
		 */
		$statuses = (array) apply_filters( 'ch_retreat_order_statuses', array_unique( $statuses ) );

		$registered = array_map(
			function ( $slug ) {
				return preg_replace( '/^wc-/', '', $slug );
			},
			array_keys( wc_get_order_statuses() )
		);

		return array_values( array_intersect( $statuses, $registered ) );
	}

	protected static function is_retreat_product( $product_id ) {
		$taxonomy = apply_filters( 'ch_retreat_taxonomy', 'collection' );
		$term     = apply_filters( 'ch_retreat_term', 'retreats' );

		return taxonomy_exists( $taxonomy ) && has_term( $term, $taxonomy, $product_id );
	}

	/**
	 * One entry per retreat line on each of the customer's orders, newest first.
	 *
	 * @param int $user_id User ID.
	 * @return array[]
	 */
	public static function get_bookings( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return array();
		}

		$orders = wc_get_orders(
			array(
				'customer' => $user_id,
				'status'   => self::statuses(),
				'limit'    => -1,
				'orderby'  => 'date',
				'order'    => 'DESC',
			)
		);

		$bookings = array();
		foreach ( $orders as $order ) {
			foreach ( $order->get_items() as $item ) {
				if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
					continue;
				}
				$product_id = (int) $item->get_product_id();
				if ( ! $product_id || ! self::is_retreat_product( $product_id ) ) {
					continue;
				}
				$product = wc_get_product( $product_id );
				if ( ! $product ) {
					continue;
				}

				$booking = array(
					'product' => $product,
					'order'   => $order,
					'payment' => self::payment_summary( $order ),
				);

				/**
				 * Filter a retreat booking before it is shown.
				 *
				 * @param array    $booking Keys: product, order, payment.
				 * @param WC_Order $order   The order.
				 */
				$bookings[] = apply_filters( 'ch_retreat_booking', $booking, $order );
			}
		}

		return $bookings;
	}

	/**
	 * Instalment payments the deposits plugin stores as child orders.
	 *
	 * @param WC_Order $order Parent order.
	 * @return WC_Order[]
	 */
	protected static function payment_orders( $order ) {
		$type = defined( 'AWCDP_POST_TYPE' ) ? AWCDP_POST_TYPE : 'awcdp_payment';

		try {
			$children = wc_get_orders(
				array(
					'type'   => $type,
					'parent' => $order->get_id(),
					'limit'  => -1,
				)
			);
		} catch ( \Throwable $e ) {
			return array();
		}

		return array_values(
			array_filter(
				(array) $children,
				function ( $child ) {
					return $child instanceof WC_Order;
				}
			)
		);
	}

	protected static function parse_time( $raw ) {
		if ( is_numeric( $raw ) ) {
			return (int) $raw;
		}
		if ( is_string( $raw ) && '' !== $raw ) {
			$time = strtotime( $raw );
			return $time ? $time : null;
		}
		return null;
	}

	/**
	 * Where a booking stands on payments.
	 *
	 * @param WC_Order $order Booking order.
	 * @return array{state:string,label:string,paid:?float,balance:?float,next_due:?int,pay_url:string,view_url:string}
	 */
	public static function payment_summary( $order ) {
		$status      = $order->get_status();
		$has_deposit = 'yes' === $order->get_meta( '_awcdp_deposits_order_has_deposit' );
		$deposit_amt = (float) $order->get_meta( '_awcdp_deposits_deposit_amount' );
		$second_amt  = (float) $order->get_meta( '_awcdp_deposits_second_payment' );
		$deposit_ok  = 'yes' === $order->get_meta( '_awcdp_deposits_deposit_paid' );
		$second_ok   = 'yes' === $order->get_meta( '_awcdp_deposits_second_payment_paid' );

		$balance  = null;
		$next_due = null;

		// Instalments stored as separate payment orders: add up what's still owed.
		$owed = 0.0;
		$any  = false;
		foreach ( self::payment_orders( $order ) as $child ) {
			if ( $child->has_status( array( 'cancelled', 'refunded' ) ) || $child->is_paid() ) {
				continue;
			}
			$any   = true;
			$owed += (float) $child->get_total();
			$due   = self::parse_time( $child->get_meta( '_awcdp_deposits_partial_payment_date' ) );
			if ( $due && ( null === $next_due || $due < $next_due ) ) {
				$next_due = $due;
			}
		}

		if ( $any ) {
			$balance_due = true;
			$balance     = $owed;
		} elseif ( $has_deposit ) {
			$balance_due = ! $second_ok && ( $deposit_ok || $order->is_paid() || 'partially-paid' === $status );
			if ( $balance_due && $second_amt > 0 ) {
				$balance = $second_amt;
			}
		} else {
			$balance_due = 'partially-paid' === $status;
		}

		$paid = null;
		if ( $balance_due && $has_deposit && $deposit_amt > 0 && $deposit_ok ) {
			$paid = $deposit_amt;
		} elseif ( $balance_due && null !== $balance ) {
			$total = (float) $order->get_total();
			if ( $total > $balance ) {
				$paid = $total - $balance;
			}
		}

		if ( $balance_due ) {
			$state = 'balance';
			$label = __( 'Balance due', 'creative-hub' );
		} elseif ( $order->is_paid() ) {
			$state = 'paid';
			$label = __( 'Paid in full', 'creative-hub' );
		} else {
			$state = 'awaiting';
			$label = __( 'Awaiting payment', 'creative-hub' );
		}

		$summary = array(
			'state'    => $state,
			'label'    => $label,
			'paid'     => $paid,
			'balance'  => $balance,
			'next_due' => $next_due,
			// The deposits plugin filters this URL to the first unpaid instalment.
			'pay_url'  => 'balance' === $state ? $order->get_checkout_payment_url() : '',
			'view_url' => $order->get_view_order_url(),
		);

		/**
		 * Filter a booking's payment summary — the place to adapt to a different
		 * deposits plugin or different meta keys.
		 *
		 * @param array    $summary Payment summary.
		 * @param WC_Order $order   Booking order.
		 */
		return apply_filters( 'ch_retreat_payment_summary', $summary, $order );
	}

	/**
	 * Markup for one booking card.
	 *
	 * @param array $booking Entry from get_bookings().
	 * @return string
	 */
	public static function render_card( $booking ) {
		/** @var WC_Product $product */
		$product = $booking['product'];
		/** @var WC_Order $order */
		$order   = $booking['order'];
		$pay     = $booking['payment'];
		$linked  = 'publish' === $product->get_status();
		$url     = $linked ? get_permalink( $product->get_id() ) : '';
		$price   = array( 'currency' => $order->get_currency() );

		ob_start();
		?>
		<article class="ch-card ch-card--retreat ch-card--<?php echo esc_attr( $pay['state'] ); ?>">
			<?php if ( $url ) : ?>
				<a class="ch-card__media" href="<?php echo esc_url( $url ); ?>">
					<?php echo wp_kses_post( $product->get_image( 'medium_large' ) ); ?>
				</a>
			<?php else : ?>
				<div class="ch-card__media">
					<?php echo wp_kses_post( $product->get_image( 'medium_large' ) ); ?>
				</div>
			<?php endif; ?>

			<div class="ch-card__body">
				<p class="ch-pill ch-pill--<?php echo esc_attr( $pay['state'] ); ?>"><?php echo esc_html( $pay['label'] ); ?></p>

				<h4 class="ch-card__title">
					<?php if ( $url ) : ?>
						<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $product->get_name() ); ?></a>
					<?php else : ?>
						<?php echo esc_html( $product->get_name() ); ?>
					<?php endif; ?>
				</h4>

				<p class="ch-card__meta">
					<?php
					/* translators: %s: order number */
					echo esc_html( sprintf( __( 'Booking #%s', 'creative-hub' ), $order->get_order_number() ) );
					?>
				</p>

				<?php if ( null !== $pay['paid'] || null !== $pay['balance'] || $pay['next_due'] ) : ?>
					<dl class="ch-pay">
						<?php if ( null !== $pay['paid'] ) : ?>
							<div class="ch-pay__row">
								<dt><?php esc_html_e( 'Paid so far', 'creative-hub' ); ?></dt>
								<dd><?php echo wp_kses_post( wc_price( $pay['paid'], $price ) ); ?></dd>
							</div>
						<?php endif; ?>
						<?php if ( null !== $pay['balance'] ) : ?>
							<div class="ch-pay__row">
								<dt><?php esc_html_e( 'Balance', 'creative-hub' ); ?></dt>
								<dd><?php echo wp_kses_post( wc_price( $pay['balance'], $price ) ); ?></dd>
							</div>
						<?php endif; ?>
						<?php if ( $pay['next_due'] ) : ?>
							<div class="ch-pay__row">
								<dt><?php esc_html_e( 'Next payment due', 'creative-hub' ); ?></dt>
								<dd><?php echo esc_html( date_i18n( get_option( 'date_format' ), $pay['next_due'] ) ); ?></dd>
							</div>
						<?php endif; ?>
					</dl>
				<?php endif; ?>

				<div class="ch-card__actions">
					<?php if ( 'balance' === $pay['state'] && $pay['pay_url'] ) : ?>
						<a class="ch-btn ch-card__cta" href="<?php echo esc_url( $pay['pay_url'] ); ?>"><?php esc_html_e( 'Pay balance', 'creative-hub' ); ?></a>
					<?php elseif ( $url ) : ?>
						<a class="ch-btn ch-card__cta" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'View retreat', 'creative-hub' ); ?></a>
					<?php else : ?>
						<a class="ch-btn ch-card__cta" href="<?php echo esc_url( $pay['view_url'] ); ?>"><?php esc_html_e( 'View booking', 'creative-hub' ); ?></a>
					<?php endif; ?>

					<?php if ( $pay['view_url'] && ( $url || ( 'balance' === $pay['state'] && $pay['pay_url'] ) ) ) : ?>
						<a class="ch-link ch-card__secondary" href="<?php echo esc_url( $pay['view_url'] ); ?>"><?php esc_html_e( 'Booking details', 'creative-hub' ); ?></a>
					<?php endif; ?>
				</div>
			</div>
		</article>
		<?php
		return ob_get_clean();
	}
}
