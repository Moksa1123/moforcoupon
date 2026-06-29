<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\Cashback;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the 'moforcoupon_cashback' coupon discount type. Like the BOGO type this single
 * filter is the whole registration — the coupon stays an ordinary shop_coupon whose
 * discount_type is 'moforcoupon_cashback'. We register NO get_discount_amount filter, so WC
 * returns 0 (cashback never reduces the cart); the reward is granted post-order by Runtime.
 */
final class Type {

	public const TYPE = 'moforcoupon_cashback';

	public static function register(): void {
		add_filter( 'woocommerce_coupon_discount_types', array( self::class, 'add_type' ) );
	}

	/**
	 * @param mixed $types
	 * @return mixed
	 */
	public static function add_type( $types ) {
		if ( is_array( $types ) ) {
			$types[ self::TYPE ] = __( '回饋金 / 點數(Cashback)', 'moforcoupon' );
		}
		return $types;
	}
}
