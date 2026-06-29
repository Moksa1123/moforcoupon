<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\FreeGift;

use MoksaWeb\Moforcoupon\Modules\AbstractModule;
use MoksaWeb\Moforcoupon\Admin\CouponSections;

defined( 'ABSPATH' ) || exit;

/**
 * Free-gift / add-product module. Lazy-loaded — boots only when
 * moforcoupon_freegift_enabled is 'yes'. Auto-adds a coupon's gift product to the
 * cart on apply (classic + Block), priced free / percent / fixed.
 */
final class Module extends AbstractModule {

	public function slug(): string {
		return 'freegift';
	}

	public function label(): string {
		return __( '加贈品 / 免費贈品', 'moforcoupon' );
	}

	public function category(): string {
		return 'coupon';
	}

	public function tagline(): string {
		return __( '套用優惠券時自動加入贈品(免費或折扣),數量鎖定、撤券即撤回', 'moforcoupon' );
	}

	public function boot(): void {
		GiftHandler::boot();

		if ( is_admin() ) {
			$fields = new Fields();
			CouponSections::register( 23, array( $fields, 'render_nonce' ), array( $fields, 'sections' ) );
			add_action( 'woocommerce_coupon_options_save', array( $fields, 'save' ), 10, 2 );
		}
	}
}
