<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Shared capability constant + "forbidden" error for the coupon operation classes (CouponOps,
 * BogoOps, SendOps). Each guarded method still performs its own current_user_can( self::CAP )
 * check — that defence-in-depth is intentional and stays per-method — but the capability name
 * and the WP_Error it returns now live in one place. Trait constants (PHP 8.2+) are exposed on
 * the using class, so existing CouponOps::CAP / BogoOps::CAP / SendOps::CAP references keep working.
 */
trait GuardedOps {

	/** Capability required by every coupon operation. */
	public const CAP = 'manage_woocommerce';

	/** The standard "insufficient permission" result returned by a failed capability check. */
	private static function denied(): \WP_Error {
		return new \WP_Error( 'moforcoupon_forbidden', __( '權限不足。', 'moforcoupon' ) );
	}
}
