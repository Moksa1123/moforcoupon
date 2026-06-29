<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Single source of truth for turning a coupon discount-type slug into a human label. Every
 * user-facing surface (reports, the AI confirm card, send / expiry emails) must go through here so
 * a raw slug like "moforcoupon_cashback" can never leak to a customer. Unknown types fall back to
 * WooCommerce's own registered label, then to the raw slug only as a last resort.
 */
final class CouponType {

	/** @return array<string,string> discount_type slug => label. */
	public static function labels(): array {
		return array(
			'percent'              => __( '百分比折扣', 'moforcoupon' ),
			'fixed_cart'           => __( '購物車固定折抵', 'moforcoupon' ),
			'fixed_product'        => __( '商品固定折抵', 'moforcoupon' ),
			'moforcoupon_bogo'     => __( '買 X 送 Y', 'moforcoupon' ),
			'moforcoupon_cashback' => __( '回饋金', 'moforcoupon' ),
			'moforcoupon_nth_item' => __( '第 N 件折扣', 'moforcoupon' ),
			'moforcoupon_mixmatch' => __( '任選優惠', 'moforcoupon' ),
		);
	}

	public static function label( string $type ): string {
		$labels = self::labels();
		if ( isset( $labels[ $type ] ) ) {
			return $labels[ $type ];
		}
		$wc = function_exists( 'wc_get_coupon_types' ) ? wc_get_coupon_types() : array();
		if ( isset( $wc[ $type ] ) ) {
			return (string) $wc[ $type ];
		}
		return '' !== $type ? $type : '—';
	}
}
