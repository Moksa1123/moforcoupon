<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\Nudge;

use MoksaWeb\Moforcoupon\Modules\AbstractModule;

defined( 'ABSPATH' ) || exit;

/**
 * "免運門檻提示" module — lazy-loaded, boots only when moforcoupon_nudge_enabled is 'yes'. Shows a
 * "再買 NT$X 免運" message on the cart and checkout to nudge shoppers over the free-shipping line.
 */
final class Module extends AbstractModule {

	public function slug(): string {
		return 'nudge';
	}

	public function label(): string {
		return __( '免運門檻提示', 'moforcoupon' );
	}

	public function category(): string {
		return 'coupon';
	}

	public function tagline(): string {
		return __( '在購物車 / 結帳顯示「再買 NT$X 免運」,推動客單價', 'moforcoupon' );
	}

	public function boot(): void {
		add_action( 'woocommerce_before_cart', array( Nudge::class, 'render' ) );
		add_action( 'woocommerce_review_order_before_payment', array( Nudge::class, 'render' ) );
	}
}
