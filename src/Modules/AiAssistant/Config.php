<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\AiAssistant;

defined( 'ABSPATH' ) || exit;

/**
 * Shared config for the in-dashboard coupon AI assistant — ability whitelist,
 * system prompt and the destructive-action handler table. Deliberately
 * decoupled: the whitelist and handlers start empty and are populated by feature
 * modules (e.g. CouponCore) via the two filters below, so this module has no hard
 * dependency on any other module.
 */
final class Config {

	public const CAP  = 'manage_woocommerce';
	public const NAME = 'Moksa 優惠券 AI';

	/**
	 * Abilities exposed to the AI as tools. Destructive ones are intercepted by
	 * the Agent and routed through the human-confirm gate.
	 *
	 * @return array<int,string>
	 */
	public static function abilities(): array {
		return (array) apply_filters( 'moforcoupon_ai_assistant_abilities', [] );
	}

	/**
	 * Destructive handler table: ability id => [ prepare, apply ]. Populated by
	 * feature modules through the filter.
	 *
	 * @return array<string,array{prepare:callable,apply:callable}>
	 */
	public static function destructive_handlers(): array {
		return (array) apply_filters( 'moforcoupon_ai_destructive_handlers', [] );
	}

	/**
	 * @return array<int,string>
	 */
	public static function destructive_abilities(): array {
		return array_keys( self::destructive_handlers() );
	}

	public static function system_instruction(): string {
		$today = function_exists( 'wp_date' ) ? wp_date( 'Y-m-d' ) : gmdate( 'Y-m-d' );

		$base = __( '你是 WooCommerce 商家的「優惠券助手」。常用工具:list-coupons 列出、get-coupon 查明細、find-coupon-by-code 查代碼是否重複、coupon-usage-summary 查用量、get-coupon-report 查績效報表(以上唯讀);create-coupon 建立、update-coupon 更新、toggle-coupon 啟用停用、delete-coupon 刪除、bulk-generate-coupons 量產、extend-expiry 延長到期、duplicate-coupon 複製、create-tiered-coupon 建階梯券、apply-template 套用範本(以上破壞性)。破壞性操作只會「提出」,系統會要使用者按「確認執行」才生效,你不必再追問確認。建立優惠券時:折扣類型只有 percent(百分比)、fixed_cart(購物車固定額)、fixed_product(商品固定額);percent 的 amount 是百分比數字,不可超過 100;到期日用 YYYY-MM-DD。', 'moforcoupon' )
			. __( ' 進階能力(都透過 create-coupon / update-coupon 的 moforcoupon 設定物件,或專用捷徑):階梯折扣(tiers,或用 create-tiered-coupon)、折扣上限(discount_cap)、自動套用(auto_apply)、互斥不可疊加(exclude_coupons)、排程起訖、會員角色、購物車最低、商品 / 分類、收件地區、付款方式、星期時段、買 X 送 Y、免費贈品、運費覆寫,以及 26 種型別的 AND/OR「進階規則」(moforcoupon.advanced_rules)。不確定有哪些規則型別或商家的付款 / 運送代碼時,先用 list-rule-types / get-settings-schema / list-payment-gateways / list-shipping-zones / list-countries 查清楚;不確定範本時用 list-templates,別硬猜。建立前先用工具確認(如代碼是否重複),簡短清楚回覆;查不到或失敗就明說,不要編造。每次都以文字作結,不要只停在工具呼叫。', 'moforcoupon' );

		// Reply-language directive: follow the user, not a hardcoded language. A merchant on an
		// English / Simplified site should not get Traditional-Chinese answers.
		$base .= ' ' . __( '請一律用使用者提問的語言回覆(介面為繁體中文時即用繁體中文)。', 'moforcoupon' );

		// Date handling is language-neutral and always applies.
		$date_rule = sprintf(
			/* translators: %s: today's date in Y-m-d format. */
			__( '今天的日期是 %s。使用者只給月 / 日或相對日期(如「12 月 31 日」「下個月底」)時,一律解析成今年;若該日期今年已過才用明年。絕對不要使用過去的年份。', 'moforcoupon' ),
			$today
		);

		// The Taiwan "N 折" conversion maths only makes sense for Chinese-locale admins; it is
		// noise (and can derail answers) on non-Chinese sites, so gate it on the user locale.
		$zhe_rule = __( '台灣折扣換算(務必照公式逐步計算,不要憑直覺):「N 折」是折後要付的價格比例,percent 的 amount = 100 減去付款百分比。個位數「N 折」的付款百分比 = N×10,所以 amount = 100 − N×10:9 折→付 90→amount = 10;8 折→amount = 20;7 折→amount = 30;5 折→amount = 50。兩位數「NN 折」的付款百分比 = NN,所以 amount = 100 − NN:85 折→amount = 15;79 折→amount = 21。特別記住:「9 折」的 amount 一定是 10,不是 9、也不是 1。只有當使用者直接講折扣本身(「折 N%」「打 N% 的折扣」「省 N%」)或固定金額(「折 N 元」)時,才照字面填。', 'moforcoupon' );

		$locale = function_exists( 'get_user_locale' ) ? (string) get_user_locale() : 'zh_TW';
		$prompt = ( 0 === strncmp( $locale, 'zh', 2 ) )
			? $base . $zhe_rule . $date_rule
			: $base . $date_rule;

		return (string) apply_filters( 'moforcoupon_ai_system_instruction', $prompt );
	}
}
