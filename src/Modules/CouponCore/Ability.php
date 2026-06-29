<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\CouponCore;

use MoksaWeb\Moforcoupon\Coupon\CouponService;
use MoksaWeb\Moforcoupon\Coupon\Meta\CouponSettings;
use MoksaWeb\Moforcoupon\Support\AbilityMeta;

defined( 'ABSPATH' ) || exit;

/**
 * WordPress Abilities API registration for coupon capabilities. One definition is
 * consumed by the command palette, REST, the in-dashboard AI assistant and MCP.
 *
 * Read abilities run directly; destructive abilities are propose-only — their
 * execute_callback points at CouponOps::*_prepare and the real change happens via
 * the confirm flow. Requires WordPress 6.9+ core Abilities API.
 */
final class Ability {

	public const CATEGORY = 'moforcoupon';

	public const CAP = 'manage_woocommerce';

	/** @var array<string,bool> Abilities hidden from MCP this request (filled by gate). */
	private static array $mcp_hidden = [];

	public static function register_category(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}
		if ( function_exists( 'wp_has_ability_category' ) && wp_has_ability_category( self::CATEGORY ) ) {
			return;
		}
		wp_register_ability_category(
			self::CATEGORY,
			[
				'label'       => __( 'Moksa Coupons', 'moforcoupon' ),
				'description' => __( 'WooCommerce 優惠券建立、查詢與管理能力', 'moforcoupon' ),
			]
		);
	}

	public static function register(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}
		self::register_reads();
		self::register_writes();
	}

	// === Read abilities ===

	private static function register_reads(): void {
		wp_register_ability(
			'moforcoupon/list-coupons',
			[
				'label'               => __( '列出優惠券', 'moforcoupon' ),
				'description'         => __( '列出優惠券,可依狀態(publish 啟用 / draft 停用)、折扣類型、關鍵字篩選,回傳精簡清單(預設 20 筆、上限 50)。唯讀。', 'moforcoupon' ),
				'category'            => self::CATEGORY,
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => [
						'status'        => [
							'type'        => 'string',
							'description' => __( '狀態:publish(啟用)/ draft(停用)/ any。不給=全部', 'moforcoupon' ),
						],
						'discount_type' => [
							'type'        => 'string',
							'enum'        => CouponService::DISCOUNT_TYPES,
							'description' => __( '折扣類型篩選(選填)', 'moforcoupon' ),
						],
						'search'        => [
							'type'        => 'string',
							'description' => __( '代碼 / 描述關鍵字(選填)', 'moforcoupon' ),
						],
						'limit'         => [
							'type'        => 'integer',
							'description' => __( '最多幾筆(預設 20,上限 50)', 'moforcoupon' ),
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
				'execute_callback'    => [ self::class, 'execute_list' ],
				'permission_callback' => [ self::class, 'can_read' ],
				'meta'                => self::read_meta(),
			]
		);

		wp_register_ability(
			'moforcoupon/get-coupon',
			[
				'label'               => __( '查優惠券明細', 'moforcoupon' ),
				'description'         => __( '取單張優惠券的完整設定(代碼、折扣類型與金額、到期日、使用次數與上限、最低消費、限定商品等)。可給代碼或 ID。唯讀。', 'moforcoupon' ),
				'category'            => self::CATEGORY,
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => [
						'code_or_id' => [
							'type'        => 'string',
							'description' => __( '優惠券代碼或 ID', 'moforcoupon' ),
						],
					],
					'required'             => [ 'code_or_id' ],
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [ 'coupon' => [ 'type' => 'object' ] ],
				],
				'execute_callback'    => [ self::class, 'execute_get' ],
				'permission_callback' => [ self::class, 'can_read' ],
				'meta'                => self::read_meta(),
			]
		);

		wp_register_ability(
			'moforcoupon/find-coupon-by-code',
			[
				'label'               => __( '查代碼是否存在', 'moforcoupon' ),
				'description'         => __( '檢查某個優惠券代碼是否已存在,回傳是否存在與其 ID。建立前可先用這個避免重複。唯讀。', 'moforcoupon' ),
				'category'            => self::CATEGORY,
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => [
						'code' => [
							'type'        => 'string',
							'description' => __( '要檢查的優惠券代碼', 'moforcoupon' ),
						],
					],
					'required'             => [ 'code' ],
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'id'     => [ 'type' => 'integer' ],
						'exists' => [ 'type' => 'boolean' ],
					],
				],
				'execute_callback'    => [ self::class, 'execute_find' ],
				'permission_callback' => [ self::class, 'can_read' ],
				'meta'                => self::read_meta(),
			]
		);

		wp_register_ability(
			'moforcoupon/coupon-usage-summary',
			[
				'label'               => __( '查優惠券用量', 'moforcoupon' ),
				'description'         => __( '查某張優惠券的使用次數、總上限與每人上限、剩餘可用次數。唯讀。', 'moforcoupon' ),
				'category'            => self::CATEGORY,
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => [
						'code_or_id' => [
							'type'        => 'string',
							'description' => __( '優惠券代碼或 ID', 'moforcoupon' ),
						],
					],
					'required'             => [ 'code_or_id' ],
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'usage_count' => [ 'type' => 'integer' ],
						'usage_limit' => [ 'type' => 'integer' ],
						'remaining'   => [ 'type' => 'integer' ],
					],
				],
				'execute_callback'    => [ self::class, 'execute_usage' ],
				'permission_callback' => [ self::class, 'can_read' ],
				'meta'                => self::read_meta(),
			]
		);
	}

	// === Destructive abilities (propose-only execute_callback) ===

	private static function register_writes(): void {
		wp_register_ability(
			'moforcoupon/create-coupon',
			[
				'label'               => __( '建立優惠券', 'moforcoupon' ),
				'description'         => __( '建立一張新的 WooCommerce 優惠券。這是破壞性操作 —— 呼叫只會「提出」,使用者按「確認執行」後才真正建立,你不需要自己再追問確認。', 'moforcoupon' ),
				'category'            => self::CATEGORY,
				'input_schema'        => self::create_input_schema(),
				'output_schema'       => self::summary_output(),
				'execute_callback'    => [ CouponOps::class, 'create_prepare' ],
				'permission_callback' => [ self::class, 'can_write' ],
				'meta'                => self::write_meta(),
			]
		);

		wp_register_ability(
			'moforcoupon/update-coupon',
			[
				'label'               => __( '更新優惠券', 'moforcoupon' ),
				'description'         => __( '更新一張既有優惠券的欄位(折扣、到期日、使用上限等;代碼不可改)。破壞性 —— 呼叫只會「提出」,使用者確認後才生效。', 'moforcoupon' ),
				'category'            => self::CATEGORY,
				'input_schema'        => self::update_input_schema(),
				'output_schema'       => self::summary_output(),
				'execute_callback'    => [ CouponOps::class, 'update_prepare' ],
				'permission_callback' => [ self::class, 'can_write' ],
				'meta'                => self::write_meta(),
			]
		);

		wp_register_ability(
			'moforcoupon/toggle-coupon',
			[
				'label'               => __( '啟用/停用優惠券', 'moforcoupon' ),
				'description'         => __( '啟用(publish)或停用(draft)一張優惠券。破壞性 —— 呼叫只會「提出」,使用者確認後才生效。', 'moforcoupon' ),
				'category'            => self::CATEGORY,
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => [
						'code_or_id' => [
							'type'        => 'string',
							'description' => __( '優惠券代碼或 ID', 'moforcoupon' ),
						],
						'enable'     => [
							'type'        => 'boolean',
							'description' => __( 'true=啟用、false=停用', 'moforcoupon' ),
						],
					],
					'required'             => [ 'code_or_id', 'enable' ],
					'additionalProperties' => false,
				],
				'output_schema'       => self::summary_output(),
				'execute_callback'    => [ CouponOps::class, 'toggle_prepare' ],
				'permission_callback' => [ self::class, 'can_write' ],
				'meta'                => self::write_meta(),
			]
		);

		wp_register_ability(
			'moforcoupon/delete-coupon',
			[
				'label'               => __( '刪除優惠券', 'moforcoupon' ),
				'description'         => __( '刪除一張優惠券(預設移到垃圾桶,force=true 永久刪除)。破壞性、不可逆 —— 呼叫只會「提出」,使用者確認後才執行。', 'moforcoupon' ),
				'category'            => self::CATEGORY,
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => [
						'code_or_id' => [
							'type'        => 'string',
							'description' => __( '優惠券代碼或 ID', 'moforcoupon' ),
						],
						'force'      => [
							'type'        => 'boolean',
							'description' => __( 'true=永久刪除、false=移到垃圾桶(預設)', 'moforcoupon' ),
						],
					],
					'required'             => [ 'code_or_id' ],
					'additionalProperties' => false,
				],
				'output_schema'       => self::summary_output(),
				'execute_callback'    => [ CouponOps::class, 'delete_prepare' ],
				'permission_callback' => [ self::class, 'can_write' ],
				'meta'                => self::write_meta(),
			]
		);

		wp_register_ability(
			'moforcoupon/bulk-generate-coupons',
			[
				'label'               => __( '量產優惠券', 'moforcoupon' ),
				'description'         => __( '一次量產多張使用相同設定、代碼唯一的優惠券(行銷活動用)。破壞性 —— 呼叫只會「提出」,使用者確認後才建立。', 'moforcoupon' ),
				'category'            => self::CATEGORY,
				'input_schema'        => self::bulk_input_schema(),
				'output_schema'       => self::summary_output(),
				'execute_callback'    => [ CouponOps::class, 'bulk_generate_prepare' ],
				'permission_callback' => [ self::class, 'can_write' ],
				'meta'                => self::write_meta(),
			]
		);

		wp_register_ability(
			'moforcoupon/extend-expiry',
			[
				'label'               => __( '延長到期日', 'moforcoupon' ),
				'description'         => __( '把一或多張優惠券的到期日延長到指定日期。破壞性 —— 呼叫只會「提出」,使用者確認後才生效。', 'moforcoupon' ),
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
							'description' => __( '新的到期日 YYYY-MM-DD', 'moforcoupon' ),
						],
					],
					'required'             => [ 'codes_or_ids', 'date_expires' ],
					'additionalProperties' => false,
				],
				'output_schema'       => self::summary_output(),
				'execute_callback'    => [ CouponOps::class, 'extend_expiry_prepare' ],
				'permission_callback' => [ self::class, 'can_write' ],
				'meta'                => self::write_meta(),
			]
		);

		wp_register_ability(
			'moforcoupon/duplicate-coupon',
			[
				'label'               => __( '複製優惠券', 'moforcoupon' ),
				'description'         => __( '把一張既有優惠券複製成一張新草稿(沿用所有折扣設定與排程 / 角色 / 購物車條件,使用次數歸零、代碼自動加上 -COPY-)。破壞性 —— 呼叫只會「提出」,使用者確認後才建立。', 'moforcoupon' ),
				'category'            => self::CATEGORY,
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => [
						'code_or_id' => [
							'type'        => 'string',
							'description' => __( '要複製的來源優惠券代碼或 ID', 'moforcoupon' ),
						],
					],
					'required'             => [ 'code_or_id' ],
					'additionalProperties' => false,
				],
				'output_schema'       => self::summary_output(),
				'execute_callback'    => [ CouponOps::class, 'duplicate_prepare' ],
				'permission_callback' => [ self::class, 'can_write' ],
				'meta'                => self::write_meta(),
			]
		);
	}

	// === Read execute callbacks ===

	/**
	 * @param mixed $input
	 * @return array<string,mixed>
	 */
	public static function execute_list( $input ): array {
		if ( ! self::can_read() ) {
			return [
				'count'   => 0,
				'coupons' => [],
			];
		}
		return CouponService::list( is_array( $input ) ? $input : [] );
	}

	/**
	 * @param mixed $input
	 * @return array<string,mixed>
	 */
	public static function execute_get( $input ): array {
		if ( ! self::can_read() ) {
			return [];
		}
		$ref    = is_array( $input ) && isset( $input['code_or_id'] ) ? (string) $input['code_or_id'] : '';
		$coupon = CouponService::get( $ref );
		return null === $coupon ? [] : [ 'coupon' => $coupon ];
	}

	/**
	 * @param mixed $input
	 * @return array<string,mixed>
	 */
	public static function execute_find( $input ): array {
		if ( ! self::can_read() ) {
			return [
				'id'     => 0,
				'exists' => false,
			];
		}
		$code = is_array( $input ) && isset( $input['code'] ) ? (string) $input['code'] : '';
		$id   = CouponService::find_id_by_code( $code );
		return [
			'id'     => $id,
			'exists' => $id > 0,
		];
	}

	/**
	 * @param mixed $input
	 * @return array<string,mixed>
	 */
	public static function execute_usage( $input ): array {
		if ( ! self::can_read() ) {
			return [];
		}
		$ref  = is_array( $input ) && isset( $input['code_or_id'] ) ? (string) $input['code_or_id'] : '';
		$data = CouponService::get( $ref );
		if ( null === $data ) {
			return [];
		}
		$limit     = (int) $data['usage_limit'];
		$count     = (int) $data['usage_count'];
		$remaining = $limit > 0 ? max( 0, $limit - $count ) : -1;
		return [
			'code'                 => $data['code'],
			'usage_count'          => $count,
			'usage_limit'          => $limit,
			'usage_limit_per_user' => (int) $data['usage_limit_per_user'],
			'remaining'            => $remaining,
		];
	}

	// === Permission callbacks ===

	public static function can_read(): bool {
		return current_user_can( self::CAP );
	}

	public static function can_write(): bool {
		return current_user_can( CouponOps::CAP );
	}

	// === Schema / meta helpers ===

	/** @return array<string,mixed> */
	private static function read_meta(): array {
		return AbilityMeta::read();
	}

	/** @return array<string,mixed> */
	private static function write_meta(): array {
		return AbilityMeta::write();
	}

	/** @return array<string,mixed> */
	private static function summary_output(): array {
		return AbilityMeta::summary_output();
	}

	/** @return array<string,mixed> */
	private static function create_input_schema(): array {
		return [
			'type'                 => 'object',
			'properties'           => [
				'code'                        => [
					'type'        => 'string',
					'description' => __( '優惠券代碼,如 SUMMER25', 'moforcoupon' ),
				],
				'discount_type'               => [
					'type'        => 'string',
					'enum'        => CouponService::DISCOUNT_TYPES,
					'default'     => 'fixed_cart',
					'description' => __( '折扣類型:percent(百分比)/ fixed_cart(購物車固定額)/ fixed_product(商品固定額)', 'moforcoupon' ),
				],
				'amount'                      => [
					'type'        => 'number',
					'description' => __( '折扣量;percent 時為百分比數字(25 = 25%)', 'moforcoupon' ),
				],
				'description'                 => [
					'type'        => 'string',
					'description' => __( '優惠券說明(選填)', 'moforcoupon' ),
				],
				'date_expires'                => [
					'type'        => 'string',
					'description' => __( '到期日 YYYY-MM-DD,可空', 'moforcoupon' ),
				],
				'individual_use'              => [ 'type' => 'boolean' ],
				'free_shipping'               => [ 'type' => 'boolean' ],
				'exclude_sale_items'          => [ 'type' => 'boolean' ],
				'minimum_amount'              => [ 'type' => 'number' ],
				'maximum_amount'              => [ 'type' => 'number' ],
				'usage_limit'                 => [ 'type' => 'integer' ],
				'usage_limit_per_user'        => [ 'type' => 'integer' ],
				'limit_usage_to_x_items'      => [ 'type' => 'integer' ],
				'product_ids'                 => [
					'type'  => 'array',
					'items' => [ 'type' => 'integer' ],
				],
				'excluded_product_ids'        => [
					'type'  => 'array',
					'items' => [ 'type' => 'integer' ],
				],
				'product_categories'          => [
					'type'  => 'array',
					'items' => [ 'type' => 'integer' ],
				],
				'excluded_product_categories' => [
					'type'  => 'array',
					'items' => [ 'type' => 'integer' ],
				],
				'email_restrictions'          => [
					'type'  => 'array',
					'items' => [ 'type' => 'string' ],
				],
				'auto_apply'                  => [
					'type'        => 'boolean',
					'description' => __( '是否自動套用(顧客進購物車自動帶入;需啟用「自動套用」模組,且券無使用次數 / Email 限制)', 'moforcoupon' ),
				],
				'discount_cap'                => [
					'type'        => 'number',
					'description' => __( '百分比折扣的最高折抵金額(例:8 折最多折 500;需啟用「折扣上限」模組)。0 或不填=不限', 'moforcoupon' ),
				],
				'exclude_coupons'             => [
					'type'        => 'boolean',
					'description' => __( '是否不可與其他優惠券並用(互斥券;需啟用「疊加控制」模組)', 'moforcoupon' ),
				],
				'moforcoupon'                 => CouponSettings::schema(),
			],
			'required'             => [ 'code', 'discount_type', 'amount' ],
			'additionalProperties' => false,
		];
	}

	/** @return array<string,mixed> */
	private static function update_input_schema(): array {
		$schema                             = self::create_input_schema();
		$schema['properties']['code_or_id'] = [
			'type'        => 'string',
			'description' => __( '要更新的優惠券代碼或 ID', 'moforcoupon' ),
		];
		unset( $schema['properties']['code'] );
		$schema['required'] = [ 'code_or_id' ];
		return $schema;
	}

	/** @return array<string,mixed> */
	private static function bulk_input_schema(): array {
		$schema = self::create_input_schema();
		unset( $schema['properties']['code'] );
		$schema['properties']['count']  = [
			'type'        => 'integer',
			'description' => __( '要量產的張數(1–500)', 'moforcoupon' ),
		];
		$schema['properties']['prefix'] = [
			'type'        => 'string',
			'description' => __( '代碼前綴(選填,如 SALE-)', 'moforcoupon' ),
		];
		$schema['required']             = [ 'count', 'discount_type', 'amount' ];
		return $schema;
	}

	// === MCP exposure gate ===

	private static function expose_destructive(): bool {
		return 'yes' === get_option( 'moforcoupon_mcp_expose_destructive', 'no' );
	}

	/**
	 * @param mixed $args
	 * @param mixed $name
	 * @return mixed
	 */
	public static function gate_mcp_exposure( $args, $name ) {
		if ( ! is_string( $name ) || 0 !== strpos( $name, 'moforcoupon/' ) || ! is_array( $args ) ) {
			return $args;
		}
		$destructive = ! empty( $args['meta']['annotations']['destructive'] );
		if ( $destructive && ! self::expose_destructive() ) {
			$args['meta']['mcp']['public'] = false;
			self::$mcp_hidden[ $name ]     = true;
		}
		return $args;
	}

	/**
	 * @param mixed  $included
	 * @param string $ability_id
	 * @return mixed
	 */
	public static function include_in_mcp( $included, $ability_id ) {
		if ( is_string( $ability_id ) && 0 === strpos( $ability_id, 'moforcoupon/' ) ) {
			return empty( self::$mcp_hidden[ $ability_id ] );
		}
		return $included;
	}
}
