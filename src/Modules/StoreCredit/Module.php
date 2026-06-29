<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\StoreCredit;

use MoksaWeb\Moforcoupon\Modules\AbstractModule;

defined( 'ABSPATH' ) || exit;

/**
 * "儲值金 / 回饋金錢包" module — lazy-loaded, boots only when moforcoupon_storecredit_enabled is
 * 'yes'. Gives the cashback coupon type a real destination: it listens for the cashback award and
 * credits a per-customer balance that is auto-applied as a discount at checkout. Without this
 * module, cashback only fires its integration hook.
 */
final class Module extends AbstractModule {

	public function slug(): string {
		return 'storecredit';
	}

	public function label(): string {
		return __( '儲值金 / 回饋金錢包', 'moforcoupon' );
	}

	public function category(): string {
		return 'coupon';
	}

	public function tagline(): string {
		return __( '把回饋金(cashback)記成顧客的儲值金餘額,結帳時自動折抵下次訂單', 'moforcoupon' );
	}

	public function boot(): void {
		// Credit the wallet whenever a cashback reward is awarded.
		add_action(
			'moforcoupon_cashback_awarded',
			static function ( $total, $order, $customer_id ): void {
				Wallet::credit(
					(int) $customer_id,
					(float) $total,
					sprintf(
						/* translators: %s: order number. */
						__( '訂單 #%s 回饋金', 'moforcoupon' ),
						$order instanceof \WC_Order ? $order->get_order_number() : ''
					)
				);
			},
			10,
			3
		);

		Checkout::register();
		Refund::register();
		GiftCard::register();
	}
}
