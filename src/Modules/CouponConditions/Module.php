<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\CouponConditions;

use MoksaWeb\Moforcoupon\Modules\AbstractModule;
use MoksaWeb\Moforcoupon\Admin\CouponSections;

defined( 'ABSPATH' ) || exit;

/**
 * Coupon conditions module: schedule window, role restrictions and cart minimums.
 * Lazy-loaded — only boots when moforcoupon_conditions_enabled is 'yes', so an
 * unchecked feature registers no hooks. Enforcement (Validator) and the admin UI
 * (Fields) are gated together, so a merchant can never set a condition that is
 * then silently ignored.
 */
final class Module extends AbstractModule {

	public function slug(): string {
		return 'conditions';
	}

	public function label(): string {
		return __( '優惠券條件(排程 / 角色 / 購物車)', 'moforcoupon' );
	}

	public function category(): string {
		return 'coupon';
	}

	public function tagline(): string {
		return __( '排程起訖、角色限制、最低小計 / 數量、顧客歷史、商品 / 分類、星期 / 時段', 'moforcoupon' );
	}

	public function boot(): void {
		// Enforcement runs on the front-end / checkout for both classic and Block.
		Validator::boot();

		if ( is_admin() ) {
			$fields = new Fields();
			CouponSections::register( 20, array( $fields, 'render_nonce' ), array( $fields, 'sections' ) );
			add_action( 'woocommerce_coupon_options_save', array( $fields, 'save' ), 10, 2 );
		}
	}
}
