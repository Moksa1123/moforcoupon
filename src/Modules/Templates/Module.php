<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\Templates;

use MoksaWeb\Moforcoupon\Modules\AbstractModule;

defined( 'ABSPATH' ) || exit;

/**
 * Coupon-templates module — lazy-loaded, boots only when moforcoupon_templates_enabled
 * is 'yes'. Adds a "優惠券範本" page of one-click presets. When the AdminMenu module is
 * on, that module reparents the page under the top-level menu; otherwise this module
 * registers the legacy submenu under WooCommerce.
 */
final class Module extends AbstractModule {

	public function slug(): string {
		return 'templates';
	}

	public function label(): string {
		return __( '優惠券範本', 'moforcoupon' );
	}

	public function category(): string {
		return 'coupon';
	}

	public function tagline(): string {
		return __( '預設好的優惠券範本,一鍵建立草稿券', 'moforcoupon' );
	}

	public function boot(): void {
		// The apply action runs from any admin context (form post → admin-post.php).
		add_action( 'admin_post_moforcoupon_apply_template', array( TemplatePage::class, 'handle' ) );

		if ( is_admin() ) {
			add_action( 'admin_enqueue_scripts', array( TemplatePage::class, 'enqueue_admin' ) );
			if ( ! \MoksaWeb\Moforcoupon\Plugin::instance()->modules()->is_enabled( 'adminmenu' ) ) {
				add_action( 'admin_menu', array( TemplatePage::class, 'register' ) );
			}
		}
	}
}
