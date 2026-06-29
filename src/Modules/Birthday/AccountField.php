<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\Birthday;

defined( 'ABSPATH' ) || exit;

/**
 * Adds a birthday field to the WooCommerce "帳戶詳細資料" form so the store has a date to send a
 * birthday coupon on. Only the month-day is kept (privacy + all the birthday coupon needs).
 */
final class AccountField {

	/** User meta: birthday as "MM-DD". */
	public const META = '_moforcoupon_birthday';

	public static function render(): void {
		$month_day = (string) get_user_meta( get_current_user_id(), self::META, true );
		$value     = ( '' !== $month_day && preg_match( '/^\d{2}-\d{2}$/', $month_day ) ) ? '2000-' . $month_day : '';
		echo '<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">';
		echo '<label for="moforcoupon_birthday">' . esc_html__( '生日(用於生日優惠券)', 'moforcoupon' ) . '</label>';
		echo '<input type="date" class="woocommerce-Input woocommerce-Input--text input-text" name="moforcoupon_birthday" id="moforcoupon_birthday" value="' . esc_attr( $value ) . '" />';
		echo '</p>';
	}

	/**
	 * @param int $user_id
	 */
	public static function save( $user_id ): void {
		$user_id = (int) $user_id;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the save-account-details nonce before this hook fires.
		$raw = isset( $_POST['moforcoupon_birthday'] ) ? sanitize_text_field( wp_unslash( $_POST['moforcoupon_birthday'] ) ) : '';
		if ( '' === $raw ) {
			delete_user_meta( $user_id, self::META );
			return;
		}
		if ( preg_match( '/^\d{4}-(\d{2})-(\d{2})$/', $raw, $m ) ) {
			update_user_meta( $user_id, self::META, $m[1] . '-' . $m[2] );
		}
	}
}
