<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\MixMatch;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the 'moforcoupon_mixmatch' coupon discount type. This single filter IS the whole type
 * registration — the coupon stays an ordinary shop_coupon whose native discount_type meta is
 * 'moforcoupon_mixmatch', and WC_Coupon::is_type() keys off it. The discount is applied by Frontend
 * via set_price (never WC's coupon-amount engine), so we register NO get_discount_amount filter.
 */
final class Type {

	public static function register(): void {
		add_filter( 'woocommerce_coupon_discount_types', array( self::class, 'add_type' ) );
	}

	/**
	 * @param array<string,string> $types
	 * @return array<string,string>
	 */
	public static function add_type( $types ) {
		if ( is_array( $types ) ) {
			$types[ MixMatchMeta::TYPE ] = __( '任選優惠(Mix & Match)', 'moforcoupon' );
		}
		return $types;
	}
}
