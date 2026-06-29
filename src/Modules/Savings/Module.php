<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\Savings;

use MoksaWeb\Moforcoupon\Modules\AbstractModule;

defined( 'ABSPATH' ) || exit;

/**
 * Savings-summary module — lazy-loaded, boots only when moforcoupon_savings_enabled is
 * 'yes'. Adds a friendly "您總共省了 NT$X" row to the cart and checkout totals, summing
 * every coupon discount; other modules (e.g. BOGO, whose savings come from set_price not
 * a coupon line) add their amount through the moforcoupon_cart_savings_total filter.
 */
final class Module extends AbstractModule {

	public function slug(): string {
		return 'savings';
	}

	public function label(): string {
		return __( '結帳省額提示', 'moforcoupon' );
	}

	public function category(): string {
		return 'coupon';
	}

	public function tagline(): string {
		return __( '在購物車與結帳頁顯示「您總共省了 NT$X」彙總,強化省錢感受', 'moforcoupon' );
	}

	public function boot(): void {
		Frontend::boot();
	}
}
