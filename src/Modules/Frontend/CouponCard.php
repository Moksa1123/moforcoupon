<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\Frontend;

use MoksaWeb\Moforcoupon\Coupon\Meta\Keys;
use MoksaWeb\Moforcoupon\Support\CouponPresenter;

defined( 'ABSPATH' ) || exit;

/**
 * Renders a single coupon "card" (badge + discount + label + eligibility hints + copy button +
 * expiry + apply link). Extracted from the [moforcoupon_coupons] shortcode so the same markup —
 * and therefore the same coupon-cards.css — is reused by the My Account "我的優惠券" page.
 */
final class CouponCard {

	public static function render( \WC_Coupon $coupon ): string {
		$code     = $coupon->get_code();
		$type_key = Catalog::type_key( (string) $coupon->get_discount_type() );
		$label    = (string) $coupon->get_meta( Keys::FRONT_LABEL, true );
		if ( '' === trim( $label ) ) {
			$label = (string) $coupon->get_description();
		}
		$expires = $coupon->get_date_expires();

		$html  = '<div class="moforcoupon-coupon moforcoupon-coupon--' . esc_attr( $type_key ) . '">';
		$html .= '<span class="moforcoupon-coupon__badge">' . esc_html( self::badge( $type_key ) ) . '</span>';
		$html .= '<div class="moforcoupon-coupon__discount">' . esc_html( self::discount_text( $coupon ) ) . '</div>';
		if ( '' !== trim( $label ) ) {
			$html .= '<div class="moforcoupon-coupon__label">' . esc_html( wp_strip_all_tags( $label ) ) . '</div>';
		}
		$hints = Hints::for_coupon( $coupon );
		if ( array() !== $hints ) {
			$html .= '<ul class="moforcoupon-coupon__hints">';
			foreach ( $hints as $hint ) {
				$html .= '<li>' . esc_html( $hint ) . '</li>';
			}
			$html .= '</ul>';
		}
		$html .= '<div class="moforcoupon-coupon__code"><code>' . esc_html( $code ) . '</code>';
		$html .= '<button type="button" class="moforcoupon-coupon__copy" data-code="' . esc_attr( $code ) . '"'
			. ' data-copied="' . esc_attr__( '已複製', 'moforcoupon' ) . '" aria-label="'
			. esc_attr(
				sprintf(
					/* translators: %s: coupon code. */
					__( '複製代碼 %s', 'moforcoupon' ),
					$code
				)
			)
			. '">' . esc_html__( '複製', 'moforcoupon' ) . '</button></div>';

		if ( $expires ) {
			$html .= '<div class="moforcoupon-coupon__expires">' . esc_html(
				sprintf(
					/* translators: %s: expiry date. */
					__( '有效期限至 %s', 'moforcoupon' ),
					$expires->date_i18n( get_option( 'date_format' ) )
				)
			) . '</div>';
		}

		$apply = self::apply_url( $coupon );
		if ( '' !== $apply ) {
			$html .= '<a class="moforcoupon-coupon__apply" href="' . esc_url( $apply ) . '">' . esc_html__( '立即套用', 'moforcoupon' ) . '</a>';
		}

		$html .= '</div>';
		return $html;
	}

	private static function badge( string $type_key ): string {
		$map = array(
			'percent'          => __( '百分比折扣', 'moforcoupon' ),
			'fixed_cart'       => __( '購物車折抵', 'moforcoupon' ),
			'fixed_product'    => __( '商品折抵', 'moforcoupon' ),
			'moforcoupon_bogo' => __( '買 X 送 Y', 'moforcoupon' ),
			'other'            => __( '優惠', 'moforcoupon' ),
		);
		return $map[ $type_key ] ?? $map['other'];
	}

	private static function discount_text( \WC_Coupon $coupon ): string {
		// Tiered / cashback coupons carry their value in feature meta, not the base amount, so the
		// raw amount (often 0) would read "0% OFF". Describe the mechanic instead.
		if ( 'yes' === (string) $coupon->get_meta( '_moforcoupon_tiers_enabled', true ) ) {
			return __( '階梯折扣', 'moforcoupon' );
		}
		$type = (string) $coupon->get_discount_type();
		if ( 'moforcoupon_cashback' === $type ) {
			return __( '回饋金', 'moforcoupon' );
		}
		$amount = (float) $coupon->get_amount();
		switch ( $type ) {
			case 'percent':
				/* translators: %s: percent number. */
				return sprintf( __( '%s%% OFF', 'moforcoupon' ), Catalog::percent_display( $amount ) );
			case 'fixed_cart':
			case 'fixed_product':
				return wp_strip_all_tags( wc_price( $amount ) );
			case 'moforcoupon_bogo':
				return __( '買就送', 'moforcoupon' );
			default:
				return $coupon->get_free_shipping() ? __( '免運費', 'moforcoupon' ) : __( '優惠', 'moforcoupon' );
		}
	}

	private static function apply_url( \WC_Coupon $coupon ): string {
		return CouponPresenter::apply_url( $coupon );
	}
}
