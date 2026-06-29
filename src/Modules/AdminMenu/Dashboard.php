<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\AdminMenu;

use MoksaWeb\Moforcoupon\Plugin;
use MoksaWeb\Moforcoupon\Settings\SettingsScreen;
use MoksaWeb\Moforcoupon\Modules\Reports\ReportsPage;
use MoksaWeb\Moforcoupon\Modules\Templates\TemplatePage;

defined( 'ABSPATH' ) || exit;

/**
 * Landing page for the top-level "Moksa 優惠券" menu — a lightweight management hub
 * with at-a-glance coupon counts and quick links. Seeds the future full dashboard.
 */
final class Dashboard {

	private const CAP = 'edit_shop_coupons';

	public static function render(): void {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}

		$counts    = wp_count_posts( 'shop_coupon' );
		$published = isset( $counts->publish ) ? (int) $counts->publish : 0;
		$draft     = isset( $counts->draft ) ? (int) $counts->draft : 0;
		$pending   = isset( $counts->pending ) ? (int) $counts->pending : 0;
		$total     = $published + $draft + $pending;

		echo '<div class="wrap moforcoupon-hub">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Moksa 優惠券管理', 'moforcoupon' ) . '</h1>';
		echo ' <a href="' . esc_url( admin_url( 'post-new.php?post_type=shop_coupon' ) ) . '" class="page-title-action">'
			. esc_html__( '新增優惠券', 'moforcoupon' ) . '</a>';
		echo '<hr class="wp-header-end">';
		echo '<p class="description">' . esc_html__( '在這裡集中管理所有優惠券、查看報表與調整設定。', 'moforcoupon' ) . '</p>';

		// Stat tiles.
		echo '<div class="moforcoupon-hub-stats">';
		self::stat_tile( __( '優惠券總數', 'moforcoupon' ), (string) $total );
		self::stat_tile( __( '啟用中', 'moforcoupon' ), (string) $published );
		self::stat_tile( __( '草稿 / 待審', 'moforcoupon' ), (string) ( $draft + $pending ) );
		echo '</div>';

		// Quick links.
		echo '<div class="moforcoupon-hub-links">';
		self::link_card(
			admin_url( 'edit.php?post_type=shop_coupon' ),
			__( '全部優惠券', 'moforcoupon' ),
			__( '檢視、編輯與搜尋所有優惠券。', 'moforcoupon' )
		);
		self::link_card(
			admin_url( 'post-new.php?post_type=shop_coupon' ),
			__( '新增優惠券', 'moforcoupon' ),
			__( '建立一張新的優惠券。', 'moforcoupon' )
		);
		if ( Plugin::instance()->modules()->is_enabled( 'templates' ) ) {
			self::link_card(
				admin_url( 'admin.php?page=' . TemplatePage::slug() ),
				__( '優惠券範本', 'moforcoupon' ),
				__( '挑一個範本一鍵建立草稿券。', 'moforcoupon' )
			);
		}
		self::link_card(
			SettingsScreen::url(),
			__( '優惠券設定', 'moforcoupon' ),
			__( '啟用 / 關閉各項功能模組。', 'moforcoupon' )
		);
		echo '</div>';

		// Coupon reports now live here on the dashboard (instead of a separate page).
		if ( Plugin::instance()->modules()->is_enabled( 'reports' ) && current_user_can( 'manage_woocommerce' ) ) {
			echo '<h2 class="moforcoupon-hub-reports">' . esc_html__( '優惠券報表', 'moforcoupon' ) . '</h2>';
			ReportsPage::render_table( admin_url( 'admin.php?page=' . Menu::TOPLEVEL ) );
		}

		echo '<style>'
			. '.moforcoupon-hub-stats{display:flex;flex-wrap:wrap;gap:16px;margin:16px 0 24px}'
			. '.moforcoupon-hub-stats .tile{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px 24px;min-width:140px}'
			. '.moforcoupon-hub-stats .tile .num{font-size:28px;font-weight:600;line-height:1.2}'
			. '.moforcoupon-hub-stats .tile .lbl{color:#646970;font-size:13px}'
			. '.moforcoupon-hub-links{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px}'
			. '.moforcoupon-hub-links a.card{display:block;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px 20px;text-decoration:none;color:inherit}'
			. '.moforcoupon-hub-links a.card:hover{border-color:#2271b1;box-shadow:0 1px 3px rgba(0,0,0,.08)}'
			. '.moforcoupon-hub-links a.card .t{font-size:15px;font-weight:600;color:#2271b1}'
			. '.moforcoupon-hub-links a.card .d{color:#646970;font-size:13px;margin-top:4px}'
			. '</style>';

		echo '</div>';
	}

	private static function stat_tile( string $label, string $value ): void {
		echo '<div class="tile"><div class="num">' . esc_html( $value ) . '</div>'
			. '<div class="lbl">' . esc_html( $label ) . '</div></div>';
	}

	private static function link_card( string $url, string $title, string $desc ): void {
		echo '<a class="card" href="' . esc_url( $url ) . '">'
			. '<div class="t">' . esc_html( $title ) . '</div>'
			. '<div class="d">' . esc_html( $desc ) . '</div></a>';
	}
}
