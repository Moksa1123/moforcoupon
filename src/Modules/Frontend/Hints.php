<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\Frontend;

use MoksaWeb\Moforcoupon\Coupon\Meta\Keys;
use MoksaWeb\Moforcoupon\Support\Urgency;

defined( 'ABSPATH' ) || exit;

/**
 * Builds short, customer-facing eligibility hints for a coupon card ("滿 NT$1,500"、"限會員"…),
 * so a shopper sees the gating conditions BEFORE copying the code and hitting an error at the
 * cart. Reads both native WC_Coupon restrictions and the plugin's own condition meta.
 */
final class Hints {

	/**
	 * @return array<int,string>
	 */
	public static function for_coupon( \WC_Coupon $coupon ): array {
		$hints = array();

		// Minimum spend — the larger of the native minimum and our own min-subtotal condition.
		$min = max( (float) $coupon->get_minimum_amount(), (float) $coupon->get_meta( Keys::MIN_SUBTOTAL, true ) );
		if ( $min > 0 ) {
			$hints[] = sprintf(
				/* translators: %s: formatted minimum amount. */
				__( '滿 %s', 'moforcoupon' ),
				self::price( $min )
			);
		}

		if ( 'yes' === $coupon->get_meta( Keys::ROLE_ENABLED, true ) && 'allowed' === $coupon->get_meta( Keys::ROLE_TYPE, true ) ) {
			$hints[] = __( '限會員', 'moforcoupon' );
		}

		if ( 'yes' === $coupon->get_meta( Keys::CUST_ENABLED, true ) && 'yes' === $coupon->get_meta( Keys::CUST_FIRST_ONLY, true ) ) {
			$hints[] = __( '限新客首購', 'moforcoupon' );
		}

		if ( array() !== $coupon->get_product_ids() || array() !== $coupon->get_product_categories() ) {
			$hints[] = __( '限指定商品', 'moforcoupon' );
		}

		if ( 'yes' === $coupon->get_meta( Keys::SHIPREGION_ENABLED, true ) ) {
			$hints[] = __( '限特定收件地區', 'moforcoupon' );
		}

		if ( 'yes' === $coupon->get_meta( Keys::PAYMENT_ENABLED, true ) ) {
			$hints[] = __( '限特定付款方式', 'moforcoupon' );
		}

		if ( $coupon->get_free_shipping() ) {
			$hints[] = __( '含免運', 'moforcoupon' );
		}

		if ( $coupon->get_individual_use() ) {
			$hints[] = __( '不可與其他券並用', 'moforcoupon' );
		}

		if ( $coupon->get_exclude_sale_items() ) {
			$hints[] = __( '特價商品不適用', 'moforcoupon' );
		}

		// "Only N left" urgency badge, derived from the native usage limit (display only — WC
		// enforces the limit itself). Hidden when usage is unlimited, to avoid a misleading "0 left".
		if ( 'yes' === $coupon->get_meta( Keys::STOCK_SHOW, true ) ) {
			$remaining = Urgency::remaining( (int) $coupon->get_usage_limit(), (int) $coupon->get_usage_count() );
			if ( Urgency::should_show_stock( $remaining, (int) $coupon->get_meta( Keys::STOCK_THRESHOLD, true ) ) ) {
				$hints[] = sprintf(
					/* translators: %d: remaining redemptions. */
					__( '僅剩 %d 張', 'moforcoupon' ),
					(int) $remaining
				);
			}
		}

		/**
		 * Filter the customer-facing eligibility hints shown on a coupon card.
		 *
		 * @param array<int,string> $hints  Hint strings.
		 * @param \WC_Coupon        $coupon The coupon.
		 */
		return array_values( (array) apply_filters( 'moforcoupon_coupon_hints', $hints, $coupon ) );
	}

	/** A readable, entity-decoded price string for inline text (re-escaped on output). */
	private static function price( float $amount ): string {
		return html_entity_decode( wp_strip_all_tags( wc_price( $amount ) ), ENT_QUOTES, 'UTF-8' );
	}
}
