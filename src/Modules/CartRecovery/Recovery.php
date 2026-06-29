<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\CartRecovery;

use MoksaWeb\Moforcoupon\Coupon\CouponService;
use MoksaWeb\Moforcoupon\Support\CouponPresenter;
use MoksaWeb\Moforcoupon\Support\PersonalCoupon;

defined( 'ABSPATH' ) || exit;

/**
 * The recovery flow: hourly, email shoppers whose carts have been abandoned for the configured
 * window (optionally with an incentive coupon), mark recovered when an order with that email is
 * placed, and purge stale records daily.
 */
final class Recovery {

	/** @return array{hours:int,coupon:bool,template:string,expiry_days:int} */
	private static function config(): array {
		$hours = (int) get_option( 'moforcoupon_cartrecovery_hours', 4 );
		return array(
			'hours'       => ( $hours >= 1 && $hours <= 168 ) ? $hours : 4,
			'coupon'      => 'yes' === get_option( 'moforcoupon_cartrecovery_coupon', 'no' ),
			'template'    => trim( (string) get_option( 'moforcoupon_cartrecovery_template', '' ) ),
			'expiry_days' => max( 0, (int) get_option( 'moforcoupon_cartrecovery_expiry_days', 7 ) ),
		);
	}

	/** Hourly: email each abandoned cart once. */
	public static function run_hourly(): void {
		$cfg = self::config();
		foreach ( Store::pending_abandoned( $cfg['hours'] * HOUR_IN_SECONDS, 50 ) as $id ) {
			$email = Store::email_of( $id );
			if ( ! is_email( $email ) ) {
				Store::set_status( $id, 'invalid' );
				continue;
			}
			$code  = '';
			$apply = '';
			if ( $cfg['coupon'] && '' !== $cfg['template'] ) {
				$source = CouponService::resolve_id( $cfg['template'] );
				if ( $source ) {
					$user   = get_user_by( 'email', $email );
					$new_id = PersonalCoupon::issue( $source, 'BACK-', $user ? (int) $user->ID : 0, $email, $cfg['expiry_days'] );
					if ( ! is_wp_error( $new_id ) ) {
						$coupon = new \WC_Coupon( (int) $new_id );
						$code   = $coupon->get_code();
						$apply  = CouponPresenter::apply_url( $coupon );
					}
				}
			}
			self::send_email( $email, Store::items_of( $id ), $code, $apply );
			Store::set_status( $id, 'emailed' );
		}
	}

	/** Daily: housekeeping. */
	public static function run_daily(): void {
		Store::purge( 30 );
	}

	/**
	 * An order was placed — stop chasing that email.
	 *
	 * @param mixed $order_id
	 */
	public static function on_order( $order_id ): void {
		$order = function_exists( 'wc_get_order' ) ? wc_get_order( (int) $order_id ) : null;
		if ( $order instanceof \WC_Order ) {
			Store::mark_recovered_by_email( (string) $order->get_billing_email() );
		}
	}

	/**
	 * @param string                                 $email
	 * @param array<int,array{name:string,qty:int}> $items
	 * @param string                                 $code
	 * @param string                                 $apply
	 */
	private static function send_email( string $email, array $items, string $code, string $apply ): void {
		$site         = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
		$checkout_url = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/' );

		/* translators: %s: site name. */
		$subject = sprintf( __( '您在 %s 的購物車還在等您', 'moforcoupon' ), $site );

		$parts   = array();
		$parts[] = '<p>' . esc_html__( '您好,您的購物車裡還有商品尚未結帳:', 'moforcoupon' ) . '</p>';
		if ( array() !== $items ) {
			$parts[] = '<ul>';
			foreach ( $items as $item ) {
				$parts[] = '<li>' . esc_html( (string) ( $item['name'] ?? '' ) ) . ' × ' . (int) ( $item['qty'] ?? 1 ) . '</li>';
			}
			$parts[] = '</ul>';
		}
		if ( '' !== $code ) {
			$parts[] = '<p>' . esc_html(
				sprintf(
					/* translators: %s: coupon code. */
					__( '回來結帳就送您優惠券:%s', 'moforcoupon' ),
					$code
				)
			) . '</p>';
		}
		$link    = '' !== $apply ? $apply : $checkout_url;
		$parts[] = '<p><a href="' . esc_url( $link ) . '">' . esc_html__( '完成結帳', 'moforcoupon' ) . '</a></p>';

		wp_mail(
			$email,
			$subject,
			implode( "\n", $parts ),
			array( 'Content-Type: text/html; charset=UTF-8' )
		);
	}
}
