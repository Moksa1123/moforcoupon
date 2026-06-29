<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\AdvancedRules;

use MoksaWeb\Moforcoupon\Modules\AbstractModule;
use MoksaWeb\Moforcoupon\Admin\CouponSections;

defined( 'ABSPATH' ) || exit;

/**
 * Advanced rule-builder module — lazy-loaded, boots only when moforcoupon_advrules_enabled
 * is 'yes'. Adds a free-form AND/OR condition tree (Advanced-Coupons-style cart conditions)
 * enforced on top of the simple per-dimension conditions. Enforcement (Engine) + admin UI
 * (Fields) are gated together.
 */
final class Module extends AbstractModule {

	public function slug(): string {
		return 'advrules';
	}

	public function label(): string {
		return __( '進階規則(AND/OR)', 'moforcoupon' );
	}

	public function category(): string {
		return 'coupon';
	}

	public function tagline(): string {
		return __( '用群組與 AND/OR 自由組合條件(小計 / 件數 / 商品 / 分類 / 地區 / 付款 / 角色 / 星期 / 時間…)', 'moforcoupon' );
	}

	public function boot(): void {
		Engine::boot();

		if ( is_admin() ) {
			$fields = new Fields();
			CouponSections::register( 22, array( $fields, 'render_nonce' ), array( $fields, 'sections' ) );
			add_action( 'woocommerce_coupon_options_save', array( $fields, 'save' ), 10, 2 );
			add_action( 'admin_enqueue_scripts', array( $fields, 'enqueue' ) );
		}
	}
}
