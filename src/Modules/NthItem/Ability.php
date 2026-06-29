<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\NthItem;

use MoksaWeb\Moforcoupon\Support\AbilityMeta;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the destructive ability moforcoupon/create-nth-item-coupon so the AI assistant /
 * command palette / MCP can build a "第 N 件折扣" coupon in one shot. The execute_callback is the
 * propose-only NthItemOps::create_prepare; the real write runs via the confirm flow
 * (NthItemOps::create_apply). Marked destructive, so the MCP gate hides it unless
 * moforcoupon_mcp_expose_destructive=yes.
 */
final class Ability {

	public const CATEGORY = 'moforcoupon';

	public static function register(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}
		wp_register_ability(
			'moforcoupon/create-nth-item-coupon',
			array(
				'label'               => __( '建立第 N 件折扣優惠券', 'moforcoupon' ),
				'description'         => __( '建立一張「第 N 件折扣」優惠券:同一組商品(或全站)每湊滿 N 件,第 N 件享免費 / 百分比 / 每件固定折扣,可一次或可重複。例:第二件六折 → n=2、reward_mode=percent、reward_value=40。破壞性 —— 呼叫只會「提出」,使用者按確認後才建立。', 'moforcoupon' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'code'           => array(
							'type'        => 'string',
							'description' => __( '優惠券代碼,如 SECOND60', 'moforcoupon' ),
						),
						'product_ids'    => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'integer' ),
							'description' => __( '適用商品 ID(留空 + 無分類 = 全站)', 'moforcoupon' ),
						),
						'category_ids'   => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'integer' ),
							'description' => __( '適用商品分類 term ID', 'moforcoupon' ),
						),
						'group_by'       => array(
							'type'        => 'string',
							'enum'        => array( 'cart', 'product' ),
							'description' => __( 'cart 整車合併 / product 每件商品各自計(預設 cart)', 'moforcoupon' ),
						),
						'n'              => array(
							'type'        => 'integer',
							'minimum'     => 2,
							'description' => __( '每幾件折一件 N(至少 2;第二件折填 2)', 'moforcoupon' ),
						),
						'reward_mode'    => array(
							'type'        => 'string',
							'enum'        => array( 'free', 'percent', 'fixed_per_item' ),
							'description' => __( '折扣方式:free 免費 / percent 折扣百分比 / fixed_per_item 每件固定額', 'moforcoupon' ),
						),
						'reward_value'   => array(
							'type'        => 'number',
							'minimum'     => 0,
							'description' => __( '折扣值;percent 為折扣 %(六折=40),fixed_per_item 為金額,free 免填', 'moforcoupon' ),
						),
						'deal_mode'      => array(
							'type'        => 'string',
							'enum'        => array( 'once', 'repeat' ),
							'description' => __( 'once 只一次 / repeat 可重複(預設 repeat)', 'moforcoupon' ),
						),
						'repeat_limit'   => array(
							'type'        => 'integer',
							'description' => __( '重複上限(0=不限,僅 repeat 有效)', 'moforcoupon' ),
						),
						'notice_message' => array(
							'type'        => 'string',
							'description' => __( '湊件提示,可用 {nth_n} {coupon_code}', 'moforcoupon' ),
						),
						'date_expires'   => array(
							'type'        => 'string',
							'description' => __( '到期日 YYYY-MM-DD,可空', 'moforcoupon' ),
						),
						'usage_limit'    => array( 'type' => 'integer' ),
						'individual_use' => array( 'type' => 'boolean' ),
					),
					'required'             => array( 'code', 'n', 'reward_mode' ),
					'additionalProperties' => false,
				),
				'output_schema'       => AbilityMeta::summary_output(),
				'execute_callback'    => array( NthItemOps::class, 'create_prepare' ),
				'permission_callback' => array( self::class, 'can_write' ),
				'meta'                => AbilityMeta::write(),
			)
		);
	}

	public static function can_write(): bool {
		return current_user_can( NthItemOps::CAP );
	}
}
