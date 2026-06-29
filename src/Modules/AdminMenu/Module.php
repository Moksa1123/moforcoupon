<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\AdminMenu;

use MoksaWeb\Moforcoupon\Modules\AbstractModule;

defined( 'ABSPATH' ) || exit;

/**
 * Admin-menu module — lazy-loaded, boots only when moforcoupon_adminmenu_enabled is
 * 'yes'. Promotes coupon management into its own top-level "Moksa 優惠券" menu and
 * reparents the shop_coupon CPT (全部優惠券 / 新增) under it, alongside the report
 * page and a settings link.
 */
final class Module extends AbstractModule {

	public function slug(): string {
		return 'adminmenu';
	}

	public function label(): string {
		return __( '優惠券管理選單', 'moforcoupon' );
	}

	public function category(): string {
		return 'coupon';
	}

	public function tagline(): string {
		return __( '把優惠券管理收進獨立頂層選單(收編全部券 / 新增 / 報表 / 設定)', 'moforcoupon' );
	}

	public function boot(): void {
		// Hide shop_coupon from core's automatic menu placement on every registration
		// (init), admin and front, so the result is consistent. Only the admin menu
		// render is actually affected.
		add_filter( 'register_post_type_args', array( Menu::class, 'reparent_cpt' ), 20, 2 );

		if ( is_admin() ) {
			add_action( 'admin_menu', array( Menu::class, 'register' ), 9 );
			// Late pass: remove WooCommerce's leftover "Coupons" pointer entry.
			add_action( 'admin_menu', array( Menu::class, 'hide_legacy_coupon_menu' ), 999 );
			// Pin coupon CPT screens to our top-level menu for correct highlighting.
			add_filter( 'parent_file', array( Menu::class, 'highlight_parent' ) );
			add_filter( 'submenu_file', array( Menu::class, 'highlight_submenu' ) );
		}
	}
}
