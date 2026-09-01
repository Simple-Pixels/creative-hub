<?php
/**
 * The entitlement engine: which products a customer has paid for, cached per user.
 *
 * @package CreativeHub
 */

defined( 'ABSPATH' ) || exit;

class CH_Entitlements {

	const CACHE_TTL = 15 * MINUTE_IN_SECONDS;

	/** @var CH_Entitlements|null */
	protected static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	protected function __construct() {
		// Any order status change can add or remove access — clear that customer's cache.
		add_action( 'woocommerce_order_status_changed', array( $this, 'flush_for_order' ), 10, 4 );
		add_action( 'woocommerce_order_refunded', array( $this, 'flush_for_order_id' ), 10, 1 );
		add_action( 'woocommerce_new_order', array( $this, 'flush_for_order_id' ), 10, 1 );
	}

	/**
	 * Product IDs (parent product IDs) the user has bought in an access-granting status.
	 *
	 * @param int $user_id User ID.
	 * @return int[]
	 */
	public function get_purchased_product_ids( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return array();
		}

		$key    = $this->cache_key( $user_id );
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$order_ids = wc_get_orders(
			array(
				'customer' => $user_id,
				'status'   => ch_get_access_statuses(),
				'limit'    => -1,
				'return'   => 'ids',
			)
		);

		$product_ids = array();
		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				continue;
			}
			foreach ( $order->get_items() as $item ) {
				if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
					continue;
				}
				// get_product_id() returns the parent product ID even for a variation.
				$product_ids[] = (int) $item->get_product_id();
				if ( $item->get_variation_id() ) {
					$product_ids[] = (int) $item->get_variation_id();
				}
			}
		}

		$product_ids = array_values( array_unique( array_filter( $product_ids ) ) );

		set_transient( $key, $product_ids, self::CACHE_TTL );

		return $product_ids;
	}

	protected function cache_key( $user_id ) {
		return 'ch_purchased_' . $user_id;
	}

	public function flush_for_user( $user_id ) {
		delete_transient( $this->cache_key( absint( $user_id ) ) );
	}

	public function flush_for_order( $order_id, $from = '', $to = '', $order = null ) {
		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order_id );
		}
		if ( $order && $order->get_customer_id() ) {
			$this->flush_for_user( $order->get_customer_id() );
		}
	}

	public function flush_for_order_id( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order && $order->get_customer_id() ) {
			$this->flush_for_user( $order->get_customer_id() );
		}
	}
}
