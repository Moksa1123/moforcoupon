<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\Referral;

use MoksaWeb\Moforcoupon\Modules\AbstractModule;

defined( 'ABSPATH' ) || exit;

/**
 * "推薦好友券(Referral)" module — lazy-loaded, boots only when moforcoupon_referral_enabled is
 * 'yes'. Gives every customer a shareable link; when a referred friend completes an order, the
 * referrer (and optionally the friend) is issued a reward coupon. Reuses the personal-coupon issuer
 * so the rewards land in each recipient's "我的優惠券" page.
 */
final class Module extends AbstractModule {

	public function slug(): string {
		return 'referral';
	}

	public function label(): string {
		return __( '推薦好友券(Referral)', 'moforcoupon' );
	}

	public function category(): string {
		return 'coupon';
	}

	public function tagline(): string {
		return __( '顧客分享專屬連結,朋友完成首購後雙方各得一張優惠券', 'moforcoupon' );
	}

	public function boot(): void {
		add_action( 'init', array( Service::class, 'capture' ) );
		add_action( 'woocommerce_checkout_create_order', array( Service::class, 'attach_to_order' ) );
		add_action( 'woocommerce_order_status_completed', array( Service::class, 'reward' ) );
		add_action( 'woocommerce_account_dashboard', array( Service::class, 'dashboard' ) );
	}
}
