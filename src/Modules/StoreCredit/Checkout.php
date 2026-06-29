<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\StoreCredit;

use MoksaWeb\Moforcoupon\Support\OrderOnce;

defined( 'ABSPATH' ) || exit;

/**
 * Spends store credit at checkout: auto-applies the customer's balance as a negative cart fee, then
 * debits the wallet once the order is paid. Also surfaces the balance on the My Account dashboard.
 */
final class Checkout {

	private const SPENT_META = '_moforcoupon_credit_used';

	public static function register(): void {
		add_action( 'woocommerce_cart_calculate_fees', array( self::class, 'apply_credit' ) );
		add_action( 'woocommerce_order_status_processing', array( self::class, 'debit_on_paid' ) );
		add_action( 'woocommerce_order_status_completed', array( self::class, 'debit_on_paid' ) );
		add_action( 'woocommerce_account_dashboard', array( self::class, 'dashboard_balance' ) );
	}

	private static function fee_label(): string {
		// "使用儲值金", not "折抵" — this is the customer paying with their wallet balance, not a
		// discount/promotion. The misleading "折抵" wording read like a price reduction on the order.
		return __( '使用儲值金', 'moforcoupon' );
	}

	/** Apply the logged-in customer's credit as a negative fee, capped at the cart's pre-credit total. */
	public static function apply_credit( \WC_Cart $cart ): void {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}
		$user_id = get_current_user_id();
		$balance = Wallet::balance( $user_id );
		if ( $balance <= 0.0 ) {
			return;
		}
		// Eligible = items + shipping + tax already in the cart, before this credit.
		$eligible = (float) $cart->get_subtotal() + (float) $cart->get_shipping_total();
		$amount   = Wallet::apply_amount( $balance, $eligible );
		if ( $amount > 0.0 ) {
			$cart->add_fee( self::fee_label(), -1 * $amount, false );
		}
	}

	/** After payment, debit the credit that was actually applied to this order (once). */
	public static function debit_on_paid( $order_id ): void {
		$order = OrderOnce::get( $order_id );
		if ( null === $order || OrderOnce::done( $order, self::SPENT_META ) ) {
			return; // not an order, or already debited.
		}
		$user_id = (int) $order->get_customer_id();
		if ( $user_id <= 0 ) {
			return;
		}

		$used = 0.0;
		foreach ( $order->get_fees() as $fee ) {
			if ( self::fee_label() === $fee->get_name() ) {
				$used += abs( (float) $fee->get_total() );
			}
		}
		if ( $used <= 0.0 ) {
			return;
		}

		$debited = Wallet::debit(
			$user_id,
			$used,
			sprintf(
				/* translators: %s: order number. */
				__( '訂單 #%s 使用儲值金', 'moforcoupon' ),
				$order->get_order_number()
			)
		);
		OrderOnce::mark( $order, self::SPENT_META, wc_format_decimal( (string) $debited ) );
	}

	/** Show the current balance on the My Account dashboard. */
	public static function dashboard_balance(): void {
		$balance = Wallet::balance( get_current_user_id() );
		if ( $balance <= 0.0 ) {
			return;
		}
		echo '<p class="moforcoupon-store-credit">' . esc_html(
			sprintf(
				/* translators: %s: formatted balance. */
				__( '您目前的儲值金餘額:%s(結帳時自動用於付款)', 'moforcoupon' ),
				wp_strip_all_tags( wc_price( $balance ) )
			)
		) . '</p>';
	}
}
