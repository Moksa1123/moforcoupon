<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\Metaboxes;

use MoksaWeb\Moforcoupon\Modules\AbstractModule;
use MoksaWeb\Moforcoupon\Coupon\Meta\Keys;

defined( 'ABSPATH' ) || exit;

/**
 * Consolidated-metabox module — lazy-loaded, boots only when
 * moforcoupon_metaboxes_enabled is 'yes'. It does NOT register the coupon fields
 * itself: every feature module renders through Admin\CouponSections, which reads the
 * same option and either keeps each section as a WooCommerce coupon-data tab or
 * gathers them ALL into one "Moksa 優惠券設定" metabox laid out as a WooCommerce-style
 * vertical tabbed panel. This module owns the settings toggle plus the CSS + JS that
 * give that single box its left-tab / switchable-panel behaviour.
 */
final class Module extends AbstractModule {

	public function slug(): string {
		return 'metaboxes';
	}

	public function label(): string {
		return __( '集中設定 Metabox', 'moforcoupon' );
	}

	public function category(): string {
		return 'coupon';
	}

	public function tagline(): string {
		return __( '把優惠券各設定區塊集中到單一個 metabox,用 WooCommerce 風格的左側分頁呈現', 'moforcoupon' );
	}

	public function boot(): void {
		if ( is_admin() ) {
			add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
		}
	}

	/** Inline the tabbed-metabox CSS + switcher JS only on the coupon edit screen. */
	public static function enqueue(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'shop_coupon' !== $screen->id ) {
			return;
		}
		wp_register_style( 'moforcoupon-metaboxes', false, array(), \MOFORCOUPON_VERSION );
		wp_enqueue_style( 'moforcoupon-metaboxes' );
		wp_add_inline_style( 'moforcoupon-metaboxes', self::css() );

		$rel  = 'src/Modules/Metaboxes/assets/js/metabox-tabs.js';
		$path = \MOFORCOUPON_PLUGIN_DIR . $rel;
		$ver  = file_exists( $path ) ? (string) filemtime( $path ) : \MOFORCOUPON_VERSION;
		wp_enqueue_script(
			'moforcoupon-metabox-tabs',
			\MOFORCOUPON_PLUGIN_URL . $rel,
			array( 'jquery' ),
			$ver,
			true
		);

		// Hide each feature section's dependent fields while its enable checkbox is off.
		$cond  = 'src/Modules/Metaboxes/assets/js/conditional-fields.js';
		$cpath = \MOFORCOUPON_PLUGIN_DIR . $cond;
		$cver  = file_exists( $cpath ) ? (string) filemtime( $cpath ) : \MOFORCOUPON_VERSION;
		wp_enqueue_script( 'moforcoupon-conditional-fields', \MOFORCOUPON_PLUGIN_URL . $cond, array( 'jquery' ), $cver, true );
		wp_localize_script(
			'moforcoupon-conditional-fields',
			'moforcouponConditional',
			array(
				'toggles' => array(
					Keys::SCHEDULE_ENABLED,
					Keys::ROLE_ENABLED,
					Keys::CUST_ENABLED,
					Keys::DAYTIME_ENABLED,
					Keys::TIERS_ENABLED,
					Keys::RULES_ENABLED,
					Keys::URL_ENABLED,
					Keys::SHIPREGION_ENABLED,
					Keys::PAYMENT_ENABLED,
					Keys::GIFT_ENABLED,
				),
			)
		);
	}

	/**
	 * Vertical tabbed-panel layout for the single consolidated metabox, mirroring the
	 * native WooCommerce coupon-data box (left tab column + right panel) but scoped to
	 * our own classes. The fields keep WooCommerce's .woocommerce_options_panel styling.
	 */
	public static function css(): string {
		return '.post-type-shop_coupon #moforcoupon_coupon_settings > .inside{margin:0;padding:0;}'
			. '.moforcoupon-panel-wrap{position:relative;overflow:hidden;}'
			. '.moforcoupon-settings-tabs{float:left;width:20%;margin:0;padding:0 0 10px;box-sizing:border-box;'
				. 'background:#f9f9f9;border-right:1px solid #eee;line-height:1.4em;}'
			. '.moforcoupon-settings-tabs li{margin:0;padding:0;display:block;position:relative;}'
			. '.moforcoupon-settings-tabs li a{display:block;padding:10px;text-decoration:none;box-shadow:none;'
				. 'border-bottom:1px solid #eee;}'
			. '.moforcoupon-settings-tabs li.active a{background:#fff;color:#555;box-shadow:inset 3px 0 0 #2271b1;}'
			. '.moforcoupon-settings-panels{float:left;width:80%;box-sizing:border-box;min-height:260px;}'
			. '.moforcoupon-panel{display:none;padding:9px 12px 12px;}'
			. '.moforcoupon-panel select,.moforcoupon-panel input,.moforcoupon-panel .select2-container{float:none;}'
			. '.moforcoupon-panel .options_group{border-top:1px solid #f0f0f1;}'
			. '.moforcoupon-panel .options_group:first-child{border-top:0;}'
			. '@media screen and (max-width:782px){'
				. '.moforcoupon-settings-tabs,.moforcoupon-settings-panels{float:none;width:100%;}'
				. '.moforcoupon-settings-tabs{border-right:0;border-bottom:1px solid #eee;}}';
	}
}
