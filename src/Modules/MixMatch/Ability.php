<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\MixMatch;

use MoksaWeb\Moforcoupon\Support\AbilityMeta;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the destructive ability moforcoupon/create-mixmatch-coupon so the AI assistant /
 * command palette / MCP can build a "任選優惠" coupon in one shot. The execute_callback is the
 * propose-only MixMatchOps::create_prepare; the real write runs via the confirm flow. Marked
 * destructive, so the MCP gate hides it unless moforcoupon_mcp_expose_destructive=yes.
 */
final class Ability {

	public const CATEGORY = 'moforcoupon';

	public static function register(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}
		wp_register_ability(
			'moforcoupon/create-mixmatch-coupon',
			array(
				'label'               => __( '建立任選優惠優惠券', 'moforcoupon' ),
				'description'         => __( '建立一張「任選優惠(Mix & Match)」優惠券:指定一組商品(或全站),顧客任選 N 件,整組以固定總價或整組百分比折扣結算,可重複。例:任選3件$299 → qty=3、price_mode=fixed_total、price_value=299;任選5件75折 → qty=5、price_mode=percent、price_value=25。破壞性 —— 呼叫只會「提出」,使用者按確認後才建立。', 'moforcoupon' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'code'           => array(
							'type'        => 'string',
							'description' => __( '優惠券代碼,如 PICK3FOR299', 'moforcoupon' ),
						),
						'product_ids'    => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'integer' ),
							'description' => __( '可任選商品 ID(留空 + 無分類 = 全站)', 'moforcoupon' ),
						),
						'category_ids'   => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'integer' ),
							'description' => __( '可任選商品分類 term ID', 'moforcoupon' ),
						),
						'qty'            => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => __( '任選件數 N(至少 1)', 'moforcoupon' ),
						),
						'price_mode'     => array(
							'type'        => 'string',
							'enum'        => array( 'fixed_total', 'percent' ),
							'description' => __( 'fixed_total 整組固定總價 / percent 整組折扣百分比', 'moforcoupon' ),
						),
						'price_value'    => array(
							'type'        => 'number',
							'minimum'     => 0,
							'description' => __( 'fixed_total 為整組總價;percent 為折扣 %(0–100)', 'moforcoupon' ),
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
							'description' => __( '湊件提示,可用 {mixmatch_qty} {coupon_code}', 'moforcoupon' ),
						),
						'date_expires'   => array(
							'type'        => 'string',
							'description' => __( '到期日 YYYY-MM-DD,可空', 'moforcoupon' ),
						),
						'usage_limit'    => array( 'type' => 'integer' ),
						'individual_use' => array( 'type' => 'boolean' ),
					),
					'required'             => array( 'code', 'qty', 'price_mode' ),
					'additionalProperties' => false,
				),
				'output_schema'       => AbilityMeta::summary_output(),
				'execute_callback'    => array( MixMatchOps::class, 'create_prepare' ),
				'permission_callback' => array( self::class, 'can_write' ),
				'meta'                => AbilityMeta::write(),
			)
		);
	}

	public static function can_write(): bool {
		return current_user_can( MixMatchOps::CAP );
	}
}
