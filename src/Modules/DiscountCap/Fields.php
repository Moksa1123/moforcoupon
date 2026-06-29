<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\DiscountCap;

use MoksaWeb\Moforcoupon\Coupon\Meta\Keys;
use MoksaWeb\Moforcoupon\Admin\FieldsSaveGuard;

defined( 'ABSPATH' ) || exit;

/**
 * One "discount cap" field in the native General coupon options, shown only for
 * percent coupons (WC's own coupon JS toggles .show_if_percent on discount-type
 * change). Uses its own dedicated nonce so it never piggybacks the core _wpnonce.
 */
final class Fields {

	use FieldsSaveGuard;

	private const CAP   = 'manage_woocommerce';
	private const NONCE = 'moforcoupon_discountcap_nonce';

	private function action( int $id ): string {
		return 'moforcoupon_save_discountcap_coupon_' . $id;
	}

	/**
	 * @param mixed $coupon_id
	 * @param mixed $coupon
	 */
	public function render( $coupon_id = 0, $coupon = null ): void {
		$coupon_id = (int) $coupon_id;
		if ( ! $coupon_id ) {
			global $post;
			$coupon_id = $post instanceof \WP_Post ? (int) $post->ID : 0;
		}
		if ( ! $coupon_id ) {
			return;
		}
		wp_nonce_field( $this->action( $coupon_id ), self::NONCE, false );
		woocommerce_wp_text_input(
			array(
				'id'            => Keys::DISCOUNT_CAP,
				'value'         => get_post_meta( $coupon_id, Keys::DISCOUNT_CAP, true ),
				'label'         => __( '折扣上限金額', 'moforcoupon' ),
				'data_type'     => 'price',
				'desc_tip'      => true,
				'description'   => __( '百分比折扣的最高折抵金額(例:打 8 折,但最多折 500)。留空 = 不限。', 'moforcoupon' ),
				'wrapper_class' => 'show_if_percent',
			)
		);
	}

	/**
	 * @param int        $post_id
	 * @param \WC_Coupon $coupon
	 */
	public function save( $post_id, $coupon ): void {
		$post_id = (int) $post_id;
		if ( ! $this->verify_save( $post_id, self::NONCE ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above.
		$raw = isset( $_POST[ Keys::DISCOUNT_CAP ] ) ? sanitize_text_field( wp_unslash( $_POST[ Keys::DISCOUNT_CAP ] ) ) : '';
		if ( '' === trim( $raw ) ) {
			delete_post_meta( $post_id, Keys::DISCOUNT_CAP );
		} else {
			update_post_meta( $post_id, Keys::DISCOUNT_CAP, wc_format_decimal( $raw ) );
		}
	}
}
