<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\CouponList;

use MoksaWeb\Moforcoupon\Modules\AbstractModule;

defined( 'ABSPATH' ) || exit;

/**
 * Coupon-list enhancements — lazy-loaded, boots only when moforcoupon_couponlist_enabled
 * is 'yes'. Adds an enabled/disabled status column, bulk 啟用/停用 actions, and a one-click
 * 複製 (duplicate) row action to the WooCommerce coupon list — things WooCommerce's own list
 * table does not provide.
 */
final class Module extends AbstractModule {

	public function slug(): string {
		return 'couponlist';
	}

	public function label(): string {
		return __( '優惠券列表增強', 'moforcoupon' );
	}

	public function category(): string {
		return 'coupon';
	}

	public function tagline(): string {
		return __( '在優惠券列表加上啟用 / 停用狀態欄、批次啟停、一鍵複製券', 'moforcoupon' );
	}

	public function boot(): void {
		if ( is_admin() ) {
			ListTable::boot();
		}
	}
}
