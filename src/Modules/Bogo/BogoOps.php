<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\Bogo;

use MoksaWeb\Moforcoupon\Coupon\CouponService;
use MoksaWeb\Moforcoupon\Support\GuardedOps;

defined( 'ABSPATH' ) || exit;

/**
 * Destructive create-bogo-coupon op as a propose/apply pair, mirroring CouponOps.
 * create_prepare proposes only (no writes); create_apply runs solely after a human
 * confirmation (AI confirm flow / REST). Both ends re-check the capability. Native
 * fields reuse CouponService; the BOGO config is written via the shared BogoMeta so
 * the AI and admin-panel paths can never diverge.
 */
final class BogoOps {

	use GuardedOps;

	/**
	 * @param mixed $input
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function create_prepare( $input ) {
		if ( ! current_user_can( self::CAP ) ) {
			return self::denied();
		}
		$input = is_array( $input ) ? $input : array();

		// Native fields: validate with a placeholder type/amount, then force the BOGO type.
		$native_input                  = $input;
		$native_input['discount_type'] = 'fixed_cart';
		$native_input['amount']        = 0;
		$fields                        = CouponService::normalize_and_validate( $native_input, false );
		if ( $fields instanceof \WP_Error ) {
			return $fields;
		}
		if ( empty( $fields['code'] ) ) {
			return new \WP_Error( 'moforcoupon_invalid_code', __( '優惠券代碼不可空白。', 'moforcoupon' ) );
		}
		if ( CouponService::find_id_by_code( $fields['code'] ) > 0 ) {
			return new \WP_Error(
				'moforcoupon_duplicate',
				/* translators: %s: coupon code. */
				sprintf( __( '優惠券代碼 %s 已存在,請換一個。', 'moforcoupon' ), $fields['code'] )
			);
		}
		$fields['discount_type'] = BogoMeta::TYPE;
		$fields['amount']        = 0;

		$bogo = self::normalize_bogo( $input );
		if ( $bogo instanceof \WP_Error ) {
			return $bogo;
		}

		return array(
			'fields'  => $fields,
			'bogo'    => $bogo,
			'summary' => self::build_summary( (string) $fields['code'], $bogo ),
		);
	}

	/**
	 * @param array<string,mixed> $params
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function create_apply( array $params ) {
		if ( ! current_user_can( self::CAP ) ) {
			return self::denied();
		}
		$fields = isset( $params['fields'] ) && is_array( $params['fields'] ) ? $params['fields'] : array();
		$bogo   = isset( $params['bogo'] ) && is_array( $params['bogo'] ) ? $params['bogo'] : array();

		$coupon = CouponService::save( $fields );
		if ( $coupon instanceof \WP_Error ) {
			return $coupon;
		}
		BogoMeta::write( $coupon->get_id(), $bogo );

		return array(
			'id'    => $coupon->get_id(),
			'reply' => sprintf(
				/* translators: %s: coupon code. */
				__( '已建立買 X 送 Y 優惠券 %s。', 'moforcoupon' ),
				$coupon->get_code()
			),
		);
	}

	/**
	 * @param array<string,mixed> $input
	 * @return array<string,mixed>|\WP_Error
	 */
	private static function normalize_bogo( array $input ) {
		$cfg         = BogoMeta::sanitize( $input );
		$has_trigger = array() !== $cfg['trigger_product_ids'] || array() !== $cfg['trigger_category_ids'];
		$has_reward  = array() !== $cfg['reward_product_ids'] || array() !== $cfg['reward_category_ids'];

		if ( ! $has_trigger ) {
			return new \WP_Error( 'moforcoupon_bogo_no_trigger', __( '請指定購買條件的商品或分類。', 'moforcoupon' ) );
		}
		if ( ! $has_reward ) {
			return new \WP_Error( 'moforcoupon_bogo_no_reward', __( '請指定贈品的商品或分類。', 'moforcoupon' ) );
		}
		if ( 'percent' === $cfg['reward_mode'] && $cfg['reward_value'] > 100 ) {
			return new \WP_Error( 'moforcoupon_bogo_bad_value', __( '百分比折扣不可超過 100。', 'moforcoupon' ) );
		}
		if ( 'fixed_per_item' === $cfg['reward_mode'] && $cfg['reward_value'] <= 0 ) {
			return new \WP_Error( 'moforcoupon_bogo_bad_value', __( '每件固定折扣需大於 0。', 'moforcoupon' ) );
		}
		return $cfg;
	}

	/**
	 * @param string              $code Coupon code.
	 * @param array<string,mixed> $cfg  Normalized BOGO config.
	 */
	private static function build_summary( string $code, array $cfg ): string {
		$mode = (string) $cfg['reward_mode'];
		if ( 'free' === $mode ) {
			$reward = __( '免費', 'moforcoupon' );
		} elseif ( 'fixed_per_item' === $mode ) {
			/* translators: %s: per-item discount amount. */
			$reward = sprintf( __( '每件折 %s', 'moforcoupon' ), (string) $cfg['reward_value'] );
		} else {
			/* translators: %s: percent discount. */
			$reward = sprintf( __( '%s%% 折扣', 'moforcoupon' ), (string) $cfg['reward_value'] );
		}
		$repeat = 'repeat' === $cfg['deal_mode'] ? __( '(可重複)', 'moforcoupon' ) : __( '(限一次)', 'moforcoupon' );

		return sprintf(
			/* translators: 1: code, 2: trigger qty, 3: reward qty, 4: reward desc, 5: repeat note. */
			__( '建立買 X 送 Y 優惠券 %1$s:每買 %2$d 件指定商品 → %3$d 件贈品 %4$s %5$s', 'moforcoupon' ),
			$code,
			(int) $cfg['trigger_qty'],
			(int) $cfg['reward_qty'],
			$reward,
			$repeat
		);
	}
}
