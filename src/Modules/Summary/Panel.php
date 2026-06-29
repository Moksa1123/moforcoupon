<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\Summary;

use MoksaWeb\Moforcoupon\Coupon\Meta\Keys;

defined( 'ABSPATH' ) || exit;

/**
 * A live "優惠券摘要" side metabox on the coupon editor. A small script reads the form as the
 * admin edits and renders a plain-language summary of what the coupon does, which advanced
 * features are on, and any detected conflicts (e.g. tiers enabled on a non-percent coupon).
 * All labels come from PHP so they stay translatable.
 */
final class Panel {

	public static function boot(): void {
		add_action( 'add_meta_boxes', array( self::class, 'add' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	public static function add(): void {
		add_meta_box(
			'moforcoupon-summary',
			__( '優惠券摘要', 'moforcoupon' ),
			array( self::class, 'render' ),
			'shop_coupon',
			'side',
			'high'
		);
	}

	public static function render(): void {
		echo '<div id="moforcoupon-summary-panel" class="moforcoupon-summary">'
			. '<p class="mfc-sum-empty">' . esc_html__( '編輯欄位時,這裡會即時顯示這張券的效果與衝突提醒。', 'moforcoupon' ) . '</p>'
			. '</div>';
	}

	public static function enqueue(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'shop_coupon' !== $screen->id ) {
			return;
		}
		$rel  = 'src/Modules/Summary/assets/js/summary.js';
		$path = \MOFORCOUPON_PLUGIN_DIR . $rel;
		$ver  = file_exists( $path ) ? (string) filemtime( $path ) : \MOFORCOUPON_VERSION;
		wp_enqueue_script( 'moforcoupon-summary', \MOFORCOUPON_PLUGIN_URL . $rel, array(), $ver, true );
		wp_localize_script(
			'moforcoupon-summary',
			'moforcouponSummary',
			array(
				'features' => self::features(),
				'i18n'     => self::i18n(),
			)
		);
		wp_register_style( 'moforcoupon-summary', false, array(), $ver );
		wp_enqueue_style( 'moforcoupon-summary' );
		wp_add_inline_style( 'moforcoupon-summary', self::css() );
	}

	/**
	 * Advanced-feature toggles to surface as "已啟用" chips: checkbox selector => label.
	 *
	 * @return array<int,array<string,string>>
	 */
	private static function features(): array {
		$map = array(
			Keys::SCHEDULE_ENABLED   => __( '排程', 'moforcoupon' ),
			Keys::CUST_ENABLED       => __( '顧客條件', 'moforcoupon' ),
			Keys::ROLE_ENABLED       => __( '會員角色', 'moforcoupon' ),
			Keys::DAYTIME_ENABLED    => __( '星期時段', 'moforcoupon' ),
			Keys::TIERS_ENABLED      => __( '階梯折扣', 'moforcoupon' ),
			Keys::RULES_ENABLED      => __( '進階規則', 'moforcoupon' ),
			Keys::SHIPREGION_ENABLED => __( '收件地區', 'moforcoupon' ),
			Keys::PAYMENT_ENABLED    => __( '付款方式', 'moforcoupon' ),
			Keys::AUTO_APPLY         => __( '自動套用', 'moforcoupon' ),
			Keys::GIFT_ENABLED       => __( '免費贈品', 'moforcoupon' ),
			Keys::STACK_EXCLUDE      => __( '不可疊加', 'moforcoupon' ),
			Keys::URL_ENABLED        => __( '網址套券', 'moforcoupon' ),
		);
		$out = array();
		foreach ( $map as $key => $label ) {
			$out[] = array(
				'sel'   => '#' . $key,
				'label' => $label,
			);
		}
		return $out;
	}

	/**
	 * @return array<string,string>
	 */
	private static function i18n(): array {
		return array(
			'percent'              => __( '百分比折扣', 'moforcoupon' ),
			'fixed_cart'           => __( '購物車固定折抵', 'moforcoupon' ),
			'fixed_product'        => __( '商品固定折抵', 'moforcoupon' ),
			'bogo'                 => __( '買 X 送 Y', 'moforcoupon' ),
			'moforcoupon_cashback' => __( '回饋金', 'moforcoupon' ),
			'cashbackTab'          => __( '訂單付款後依「回饋金」分頁設定回饋', 'moforcoupon' ),
			'discountHead'         => __( '折扣方式', 'moforcoupon' ),
			'featuresHead'         => __( '已啟用功能', 'moforcoupon' ),
			'conflictsHead'        => __( '提醒', 'moforcoupon' ),
			/* translators: %s: discount expressed as a Taiwan 折 number. */
			'zhe'                  => __( '約 %s 折', 'moforcoupon' ),
			/* translators: %s: percentage off. */
			'percentOff'           => __( '折 %s%%', 'moforcoupon' ),
			/* translators: %s: fixed amount off. */
			'amountOff'            => __( '折抵 %s', 'moforcoupon' ),
			'noExpiry'             => __( '無到期日', 'moforcoupon' ),
			/* translators: %s: expiry date. */
			'expiresOn'            => __( '到期:%s', 'moforcoupon' ),
			'tiersDrive'           => __( '折扣值由階梯表決定', 'moforcoupon' ),
			'bogoTab'              => __( '請到「買 X 送 Y」分頁設定觸發與獎勵', 'moforcoupon' ),
			'cTiersType'           => __( '階梯折扣僅適用「百分比折扣」,目前折扣類型不符。', 'moforcoupon' ),
			'cMinMax'              => __( '購物車最低金額大於最高金額,此券將永遠無法使用。', 'moforcoupon' ),
			'cPercentRange'        => __( '百分比折扣不可超過 100。', 'moforcoupon' ),
			'none'                 => __( '(尚未設定)', 'moforcoupon' ),
		);
	}

	private static function css(): string {
		return '.moforcoupon-summary .mfc-sum-head{font-weight:600;margin:10px 0 4px;font-size:12px;color:#1d2327;}'
			. '.moforcoupon-summary .mfc-sum-val{margin:0 0 6px;font-size:13px;}'
			. '.moforcoupon-summary .mfc-sum-chips{display:flex;flex-wrap:wrap;gap:5px;}'
			. '.moforcoupon-summary .mfc-sum-chip{background:#f0f6fc;color:#0a4b78;border-radius:10px;padding:1px 8px;font-size:11px;}'
			. '.moforcoupon-summary .mfc-sum-warn{background:#fcf0f1;color:#8a1f11;border-left:3px solid #d63638;padding:5px 8px;margin:4px 0;font-size:12px;border-radius:2px;}'
			. '.moforcoupon-summary .mfc-sum-info{color:#646970;font-size:12px;margin:3px 0;}';
	}
}
