<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\DiscountTiers;

use MoksaWeb\Moforcoupon\Modules\AbstractModule;
use MoksaWeb\Moforcoupon\Admin\CouponSections;

defined( 'ABSPATH' ) || exit;

/**
 * Tiered-discount module — lazy-loaded, boots only when moforcoupon_discounttiers_enabled
 * is 'yes'. Lets ONE percent coupon give a different percent-off per cart tier (subtotal /
 * quantity), optionally scoped to chosen products / categories. Enforcement (Engine) and the
 * admin UI (Fields) are gated together.
 */
final class Module extends AbstractModule {

	public function slug(): string {
		return 'discounttiers';
	}

	public function label(): string {
		return __( '階梯折扣', 'moforcoupon' );
	}

	public function category(): string {
		return 'coupon';
	}

	public function tagline(): string {
		return __( '同一張券依購物車門檻給不同折扣(例:未滿 1000 折 10%、滿 1000 折 20%)', 'moforcoupon' );
	}

	public function boot(): void {
		Engine::boot();

		/** Filter: show the cart/checkout "spend NT$X more for a bigger discount" nudge. */
		if ( apply_filters( 'moforcoupon_tiers_show_nudge', true ) ) {
			Nudge::boot();
		}

		if ( is_admin() ) {
			$fields = new Fields();
			CouponSections::register( 18, array( $fields, 'render_nonce' ), array( $fields, 'sections' ) );
			add_action( 'woocommerce_coupon_options_save', array( $fields, 'save' ), 10, 2 );
			add_action( 'admin_enqueue_scripts', array( $fields, 'enqueue' ) );
		}
	}
}
