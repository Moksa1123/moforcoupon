<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\CouponSend;

use MoksaWeb\Moforcoupon\Coupon\CouponService;
use MoksaWeb\Moforcoupon\Support\GuardedOps;

defined( 'ABSPATH' ) || exit;

/**
 * Propose/apply pair for the send-coupon ability. execute_callback points at
 * send_prepare (proposal only — never sends); send_apply does the real send and runs
 * solely via the in-dashboard confirm flow / admin action after a human confirmation.
 * Both ends re-check the capability.
 */
final class SendOps {

	use GuardedOps;

	/**
	 * @param mixed $input
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function send_prepare( $input ) {
		if ( ! current_user_can( self::CAP ) ) {
			return self::denied();
		}
		$input = is_array( $input ) ? $input : array();
		$id    = CouponService::resolve_id( $input['code_or_id'] ?? '' );
		if ( ! $id ) {
			return new \WP_Error( 'moforcoupon_not_found', __( '找不到該優惠券。', 'moforcoupon' ) );
		}
		$email = isset( $input['email'] ) ? sanitize_email( (string) $input['email'] ) : '';
		if ( '' === $email || ! is_email( $email ) ) {
			return new \WP_Error( 'moforcoupon_bad_email', __( '收件人 Email 無效。', 'moforcoupon' ) );
		}
		$note     = isset( $input['note'] ) ? sanitize_text_field( (string) $input['note'] ) : '';
		$restrict = ! empty( $input['restrict_to_email'] );
		$data     = CouponService::get( $id );
		$code     = (string) ( $data['code'] ?? $id );

		return array(
			'id'       => $id,
			'email'    => $email,
			'note'     => $note,
			'restrict' => $restrict,
			'summary'  => sprintf(
				/* translators: 1: coupon code, 2: recipient email, 3: optional restriction note. */
				__( '將優惠券 %1$s 寄送至 %2$s%3$s', 'moforcoupon' ),
				$code,
				$email,
				$restrict ? __( '(並鎖定只限此 Email 使用)', 'moforcoupon' ) : ''
			),
		);
	}

	/**
	 * @param array<string,mixed> $params
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function send_apply( array $params ) {
		if ( ! current_user_can( self::CAP ) ) {
			return self::denied();
		}
		$id     = (int) ( $params['id'] ?? 0 );
		$email  = (string) ( $params['email'] ?? '' );
		$note   = (string) ( $params['note'] ?? '' );
		$result = SendService::send( $id, $email, $note, ! empty( $params['restrict'] ) );
		if ( $result instanceof \WP_Error ) {
			return $result;
		}
		return array(
			'id'    => $id,
			/* translators: %s: recipient email. */
			'reply' => sprintf( __( '已將優惠券寄送至 %s。', 'moforcoupon' ), $email ),
		);
	}
}
