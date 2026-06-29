<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\CartRecovery;

use MoksaWeb\Moforcoupon\Modules\AbstractModule;
use MoksaWeb\Moforcoupon\Support\Cron;

defined( 'ABSPATH' ) || exit;

/**
 * "棄單挽回" module — lazy-loaded, boots only when moforcoupon_cartrecovery_enabled is 'yes'. Snapshots
 * a shopper's cart when they enter their email at checkout, then (hourly) emails them — optionally
 * with an incentive coupon — if the cart is abandoned, stopping once they place an order.
 */
final class Module extends AbstractModule {

	public function slug(): string {
		return 'cartrecovery';
	}

	public function label(): string {
		return __( '棄單挽回', 'moforcoupon' );
	}

	public function category(): string {
		return 'coupon';
	}

	public function tagline(): string {
		return __( '顧客結帳填了 Email 卻沒付款時,自動 Email 提醒(可附優惠券)把訂單救回來', 'moforcoupon' );
	}

	public function boot(): void {
		add_action( 'init', array( Store::class, 'register_cpt' ) );
		add_action( 'woocommerce_checkout_update_order_review', array( Capture::class, 'capture' ) );
		add_action( 'woocommerce_checkout_order_processed', array( Recovery::class, 'on_order' ) );
		add_action( 'woocommerce_thankyou', array( Recovery::class, 'on_order' ) );
		add_action( Cron::HOURLY, array( Recovery::class, 'run_hourly' ) );
		add_action( Cron::HOOK, array( Recovery::class, 'run_daily' ) );
	}
}
