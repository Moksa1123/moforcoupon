<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\MixMatch;

use MoksaWeb\Moforcoupon\Modules\AbstractModule;
use MoksaWeb\Moforcoupon\Admin\CouponSections;

defined( 'ABSPATH' ) || exit;

/**
 * "任選優惠(Mix & Match)" module. Lazy-loaded — boots only when moforcoupon_mixmatch_enabled is
 * 'yes'. Registers the 'moforcoupon_mixmatch' discount type, the cart runtime (classic +
 * Block/Store-API), the admin panel, and the create-mixmatch-coupon ability for the AI / MCP.
 */
final class Module extends AbstractModule {

	public function slug(): string {
		return 'mixmatch';
	}

	public function label(): string {
		return __( '任選優惠(Mix & Match)', 'moforcoupon' );
	}

	public function category(): string {
		return 'coupon';
	}

	public function tagline(): string {
		return __( '任選 N 件 $X / 任選 N 件 Y 折:指定商品任選湊組享優惠價,可重複', 'moforcoupon' );
	}

	public function boot(): void {
		Type::register();
		Frontend::boot();

		if ( function_exists( 'wp_register_ability' ) ) {
			add_action( 'wp_abilities_api_init', array( Ability::class, 'register' ) );
			add_filter( 'moforcoupon_ai_assistant_abilities', array( self::class, 'ai_abilities' ) );
			add_filter( 'moforcoupon_ai_destructive_handlers', array( self::class, 'ai_handlers' ) );
		}

		if ( is_admin() ) {
			$fields = new Fields();
			CouponSections::register( 24, array( $fields, 'render_nonce' ), array( $fields, 'sections' ) );
			add_action( 'woocommerce_coupon_options_save', array( $fields, 'save' ), 10, 2 );
			add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_admin' ) );
		}
	}

	/**
	 * @param array<int,string> $abilities
	 * @return array<int,string>
	 */
	public static function ai_abilities( array $abilities ): array {
		$abilities[] = 'moforcoupon/create-mixmatch-coupon';
		return $abilities;
	}

	/**
	 * @param array<string,array{prepare:callable,apply:callable}> $handlers
	 * @return array<string,array{prepare:callable,apply:callable}>
	 */
	public static function ai_handlers( array $handlers ): array {
		$handlers['moforcoupon/create-mixmatch-coupon'] = array(
			'prepare' => array( MixMatchOps::class, 'create_prepare' ),
			'apply'   => array( MixMatchOps::class, 'create_apply' ),
		);
		return $handlers;
	}

	/** Screen-gated admin toggle JS (coupon editor only). */
	public static function enqueue_admin( string $hook = '' ): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'shop_coupon' !== $screen->post_type ) {
			return;
		}
		$rel  = 'src/Modules/MixMatch/assets/js/mixmatch-admin.js';
		$path = MOFORCOUPON_PLUGIN_DIR . $rel;
		$ver  = file_exists( $path ) ? (string) filemtime( $path ) : MOFORCOUPON_VERSION;
		wp_enqueue_script(
			'moforcoupon-mixmatch-admin',
			MOFORCOUPON_PLUGIN_URL . $rel,
			array( 'jquery' ),
			$ver,
			true
		);
	}
}
