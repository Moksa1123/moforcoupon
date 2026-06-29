<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\Templates;

use MoksaWeb\Moforcoupon\Coupon\Meta\Keys;

defined( 'ABSPATH' ) || exit;

/**
 * Built-in coupon templates — curated presets an admin can apply with one click to
 * spin up a pre-filled draft coupon (matching the Advanced Coupons "templates" UX).
 *
 * Each template is pure data: native WC_Coupon fields + our _moforcoupon_* meta.
 * `category` groups templates by marketing goal (new-customer / AOV / shipping / …)
 * so the page can section + filter them for quick selection. `requires` names the
 * feature module(s) a template depends on (a string or an array when it needs more
 * than one, e.g. 滿額免運 needs both shipping + conditions); the page disables apply
 * (and Applier refuses) when any required module is off, so a template never produces
 * a silently-inert coupon.
 */
final class Catalog {

	/**
	 * Marketing-goal buckets, in display order. Distinct from a coupon's discount
	 * mechanic (percent / fixed / BOGO) — that stays the per-card badge.
	 *
	 * @return array<string,string> category key => human label
	 */
	public static function categories(): array {
		return array(
			'acquisition' => __( '新客獲取', 'moforcoupon' ),
			'aov'         => __( '提高客單價', 'moforcoupon' ),
			'shipping'    => __( '運費優惠', 'moforcoupon' ),
			'promo'       => __( '促銷・限時', 'moforcoupon' ),
			'seasonal'    => __( '節慶・季節', 'moforcoupon' ),
			'bonus'       => __( '買送・贈品', 'moforcoupon' ),
			'member'      => __( '會員・回購', 'moforcoupon' ),
		);
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function all(): array {
		return array(

			// ── 新客獲取 ────────────────────────────────────────────────
			array(
				'id'       => 'new_customer',
				'category' => 'acquisition',
				'label'    => __( '新客首購 9 折', 'moforcoupon' ),
				'desc'     => __( '新顧客的第一筆訂單享 9 折(每位顧客限用一次)。', 'moforcoupon' ),
				'type_key' => 'percent',
				'requires' => 'conditions',
				'prefix'   => 'NEW',
				'native'   => array(
					'discount_type'        => 'percent',
					'amount'               => 10,
					'usage_limit_per_user' => 1,
					'description'          => __( '新客首購優惠', 'moforcoupon' ),
				),
				'meta'     => array(
					Keys::CUST_ENABLED    => 'yes',
					Keys::CUST_FIRST_ONLY => 'yes',
				),
			),
			array(
				'id'       => 'welcome_fixed',
				'category' => 'acquisition',
				'label'    => __( '新客滿 600 折 150', 'moforcoupon' ),
				'desc'     => __( '新顧客滿 600 元現折 150 元,鼓勵首購湊單(每人限一次)。', 'moforcoupon' ),
				'type_key' => 'fixed_cart',
				'requires' => 'conditions',
				'prefix'   => 'WELCOME',
				'native'   => array(
					'discount_type'        => 'fixed_cart',
					'amount'               => 150,
					'usage_limit_per_user' => 1,
					'description'          => __( '新客迎新折抵', 'moforcoupon' ),
				),
				'meta'     => array(
					Keys::CUST_ENABLED    => 'yes',
					Keys::CUST_FIRST_ONLY => 'yes',
					Keys::MIN_SUBTOTAL    => '600',
				),
			),
			array(
				'id'       => 'signup_link',
				'category' => 'acquisition',
				'label'    => __( '專屬連結領券', 'moforcoupon' ),
				'desc'     => __( '可用網址自動套券,適合 EDM／社群導流(套用後到「網址套券」分頁設定連結代稱)。', 'moforcoupon' ),
				'type_key' => 'percent',
				'requires' => 'url',
				'prefix'   => 'LINK',
				'native'   => array(
					'discount_type' => 'percent',
					'amount'        => 10,
					'description'   => __( '連結導流券', 'moforcoupon' ),
				),
				'meta'     => array(
					Keys::URL_ENABLED => 'yes',
				),
			),

			// ── 提高客單價 ──────────────────────────────────────────────
			array(
				'id'       => 'spend_save',
				'category' => 'aov',
				'label'    => __( '滿 1000 折 100', 'moforcoupon' ),
				'desc'     => __( '購物車滿 1000 元即可折抵 100 元。', 'moforcoupon' ),
				'type_key' => 'fixed_cart',
				'requires' => 'conditions',
				'prefix'   => 'SAVE',
				'native'   => array(
					'discount_type' => 'fixed_cart',
					'amount'        => 100,
					'description'   => __( '滿額折抵', 'moforcoupon' ),
				),
				'meta'     => array(
					Keys::MIN_SUBTOTAL => '1000',
				),
			),
			array(
				'id'       => 'spend_save_big',
				'category' => 'aov',
				'label'    => __( '滿 3000 折 400', 'moforcoupon' ),
				'desc'     => __( '購物車滿 3000 元折抵 400 元,推高客單價。', 'moforcoupon' ),
				'type_key' => 'fixed_cart',
				'requires' => 'conditions',
				'prefix'   => 'SAVE',
				'native'   => array(
					'discount_type' => 'fixed_cart',
					'amount'        => 400,
					'description'   => __( '大額滿減', 'moforcoupon' ),
				),
				'meta'     => array(
					Keys::MIN_SUBTOTAL => '3000',
				),
			),
			array(
				'id'       => 'spend_percent',
				'category' => 'aov',
				'label'    => __( '滿 2000 享 88 折', 'moforcoupon' ),
				'desc'     => __( '購物車滿 2000 元即享 88 折。', 'moforcoupon' ),
				'type_key' => 'percent',
				'requires' => 'conditions',
				'prefix'   => 'OVER',
				'native'   => array(
					'discount_type' => 'percent',
					'amount'        => 12,
					'description'   => __( '滿額折扣', 'moforcoupon' ),
				),
				'meta'     => array(
					Keys::MIN_SUBTOTAL => '2000',
				),
			),
			array(
				'id'       => 'bulk_qty',
				'category' => 'aov',
				'label'    => __( '滿 3 件 9 折', 'moforcoupon' ),
				'desc'     => __( '購物車滿 3 件商品即享 9 折。', 'moforcoupon' ),
				'type_key' => 'percent',
				'requires' => 'conditions',
				'prefix'   => 'BULK',
				'native'   => array(
					'discount_type' => 'percent',
					'amount'        => 10,
					'description'   => __( '量購優惠', 'moforcoupon' ),
				),
				'meta'     => array(
					Keys::MIN_QTY => '3',
				),
			),

			// ── 運費優惠 ────────────────────────────────────────────────
			array(
				'id'       => 'free_ship',
				'category' => 'shipping',
				'label'    => __( '免運券', 'moforcoupon' ),
				'desc'     => __( '套用後所有運送方式免運費(透過運費覆寫)。', 'moforcoupon' ),
				'type_key' => 'fixed_cart',
				'requires' => 'shipping',
				'prefix'   => 'FREESHIP',
				'native'   => array(
					'discount_type' => 'fixed_cart',
					'amount'        => 0,
					'description'   => __( '免運優惠', 'moforcoupon' ),
				),
				'meta'     => array(
					Keys::SHIP_MODE => 'free',
				),
			),
			array(
				'id'       => 'free_ship_min',
				'category' => 'shipping',
				'label'    => __( '滿 800 免運', 'moforcoupon' ),
				'desc'     => __( '購物車滿 800 元才享免運,兼顧運費成本與湊單。', 'moforcoupon' ),
				'type_key' => 'fixed_cart',
				'requires' => array( 'shipping', 'conditions' ),
				'prefix'   => 'FREESHIP',
				'native'   => array(
					'discount_type' => 'fixed_cart',
					'amount'        => 0,
					'description'   => __( '滿額免運', 'moforcoupon' ),
				),
				'meta'     => array(
					Keys::SHIP_MODE    => 'free',
					Keys::MIN_SUBTOTAL => '800',
				),
			),
			array(
				'id'       => 'half_ship',
				'category' => 'shipping',
				'label'    => __( '運費半價', 'moforcoupon' ),
				'desc'     => __( '套用後所有運送方式運費打 5 折。', 'moforcoupon' ),
				'type_key' => 'fixed_cart',
				'requires' => 'shipping',
				'prefix'   => 'SHIP',
				'native'   => array(
					'discount_type' => 'fixed_cart',
					'amount'        => 0,
					'description'   => __( '運費折扣', 'moforcoupon' ),
				),
				'meta'     => array(
					Keys::SHIP_MODE  => 'percent',
					Keys::SHIP_VALUE => '50',
				),
			),

			// ── 促銷・限時 ──────────────────────────────────────────────
			array(
				'id'       => 'storewide',
				'category' => 'promo',
				'label'    => __( '全站 85 折', 'moforcoupon' ),
				'desc'     => __( '全站商品一律 85 折,適合大促銷。', 'moforcoupon' ),
				'type_key' => 'percent',
				'requires' => '',
				'prefix'   => 'SALE',
				'native'   => array(
					'discount_type' => 'percent',
					'amount'        => 15,
					'description'   => __( '全站促銷', 'moforcoupon' ),
				),
				'meta'     => array(),
			),
			array(
				'id'       => 'weekend_sale',
				'category' => 'promo',
				'label'    => __( '週末限定 8 折', 'moforcoupon' ),
				'desc'     => __( '僅在週六、週日可用的 8 折券(依商店時區判定)。', 'moforcoupon' ),
				'type_key' => 'percent',
				'requires' => 'conditions',
				'prefix'   => 'WEEKEND',
				'native'   => array(
					'discount_type' => 'percent',
					'amount'        => 20,
					'description'   => __( '週末促銷', 'moforcoupon' ),
				),
				'meta'     => array(
					Keys::DAYTIME_ENABLED => 'yes',
					Keys::DAYTIME_DAYS    => array( 0, 6 ),
				),
			),
			array(
				'id'       => 'happy_hour',
				'category' => 'promo',
				'label'    => __( '歡樂時光 9 折', 'moforcoupon' ),
				'desc'     => __( '每天 14:00–17:00 限定 9 折,帶動離峰時段下單。', 'moforcoupon' ),
				'type_key' => 'percent',
				'requires' => 'conditions',
				'prefix'   => 'HAPPY',
				'native'   => array(
					'discount_type' => 'percent',
					'amount'        => 10,
					'description'   => __( '離峰時段優惠', 'moforcoupon' ),
				),
				'meta'     => array(
					Keys::DAYTIME_ENABLED => 'yes',
					Keys::DAYTIME_DAYS    => array( 0, 1, 2, 3, 4, 5, 6 ),
					Keys::DAYTIME_START   => '14:00',
					Keys::DAYTIME_END     => '17:00',
				),
			),
			array(
				'id'       => 'flash_sale',
				'category' => 'promo',
				'label'    => __( '限時 75 折', 'moforcoupon' ),
				'desc'     => __( '短期限時促銷(套用後到「排程」分頁設定起訖時間即自動上下架)。', 'moforcoupon' ),
				'type_key' => 'percent',
				'requires' => 'conditions',
				'prefix'   => 'FLASH',
				'native'   => array(
					'discount_type' => 'percent',
					'amount'        => 25,
					'description'   => __( '限時快閃', 'moforcoupon' ),
				),
				'meta'     => array(
					Keys::SCHEDULE_ENABLED => 'yes',
				),
			),

			// ── 節慶・季節(購物節 × 折扣級距,搭配建立視窗的「到期日」即為限時券) ──
			array(
				'id'       => 'black_friday',
				'category' => 'seasonal',
				'label'    => __( '黑色星期五 8 折', 'moforcoupon' ),
				'desc'     => __( '黑色星期五大檔 8 折券(建立時設定到期日即成限時券)。', 'moforcoupon' ),
				'type_key' => 'percent',
				'requires' => '',
				'prefix'   => 'BF',
				'native'   => array(
					'discount_type' => 'percent',
					'amount'        => 20,
					'description'   => __( '黑色星期五優惠', 'moforcoupon' ),
				),
				'meta'     => array(),
			),
			array(
				'id'       => 'cyber_monday',
				'category' => 'seasonal',
				'label'    => __( '網購星期一 85 折', 'moforcoupon' ),
				'desc'     => __( '網購星期一(Cyber Monday)線上專屬 85 折。', 'moforcoupon' ),
				'type_key' => 'percent',
				'requires' => '',
				'prefix'   => 'CYBER',
				'native'   => array(
					'discount_type' => 'percent',
					'amount'        => 15,
					'description'   => __( '網購星期一優惠', 'moforcoupon' ),
				),
				'meta'     => array(),
			),
			array(
				'id'       => 'double_eleven',
				'category' => 'seasonal',
				'label'    => __( '雙11 狂歡 8 折', 'moforcoupon' ),
				'desc'     => __( '雙十一購物節 8 折券。', 'moforcoupon' ),
				'type_key' => 'percent',
				'requires' => '',
				'prefix'   => 'DOUBLE11',
				'native'   => array(
					'discount_type' => 'percent',
					'amount'        => 20,
					'description'   => __( '雙11 優惠', 'moforcoupon' ),
				),
				'meta'     => array(),
			),
			array(
				'id'       => 'double_twelve',
				'category' => 'seasonal',
				'label'    => __( '雙12 限時 88 折', 'moforcoupon' ),
				'desc'     => __( '雙十二接力檔 88 折券。', 'moforcoupon' ),
				'type_key' => 'percent',
				'requires' => '',
				'prefix'   => 'DOUBLE12',
				'native'   => array(
					'discount_type' => 'percent',
					'amount'        => 12,
					'description'   => __( '雙12 優惠', 'moforcoupon' ),
				),
				'meta'     => array(),
			),
			array(
				'id'       => 'anniversary',
				'category' => 'seasonal',
				'label'    => __( '週年慶 9 折', 'moforcoupon' ),
				'desc'     => __( '週年慶全館 9 折券。', 'moforcoupon' ),
				'type_key' => 'percent',
				'requires' => '',
				'prefix'   => 'ANNIV',
				'native'   => array(
					'discount_type' => 'percent',
					'amount'        => 10,
					'description'   => __( '週年慶優惠', 'moforcoupon' ),
				),
				'meta'     => array(),
			),
			array(
				'id'       => 'christmas',
				'category' => 'seasonal',
				'label'    => __( '聖誕節 85 折', 'moforcoupon' ),
				'desc'     => __( '聖誕檔期 85 折券。', 'moforcoupon' ),
				'type_key' => 'percent',
				'requires' => '',
				'prefix'   => 'XMAS',
				'native'   => array(
					'discount_type' => 'percent',
					'amount'        => 15,
					'description'   => __( '聖誕優惠', 'moforcoupon' ),
				),
				'meta'     => array(),
			),
			array(
				'id'       => 'christmas_free_ship',
				'category' => 'seasonal',
				'label'    => __( '聖誕節免運', 'moforcoupon' ),
				'desc'     => __( '聖誕檔期全站免運券(透過運費覆寫)。', 'moforcoupon' ),
				'type_key' => 'fixed_cart',
				'requires' => 'shipping',
				'prefix'   => 'XMASSHIP',
				'native'   => array(
					'discount_type' => 'fixed_cart',
					'amount'        => 0,
					'description'   => __( '聖誕免運', 'moforcoupon' ),
				),
				'meta'     => array(
					Keys::SHIP_MODE => 'free',
				),
			),
			array(
				'id'       => 'new_year',
				'category' => 'seasonal',
				'label'    => __( '跨年新年 88 折', 'moforcoupon' ),
				'desc'     => __( '跨年 / 元旦新年 88 折券。', 'moforcoupon' ),
				'type_key' => 'percent',
				'requires' => '',
				'prefix'   => 'NEWYEAR',
				'native'   => array(
					'discount_type' => 'percent',
					'amount'        => 12,
					'description'   => __( '新年優惠', 'moforcoupon' ),
				),
				'meta'     => array(),
			),
			array(
				'id'       => 'lunar_new_year',
				'category' => 'seasonal',
				'label'    => __( '農曆春節 紅包折 200', 'moforcoupon' ),
				'desc'     => __( '農曆春節紅包券,直接折抵 200 元。', 'moforcoupon' ),
				'type_key' => 'fixed_cart',
				'requires' => '',
				'prefix'   => 'CNY',
				'native'   => array(
					'discount_type' => 'fixed_cart',
					'amount'        => 200,
					'description'   => __( '春節紅包折抵', 'moforcoupon' ),
				),
				'meta'     => array(),
			),
			array(
				'id'       => 'valentines',
				'category' => 'seasonal',
				'label'    => __( '情人節 88 折', 'moforcoupon' ),
				'desc'     => __( '情人節檔期 88 折券。', 'moforcoupon' ),
				'type_key' => 'percent',
				'requires' => '',
				'prefix'   => 'LOVE',
				'native'   => array(
					'discount_type' => 'percent',
					'amount'        => 12,
					'description'   => __( '情人節優惠', 'moforcoupon' ),
				),
				'meta'     => array(),
			),
			array(
				'id'       => 'mothers_day',
				'category' => 'seasonal',
				'label'    => __( '母親節 9 折', 'moforcoupon' ),
				'desc'     => __( '母親節檔期 9 折券。', 'moforcoupon' ),
				'type_key' => 'percent',
				'requires' => '',
				'prefix'   => 'MOM',
				'native'   => array(
					'discount_type' => 'percent',
					'amount'        => 10,
					'description'   => __( '母親節優惠', 'moforcoupon' ),
				),
				'meta'     => array(),
			),
			array(
				'id'       => 'fathers_day',
				'category' => 'seasonal',
				'label'    => __( '父親節 9 折', 'moforcoupon' ),
				'desc'     => __( '父親節檔期 9 折券。', 'moforcoupon' ),
				'type_key' => 'percent',
				'requires' => '',
				'prefix'   => 'DAD',
				'native'   => array(
					'discount_type' => 'percent',
					'amount'        => 10,
					'description'   => __( '父親節優惠', 'moforcoupon' ),
				),
				'meta'     => array(),
			),
			array(
				'id'       => 'mid_autumn',
				'category' => 'seasonal',
				'label'    => __( '中秋節 9 折', 'moforcoupon' ),
				'desc'     => __( '中秋檔期 9 折券。', 'moforcoupon' ),
				'type_key' => 'percent',
				'requires' => '',
				'prefix'   => 'MOON',
				'native'   => array(
					'discount_type' => 'percent',
					'amount'        => 10,
					'description'   => __( '中秋優惠', 'moforcoupon' ),
				),
				'meta'     => array(),
			),
			array(
				'id'       => 'double_eleven_free_ship',
				'category' => 'seasonal',
				'label'    => __( '雙11 免運', 'moforcoupon' ),
				'desc'     => __( '雙十一全站免運券(透過運費覆寫)。', 'moforcoupon' ),
				'type_key' => 'fixed_cart',
				'requires' => 'shipping',
				'prefix'   => 'D11SHIP',
				'native'   => array(
					'discount_type' => 'fixed_cart',
					'amount'        => 0,
					'description'   => __( '雙11 免運', 'moforcoupon' ),
				),
				'meta'     => array(
					Keys::SHIP_MODE => 'free',
				),
			),

			array(
				'id'       => 'mid_year_618',
				'category' => 'seasonal',
				'label'    => __( '618 年中慶 8 折', 'moforcoupon' ),
				'desc'     => __( '618 年中購物節大檔 8 折券,上半年最重要的折扣戰。', 'moforcoupon' ),
				'type_key' => 'percent',
				'requires' => '',
				'prefix'   => 'MID618',
				'native'   => array(
					'discount_type' => 'percent',
					'amount'        => 20,
					'description'   => __( '618 年中慶', 'moforcoupon' ),
				),
				'meta'     => array(),
			),
			array(
				'id'       => 'womens_day',
				'category' => 'seasonal',
				'label'    => __( '38 婦女節 88 折', 'moforcoupon' ),
				'desc'     => __( '38 女王節 88 折券,主攻美妝保養與寵愛自己。', 'moforcoupon' ),
				'type_key' => 'percent',
				'requires' => '',
				'prefix'   => 'WOMEN',
				'native'   => array(
					'discount_type' => 'percent',
					'amount'        => 12,
					'description'   => __( '38 婦女節優惠', 'moforcoupon' ),
				),
				'meta'     => array(),
			),
			array(
				'id'       => 'qixi',
				'category' => 'seasonal',
				'label'    => __( '七夕情人節 88 折', 'moforcoupon' ),
				'desc'     => __( '七夕情人節 88 折券,情侶送禮檔期。', 'moforcoupon' ),
				'type_key' => 'percent',
				'requires' => '',
				'prefix'   => 'QIXI',
				'native'   => array(
					'discount_type' => 'percent',
					'amount'        => 12,
					'description'   => __( '七夕情人節優惠', 'moforcoupon' ),
				),
				'meta'     => array(),
			),
			array(
				'id'       => 'childrens_day',
				'category' => 'seasonal',
				'label'    => __( '兒童節折 100', 'moforcoupon' ),
				'desc'     => __( '兒童節親子檔期直接折 100 元,主打母嬰與親子商品。', 'moforcoupon' ),
				'type_key' => 'fixed_cart',
				'requires' => '',
				'prefix'   => 'KIDS',
				'native'   => array(
					'discount_type' => 'fixed_cart',
					'amount'        => 100,
					'description'   => __( '兒童節折抵', 'moforcoupon' ),
				),
				'meta'     => array(),
			),
			array(
				'id'       => 'dragon_boat',
				'category' => 'seasonal',
				'label'    => __( '端午節 9 折', 'moforcoupon' ),
				'desc'     => __( '端午節檔期 9 折券,粽子禮盒與消暑商品。', 'moforcoupon' ),
				'type_key' => 'percent',
				'requires' => '',
				'prefix'   => 'DRAGON',
				'native'   => array(
					'discount_type' => 'percent',
					'amount'        => 10,
					'description'   => __( '端午節優惠', 'moforcoupon' ),
				),
				'meta'     => array(),
			),
			array(
				'id'       => 'national_day',
				'category' => 'seasonal',
				'label'    => __( '雙十國慶 9 折', 'moforcoupon' ),
				'desc'     => __( '雙十國慶連假 9 折券。', 'moforcoupon' ),
				'type_key' => 'percent',
				'requires' => '',
				'prefix'   => 'NATION',
				'native'   => array(
					'discount_type' => 'percent',
					'amount'        => 10,
					'description'   => __( '雙十國慶優惠', 'moforcoupon' ),
				),
				'meta'     => array(),
			),
			array(
				'id'       => 'halloween',
				'category' => 'seasonal',
				'label'    => __( '萬聖節 85 折', 'moforcoupon' ),
				'desc'     => __( '萬聖節檔期 85 折券。', 'moforcoupon' ),
				'type_key' => 'percent',
				'requires' => '',
				'prefix'   => 'HALLOWEEN',
				'native'   => array(
					'discount_type' => 'percent',
					'amount'        => 15,
					'description'   => __( '萬聖節優惠', 'moforcoupon' ),
				),
				'meta'     => array(),
			),
			array(
				'id'       => 'back_to_school',
				'category' => 'seasonal',
				'label'    => __( '開學季 9 折', 'moforcoupon' ),
				'desc'     => __( '開學季 9 折券,文具 3C 與生活用品。', 'moforcoupon' ),
				'type_key' => 'percent',
				'requires' => '',
				'prefix'   => 'SCHOOL',
				'native'   => array(
					'discount_type' => 'percent',
					'amount'        => 10,
					'description'   => __( '開學季優惠', 'moforcoupon' ),
				),
				'meta'     => array(),
			),

			// ── 買送・贈品 ──────────────────────────────────────────────
			array(
				'id'       => 'bogo',
				'category' => 'bonus',
				'label'    => __( '買二送一', 'moforcoupon' ),
				'desc'     => __( '買 2 件指定商品送 1 件(套用後到「買 X 送 Y」分頁指定商品)。', 'moforcoupon' ),
				'type_key' => 'moforcoupon_bogo',
				'requires' => 'bogo',
				'prefix'   => 'BOGO',
				'native'   => array(
					'discount_type' => 'moforcoupon_bogo',
					'amount'        => 0,
					'description'   => __( '買二送一', 'moforcoupon' ),
				),
				'meta'     => array(
					Keys::BOGO_TRIGGER_QTY  => '2',
					Keys::BOGO_REWARD_QTY   => '1',
					Keys::BOGO_REWARD_MODE  => 'free',
					Keys::BOGO_DEAL_MODE    => 'repeat',
					Keys::BOGO_REPEAT_LIMIT => '0',
				),
			),
			array(
				'id'       => 'second_half',
				'category' => 'bonus',
				'label'    => __( '第二件半價', 'moforcoupon' ),
				'desc'     => __( '買 1 件、第二件半價(套用後到「買 X 送 Y」分頁指定商品)。', 'moforcoupon' ),
				'type_key' => 'moforcoupon_bogo',
				'requires' => 'bogo',
				'prefix'   => 'SECOND',
				'native'   => array(
					'discount_type' => 'moforcoupon_bogo',
					'amount'        => 0,
					'description'   => __( '第二件半價', 'moforcoupon' ),
				),
				'meta'     => array(
					Keys::BOGO_TRIGGER_QTY  => '1',
					Keys::BOGO_REWARD_QTY   => '1',
					Keys::BOGO_REWARD_MODE  => 'percent',
					Keys::BOGO_REWARD_VALUE => '50',
					Keys::BOGO_DEAL_MODE    => 'repeat',
					Keys::BOGO_REPEAT_LIMIT => '0',
				),
			),
			array(
				'id'       => 'free_gift',
				'category' => 'bonus',
				'label'    => __( '滿 1500 送贈品', 'moforcoupon' ),
				'desc'     => __( '購物車滿 1500 元自動加贈一件商品(套用後到「免費贈品」分頁指定贈品)。', 'moforcoupon' ),
				'type_key' => 'fixed_cart',
				'requires' => array( 'freegift', 'conditions' ),
				'prefix'   => 'GIFT',
				'native'   => array(
					'discount_type' => 'fixed_cart',
					'amount'        => 0,
					'description'   => __( '滿額贈品', 'moforcoupon' ),
				),
				'meta'     => array(
					Keys::GIFT_ENABLED => 'yes',
					Keys::GIFT_MODE    => 'free',
					Keys::GIFT_QTY     => '1',
					Keys::MIN_SUBTOTAL => '1500',
				),
			),

			// ── 會員・回購 ──────────────────────────────────────────────
			array(
				'id'       => 'member_only',
				'category' => 'member',
				'label'    => __( '會員專屬 9 折', 'moforcoupon' ),
				'desc'     => __( '僅限已登入的會員(customer 角色)使用的 9 折券。', 'moforcoupon' ),
				'type_key' => 'percent',
				'requires' => 'conditions',
				'prefix'   => 'MEMBER',
				'native'   => array(
					'discount_type' => 'percent',
					'amount'        => 10,
					'description'   => __( '會員專屬優惠', 'moforcoupon' ),
				),
				'meta'     => array(
					Keys::ROLE_ENABLED => 'yes',
					Keys::ROLE_TYPE    => 'allowed',
					Keys::ROLE_LIST    => array( 'customer' ),
				),
			),
			array(
				'id'       => 'returning',
				'category' => 'member',
				'label'    => __( '回購禮 88 折', 'moforcoupon' ),
				'desc'     => __( '回饋已完成至少 1 筆訂單的老顧客 88 折。', 'moforcoupon' ),
				'type_key' => 'percent',
				'requires' => 'conditions',
				'prefix'   => 'AGAIN',
				'native'   => array(
					'discount_type' => 'percent',
					'amount'        => 12,
					'description'   => __( '回購優惠', 'moforcoupon' ),
				),
				'meta'     => array(
					Keys::CUST_ENABLED    => 'yes',
					Keys::CUST_MIN_ORDERS => '1',
				),
			),
			array(
				'id'       => 'big_spender',
				'category' => 'member',
				'label'    => __( '高消費會員 8 折', 'moforcoupon' ),
				'desc'     => __( '回饋累積消費滿 10000 元的高價值客戶 8 折。', 'moforcoupon' ),
				'type_key' => 'percent',
				'requires' => 'conditions',
				'prefix'   => 'TOPVIP',
				'native'   => array(
					'discount_type' => 'percent',
					'amount'        => 20,
					'description'   => __( '高消費會員優惠', 'moforcoupon' ),
				),
				'meta'     => array(
					Keys::CUST_ENABLED   => 'yes',
					Keys::CUST_MIN_SPENT => '10000',
				),
			),
			// ── 階梯折扣 / 進階規則 / 區域 / 付款 等新功能展示 ──────────────
			array(
				'id'       => 'tiered_aov',
				'category' => 'aov',
				'label'    => __( '累積消費分級折扣', 'moforcoupon' ),
				'desc'     => __( '同一張券依購物車金額分級:滿 1000 折 10%、滿 2000 折 15%、滿 3000 折 20%,自動取最高級距。', 'moforcoupon' ),
				'type_key' => 'percent',
				'requires' => 'discounttiers',
				'prefix'   => 'TIER',
				'native'   => array(
					'discount_type' => 'percent',
					'amount'        => 10,
					'description'   => __( '累積消費分級折扣', 'moforcoupon' ),
				),
				'meta'     => array(
					Keys::TIERS_ENABLED     => 'yes',
					Keys::TIERS_TARGET_MODE => 'all',
					Keys::TIERS             => '[{"min_subtotal":1000,"min_qty":0,"percent":10},{"min_subtotal":2000,"min_qty":0,"percent":15},{"min_subtotal":3000,"min_qty":0,"percent":20}]',
				),
			),
			array(
				'id'       => 'advanced_combo',
				'category' => 'aov',
				'label'    => __( '進階規則:滿 600 且 2 件折 80', 'moforcoupon' ),
				'desc'     => __( '示範進階規則 AND 組合:購物車滿 600 元「且」滿 2 件才折 80 元。可到「進階規則」分頁再擴充。', 'moforcoupon' ),
				'type_key' => 'fixed_cart',
				'requires' => 'advrules',
				'prefix'   => 'COMBO',
				'native'   => array(
					'discount_type' => 'fixed_cart',
					'amount'        => 80,
					'description'   => __( '滿額且滿件折抵', 'moforcoupon' ),
				),
				'meta'     => array(
					Keys::RULES_ENABLED => 'yes',
					Keys::RULES         => '{"match":"all","groups":[{"match":"all","rules":[{"type":"subtotal","op":"gte","value":"600"},{"type":"quantity","op":"gte","value":"2"}]}]}',
				),
			),
			array(
				'id'       => 'winback',
				'category' => 'member',
				'label'    => __( '近 30 天未購回訪禮', 'moforcoupon' ),
				'desc'     => __( '老顧客距上次下單超過 30 天即可折 100 元,喚回沉睡客(限已登入會員,訪客無購買紀錄)。', 'moforcoupon' ),
				'type_key' => 'fixed_cart',
				'requires' => 'advrules',
				'prefix'   => 'BACK',
				'native'   => array(
					'discount_type' => 'fixed_cart',
					'amount'        => 100,
					'description'   => __( '回訪喚回禮', 'moforcoupon' ),
				),
				'meta'     => array(
					Keys::RULES_ENABLED => 'yes',
					Keys::RULES         => '{"match":"all","groups":[{"match":"all","rules":[{"type":"hours_since_last_order","op":"gte","value":"720"}]}]}',
				),
			),
			array(
				'id'       => 'tw_mainland_freeship',
				'category' => 'shipping',
				'label'    => __( '本島免運(限台灣)', 'moforcoupon' ),
				'desc'     => __( '收件地址為台灣才免運,海外不適用。', 'moforcoupon' ),
				'type_key' => 'fixed_cart',
				'requires' => array( 'shipping', 'conditions' ),
				'prefix'   => 'TWSHIP',
				'native'   => array(
					'discount_type' => 'fixed_cart',
					'amount'        => 0,
					'description'   => __( '本島免運', 'moforcoupon' ),
				),
				'meta'     => array(
					Keys::SHIP_MODE            => 'free',
					Keys::SHIPREGION_ENABLED   => 'yes',
					Keys::SHIPREGION_MODE      => 'allow',
					Keys::SHIPREGION_COUNTRIES => array( 'TW' ),
				),
			),
			array(
				'id'       => 'heavy_freeship',
				'category' => 'shipping',
				'label'    => __( '重量滿 5kg 免運', 'moforcoupon' ),
				'desc'     => __( '購物車總重量滿 5 公斤免運,適合食品 / 寵糧等重貨(套用後請依實際商品重量調整門檻)。', 'moforcoupon' ),
				'type_key' => 'fixed_cart',
				'requires' => array( 'shipping', 'advrules' ),
				'prefix'   => 'HEAVY',
				'native'   => array(
					'discount_type' => 'fixed_cart',
					'amount'        => 0,
					'description'   => __( '重量免運', 'moforcoupon' ),
				),
				'meta'     => array(
					Keys::SHIP_MODE     => 'free',
					Keys::RULES_ENABLED => 'yes',
					Keys::RULES         => '{"match":"all","groups":[{"match":"all","rules":[{"type":"cart_weight","op":"gte","value":"5"}]}]}',
				),
			),
			array(
				'id'       => 'percent_capped',
				'category' => 'aov',
				'label'    => __( '全站 85 折(上限 500)', 'moforcoupon' ),
				'desc'     => __( '全站 85 折,但單筆折抵最多 500 元,保護高客單訂單的毛利。', 'moforcoupon' ),
				'type_key' => 'percent',
				'requires' => 'discountcap',
				'prefix'   => 'CAPD',
				'native'   => array(
					'discount_type' => 'percent',
					'amount'        => 15,
					'description'   => __( '折扣上限保護', 'moforcoupon' ),
				),
				'meta'     => array(
					Keys::DISCOUNT_CAP => '500',
				),
			),
			array(
				'id'       => 'auto_sitewide',
				'category' => 'promo',
				'label'    => __( '自動套用全站 95 折', 'moforcoupon' ),
				'desc'     => __( '顧客進購物車自動帶入的全站 95 折,免輸入代碼(套用後到排程分頁設定活動起訖,避免變成永久折扣)。', 'moforcoupon' ),
				'type_key' => 'percent',
				'requires' => array( 'autoapply', 'conditions' ),
				'prefix'   => 'AUTO',
				'native'   => array(
					'discount_type' => 'percent',
					'amount'        => 5,
					'description'   => __( '自動套用全站折扣', 'moforcoupon' ),
				),
				'meta'     => array(
					Keys::AUTO_APPLY       => 'yes',
					Keys::SCHEDULE_ENABLED => 'yes',
				),
			),
			array(
				'id'       => 'vip_exclusive',
				'category' => 'member',
				'label'    => __( 'VIP 不可疊加 8 折', 'moforcoupon' ),
				'desc'     => __( '會員(customer 角色)專屬 8 折,且不可與其他優惠券並用。', 'moforcoupon' ),
				'type_key' => 'percent',
				'requires' => array( 'conditions', 'stacking' ),
				'prefix'   => 'VIP',
				'native'   => array(
					'discount_type' => 'percent',
					'amount'        => 20,
					'description'   => __( 'VIP 專屬不可疊加', 'moforcoupon' ),
				),
				'meta'     => array(
					Keys::ROLE_ENABLED  => 'yes',
					Keys::ROLE_TYPE     => 'allowed',
					Keys::ROLE_LIST     => array( 'customer' ),
					Keys::STACK_EXCLUDE => 'yes',
				),
			),
			array(
				'id'       => 'payment_specific',
				'category' => 'promo',
				'label'    => __( '指定付款方式專屬折 50', 'moforcoupon' ),
				'desc'     => __( '限定特定付款方式才可用的折 50 券(套用後到「付款方式」分頁選擇要限定的付款方式,如 LINE Pay / 街口)。', 'moforcoupon' ),
				'type_key' => 'fixed_cart',
				'requires' => 'conditions',
				'prefix'   => 'PAY',
				'native'   => array(
					'discount_type' => 'fixed_cart',
					'amount'        => 50,
					'description'   => __( '付款方式專屬折抵', 'moforcoupon' ),
				),
				'meta'     => array(
					Keys::PAYMENT_ENABLED => 'yes',
					Keys::PAYMENT_MODE    => 'allow',
				),
			),
			array(
				'id'       => 'category_required',
				'category' => 'promo',
				'label'    => __( '必含指定分類折扣', 'moforcoupon' ),
				'desc'     => __( '購物車含指定分類商品才享 85 折,適合交叉銷售 / 組合促銷(套用後到「商品條件」分頁選擇必含分類)。', 'moforcoupon' ),
				'type_key' => 'percent',
				'requires' => 'conditions',
				'prefix'   => 'BUNDLE',
				'native'   => array(
					'discount_type' => 'percent',
					'amount'        => 15,
					'description'   => __( '必含分類折扣', 'moforcoupon' ),
				),
				'meta'     => array(
					Keys::REQ_CATEGORIES_MODE => 'any',
				),
			),
			array(
				'id'       => 'bogo_category_once',
				'category' => 'bonus',
				'label'    => __( '指定分類買三送一', 'moforcoupon' ),
				'desc'     => __( '指定分類買 3 件送 1 件(單次,不重複疊送;套用後到「買 X 送 Y」分頁指定分類)。', 'moforcoupon' ),
				'type_key' => 'moforcoupon_bogo',
				'requires' => 'bogo',
				'prefix'   => 'BGIFT',
				'native'   => array(
					'discount_type' => 'moforcoupon_bogo',
					'amount'        => 0,
					'description'   => __( '指定分類買三送一', 'moforcoupon' ),
				),
				'meta'     => array(
					Keys::BOGO_TRIGGER_QTY  => '3',
					Keys::BOGO_REWARD_QTY   => '1',
					Keys::BOGO_REWARD_MODE  => 'free',
					Keys::BOGO_DEAL_MODE    => 'once',
					Keys::BOGO_REPEAT_LIMIT => '1',
				),
			),
			array(
				'id'       => 'birthday',
				'category' => 'member',
				'label'    => __( '生日禮 88 折', 'moforcoupon' ),
				'desc'     => __( '壽星生日月專屬 88 折券,每人限用一次(套用後寄給壽星)。', 'moforcoupon' ),
				'type_key' => 'percent',
				'requires' => '',
				'prefix'   => 'BIRTHDAY',
				'native'   => array(
					'discount_type'        => 'percent',
					'amount'               => 12,
					'usage_limit_per_user' => 1,
					'description'          => __( '生日禮優惠', 'moforcoupon' ),
				),
				'meta'     => array(),
			),
		);
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public static function get( string $id ): ?array {
		foreach ( self::all() as $template ) {
			if ( $template['id'] === $id ) {
				return $template;
			}
		}
		return null;
	}

	/**
	 * Normalize a template's `requires` (string | array | absent) to a clean list of
	 * module slugs. Empty string / empty array → no requirement.
	 *
	 * @param array<string,mixed> $tpl
	 * @return array<int,string>
	 */
	public static function required_modules( array $tpl ): array {
		$req = $tpl['requires'] ?? array();
		if ( is_string( $req ) ) {
			$req = ( '' === $req ) ? array() : array( $req );
		}
		if ( ! is_array( $req ) ) {
			return array();
		}
		$slugs = array();
		foreach ( $req as $slug ) {
			$slug = (string) $slug;
			if ( '' !== $slug ) {
				$slugs[] = $slug;
			}
		}
		return array_values( array_unique( $slugs ) );
	}

	/** Human label for a category key (falls back to "其他"). */
	public static function category_label( string $key ): string {
		$map = self::categories();
		return $map[ $key ] ?? __( '其他', 'moforcoupon' );
	}

	/**
	 * Human label for a feature-module slug — single source shared by the page (blocked
	 * notice) and the Applier (error message).
	 */
	public static function module_label( string $slug ): string {
		$map = array(
			'conditions'    => __( '優惠券條件', 'moforcoupon' ),
			'shipping'      => __( '運費覆寫', 'moforcoupon' ),
			'bogo'          => __( '買 X 送 Y(BOGO)', 'moforcoupon' ),
			'freegift'      => __( '免費贈品', 'moforcoupon' ),
			'url'           => __( '網址套券', 'moforcoupon' ),
			'stacking'      => __( '疊加控制', 'moforcoupon' ),
			'frontend'      => __( '前台優惠牆', 'moforcoupon' ),
			'discountcap'   => __( '折扣上限', 'moforcoupon' ),
			'discounttiers' => __( '階梯折扣', 'moforcoupon' ),
			'advrules'      => __( '進階規則(AND/OR)', 'moforcoupon' ),
			'autoapply'     => __( '自動套用優惠券', 'moforcoupon' ),
		);
		return $map[ $slug ] ?? $slug;
	}

	/**
	 * Whitelist a template's meta map to known plugin keys only (defensive — a typo
	 * in a template would otherwise write a junk meta key).
	 *
	 * @param array<string,mixed> $meta
	 * @return array<string,mixed>
	 */
	public static function sanitize_meta( array $meta ): array {
		$allowed = Keys::all();
		$clean   = array();
		foreach ( $meta as $key => $value ) {
			if ( in_array( $key, $allowed, true ) ) {
				$clean[ $key ] = $value;
			}
		}
		return $clean;
	}
}
