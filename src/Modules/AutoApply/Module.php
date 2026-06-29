<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\AutoApply;

use MoksaWeb\Moforcoupon\Modules\AbstractModule;

defined( 'ABSPATH' ) || exit;

/**
 * Auto-apply module. Lazy-loaded — boots only when moforcoupon_autoapply_enabled is
 * 'yes'. Coupons flagged for auto-apply are added to the cart automatically when
 * their conditions are met, with no code entry. Works for classic + Block carts.
 */
final class Module extends AbstractModule {

	public function slug(): string {
		return 'autoapply';
	}

	public function label(): string {
		return __( '自動套用優惠券', 'moforcoupon' );
	}

	public function category(): string {
		return 'coupon';
	}

	public function tagline(): string {
		return __( '購物車符合條件時自動帶入優惠券,免輸入代碼', 'moforcoupon' );
	}

	public function boot(): void {
		Frontend::boot();

		// Keep the derived id-cache in sync (safety net beyond AutoApplyMeta::write).
		add_action( 'save_post_shop_coupon', array( AutoApplyMeta::class, 'on_save' ), 20, 1 );
		add_action( 'untrashed_post', array( AutoApplyMeta::class, 'on_save' ) );
		add_action( 'wp_trash_post', array( AutoApplyMeta::class, 'on_remove' ) );
		add_action( 'before_delete_post', array( AutoApplyMeta::class, 'on_remove' ) );
		// Meta-level sync so REST / AI / WP-CLI writes of the flag also refresh the cache
		// (WC REST fires save_post before it writes meta, so save_post alone is stale).
		add_action( 'added_post_meta', array( AutoApplyMeta::class, 'on_meta_change' ), 10, 3 );
		add_action( 'updated_post_meta', array( AutoApplyMeta::class, 'on_meta_change' ), 10, 3 );
		add_action( 'deleted_post_meta', array( AutoApplyMeta::class, 'on_meta_change' ), 10, 3 );

		if ( is_admin() ) {
			$fields = new Fields();
			add_action( 'woocommerce_coupon_options', array( $fields, 'render' ), 10, 2 );
			add_action( 'woocommerce_coupon_options_save', array( $fields, 'save' ), 10, 2 );
		}
	}
}
