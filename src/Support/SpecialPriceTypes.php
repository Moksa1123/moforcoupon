<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Support;

defined( 'ABSPATH' ) || exit;

/**
 * The plugin's "special-price" coupon types — the ones that discount by rewriting cart line prices
 * via set_price() in a woocommerce_before_calculate_totals hook (BOGO, Nth-item, Mix & Match),
 * rather than through WooCommerce's own coupon-amount engine.
 *
 * Because each of those modules hooks the SAME recalculation and overwrites (not adds to) a line's
 * price, two different special-price coupons whose item sets overlap would clobber each other — the
 * last module to run wins and the other's discount silently vanishes (and the savings summary would
 * double-count). So at most ONE special-price coupon may be applied per cart. assert_single() is the
 * shared guard every such module calls from its woocommerce_coupon_is_valid filter; it keeps the
 * first-applied special coupon and rejects any later one of any special type.
 */
final class SpecialPriceTypes {

	/** @var array<int,string> Discount-type slugs that discount via set_price(). */
	public const TYPES = array( 'moforcoupon_bogo', 'moforcoupon_nth_item', 'moforcoupon_mixmatch' );

	/** Whether a coupon is one of the set_price special-price types. */
	public static function is_special( \WC_Coupon $coupon ): bool {
		foreach ( self::TYPES as $type ) {
			if ( $coupon->is_type( $type ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Enforce one special-price coupon per cart: if another special-price coupon is already applied
	 * BEFORE this one, reject this one. Reaching $coupon's own code first means it is the kept one.
	 * 0-discount is never a reason to reject (these coupons keep a 0 nominal amount).
	 *
	 * @throws \Exception When a different special-price coupon already holds the cart.
	 */
	public static function assert_single( \WC_Coupon $coupon ): void {
		if ( ! function_exists( 'WC' ) || ! ( WC()->cart instanceof \WC_Cart ) ) {
			return;
		}
		foreach ( WC()->cart->get_applied_coupons() as $applied_code ) {
			// get_applied_coupons() returns normalized (lowercased) codes.
			if ( strtolower( (string) $applied_code ) === strtolower( $coupon->get_code() ) ) {
				return; // Reached self first → this is the kept one.
			}
			$other = new \WC_Coupon( $applied_code );
			if ( self::is_special( $other ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- esc_html applied.
				throw new \Exception( esc_html__( '每次只能使用一張特惠折扣型(買 X 送 Y / 第 N 件折扣 / 任選優惠)優惠券。', 'moforcoupon' ) );
			}
		}
	}
}
