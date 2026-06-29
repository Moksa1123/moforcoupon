<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\Cashback;

use MoksaWeb\Moforcoupon\Coupon\Meta\Keys;
use MoksaWeb\Moforcoupon\Support\OrderOnce;

defined( 'ABSPATH' ) || exit;

/**
 * Grants a cashback coupon's reward once its order is paid. The reward maths is pure (and
 * unit-tested); award() runs it per cashback coupon on the order, fires the integrator hook,
 * and stamps the order so the reward is never granted twice (both processing + completed fire).
 */
final class Runtime {

	private const ORDER_META = '_moforcoupon_cashback_awarded';

	private const REVERSED_META = '_moforcoupon_cashback_reversed';

	public static function boot(): void {
		add_action( 'woocommerce_order_status_completed', array( self::class, 'award' ) );
		add_action( 'woocommerce_order_status_processing', array( self::class, 'award' ) );
		add_action( 'woocommerce_order_refunded', array( self::class, 'reverse' ), 10, 2 );
	}

	/**
	 * On refund, reverse the cashback proportionally to the amount refunded (idempotent via a
	 * running "reversed so far" total). Fires moforcoupon_cashback_reversed so the wallet / points
	 * integrator can claw the reward back.
	 *
	 * @param mixed $order_id
	 * @param mixed $refund_id
	 */
	public static function reverse( $order_id, $refund_id = 0 ): void {
		$order = OrderOnce::get( $order_id );
		if ( null === $order ) {
			return;
		}
		$awarded = (float) $order->get_meta( self::ORDER_META );
		$total   = (float) $order->get_total();
		if ( $awarded <= 0.0 || $total <= 0.0 ) {
			return;
		}
		$ratio   = min( 1.0, (float) $order->get_total_refunded() / $total );
		$target  = round( $awarded * $ratio, 2 );
		$already = (float) $order->get_meta( self::REVERSED_META );
		$delta   = round( $target - $already, 2 );
		if ( $delta <= 0.0 ) {
			return;
		}

		/**
		 * Fires when previously-awarded cashback must be clawed back (order refunded).
		 *
		 * @param float     $delta       Amount to reverse now (store currency).
		 * @param \WC_Order $order       The refunded order.
		 * @param int       $customer_id Customer user id (0 for guest).
		 */
		do_action( 'moforcoupon_cashback_reversed', $delta, $order, (int) $order->get_customer_id() );

		$order->update_meta_data( self::REVERSED_META, wc_format_decimal( (string) $target ) );
		$order->save();
	}

	/**
	 * Pure: reward amount for a mode — percent of the order base, or a fixed amount.
	 */
	public static function compute_reward( string $mode, float $value, float $base ): float {
		if ( $value <= 0.0 ) {
			return 0.0;
		}
		if ( 'percent' === $mode ) {
			return max( 0.0, round( $base * $value / 100.0, 2 ) );
		}
		return max( 0.0, round( $value, 2 ) ); // fixed amount.
	}

	/**
	 * @param mixed $order_id
	 */
	public static function award( $order_id ): void {
		$order = OrderOnce::get( $order_id );
		if ( null === $order || OrderOnce::done( $order, self::ORDER_META ) ) {
			return; // not an order, or already awarded.
		}

		$total   = 0.0;
		$details = array();
		foreach ( $order->get_coupon_codes() as $code ) {
			$coupon = new \WC_Coupon( $code );
			if ( ! $coupon->is_type( Type::TYPE ) ) {
				continue;
			}
			$mode   = (string) $coupon->get_meta( Keys::CASHBACK_MODE, true );
			$mode   = 'fixed' === $mode ? 'fixed' : 'percent';
			$value  = (float) $coupon->get_meta( Keys::CASHBACK_VALUE, true );
			$reward = self::compute_reward( $mode, $value, (float) $order->get_total() );
			if ( $reward <= 0.0 ) {
				continue;
			}
			$total    += $reward;
			$details[] = array(
				'code'   => (string) $code,
				'mode'   => $mode,
				'value'  => $value,
				'reward' => $reward,
			);
		}
		if ( $total <= 0.0 ) {
			return;
		}

		/**
		 * Fires when a cashback coupon's reward should be credited to the customer. Integrators
		 * (store credit / points / loyalty) hook this to add the balance to the customer.
		 *
		 * @param float                        $total       Total reward for the order (store currency).
		 * @param \WC_Order                    $order       The paid order.
		 * @param int                          $customer_id Customer user id (0 for guest).
		 * @param array<int,array<string,mixed>> $details   Per-coupon breakdown.
		 */
		do_action( 'moforcoupon_cashback_awarded', $total, $order, (int) $order->get_customer_id(), $details );

		$order->add_order_note(
			sprintf(
				/* translators: %s: cashback amount. */
				__( '已回饋 %s(透過 moforcoupon_cashback_awarded 串接的錢包 / 點數系統)。', 'moforcoupon' ),
				html_entity_decode( wp_strip_all_tags( wc_price( $total ) ), ENT_QUOTES, 'UTF-8' )
			)
		);
		OrderOnce::mark( $order, self::ORDER_META, wc_format_decimal( (string) $total ) );
	}
}
