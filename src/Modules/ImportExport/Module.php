<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\ImportExport;

use MoksaWeb\Moforcoupon\Modules\AbstractModule;

defined( 'ABSPATH' ) || exit;

/**
 * CSV import / export module — lazy-loaded, boots only when moforcoupon_importexport_enabled
 * is 'yes'. Adds an "匯入 / 匯出" admin page under the coupon menu for backing up, auditing or
 * bulk-editing coupons as CSV.
 */
final class Module extends AbstractModule {

	public function slug(): string {
		return 'importexport';
	}

	public function label(): string {
		return __( 'CSV 匯入 / 匯出', 'moforcoupon' );
	}

	public function category(): string {
		return 'coupon';
	}

	public function tagline(): string {
		return __( '把優惠券匯出成 CSV 備份 / 稽核,或用 CSV 大量新建與更新', 'moforcoupon' );
	}

	public function boot(): void {
		if ( is_admin() ) {
			ImportExport::boot();
		}
	}
}
