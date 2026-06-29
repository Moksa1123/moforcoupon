<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\Remarketing;

use MoksaWeb\Moforcoupon\Coupon\CouponService;
use MoksaWeb\Moforcoupon\Support\AbilityMeta;

defined( 'ABSPATH' ) || exit;

/**
 * Exposes the post-purchase remarketing rule to the Abilities API / AI assistant / MCP: read the
 * current configuration, or set it in one call (validating the template coupon exists). Honours the
 * "every coupon action is an ability" promise for the marketing-automation layer.
 */
final class Ability {

	private const CAP      = 'manage_woocommerce';
	private const CATEGORY = 'moforcoupon';

	public static function register(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'moforcoupon/get-remarketing-config',
			array(
				'label'               => __( '查詢再行銷設定', 'moforcoupon' ),
				'description'         => __( '讀取「訂單完成後自動發券」目前的設定:範本券、發放條件、金額門檻、有效天數、是否寄信。唯讀。', 'moforcoupon' ),
				'category'            => self::CATEGORY,
				'input_schema'        => AbilityMeta::empty_input(),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'enabled'     => array( 'type' => 'boolean' ),
						'source'      => array( 'type' => 'string' ),
						'condition'   => array( 'type' => 'string' ),
						'min_total'   => array( 'type' => 'number' ),
						'expiry_days' => array( 'type' => 'integer' ),
						'email'       => array( 'type' => 'boolean' ),
					),
				),
				'execute_callback'    => array( self::class, 'get_config' ),
				'permission_callback' => array( self::class, 'can_manage' ),
				'meta'                => AbilityMeta::read(),
			)
		);

		wp_register_ability(
			'moforcoupon/set-remarketing-config',
			array(
				'label'               => __( '設定再行銷規則', 'moforcoupon' ),
				'description'         => __( '設定「訂單完成後自動發券」:範本券代碼、發放條件(all / first_order / min_total)、金額門檻、有效天數、是否寄信。會驗證範本券是否存在。', 'moforcoupon' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'enabled'     => array(
							'type'        => 'boolean',
							'description' => __( '是否啟用自動發券', 'moforcoupon' ),
						),
						'source'      => array(
							'type'        => 'string',
							'description' => __( '範本優惠券代碼(會複製成顧客專屬券)', 'moforcoupon' ),
						),
						'condition'   => array(
							'type'        => 'string',
							'enum'        => Rules::CONDITIONS,
							'description' => __( '發放條件', 'moforcoupon' ),
						),
						'min_total'   => array(
							'type'        => 'number',
							'description' => __( '條件為 min_total 時的金額門檻', 'moforcoupon' ),
						),
						'expiry_days' => array(
							'type'        => 'integer',
							'description' => __( '回購券有效天數(0 = 沿用範本到期)', 'moforcoupon' ),
						),
						'email'       => array(
							'type'        => 'boolean',
							'description' => __( '是否同時 Email 通知顧客', 'moforcoupon' ),
						),
					),
					'required'             => array(),
					'additionalProperties' => false,
				),
				'output_schema'       => AbilityMeta::summary_output(),
				'execute_callback'    => array( self::class, 'set_config' ),
				'permission_callback' => array( self::class, 'can_manage' ),
				'meta'                => AbilityMeta::write(),
			)
		);
	}

	public static function can_manage(): bool {
		return current_user_can( self::CAP );
	}

	/**
	 * @param mixed $input
	 * @return array<string,mixed>
	 */
	public static function get_config( $input ): array {
		if ( ! self::can_manage() ) {
			return array( 'enabled' => false );
		}
		return array(
			'enabled'     => 'yes' === get_option( 'moforcoupon_remarketing_enabled', 'no' ),
			'source'      => (string) get_option( 'moforcoupon_remarketing_source', '' ),
			'condition'   => Rules::normalize_condition( (string) get_option( 'moforcoupon_remarketing_condition', 'all' ) ),
			'min_total'   => (float) get_option( 'moforcoupon_remarketing_min_total', 0 ),
			'expiry_days' => (int) get_option( 'moforcoupon_remarketing_expiry_days', 30 ),
			'email'       => 'yes' === get_option( 'moforcoupon_remarketing_email', 'no' ),
		);
	}

	/**
	 * @param mixed $input
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function set_config( $input ) {
		if ( ! self::can_manage() ) {
			return new \WP_Error( 'moforcoupon_forbidden', __( '權限不足。', 'moforcoupon' ) );
		}
		$input = is_array( $input ) ? $input : array();

		if ( isset( $input['source'] ) ) {
			$src = trim( (string) $input['source'] );
			if ( '' !== $src && ! CouponService::resolve_id( $src ) ) {
				return new \WP_Error( 'moforcoupon_not_found', __( '找不到指定的範本優惠券代碼。', 'moforcoupon' ) );
			}
			update_option( 'moforcoupon_remarketing_source', $src );
		}
		if ( isset( $input['enabled'] ) ) {
			update_option( 'moforcoupon_remarketing_enabled', $input['enabled'] ? 'yes' : 'no' );
		}
		if ( isset( $input['condition'] ) ) {
			update_option( 'moforcoupon_remarketing_condition', Rules::normalize_condition( (string) $input['condition'] ) );
		}
		if ( isset( $input['min_total'] ) ) {
			update_option( 'moforcoupon_remarketing_min_total', (string) (float) $input['min_total'] );
		}
		if ( isset( $input['expiry_days'] ) ) {
			update_option( 'moforcoupon_remarketing_expiry_days', (string) max( 0, (int) $input['expiry_days'] ) );
		}
		if ( isset( $input['email'] ) ) {
			update_option( 'moforcoupon_remarketing_email', $input['email'] ? 'yes' : 'no' );
		}

		return array( 'summary' => __( '再行銷設定已更新。', 'moforcoupon' ) );
	}
}
