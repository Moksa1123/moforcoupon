<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\Cashback;

use MoksaWeb\Moforcoupon\Coupon\Meta\Keys;
use MoksaWeb\Moforcoupon\Admin\FieldsSaveGuard;

defined( 'ABSPATH' ) || exit;

/**
 * "回饋金 / 點數" coupon edit-screen tab — lets an admin set the cashback reward (a percentage
 * of the order or a fixed amount) when the coupon's discount type is moforcoupon_cashback. The
 * tab is shown / hidden by discount_type via cashback-admin.js (mirrors the BOGO pattern). The
 * same meta is also writable through the moforcoupon settings object (REST / AI).
 */
final class Fields {

	use FieldsSaveGuard;

	private const CAP   = 'manage_woocommerce';
	private const NONCE = 'moforcoupon_cashback_nonce';

	private function action( int $id ): string {
		return 'moforcoupon_save_cashback_coupon_' . $id;
	}

	/**
	 * @return array<int,array{id:string,title:string,class:array<int,string>,render:callable}>
	 */
	public function sections(): array {
		return array(
			array(
				'id'     => 'moforcoupon_cashback',
				'title'  => __( '回饋金 / 點數', 'moforcoupon' ),
				'class'  => array( 'moforcoupon_cashback_tab' ),
				'render' => function (): void {
					$this->render_panel();
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

	private function render_panel(): void {
		global $post;
		if ( ! $post instanceof \WP_Post ) {
			return;
		}
		$id = (int) $post->ID;

		echo '<p class="description" style="margin:8px 0;">'
			. esc_html__( '把「折扣類型」設為「回饋金 / 點數(Cashback)」時,此券不直接折購物車,而是在訂單付款後依下列設定回饋。回饋會透過 moforcoupon_cashback_awarded 事件交給你串接的錢包 / 點數系統處理。', 'moforcoupon' )
			. '</p>';

		woocommerce_wp_select(
			array(
				'id'      => Keys::CASHBACK_MODE,
				'value'   => 'fixed' === get_post_meta( $id, Keys::CASHBACK_MODE, true ) ? 'fixed' : 'percent',
				'label'   => __( '回饋方式', 'moforcoupon' ),
				'options' => array(
					'percent' => __( '訂單金額百分比', 'moforcoupon' ),
					'fixed'   => __( '固定金額', 'moforcoupon' ),
				),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'                => Keys::CASHBACK_VALUE,
				'value'             => get_post_meta( $id, Keys::CASHBACK_VALUE, true ),
				'label'             => __( '回饋值', 'moforcoupon' ),
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '0.01',
				),
				'description'       => __( '百分比填 5 = 回饋訂單金額的 5%;固定金額填 100 = 回饋 100(商店幣別)。', 'moforcoupon' ),
				'desc_tip'          => true,
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
		$mode = isset( $_POST[ Keys::CASHBACK_MODE ] ) ? sanitize_key( wp_unslash( $_POST[ Keys::CASHBACK_MODE ] ) ) : 'percent';
		update_post_meta( $post_id, Keys::CASHBACK_MODE, 'fixed' === $mode ? 'fixed' : 'percent' );

		$value = isset( $_POST[ Keys::CASHBACK_VALUE ] )
			? (float) wc_format_decimal( sanitize_text_field( wp_unslash( $_POST[ Keys::CASHBACK_VALUE ] ) ) )
			: 0.0;
		if ( $value > 0 ) {
			update_post_meta( $post_id, Keys::CASHBACK_VALUE, $value );
		} else {
			delete_post_meta( $post_id, Keys::CASHBACK_VALUE );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}
}
