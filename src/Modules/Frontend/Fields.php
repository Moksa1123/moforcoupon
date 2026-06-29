<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\Frontend;

use MoksaWeb\Moforcoupon\Coupon\Meta\Keys;
use MoksaWeb\Moforcoupon\Admin\FieldsSaveGuard;

defined( 'ABSPATH' ) || exit;

/**
 * "前台顯示" coupon edit-screen tab: opt the coupon into the public
 * [moforcoupon_coupons] card list and set its marketing label. Dedicated nonce.
 */
final class Fields {

	use FieldsSaveGuard;

	private const CAP   = 'manage_woocommerce';
	private const NONCE = 'moforcoupon_frontend_nonce';

	private function action( int $id ): string {
		return 'moforcoupon_save_frontend_coupon_' . $id;
	}

	/**
	 * @return array<int,array{id:string,title:string,render:callable}>
	 */
	public function sections(): array {
		return array(
			array(
				'id'     => 'moforcoupon_frontend',
				'title'  => __( '前台顯示', 'moforcoupon' ),
				'render' => function (): void {
					$this->render_frontend_panel();
				},
			),
		);
	}

	public function render_nonce(): void {
		global $post;
		if ( $post instanceof \WP_Post ) {
			wp_nonce_field( $this->action( (int) $post->ID ), self::NONCE, false );
		}
	}

	private function render_frontend_panel(): void {
		global $post;
		if ( ! $post instanceof \WP_Post ) {
			return;
		}
		$id = (int) $post->ID;

		woocommerce_wp_checkbox(
			array(
				'id'          => Keys::SHOW_IN_LIST,
				'value'       => get_post_meta( $id, Keys::SHOW_IN_LIST, true ),
				'label'       => __( '在前台優惠券清單顯示', 'moforcoupon' ),
				'description' => __( '勾選後,此優惠券會出現在 [moforcoupon_coupons] 短代碼的卡片清單中。', 'moforcoupon' ),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'          => Keys::FRONT_LABEL,
				'value'       => get_post_meta( $id, Keys::FRONT_LABEL, true ),
				'label'       => __( '前台說明文字', 'moforcoupon' ),
				'desc_tip'    => true,
				'description' => __( '顯示在卡片上的行銷說明(留空則用優惠券描述)。', 'moforcoupon' ),
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

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified above.
		update_post_meta( $post_id, Keys::SHOW_IN_LIST, isset( $_POST[ Keys::SHOW_IN_LIST ] ) ? 'yes' : '' );
		$label = isset( $_POST[ Keys::FRONT_LABEL ] ) ? sanitize_text_field( wp_unslash( $_POST[ Keys::FRONT_LABEL ] ) ) : '';
		if ( '' === $label ) {
			delete_post_meta( $post_id, Keys::FRONT_LABEL );
		} else {
			update_post_meta( $post_id, Keys::FRONT_LABEL, $label );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}
}
