<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\NthItem;

use MoksaWeb\Moforcoupon\Coupon\CouponService;
use MoksaWeb\Moforcoupon\Support\GuardedOps;

defined( 'ABSPATH' ) || exit;

/**
 * Destructive create-nth-item-coupon op as a propose/apply pair, mirroring BogoOps.
 * create_prepare proposes only (no writes); create_apply runs solely after a human confirmation.
 * Both ends re-check the capability. The Nth-item config is written via the shared NthItemMeta so
 * the AI and admin-panel paths can never diverge.
 */
final class NthItemOps {

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
		$fields['discount_type'] = NthItemMeta::TYPE;
		$fields['amount']        = 0;

		$nth = self::normalize_nth( $input );
		if ( $nth instanceof \WP_Error ) {
			return $nth;
		}

		return array(
			'fields'  => $fields,
			'nth'     => $nth,
			'summary' => self::build_summary( (string) $fields['code'], $nth ),
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
		$nth    = isset( $params['nth'] ) && is_array( $params['nth'] ) ? $params['nth'] : array();

		$coupon = CouponService::save( $fields );
		if ( $coupon instanceof \WP_Error ) {
			return $coupon;
		}
		NthItemMeta::write( $coupon->get_id(), $nth );

		return array(
			'id'    => $coupon->get_id(),
			'reply' => sprintf(
				/* translators: %s: coupon code. */
				__( '已建立第 N 件折扣優惠券 %s。', 'moforcoupon' ),
				$coupon->get_code()
			),
		);
	}

	/**
	 * @param array<string,mixed> $input
	 * @return array<string,mixed>|\WP_Error
	 */
	private static function normalize_nth( array $input ) {
		// Validate the RAW value before sanitize clamps it, so an out-of-range request returns an
		// explicit error to the AI/MCP caller instead of being silently rewritten to N=2.
		if ( (int) ( $input['n'] ?? 0 ) < 2 ) {
			return new \WP_Error( 'moforcoupon_nth_bad_n', __( 'N 至少為 2(第二件起)。', 'moforcoupon' ) );
		}
		$cfg = NthItemMeta::sanitize( $input );
		if ( 'percent' === $cfg['reward_mode'] && $cfg['reward_value'] > 100 ) {
			return new \WP_Error( 'moforcoupon_nth_bad_value', __( '百分比折扣不可超過 100。', 'moforcoupon' ) );
		}
		if ( 'fixed_per_item' === $cfg['reward_mode'] && $cfg['reward_value'] <= 0 ) {
			return new \WP_Error( 'moforcoupon_nth_bad_value', __( '每件固定折扣需大於 0。', 'moforcoupon' ) );
		}
		return $cfg;
	}

	/**
	 * @param string              $code Coupon code.
	 * @param array<string,mixed> $cfg  Normalized Nth-item config.
	 */
	private static function build_summary( string $code, array $cfg ): string {
		$mode = (string) $cfg['reward_mode'];
		if ( 'free' === $mode ) {
			$reward = __( '免費', 'moforcoupon' );
		} elseif ( 'fixed_per_item' === $mode ) {
			/* translators: %s: per-item discount amount. */
			$reward = sprintf( __( '每件折 %s', 'moforcoupon' ), (string) $cfg['reward_value'] );
		} else {
			/* translators: %s: discount percent. */
			$reward = sprintf( __( '折 %s%%', 'moforcoupon' ), (string) $cfg['reward_value'] );
		}
		$repeat = 'repeat' === $cfg['deal_mode'] ? __( '(可重複)', 'moforcoupon' ) : __( '(限一次)', 'moforcoupon' );

		return sprintf(
			/* translators: 1: code, 2: N, 3: reward desc, 4: repeat note. */
			__( '建立第 N 件折扣優惠券 %1$s:每滿 %2$d 件,第 N 件 %3$s %4$s', 'moforcoupon' ),
			$code,
			(int) $cfg['n'],
			$reward,
			$repeat
		);
	}
}
