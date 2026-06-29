<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\StoreCredit;

defined( 'ABSPATH' ) || exit;

/**
 * A per-customer store-credit balance with a small audit log, stored in user meta. This is what
 * turns the cashback coupon type from "fires a hook" into a usable reward: cashback credits the
 * wallet, and the wallet is auto-applied as a discount at checkout (see Checkout).
 */
final class Wallet {

	private const BALANCE_META = '_moforcoupon_store_credit';
	private const LOG_META     = '_moforcoupon_store_credit_log';
	private const LOG_MAX      = 50;

	/** How much credit can be applied: min of the (non-negative) balance and the eligible total. Pure. */
	public static function apply_amount( float $balance, float $eligible_total ): float {
		$balance        = max( 0.0, $balance );
		$eligible_total = max( 0.0, $eligible_total );
		return round( min( $balance, $eligible_total ), 2 );
	}

	public static function balance( int $user_id ): float {
		if ( $user_id <= 0 ) {
			return 0.0;
		}
		return max( 0.0, (float) get_user_meta( $user_id, self::BALANCE_META, true ) );
	}

	/** Add credit and record a log entry. */
	public static function credit( int $user_id, float $amount, string $note ): void {
		if ( $user_id <= 0 || $amount <= 0.0 ) {
			return;
		}
		$new = round( self::balance( $user_id ) + $amount, 2 );
		update_user_meta( $user_id, self::BALANCE_META, $new );
		self::record( $user_id, round( $amount, 2 ), $new, $note );
	}

	/** Spend up to the available balance; returns the amount actually debited. */
	public static function debit( int $user_id, float $amount, string $note ): float {
		$taken = self::apply_amount( self::balance( $user_id ), $amount );
		if ( $user_id <= 0 || $taken <= 0.0 ) {
			return 0.0;
		}
		$new = round( self::balance( $user_id ) - $taken, 2 );
		update_user_meta( $user_id, self::BALANCE_META, max( 0.0, $new ) );
		self::record( $user_id, -$taken, max( 0.0, $new ), $note );
		return $taken;
	}

	/** @return array<int,array{t:int,delta:float,balance:float,note:string}> newest first. */
	public static function log( int $user_id ): array {
		if ( $user_id <= 0 ) {
			return array();
		}
		$log = get_user_meta( $user_id, self::LOG_META, true );
		return is_array( $log ) ? $log : array();
	}

	private static function record( int $user_id, float $delta, float $balance, string $note ): void {
		$log = self::log( $user_id );
		array_unshift(
			$log,
			array(
				't'       => time(),
				'delta'   => $delta,
				'balance' => $balance,
				'note'    => sanitize_text_field( $note ),
			)
		);
		update_user_meta( $user_id, self::LOG_META, array_slice( $log, 0, self::LOG_MAX ) );
	}
}
