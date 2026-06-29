<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\CouponCore;

use MoksaWeb\Moforcoupon\Coupon\Meta\CouponSettings;
use MoksaWeb\Moforcoupon\Coupon\Meta\Keys;
use MoksaWeb\Moforcoupon\Support\AbilityMeta;
use MoksaWeb\Moforcoupon\Support\Rules;
use MoksaWeb\Moforcoupon\Modules\Reports\ReportService;
use MoksaWeb\Moforcoupon\Modules\Templates\Catalog;

defined( 'ABSPATH' ) || exit;

/**
 * Discovery / lookup / report / template / lifecycle abilities — the second wave that
 * makes the marquee feature surface (advanced rules, tiers, region/payment conditions,
 * templates, analytics) actually usable by AI / MCP. Read abilities run directly; the
 * four write abilities are propose-only (execute_callback → CouponOps::*_prepare) and
 * confirmed via the same human-confirm flow as the core writes.
 *
 * Registered on wp_abilities_api_init alongside CouponCore\Ability, so the same MCP
 * exposure gate and category apply.
 */
final class ToolsAbility {

	private const CATEGORY = Ability::CATEGORY;
	private const CAP      = Ability::CAP;

	public static function register(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}
		self::register_discovery();
		self::register_report_templates();
		self::register_writes();
	}

	/* ---------------- discovery / lookup (read) ---------------- */

	private static function register_discovery(): void {
		wp_register_ability(
			'moforcoupon/list-rule-types',
			[
				'label'               => __( '列出進階規則型別', 'moforcoupon' ),
				'description'         => __( '列出「進階規則(AND/OR)」可用的全部 26 種條件型別、各自允許的運算子(op)與 value 形狀。建構 moforcoupon.advanced_rules 前先查它。唯讀。', 'moforcoupon' ),
				'category'            => self::CATEGORY,
				'input_schema'        => self::empty_input(),
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [ 'types' => [ 'type' => 'object' ] ],
				],
				'execute_callback'    => [ self::class, 'execute_rule_types' ],
				'permission_callback' => [ self::class, 'can_read' ],
				'meta'                => self::read_meta(),
			]
		);

		wp_register_ability(
			'moforcoupon/get-settings-schema',
			[
				'label'               => __( '取得進階設定 schema', 'moforcoupon' ),
				'description'         => __( '回傳 create-coupon / update-coupon 的 moforcoupon 進階設定物件完整 JSON schema(排程 / 條件 / 階梯 / 進階規則 / BOGO / 贈品 / 運費…)。唯讀。', 'moforcoupon' ),
				'category'            => self::CATEGORY,
				'input_schema'        => self::empty_input(),
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [ 'schema' => [ 'type' => 'object' ] ],
				],
				'execute_callback'    => [ self::class, 'execute_settings_schema' ],
				'permission_callback' => [ self::class, 'can_read' ],
				'meta'                => self::read_meta(),
			]
		);

		wp_register_ability(
			'moforcoupon/list-payment-gateways',
			[
				'label'               => __( '列出付款方式', 'moforcoupon' ),
				'description'         => __( '列出本店所有付款方式的 id、名稱與是否啟用 —— 設定「付款方式條件」或 payment_method 規則時用真實 id。唯讀。', 'moforcoupon' ),
				'category'            => self::CATEGORY,
				'input_schema'        => self::empty_input(),
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [ 'gateways' => [ 'type' => 'array' ] ],
				],
				'execute_callback'    => [ self::class, 'execute_gateways' ],
				'permission_callback' => [ self::class, 'can_read' ],
				'meta'                => self::read_meta(),
			]
		);

		wp_register_ability(
			'moforcoupon/list-shipping-zones',
			[
				'label'               => __( '列出運送區域', 'moforcoupon' ),
				'description'         => __( '列出 WooCommerce 運送區域的 id 與名稱(含「其他地區」zone 0)—— shipping_zone 規則用真實 id。唯讀。', 'moforcoupon' ),
				'category'            => self::CATEGORY,
				'input_schema'        => self::empty_input(),
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [ 'zones' => [ 'type' => 'array' ] ],
				],
				'execute_callback'    => [ self::class, 'execute_zones' ],
				'permission_callback' => [ self::class, 'can_read' ],
				'meta'                => self::read_meta(),
			]
		);

		wp_register_ability(
			'moforcoupon/list-countries',
			[
				'label'               => __( '列出國家代碼', 'moforcoupon' ),
				'description'         => __( '列出國家 / 地區的 ISO 代碼與名稱 —— 設定「收件地區條件」或 shipping_country 規則用大寫代碼(如 TW)。唯讀。', 'moforcoupon' ),
				'category'            => self::CATEGORY,
				'input_schema'        => self::empty_input(),
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [ 'countries' => [ 'type' => 'array' ] ],
				],
				'execute_callback'    => [ self::class, 'execute_countries' ],
				'permission_callback' => [ self::class, 'can_read' ],
				'meta'                => self::read_meta(),
			]
		);

		wp_register_ability(
			'moforcoupon/validate-rules',
			[
				'label'               => __( '驗證進階規則樹', 'moforcoupon' ),
				'description'         => __( '對一棵「進階規則」樹做乾跑:回傳是否有有效規則、正規化結果、用到的型別、被丟掉的未知型別;若給 sample_cart 還會回傳是否通過。不寫入。唯讀。', 'moforcoupon' ),
				'category'            => self::CATEGORY,
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => [
						'rules'       => [
							'type'        => [ 'object', 'string' ],
							'description' => __( '規則樹物件或其 JSON 字串', 'moforcoupon' ),
						],
						'sample_cart' => [
							'type'        => 'object',
							'description' => __( '可選的情境(subtotal/qty/products/categories/country/payment/roles/weight…),用來試算是否通過', 'moforcoupon' ),
						],
					],
					'required'             => [ 'rules' ],
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'valid'                 => [ 'type' => 'boolean' ],
						'used_types'            => [ 'type' => 'array' ],
						'unknown_types_dropped' => [ 'type' => 'array' ],
					],
				],
				'execute_callback'    => [ self::class, 'execute_validate_rules' ],
				'permission_callback' => [ self::class, 'can_read' ],
				'meta'                => self::read_meta(),
			]
		);
	}

	/* ---------------- report + templates (read) ---------------- */

	private static function register_report_templates(): void {
		wp_register_ability(
			'moforcoupon/get-coupon-report',
			[
				'label'               => __( '優惠券績效報表', 'moforcoupon' ),
				'description'         => __( '每張優惠券的使用訂單數與折抵總額(只算已付款訂單),依折抵金額由高到低排序。唯讀。', 'moforcoupon' ),
				'category'            => self::CATEGORY,
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => [
						'limit'         => [
							'type'        => 'integer',
							'description' => __( '最多回傳幾筆(預設 20)', 'moforcoupon' ),
						],
						'sort'          => [
							'type'        => 'string',
							'enum'        => [ 'discount', 'orders' ],
							'description' => __( '排序:discount(折抵,預設)/ orders(訂單數)', 'moforcoupon' ),
						],
						'force_refresh' => [
							'type'        => 'boolean',
							'description' => __( '忽略 1 小時快取重新計算', 'moforcoupon' ),
						],
					],
					'required'             => [],
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'count' => [ 'type' => 'integer' ],
						'rows'  => [ 'type' => 'array' ],
					],
				],
				'execute_callback'    => [ self::class, 'execute_report' ],
				'permission_callback' => [ self::class, 'can_read' ],
				'meta'                => self::read_meta(),
			]
		);

		wp_register_ability(
			'moforcoupon/coupon-revenue-overview',
			[
				'label'               => __( '優惠券營收總覽', 'moforcoupon' ),
				'description'         => __( '近 N 天內帶券已付款訂單的營收、折抵總額、訂單數、平均客單價,以及每日趨勢。回答「優惠券帶來多少生意」。唯讀。', 'moforcoupon' ),
				'category'            => self::CATEGORY,
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => [
						'days' => [
							'type'        => 'integer',
							'description' => __( '統計近幾天(1–365,預設 30)', 'moforcoupon' ),
						],
					],
					'required'             => [],
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'days'           => [ 'type' => 'integer' ],
						'coupon_orders'  => [ 'type' => 'integer' ],
						'coupon_revenue' => [ 'type' => 'number' ],
						'total_discount' => [ 'type' => 'number' ],
						'daily'          => [ 'type' => 'array' ],
					],
				],
				'execute_callback'    => [ self::class, 'execute_overview' ],
				'permission_callback' => [ self::class, 'can_read' ],
				'meta'                => self::read_meta(),
			]
		);

		wp_register_ability(
			'moforcoupon/list-templates',
			[
				'label'               => __( '列出優惠券範本', 'moforcoupon' ),
				'description'         => __( '列出內建的優惠券範本(id、名稱、說明、分類、折扣型別、所需模組)。要一鍵建券時搭配 apply-template。唯讀。', 'moforcoupon' ),
				'category'            => self::CATEGORY,
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => [
						'category' => [
							'type'        => 'string',
							'description' => __( '只列某分類(acquisition/aov/shipping/promo/seasonal/bonus/member)', 'moforcoupon' ),
						],
					],
					'required'             => [],
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'count'     => [ 'type' => 'integer' ],
						'templates' => [ 'type' => 'array' ],
					],
				],
				'execute_callback'    => [ self::class, 'execute_templates' ],
				'permission_callback' => [ self::class, 'can_read' ],
				'meta'                => self::read_meta(),
			]
		);

		wp_register_ability(
			'moforcoupon/list-scheduled-coupons',
			[
				'label'               => __( '列出排程 / 到期狀況', 'moforcoupon' ),
				'description'         => __( '列出優惠券的到期日、排程起訖與活動分組(campaign),依到期日排序;可只看某 campaign。管理多階段活動或快到期的券時用。唯讀。', 'moforcoupon' ),
				'category'            => self::CATEGORY,
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => [
						'campaign' => [
							'type'        => 'string',
							'description' => __( '只列此活動分組的券', 'moforcoupon' ),
						],
					],
					'required'             => [],
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'count'   => [ 'type' => 'integer' ],
						'coupons' => [ 'type' => 'array' ],
					],
				],
				'execute_callback'    => [ self::class, 'execute_scheduled' ],
				'permission_callback' => [ self::class, 'can_read' ],
				'meta'                => self::read_meta(),
			]
		);
	}

	/* ---------------- write (propose-only) ---------------- */

	private static function register_writes(): void {
		wp_register_ability(
			'moforcoupon/create-tiered-coupon',
			[
				'label'               => __( '建立階梯折扣券', 'moforcoupon' ),
				'description'         => __( '用簡單的階梯表建立一張「依購物車門檻給不同折扣」的百分比券(例:滿1000折10%、滿2000折20%)。破壞性 —— 呼叫只會「提出」,使用者確認後才建立。', 'moforcoupon' ),
				'category'            => self::CATEGORY,
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => [
						'code'              => [
							'type'        => 'string',
							'description' => __( '優惠券代碼', 'moforcoupon' ),
						],
						'basis'             => [
							'type'        => 'string',
							'enum'        => [ 'subtotal', 'quantity', 'weight' ],
							'description' => __( '階梯依據:subtotal 小計(預設)/ quantity 件數 / weight 重量', 'moforcoupon' ),
						],
						'tiers'             => [
							'type'        => 'array',
							'description' => __( '階梯列,每階 { threshold 門檻, kind percent|fixed, value }。percent 的 value 是 0-100 百分比(10=折10%=9折);fixed 的 value 是固定折抵金額。可混用。', 'moforcoupon' ),
							'items'       => [
								'type'                 => 'object',
								'properties'           => [
									'threshold' => [ 'type' => 'number' ],
									'kind'      => [
										'type' => 'string',
										'enum' => [ 'percent', 'fixed' ],
									],
									'value'     => [ 'type' => 'number' ],
								],
								'additionalProperties' => false,
							],
						],
						'target_mode'       => [
							'type'        => 'string',
							'enum'        => [ 'cart', 'products', 'categories' ],
							'description' => __( '折扣套用範圍:cart(整車,預設)/ products / categories', 'moforcoupon' ),
						],
						'target_products'   => [
							'type'  => 'array',
							'items' => [ 'type' => 'integer' ],
						],
						'target_categories' => [
							'type'  => 'array',
							'items' => [ 'type' => 'integer' ],
						],
						'date_expires'      => [ 'type' => 'string' ],
						'usage_limit'       => [ 'type' => 'integer' ],
						'description'       => [ 'type' => 'string' ],
					],
					'required'             => [ 'code', 'tiers' ],
					'additionalProperties' => false,
				],
				'output_schema'       => self::summary_output(),
				'execute_callback'    => [ CouponOps::class, 'create_tiered_prepare' ],
				'permission_callback' => [ self::class, 'can_write' ],
				'meta'                => self::write_meta(),
			]
		);

		wp_register_ability(
			'moforcoupon/apply-template',
			[
				'label'               => __( '套用優惠券範本', 'moforcoupon' ),
				'description'         => __( '套用一個內建範本建立草稿優惠券(先用 list-templates 取得 template_id)。可微調 code / amount / date_expires / usage_limit 等。破壞性 —— 呼叫只會「提出」,使用者確認後才建立。', 'moforcoupon' ),
				'category'            => self::CATEGORY,
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => [
						'template_id' => [
							'type'        => 'string',
							'description' => __( '範本 id(來自 list-templates)', 'moforcoupon' ),
						],
						'overrides'   => [
							'type'                 => 'object',
							'description'          => __( '可選微調', 'moforcoupon' ),
							'properties'           => [
								'code'                 => [ 'type' => 'string' ],
								'amount'               => [ 'type' => 'number' ],
								'date_expires'         => [ 'type' => 'string' ],
								'usage_limit'          => [ 'type' => 'integer' ],
								'usage_limit_per_user' => [ 'type' => 'integer' ],
								'description'          => [ 'type' => 'string' ],
								'individual_use'       => [ 'type' => 'string' ],
							],
							'additionalProperties' => false,
						],
					],
					'required'             => [ 'template_id' ],
					'additionalProperties' => false,
				],
				'output_schema'       => self::summary_output(),
				'execute_callback'    => [ CouponOps::class, 'apply_template_prepare' ],
				'permission_callback' => [ self::class, 'can_write' ],
				'meta'                => self::write_meta(),
			]
		);

		wp_register_ability(
			'moforcoupon/restore-coupon',
			[
				'label'               => __( '還原刪除的優惠券', 'moforcoupon' ),
				'description'         => __( '把垃圾桶中的優惠券還原為草稿(提供數字 ID)。破壞性 —— 呼叫只會「提出」,使用者確認後才還原。', 'moforcoupon' ),
				'category'            => self::CATEGORY,
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => [
						'code_or_id' => [
							'type'        => 'string',
							'description' => __( '已刪除優惠券的數字 ID', 'moforcoupon' ),
						],
					],
					'required'             => [ 'code_or_id' ],
					'additionalProperties' => false,
				],
				'output_schema'       => self::summary_output(),
				'execute_callback'    => [ CouponOps::class, 'restore_prepare' ],
				'permission_callback' => [ self::class, 'can_write' ],
				'meta'                => self::write_meta(),
			]
		);

		wp_register_ability(
			'moforcoupon/expire-now',
			[
				'label'               => __( '立即失效', 'moforcoupon' ),
				'description'         => __( '把一或多張優惠券立即設為失效(到期日設為昨天)。比停用更明確地「立刻結束活動」。破壞性 —— 呼叫只會「提出」,使用者確認後才生效。', 'moforcoupon' ),
				'category'            => self::CATEGORY,
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => [
						'codes_or_ids' => [
							'type'        => 'array',
							'items'       => [ 'type' => 'string' ],
							'description' => __( '優惠券代碼或 ID 陣列', 'moforcoupon' ),
						],
					],
					'required'             => [ 'codes_or_ids' ],
					'additionalProperties' => false,
				],
				'output_schema'       => self::summary_output(),
				'execute_callback'    => [ CouponOps::class, 'expire_now_prepare' ],
				'permission_callback' => [ self::class, 'can_write' ],
				'meta'                => self::write_meta(),
			]
		);

		wp_register_ability(
			'moforcoupon/bulk-reschedule-expiry',
			[
				'label'               => __( '批次調整到期日', 'moforcoupon' ),
				'description'         => __( '一次把多張優惠券的到期日設為同一天(date_expires 留空 = 清除到期日改永久)。管理活動延期 / 提前結束很方便。破壞性 —— 呼叫只會「提出」,使用者確認後才生效。', 'moforcoupon' ),
				'category'            => self::CATEGORY,
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => [
						'codes_or_ids' => [
							'type'        => 'array',
							'items'       => [ 'type' => 'string' ],
							'description' => __( '優惠券代碼或 ID 陣列', 'moforcoupon' ),
						],
						'date_expires' => [
							'type'        => 'string',
							'description' => __( '新的到期日 YYYY-MM-DD;留空 = 清除到期日', 'moforcoupon' ),
						],
					],
					'required'             => [ 'codes_or_ids' ],
					'additionalProperties' => false,
				],
				'output_schema'       => self::summary_output(),
				'execute_callback'    => [ CouponOps::class, 'bulk_reschedule_prepare' ],
				'permission_callback' => [ self::class, 'can_write' ],
				'meta'                => self::write_meta(),
			]
		);
	}

	/* ---------------- read execute callbacks ---------------- */

	/**
	 * @param mixed $input
	 * @return array<string,mixed>
	 */
	public static function execute_scheduled( $input ): array {
		if ( ! self::can_read() ) {
			return [
				'count'   => 0,
				'coupons' => [],
			];
		}
		$input    = is_array( $input ) ? $input : [];
		$campaign = isset( $input['campaign'] ) ? trim( (string) $input['campaign'] ) : '';
		$query    = new \WP_Query(
			[
				'post_type'      => 'shop_coupon',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
			]
		);
		$rows     = [];
		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			$id   = (int) $post->ID;
			$camp = (string) get_post_meta( $id, Keys::CAMPAIGN, true );
			if ( '' !== $campaign && 0 !== strcasecmp( $camp, $campaign ) ) {
				continue;
			}
			$coupon  = new \WC_Coupon( $id );
			$expires = $coupon->get_date_expires();
			$rows[]  = [
				'code'           => $coupon->get_code(),
				'status'         => (string) get_post_status( $id ),
				'date_expires'   => $expires ? $expires->date( 'Y-m-d' ) : '',
				'schedule_start' => (string) get_post_meta( $id, Keys::SCHEDULE_START, true ),
				'schedule_end'   => (string) get_post_meta( $id, Keys::SCHEDULE_END, true ),
				'campaign'       => $camp,
			];
		}
		// Soonest expiry first; coupons with no expiry sort last.
		usort(
			$rows,
			static fn( array $a, array $b ): int => ( '' === $a['date_expires'] ? '9999-12-31' : $a['date_expires'] )
				<=> ( '' === $b['date_expires'] ? '9999-12-31' : $b['date_expires'] )
		);
		return [
			'count'   => count( $rows ),
			'coupons' => $rows,
		];
	}

	/** @return array<string,mixed> */
	public static function execute_rule_types(): array {
		if ( ! self::can_read() ) {
			return [];
		}
		return [ 'types' => Rules::types() ];
	}

	/** @return array<string,mixed> */
	public static function execute_settings_schema(): array {
		if ( ! self::can_read() ) {
			return [];
		}
		return [ 'schema' => CouponSettings::schema() ];
	}

	/** @return array<string,mixed> */
	public static function execute_gateways(): array {
		if ( ! self::can_read() || ! function_exists( 'WC' ) || ! WC()->payment_gateways() ) {
			return [ 'gateways' => [] ];
		}
		$out = [];
		foreach ( WC()->payment_gateways()->payment_gateways() as $gateway ) {
			if ( is_object( $gateway ) && isset( $gateway->id ) ) {
				$out[] = [
					'id'      => (string) $gateway->id,
					'title'   => method_exists( $gateway, 'get_title' ) ? wp_strip_all_tags( (string) $gateway->get_title() ) : (string) $gateway->id,
					'enabled' => isset( $gateway->enabled ) && 'yes' === $gateway->enabled,
				];
			}
		}
		return [ 'gateways' => $out ];
	}

	/** @return array<string,mixed> */
	public static function execute_zones(): array {
		if ( ! self::can_read() || ! class_exists( '\WC_Shipping_Zones' ) ) {
			return [ 'zones' => [] ];
		}
		$out = [];
		foreach ( \WC_Shipping_Zones::get_zones() as $zone ) {
			if ( isset( $zone['id'], $zone['zone_name'] ) ) {
				$out[] = [
					'id'   => (int) $zone['id'],
					'name' => (string) $zone['zone_name'],
				];
			}
		}
		$out[] = [
			'id'   => 0,
			'name' => __( '其他地區(未涵蓋)', 'moforcoupon' ),
		];
		return [ 'zones' => $out ];
	}

	/** @return array<string,mixed> */
	public static function execute_countries(): array {
		if ( ! self::can_read() || ! function_exists( 'WC' ) || ! WC()->countries ) {
			return [ 'countries' => [] ];
		}
		$out = [];
		foreach ( WC()->countries->get_countries() as $code => $name ) {
			$out[] = [
				'code' => (string) $code,
				'name' => (string) $name,
			];
		}
		return [ 'countries' => $out ];
	}

	/**
	 * @param mixed $input
	 * @return array<string,mixed>
	 */
	public static function execute_validate_rules( $input ): array {
		if ( ! self::can_read() ) {
			return [];
		}
		$input   = is_array( $input ) ? $input : [];
		$raw     = $input['rules'] ?? '';
		$set     = Rules::parse( $raw );
		$known   = Rules::type_keys();
		$dropped = array_values(
			array_unique(
				array_filter(
					self::raw_rule_types( $raw ),
					static fn( string $t ): bool => ! in_array( $t, $known, true )
				)
			)
		);
		$out     = [
			'valid'                 => [] !== $set['groups'],
			'normalized'            => $set,
			'used_types'            => Rules::types_used( $set ),
			'unknown_types_dropped' => $dropped,
		];
		if ( isset( $input['sample_cart'] ) && is_array( $input['sample_cart'] ) ) {
			$out['would_pass'] = Rules::evaluate( $set, $input['sample_cart'], false );
		}
		return $out;
	}

	/**
	 * @param mixed $input
	 * @return array<string,mixed>
	 */
	public static function execute_report( $input ): array {
		if ( ! self::can_read() ) {
			return [
				'count' => 0,
				'rows'  => [],
			];
		}
		$input = is_array( $input ) ? $input : [];
		$rows  = ReportService::compute( ! empty( $input['force_refresh'] ) );
		if ( ( $input['sort'] ?? 'discount' ) === 'orders' ) {
			usort( $rows, static fn( array $a, array $b ): int => (int) $b['orders'] <=> (int) $a['orders'] );
		}
		$limit = isset( $input['limit'] ) ? max( 1, (int) $input['limit'] ) : 20;
		$rows  = array_slice( $rows, 0, $limit );
		return [
			'count' => count( $rows ),
			'rows'  => $rows,
		];
	}

	/**
	 * @param mixed $input
	 * @return array<string,mixed>
	 */
	public static function execute_overview( $input ): array {
		if ( ! self::can_read() ) {
			return [ 'coupon_orders' => 0 ];
		}
		$input = is_array( $input ) ? $input : [];
		$days  = isset( $input['days'] ) ? (int) $input['days'] : 30;
		return ReportService::overview( $days );
	}

	/**
	 * @param mixed $input
	 * @return array<string,mixed>
	 */
	public static function execute_templates( $input ): array {
		if ( ! self::can_read() ) {
			return [
				'count'     => 0,
				'templates' => [],
			];
		}
		$input    = is_array( $input ) ? $input : [];
		$category = isset( $input['category'] ) ? (string) $input['category'] : '';
		$out      = [];
		foreach ( Catalog::all() as $tpl ) {
			if ( '' !== $category && ( $tpl['category'] ?? '' ) !== $category ) {
				continue;
			}
			$out[] = [
				'id'       => (string) ( $tpl['id'] ?? '' ),
				'label'    => (string) ( $tpl['label'] ?? '' ),
				'desc'     => (string) ( $tpl['desc'] ?? '' ),
				'category' => (string) ( $tpl['category'] ?? '' ),
				'type_key' => (string) ( $tpl['type_key'] ?? '' ),
				'requires' => Catalog::required_modules( $tpl ),
			];
		}
		return [
			'count'     => count( $out ),
			'templates' => $out,
		];
	}

	/**
	 * Flat list of rule types named in a raw (pre-parse) tree, to surface dropped/unknown.
	 *
	 * @param mixed $raw
	 * @return array<int,string>
	 */
	private static function raw_rule_types( $raw ): array {
		if ( is_string( $raw ) ) {
			$decoded = json_decode( $raw, true );
			$raw     = is_array( $decoded ) ? $decoded : [];
		}
		if ( ! is_array( $raw ) ) {
			return [];
		}
		$types = [];
		foreach ( ( $raw['groups'] ?? [] ) as $group ) {
			foreach ( ( is_array( $group ) ? ( $group['rules'] ?? [] ) : [] ) as $rule ) {
				if ( is_array( $rule ) && isset( $rule['type'] ) ) {
					$types[] = (string) $rule['type'];
				}
			}
		}
		return $types;
	}

	/* ---------------- helpers ---------------- */

	public static function can_read(): bool {
		return current_user_can( self::CAP );
	}

	public static function can_write(): bool {
		return current_user_can( CouponOps::CAP );
	}

	/**
	 * Input schema for a no-argument ability. `properties` MUST be an object literal so it
	 * serializes to JSON `{}` (not `[]`): the WordPress AI Client passes this verbatim into
	 * each LLM function declaration, and providers reject `"properties":[]` with "[] is not
	 * of type 'object'", which 400s the whole request (every tool is validated up front).
	 * WP_Ability stores input_schema as-is, so the object cast survives registration.
	 *
	 * @return array<string,mixed>
	 */
	private static function empty_input(): array {
		return AbilityMeta::empty_input();
	}

	/** @return array<string,mixed> */
	private static function summary_output(): array {
		return AbilityMeta::summary_output();
	}

	/** @return array<string,mixed> */
	private static function read_meta(): array {
		return AbilityMeta::read();
	}

	/** @return array<string,mixed> */
	private static function write_meta(): array {
		return AbilityMeta::write();
	}
}
