<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\DiscountCap;

use MoksaWeb\Moforcoupon\Modules\AbstractModule;

defined( 'ABSPATH' ) || exit;

/**
 * Discount-cap module. Lazy-loaded — boots only when moforcoupon_discountcap_enabled
 * is 'yes'. Lets a percent coupon's total discount be capped at a maximum amount.
 */
final class Module extends AbstractModule {

	public function slug(): string {
		return 'discountcap';
	}

	public function label(): string {
		return __( '折扣上限', 'moforcoupon' );
	}

	public function category(): string {
		return 'coupon';
	}

	public function tagline(): string {
		return __( '百分比折扣設定最高折抵金額(例:8 折最多折 500)', 'moforcoupon' );
	}

	public function boot(): void {
		Cap::boot();

		if ( is_admin() ) {
			$fields = new Fields();
			add_action( 'woocommerce_coupon_options', array( $fields, 'render' ), 10, 2 );
			add_action( 'woocommerce_coupon_options_save', array( $fields, 'save' ), 10, 2 );
		}
	}
}
