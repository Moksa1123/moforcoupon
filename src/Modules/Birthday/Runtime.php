<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\Birthday;

use MoksaWeb\Moforcoupon\Coupon\CouponService;
use MoksaWeb\Moforcoupon\Support\PersonalCoupon;

defined( 'ABSPATH' ) || exit;

/**
 * Daily pass: issue a birthday coupon (cloned from a template) to every customer whose birthday is
 * today, at most once per year.
 */
final class Runtime {

	private const YEAR_META = '_moforcoupon_birthday_year';

	/** @return array{template:string,expiry_days:int} */
	private static function config(): array {
		return array(
			'template'    => trim( (string) get_option( 'moforcoupon_birthday_template', '' ) ),
			'expiry_days' => max( 0, (int) get_option( 'moforcoupon_birthday_expiry_days', 30 ) ),
		);
	}

	public static function run(): void {
		$cfg = self::config();
		if ( '' === $cfg['template'] ) {
			return;
		}
		$source = CouponService::resolve_id( $cfg['template'] );
		if ( ! $source ) {
			return;
		}

		$today_md = wp_date( 'm-d' );
		$year     = (int) wp_date( 'Y' );

		$users = get_users(
			array(
				'meta_key'   => AccountField::META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- daily cron, exact match on MM-DD.
				'meta_value' => $today_md,           // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'number'     => 500,
				'fields'     => array( 'ID', 'user_email' ),
			)
		);

		foreach ( $users as $user ) {
			$uid = (int) $user->ID;
			if ( (int) get_user_meta( $uid, self::YEAR_META, true ) === $year ) {
				continue; // already issued this year.
			}
			$result = PersonalCoupon::issue( $source, 'BDAY-', $uid, (string) $user->user_email, $cfg['expiry_days'] );
			if ( is_wp_error( $result ) ) {
				continue;
			}
			update_user_meta( $uid, self::YEAR_META, $year );

			/**
			 * Fires after a birthday coupon has been issued.
			 *
			 * @param int    $uid       Customer user id.
			 * @param int    $coupon_id New coupon id.
			 */
			do_action( 'moforcoupon_birthday_issued', $uid, (int) $result );
		}
	}
}
