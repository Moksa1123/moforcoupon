<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\Frontend;

use MoksaWeb\Moforcoupon\Modules\AbstractModule;
use MoksaWeb\Moforcoupon\Admin\CouponSections;

defined( 'ABSPATH' ) || exit;

/**
 * Frontend-display module — lazy-loaded, boots only when moforcoupon_frontend_enabled
 * is 'yes'. Provides the [moforcoupon_coupons] card-list shortcode plus a per-coupon
 * "前台顯示" opt-in tab.
 */
final class Module extends AbstractModule {

	public function slug(): string {
		return 'frontend';
	}

	public function label(): string {
		return __( '前台優惠券顯示', 'moforcoupon' );
	}

	public function category(): string {
		return 'coupon';
	}

	public function tagline(): string {
		return __( '用 [moforcoupon_coupons] 短代碼在前台顯示可用優惠券卡片', 'moforcoupon' );
	}

	public function boot(): void {
		Shortcode::register();
		CardsCache::register();
		add_action( 'init', array( Block::class, 'register' ) );

		if ( is_admin() ) {
			$fields = new Fields();
			CouponSections::register( 26, array( $fields, 'render_nonce' ), array( $fields, 'sections' ) );
			add_action( 'woocommerce_coupon_options_save', array( $fields, 'save' ), 10, 2 );
		}
	}
}
