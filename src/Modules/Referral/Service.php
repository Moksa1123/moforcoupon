<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\Referral;

use MoksaWeb\Moforcoupon\Coupon\CouponService;
use MoksaWeb\Moforcoupon\Support\OrderOnce;
use MoksaWeb\Moforcoupon\Support\PersonalCoupon;

defined( 'ABSPATH' ) || exit;

/**
 * Refer-a-friend engine. Each customer gets a stable referral link (?mfc_ref=<code>); a visitor who
 * arrives through it has the referrer stored on their order at checkout; when that order completes
 * (and the friend hasn't been counted for this referrer before) the referrer — and optionally the
 * friend — is issued a reward coupon by cloning a configured template.
 */
final class Service {

	public const COOKIE = 'mfc_ref';

	private const CODE_META     = '_moforcoupon_referral_code';
	private const ORDER_REF     = '_moforcoupon_referred_by';
	private const REWARDED_META = '_moforcoupon_referral_rewarded';
	private const FRIENDS_META  = '_moforcoupon_referred_friends';

	/** @return array{referrer_template:string,friend_template:string,expiry_days:int} */
	private static function config(): array {
		return array(
			'referrer_template' => trim( (string) get_option( 'moforcoupon_referral_referrer_template', '' ) ),
			'friend_template'   => trim( (string) get_option( 'moforcoupon_referral_friend_template', '' ) ),
			'expiry_days'       => max( 0, (int) get_option( 'moforcoupon_referral_expiry_days', 30 ) ),
		);
	}

	/** This customer's referral code (generated once, stored in user meta). */
	public static function code_for( int $user_id ): string {
		if ( $user_id <= 0 ) {
			return '';
		}
		$code = (string) get_user_meta( $user_id, self::CODE_META, true );
		if ( '' === $code ) {
			$code = strtolower( wp_generate_password( 8, false, false ) );
			update_user_meta( $user_id, self::CODE_META, $code );
		}
		return $code;
	}

	public static function url_for( int $user_id ): string {
		$code = self::code_for( $user_id );
		return '' === $code ? '' : add_query_arg( self::COOKIE, $code, home_url( '/' ) );
	}

	/** The user id behind a referral code, or 0. */
	public static function resolve( string $code ): int {
		$code = sanitize_text_field( $code );
		if ( '' === $code ) {
			return 0;
		}
		$users = get_users(
			array(
				'meta_key'   => self::CODE_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- single-row lookup by unique referral code.
				'meta_value' => $code,           // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'number'     => 1,
				'fields'     => 'ID',
			)
		);
		return $users ? (int) $users[0] : 0;
	}

	/** Capture ?mfc_ref into a 30-day cookie so the referral survives until the friend orders. */
	public static function capture(): void {
		if ( is_admin() ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only attribution from a shared link; only sets a cookie.
		$code = isset( $_GET[ self::COOKIE ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::COOKIE ] ) ) : '';
		if ( '' === $code || headers_sent() ) {
			return;
		}
		setcookie( self::COOKIE, $code, time() + 30 * DAY_IN_SECONDS, defined( 'COOKIEPATH' ) ? COOKIEPATH : '/', defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '' );
	}

	/** At checkout, record the referrer on the order (not the friend themselves). */
	public static function attach_to_order( \WC_Order $order ): void {
		$code = isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : '';
		if ( '' === $code ) {
			return;
		}
		$referrer = self::resolve( $code );
		if ( $referrer > 0 && $referrer !== (int) $order->get_customer_id() ) {
			$order->update_meta_data( self::ORDER_REF, $referrer );
		}
	}

	/** On order completion, reward the referrer (once per friend) and optionally the friend. */
	public static function reward( $order_id ): void {
		$order = OrderOnce::get( $order_id );
		if ( null === $order || OrderOnce::done( $order, self::REWARDED_META ) ) {
			return;
		}
		$referrer = (int) $order->get_meta( self::ORDER_REF );
		$customer = (int) $order->get_customer_id();
		if ( $referrer <= 0 || $referrer === $customer ) {
			return;
		}

		$cfg = self::config();
		if ( '' === $cfg['referrer_template'] ) {
			return;
		}

		// One reward per friend (by email) per referrer — stops repeat orders farming rewards.
		$friend_email = strtolower( (string) $order->get_billing_email() );
		$friends      = (array) get_user_meta( $referrer, self::FRIENDS_META, true );
		if ( '' !== $friend_email && in_array( $friend_email, $friends, true ) ) {
			OrderOnce::mark( $order, self::REWARDED_META, (string) $referrer );
			return;
		}

		$referrer_user = get_user_by( 'id', $referrer );
		$rt            = CouponService::resolve_id( $cfg['referrer_template'] );
		if ( $rt ) {
			PersonalCoupon::issue( $rt, 'REF-', $referrer, $referrer_user ? (string) $referrer_user->user_email : '', $cfg['expiry_days'] );
		}
		if ( '' !== $cfg['friend_template'] ) {
			$ft = CouponService::resolve_id( $cfg['friend_template'] );
			if ( $ft ) {
				PersonalCoupon::issue( $ft, 'WELCOME-', $customer, $friend_email, $cfg['expiry_days'] );
			}
		}

		if ( '' !== $friend_email ) {
			$friends[] = $friend_email;
			update_user_meta( $referrer, self::FRIENDS_META, array_slice( array_values( array_unique( $friends ) ), -500 ) );
		}
		OrderOnce::mark( $order, self::REWARDED_META, (string) $referrer );

		/**
		 * Fires after a referral reward has been issued.
		 *
		 * @param int       $referrer Referrer user id.
		 * @param int       $customer Referred customer user id (0 for guest).
		 * @param \WC_Order $order    The qualifying order.
		 */
		do_action( 'moforcoupon_referral_rewarded', $referrer, $customer, $order );
	}

	/** Show the customer's referral link on the My Account dashboard. */
	public static function dashboard(): void {
		$url = self::url_for( get_current_user_id() );
		if ( '' === $url ) {
			return;
		}
		echo '<div class="moforcoupon-referral"><h3>' . esc_html__( '推薦好友', 'moforcoupon' ) . '</h3>';
		echo '<p>' . esc_html__( '把您的專屬連結分享給朋友;朋友完成首次訂單後,您就能獲得回饋優惠券。', 'moforcoupon' ) . '</p>';
		echo '<input type="text" readonly class="moforcoupon-referral__link" value="' . esc_attr( $url ) . '" onclick="this.select();" style="width:100%;max-width:480px;padding:6px;" />';
		echo '</div>';
	}
}
