<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\ShippingOverride;

use MoksaWeb\Moforcoupon\Modules\AbstractModule;
use MoksaWeb\Moforcoupon\Admin\CouponSections;

defined( 'ABSPATH' ) || exit;

/**
 * Shipping-override module — lazy-loaded, boots only when
 * moforcoupon_shipping_enabled is 'yes'. Rewrites shipping-rate costs (free / percent
 * off / fixed off) for any applied coupon carrying an override. Enforcement
 * (RateModifier) and the admin UI (Fields) are gated together.
 */
final class Module extends AbstractModule {

	public function slug(): string {
		return 'shipping';
	}

	public function label(): string {
		return __( '運費覆寫', 'moforcoupon' );
	}

	public function category(): string {
		return 'coupon';
	}

	public function tagline(): string {
		return __( '套用優惠券時免運 / 折運費(百分比或固定額)', 'moforcoupon' );
	}

	public function boot(): void {
		RateModifier::boot();

		if ( is_admin() ) {
			$fields = new Fields();
			CouponSections::register( 25, array( $fields, 'render_nonce' ), array( $fields, 'sections' ) );
			add_action( 'woocommerce_coupon_options_save', array( $fields, 'save' ), 10, 2 );
		}
	}
}
