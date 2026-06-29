<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\Cashback;

use MoksaWeb\Moforcoupon\Modules\AbstractModule;
use MoksaWeb\Moforcoupon\Admin\CouponSections;

defined( 'ABSPATH' ) || exit;

/**
 * Cashback / loyalty-reward module — lazy-loaded, boots only when moforcoupon_cashback_enabled
 * is 'yes'. Registers the 'moforcoupon_cashback' discount type: a 0-nominal coupon that gives
 * NO cart discount but, once the order is paid, computes a reward (percent of total or a fixed
 * amount) and fires the `moforcoupon_cashback_awarded` action so a store-credit / points / loyalty
 * integration can credit the customer. The reward config is set through the moforcoupon settings
 * object (AI / REST / import), and the awarded amount is stored on the order to prevent double
 * crediting. This plugin ships the contract + computation; the wallet itself is the integrator's.
 */
final class Module extends AbstractModule {

	public function slug(): string {
		return 'cashback';
	}

	public function label(): string {
		return __( '回饋金 / 點數(Cashback)', 'moforcoupon' );
	}

	public function category(): string {
		return 'coupon';
	}

	public function tagline(): string {
		return __( '訂單完成後回饋金額 / 點數(透過 moforcoupon_cashback_awarded hook 串接錢包或點數系統)', 'moforcoupon' );
	}

	public function boot(): void {
		Type::register();
		Runtime::boot();

		if ( is_admin() ) {
			$fields = new Fields();
			CouponSections::register( 23, array( $fields, 'render_nonce' ), array( $fields, 'sections' ) );
			add_action( 'woocommerce_coupon_options_save', array( $fields, 'save' ), 10, 2 );
			add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_admin' ) );
		}
	}

	/** Screen-gated admin toggle JS (coupon editor only). */
	public static function enqueue_admin(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'shop_coupon' !== $screen->post_type ) {
			return;
		}
		$rel  = 'src/Modules/Cashback/assets/js/cashback-admin.js';
		$path = \MOFORCOUPON_PLUGIN_DIR . $rel;
		$ver  = file_exists( $path ) ? (string) filemtime( $path ) : \MOFORCOUPON_VERSION;
		wp_enqueue_script( 'moforcoupon-cashback-admin', \MOFORCOUPON_PLUGIN_URL . $rel, array( 'jquery' ), $ver, true );
	}
}
