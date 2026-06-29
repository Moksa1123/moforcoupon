<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\Bogo;

use MoksaWeb\Moforcoupon\Support\AbilityMeta;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the destructive ability moforcoupon/create-bogo-coupon so the AI
 * assistant / command palette / MCP can build a "買 X 送 Y" coupon in one shot. The
 * execute_callback is the propose-only BogoOps::create_prepare; the real write runs
 * via the confirm flow (BogoOps::create_apply). Marked destructive, so the existing
 * MCP gate hides it unless moforcoupon_mcp_expose_destructive=yes.
 */
final class Ability {

	public const CATEGORY = 'moforcoupon';

	public static function register(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}
		wp_register_ability(
			'moforcoupon/create-bogo-coupon',
			array(
				'label'               => __( '建立買 X 送 Y 優惠券', 'moforcoupon' ),
				'description'         => __( '建立一張「買 X 送 Y(BOGO)」優惠券:顧客買滿指定商品 / 分類達數量後,購物車中的贈品商品可享免費 / 百分比 / 固定折扣,可設定一次或可重複。破壞性 —— 呼叫只會「提出」,使用者按確認後才建立。', 'moforcoupon' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'code'                 => array(
							'type'        => 'string',
							'description' => __( '優惠券代碼,如 BUY2GET1', 'moforcoupon' ),
						),
						'trigger_product_ids'  => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'integer' ),
							'description' => __( '購買條件商品 ID(與分類擇一或併用)', 'moforcoupon' ),
						),
						'trigger_category_ids' => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'integer' ),
							'description' => __( '購買條件商品分類 term ID', 'moforcoupon' ),
						),
						'trigger_qty'          => array(
							'type'        => 'integer',
							'description' => __( '需購買的件數 N(預設 1)', 'moforcoupon' ),
						),
						'reward_product_ids'   => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'integer' ),
							'description' => __( '贈品商品 ID', 'moforcoupon' ),
						),
						'reward_category_ids'  => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'integer' ),
							'description' => __( '贈品商品分類 term ID', 'moforcoupon' ),
						),
						'reward_qty'           => array(
							'type'        => 'integer',
							'description' => __( '贈送 / 折扣件數 M(預設 1)', 'moforcoupon' ),
						),
						'reward_mode'          => array(
							'type'        => 'string',
							'enum'        => array( 'free', 'percent', 'fixed_per_item' ),
							'description' => __( '折扣方式:free 免費 / percent 百分比 / fixed_per_item 每件固定額', 'moforcoupon' ),
						),
						'reward_value'         => array(
							'type'        => 'number',
							'description' => __( '折扣值;percent 為 0–100,fixed_per_item 為金額,free 免填', 'moforcoupon' ),
						),
						'deal_mode'            => array(
							'type'        => 'string',
							'enum'        => array( 'once', 'repeat' ),
							'description' => __( 'once 只一次 / repeat 可重複(預設 once)', 'moforcoupon' ),
						),
						'repeat_limit'         => array(
							'type'        => 'integer',
							'description' => __( '重複上限(0=不限,僅 repeat 有效)', 'moforcoupon' ),
						),
						'date_expires'         => array(
							'type'        => 'string',
							'description' => __( '到期日 YYYY-MM-DD,可空', 'moforcoupon' ),
						),
						'usage_limit'          => array( 'type' => 'integer' ),
						'individual_use'       => array( 'type' => 'boolean' ),
					),
					'required'             => array( 'code', 'reward_mode' ),
					'additionalProperties' => false,
				),
				'output_schema'       => AbilityMeta::summary_output(),
				'execute_callback'    => array( BogoOps::class, 'create_prepare' ),
				'permission_callback' => array( self::class, 'can_write' ),
				'meta'                => AbilityMeta::write(),
			)
		);
	}

	public static function can_write(): bool {
		return current_user_can( BogoOps::CAP );
	}
}
