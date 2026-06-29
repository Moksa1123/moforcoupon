<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\TabIcons;

use MoksaWeb\Moforcoupon\Modules\AbstractModule;

defined( 'ABSPATH' ) || exit;

/**
 * Tab-icons module — lazy-loaded, boots only when moforcoupon_tabicons_enabled is
 * 'yes'. Adds a uniform monochrome line icon to each Moksa coupon-settings tab on the
 * coupon edit screen (matching the Advanced Coupons coupon-settings look).
 */
final class Module extends AbstractModule {

	public function slug(): string {
		return 'tabicons';
	}

	public function label(): string {
		return __( '優惠券設定圖示', 'moforcoupon' );
	}

	public function category(): string {
		return 'coupon';
	}

	public function tagline(): string {
		return __( '為每個優惠券設定分頁加上統一風格的單色圖示', 'moforcoupon' );
	}

	public function boot(): void {
		if ( is_admin() ) {
			add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
		}
	}

	/** Inline the tab-icon CSS only on the coupon edit screen. */
	public static function enqueue(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'shop_coupon' !== $screen->id ) {
			return;
		}
		wp_register_style( 'moforcoupon-tab-icons', false, array(), \MOFORCOUPON_VERSION );
		wp_enqueue_style( 'moforcoupon-tab-icons' );
		wp_add_inline_style( 'moforcoupon-tab-icons', Icons::css() );
	}
}
