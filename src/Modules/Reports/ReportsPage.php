<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\Reports;

defined( 'ABSPATH' ) || exit;

/**
 * Standalone "優惠券報表" admin page (submenu under WooCommerce). Read-only table of
 * per-coupon performance from ReportService. This page also seeds the future
 * standalone coupon-management screen.
 */
final class ReportsPage {

	private const SLUG  = 'moforcoupon-reports';
	private const CAP   = 'manage_woocommerce';
	private const NONCE = 'moforcoupon_reports_refresh';

	/** Public accessor so the AdminMenu module can reparent this page. */
	public static function slug(): string {
		return self::SLUG;
	}

	public static function register(): void {
		add_submenu_page(
			'woocommerce',
			__( '優惠券報表', 'moforcoupon' ),
			__( '優惠券報表', 'moforcoupon' ),
			self::CAP,
			self::SLUG,
			array( self::class, 'render' )
		);
	}

	public static function render(): void {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		echo '<div class="wrap moforcoupon-reports">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( '優惠券報表', 'moforcoupon' ) . '</h1>';
		echo '<hr class="wp-header-end">';
		echo '<p class="description">' . esc_html__( '依已付款訂單統計每張優惠券的使用訂單數與折抵總額(每小時快取一次)。', 'moforcoupon' ) . '</p>';
		self::render_table( admin_url( 'admin.php?page=' . self::SLUG ) );
		echo '</div>';
	}

	/**
	 * Render the report summary + table. Reused by the standalone page and by the
	 * dashboard (where reports now live). $page_url is the base for the refresh link.
	 */
	public static function render_table( string $page_url ): void {
		$rows           = ReportService::compute( self::refresh_requested() );
		$total_discount = 0.0;
		$total_orders   = 0;
		foreach ( $rows as $row ) {
			$total_discount += (float) $row['discount'];
			$total_orders   += (int) $row['orders'];
		}

		$refresh_url = wp_nonce_url( add_query_arg( 'refresh', '1', $page_url ), self::NONCE );

		echo '<p>';
		echo '<a href="' . esc_url( $refresh_url ) . '" class="button">' . esc_html__( '重新整理', 'moforcoupon' ) . '</a> ';
		printf(
			/* translators: 1: number of coupons, 2: total orders, 3: total discount. */
			esc_html__( '共 %1$s 張券被使用、%2$s 筆訂單,累計折抵 %3$s。', 'moforcoupon' ),
			'<strong>' . esc_html( (string) count( $rows ) ) . '</strong>',
			'<strong>' . esc_html( (string) $total_orders ) . '</strong>',
			'<strong>' . wp_kses_post( wc_price( $total_discount ) ) . '</strong>'
		);
		echo '</p>';

		self::render_overview();
		self::render_campaigns();

		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr>';
		foreach (
			array(
				__( '代碼', 'moforcoupon' ),
				__( '類型', 'moforcoupon' ),
				__( '折扣量', 'moforcoupon' ),
				__( '狀態', 'moforcoupon' ),
				__( '使用訂單數', 'moforcoupon' ),
				__( '折抵總額', 'moforcoupon' ),
				__( '使用次數 / 上限', 'moforcoupon' ),
				__( '到期日', 'moforcoupon' ),
			) as $heading
		) {
			echo '<th>' . esc_html( $heading ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		if ( array() === $rows ) {
			echo '<tr><td colspan="8">' . esc_html__( '尚無優惠券使用紀錄。', 'moforcoupon' ) . '</td></tr>';
		}
		foreach ( $rows as $row ) {
			$limit = (int) $row['usage_limit'] > 0 ? (string) (int) $row['usage_limit'] : '∞';
			echo '<tr>';
			echo '<td><code>' . esc_html( (string) $row['code'] ) . '</code></td>';
			echo '<td>' . esc_html( self::type_label( (string) $row['type'] ) ) . '</td>';
			$amount_display = ( '' !== $row['amount'] && 0.0 !== (float) $row['amount'] ) ? (string) $row['amount'] : '—';
			echo '<td>' . esc_html( $amount_display ) . '</td>';
			echo '<td>' . esc_html( self::status_label( (string) $row['status'] ) ) . '</td>';
			echo '<td>' . esc_html( (string) (int) $row['orders'] ) . '</td>';
			echo '<td>' . wp_kses_post( wc_price( (float) $row['discount'] ) ) . '</td>';
			echo '<td>' . esc_html( (string) (int) $row['usage_count'] . ' / ' . $limit ) . '</td>';
			echo '<td>' . esc_html( '' !== $row['expires'] ? (string) $row['expires'] : '—' ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	/** Top-line "coupons drove this much business" overview + a compact recent-days trend. */
	private static function render_overview(): void {
		$ov = ReportService::overview( 30 );

		echo '<div class="moforcoupon-report-overview" style="display:flex;flex-wrap:wrap;gap:12px;margin:12px 0;">';
		$cards = array(
			array( __( '近 30 天帶券訂單', 'moforcoupon' ), esc_html( (string) $ov['coupon_orders'] ) ),
			array( __( '帶券訂單營收', 'moforcoupon' ), wp_kses_post( wc_price( (float) $ov['coupon_revenue'] ) ) ),
			array( __( '折抵總額', 'moforcoupon' ), wp_kses_post( wc_price( (float) $ov['total_discount'] ) ) ),
			array( __( '平均客單價(帶券)', 'moforcoupon' ), wp_kses_post( wc_price( (float) $ov['avg_order_value'] ) ) ),
		);
		foreach ( $cards as $card ) {
			echo '<div style="flex:1 1 160px;min-width:160px;border:1px solid #e0e0e0;border-radius:8px;padding:12px;background:#fff;">';
			echo '<div style="font-size:12px;color:#666;">' . esc_html( (string) $card[0] ) . '</div>';
			echo '<div style="font-size:20px;font-weight:700;margin-top:4px;">' . $card[1] . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each value escaped above (esc_html / wc_price via wp_kses_post).
			echo '</div>';
		}
		echo '</div>';

		$recent = array_slice( $ov['daily'], -14 );
		if ( array() !== $recent ) {
			echo '<table class="wp-list-table widefat fixed striped" style="margin-bottom:18px;max-width:560px;">';
			echo '<thead><tr><th>' . esc_html__( '日期', 'moforcoupon' ) . '</th><th>' . esc_html__( '帶券訂單', 'moforcoupon' )
				. '</th><th>' . esc_html__( '折抵', 'moforcoupon' ) . '</th><th>' . esc_html__( '營收', 'moforcoupon' ) . '</th></tr></thead><tbody>';
			foreach ( $recent as $day ) {
				echo '<tr><td>' . esc_html( (string) $day['date'] ) . '</td>';
				echo '<td>' . esc_html( (string) (int) $day['orders'] ) . '</td>';
				echo '<td>' . wp_kses_post( wc_price( (float) $day['discount'] ) ) . '</td>';
				echo '<td>' . wp_kses_post( wc_price( (float) $day['revenue'] ) ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
	}

	/** Per-campaign rollup table (only shown when coupons carry campaign tags). */
	private static function render_campaigns(): void {
		$rows = ReportService::by_campaign();
		if ( array() === $rows ) {
			return;
		}
		echo '<h2 style="margin:18px 0 6px;font-size:14px;">' . esc_html__( '行銷活動成效', 'moforcoupon' ) . '</h2>';
		echo '<table class="wp-list-table widefat fixed striped" style="max-width:680px;margin-bottom:18px;">';
		echo '<thead><tr><th>' . esc_html__( '活動', 'moforcoupon' ) . '</th><th>' . esc_html__( '券數', 'moforcoupon' )
			. '</th><th>' . esc_html__( '使用訂單數', 'moforcoupon' ) . '</th><th>' . esc_html__( '折抵總額', 'moforcoupon' )
			. '</th><th>' . esc_html__( '帶券營收', 'moforcoupon' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			echo '<tr><td><strong>' . esc_html( (string) $row['campaign'] ) . '</strong></td>';
			echo '<td>' . esc_html( (string) (int) $row['coupons'] ) . '</td>';
			echo '<td>' . esc_html( (string) (int) $row['orders'] ) . '</td>';
			echo '<td>' . wp_kses_post( wc_price( (float) $row['discount'] ) ) . '</td>';
			echo '<td>' . wp_kses_post( wc_price( (float) $row['revenue'] ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	private static function refresh_requested(): bool {
		if ( isset( $_GET['refresh'], $_GET['_wpnonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );
			return (bool) wp_verify_nonce( $nonce, self::NONCE );
		}
		return false;
	}

	private static function type_label( string $type ): string {
		return \MoksaWeb\Moforcoupon\Support\CouponType::label( $type );
	}

	private static function status_label( string $status ): string {
		$map = array(
			'publish' => __( '啟用', 'moforcoupon' ),
			'draft'   => __( '停用', 'moforcoupon' ),
			'trash'   => __( '已刪除', 'moforcoupon' ),
			'deleted' => __( '已刪除', 'moforcoupon' ),
		);
		return $map[ $status ] ?? $status;
	}
}
