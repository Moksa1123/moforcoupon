<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\Frontend;

use MoksaWeb\Moforcoupon\Coupon\Meta\Keys;
use MoksaWeb\Moforcoupon\Support\CouponPresenter;
use MoksaWeb\Moforcoupon\Support\Urgency;

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

		$html .= self::countdown_html( $coupon, $expires );

		$apply = self::apply_url( $coupon );
		if ( '' !== $apply ) {
			$html .= '<a class="moforcoupon-coupon__apply" href="' . esc_url( $apply ) . '">' . esc_html__( '立即套用', 'moforcoupon' ) . '</a>';
		}

		$html .= '</div>';
		return $html;
	}

	private static function badge( string $type_key ): string {
		$map = array(
			'percent'              => __( '百分比折扣', 'moforcoupon' ),
			'fixed_cart'           => __( '購物車折抵', 'moforcoupon' ),
			'fixed_product'        => __( '商品折抵', 'moforcoupon' ),
			'moforcoupon_bogo'     => __( '買 X 送 Y', 'moforcoupon' ),
			'moforcoupon_nth_item' => __( '第 N 件折扣', 'moforcoupon' ),
			'moforcoupon_mixmatch' => __( '任選優惠', 'moforcoupon' ),
			'moforcoupon_cashback' => __( '回饋金', 'moforcoupon' ),
			'other'                => __( '優惠', 'moforcoupon' ),
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
			case 'moforcoupon_nth_item':
				return __( '第 N 件折扣', 'moforcoupon' );
			case 'moforcoupon_mixmatch':
				return __( '任選優惠', 'moforcoupon' );
			default:
				return $coupon->get_free_shipping() ? __( '免運費', 'moforcoupon' ) : __( '優惠', 'moforcoupon' );
		}
	}

	private static function apply_url( \WC_Coupon $coupon ): string {
		return CouponPresenter::apply_url( $coupon );
	}

	/**
	 * Live-countdown block. Server bakes only the target epoch (data-deadline, UTC ms) plus a
	 * no-JS fallback date; coupon-cards.js ticks the actual countdown so the cached, shared card
	 * HTML is never a frozen "3 days left". Empty string when disabled or already past.
	 *
	 * @param \WC_Coupon        $coupon  The coupon being rendered.
	 * @param \WC_DateTime|null $expires The coupon's expiry (already fetched by render()).
	 */
	private static function countdown_html( \WC_Coupon $coupon, $expires ): string {
		if ( 'yes' !== (string) $coupon->get_meta( Keys::COUNTDOWN_ENABLED, true ) ) {
			return '';
		}
		$source          = Urgency::source( $coupon->get_meta( Keys::COUNTDOWN_SOURCE, true ) );
		$expires_ts      = ( $expires instanceof \WC_DateTime ) ? $expires->getTimestamp() : null;
		$schedule_end_ts = self::wallclock_to_epoch( (string) $coupon->get_meta( Keys::SCHEDULE_END, true ) );
		$deadline        = Urgency::deadline_ts( $source, $expires_ts, $schedule_end_ts );
		if ( ! Urgency::is_live( $deadline, time() ) ) {
			return '';
		}
		return '<div class="moforcoupon-coupon__countdown" data-deadline="' . esc_attr( (string) ( $deadline * 1000 ) ) . '"'
			. ' data-ended="' . esc_attr__( '已結束', 'moforcoupon' ) . '">'
			. esc_html(
				sprintf(
					/* translators: %s: deadline date-time. */
					__( '倒數至 %s', 'moforcoupon' ),
					wp_date( get_option( 'date_format' ) . ' H:i', $deadline )
				)
			) . '</div>';
	}

	/**
	 * Convert a site wall-clock 'Y-m-d H:i:s' schedule string to a UTC epoch (null when empty /
	 * unparseable). The schedule meta is stored in the site timezone, so it must be read through
	 * wp_timezone() — treating it as UTC would shift the deadline.
	 */
	private static function wallclock_to_epoch( string $wallclock ): ?int {
		if ( '' === trim( $wallclock ) ) {
			return null;
		}
		try {
			return ( new \DateTimeImmutable( $wallclock, wp_timezone() ) )->getTimestamp();
		} catch ( \Exception $e ) {
			return null;
		}
	}
}
