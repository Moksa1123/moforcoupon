<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\StoreCredit;

use MoksaWeb\Moforcoupon\Support\OrderOnce;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps the wallet honest when an order is refunded: returns the store credit that was spent on the
 * order, and (as the cashback integrator) claws back reversed cashback. Both adjust proportionally
 * to the amount refunded and are idempotent via a running total, so partial + repeated refunds are
 * handled correctly.
 */
final class Refund {

	/** Order meta written by Checkout::debit_on_paid (the credit actually spent on this order). */
	private const CREDIT_USED = '_moforcoupon_credit_used';
	/** Running total of credit already returned for this order. */
	private const RECREDITED = '_moforcoupon_credit_recredited';

	public static function register(): void {
		add_action( 'woocommerce_order_refunded', array( self::class, 'on_refund' ), 10, 2 );
		add_action( 'moforcoupon_cashback_reversed', array( self::class, 'on_cashback_reversed' ), 10, 3 );
	}

	/**
	 * @param mixed $order_id
	 * @param mixed $refund_id
	 */
	public static function on_refund( $order_id, $refund_id = 0 ): void {
		$order = OrderOnce::get( $order_id );
		if ( null === $order ) {
			return;
		}
		$user_id = (int) $order->get_customer_id();
		$used    = (float) $order->get_meta( self::CREDIT_USED );
		$total   = (float) $order->get_total();
		if ( $user_id <= 0 || $used <= 0.0 || $total <= 0.0 ) {
			return;
		}
		$ratio   = min( 1.0, (float) $order->get_total_refunded() / $total );
		$target  = round( $used * $ratio, 2 );
		$already = (float) $order->get_meta( self::RECREDITED );
		$delta   = round( $target - $already, 2 );
		if ( $delta <= 0.0 ) {
			return;
		}
		Wallet::credit(
			$user_id,
			$delta,
			sprintf(
				/* translators: %s: order number. */
				__( '訂單 #%s 退款,退回儲值金', 'moforcoupon' ),
				$order->get_order_number()
			)
		);
		$order->update_meta_data( self::RECREDITED, wc_format_decimal( (string) $target ) );
		$order->save();
	}

	/**
	 * Cashback was clawed back (the Cashback runtime fired this) — debit the wallet.
	 *
	 * @param mixed $amount
	 * @param mixed $order
	 * @param mixed $customer_id
	 */
	public static function on_cashback_reversed( $amount, $order, $customer_id ): void {
		$user_id = (int) $customer_id;
		$amount  = (float) $amount;
		if ( $user_id <= 0 || $amount <= 0.0 ) {
			return;
		}
		Wallet::debit(
			$user_id,
			$amount,
			sprintf(
				/* translators: %s: order number. */
				__( '訂單 #%s 退款,扣回回饋金', 'moforcoupon' ),
				$order instanceof \WC_Order ? $order->get_order_number() : ''
			)
		);
	}
}
