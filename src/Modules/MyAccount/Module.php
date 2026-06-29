<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\MyAccount;

use MoksaWeb\Moforcoupon\Modules\AbstractModule;

defined( 'ABSPATH' ) || exit;

/**
 * "會員中心優惠券" module — lazy-loaded, boots only when moforcoupon_myaccount_enabled is 'yes'.
 * Adds a "我的優惠券" tab to the WooCommerce My Account area listing the coupons issued to /
 * locked to the logged-in customer. The marketing loop: a coupon sent to a customer (via the
 * send-coupon ability or a post-purchase rule) shows up here for them to copy or one-click apply.
 */
final class Module extends AbstractModule {

	private const REWRITE_FLAG = 'moforcoupon_myaccount_rewrite';

	public function slug(): string {
		return 'myaccount';
	}

	public function label(): string {
		return __( '會員中心優惠券', 'moforcoupon' );
	}

	public function category(): string {
		return 'coupon';
	}

	public function tagline(): string {
		return __( '在 WooCommerce「我的帳戶」顯示顧客專屬優惠券,可複製或一鍵套用', 'moforcoupon' );
	}

	public function boot(): void {
		add_action( 'init', array( Endpoint::class, 'add_endpoint' ) );
		add_filter( 'woocommerce_account_menu_items', array( Endpoint::class, 'add_menu_item' ) );
		add_action( 'woocommerce_account_' . Endpoint::SLUG . '_endpoint', array( Endpoint::class, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( Endpoint::class, 'enqueue' ) );

		// Register the endpoint then flush rewrite rules once per plugin version (the new endpoint
		// needs fresh rules to resolve). Cheap after the first run thanks to the version flag.
		add_action( 'init', array( self::class, 'maybe_flush' ), 99 );
	}

	public static function maybe_flush(): void {
		if ( get_option( self::REWRITE_FLAG ) === MOFORCOUPON_VERSION ) {
			return;
		}
		flush_rewrite_rules( false );
		update_option( self::REWRITE_FLAG, MOFORCOUPON_VERSION );
	}
}
