<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\Summary;

use MoksaWeb\Moforcoupon\Modules\AbstractModule;

defined( 'ABSPATH' ) || exit;

/**
 * Live coupon-summary module — lazy-loaded, boots only when moforcoupon_summary_enabled is
 * 'yes'. Adds a side metabox on the coupon editor that, as the admin edits, shows what the
 * coupon does, which advanced features are on, and any detected conflicts.
 */
final class Module extends AbstractModule {

	public function slug(): string {
		return 'summary';
	}

	public function label(): string {
		return __( '編輯頁即時摘要', 'moforcoupon' );
	}

	public function category(): string {
		return 'coupon';
	}

	public function tagline(): string {
		return __( '編輯優惠券時即時顯示效果摘要與條件衝突提醒', 'moforcoupon' );
	}

	public function boot(): void {
		if ( is_admin() ) {
			Panel::boot();
		}
	}
}
