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

		echo '<p class="form-field"><strong>' . esc_html__( '急迫感(限時 / 限量)', 'moforcoupon' ) . '</strong></p>';
		woocommerce_wp_checkbox(
			array(
				'id'          => Keys::COUNTDOWN_ENABLED,
				'value'       => get_post_meta( $id, Keys::COUNTDOWN_ENABLED, true ),
				'label'       => __( '顯示倒數計時', 'moforcoupon' ),
				'description' => __( '在卡片上顯示倒數到期計時器(需設定到期日或排程結束)。', 'moforcoupon' ),
			)
		);
		woocommerce_wp_select(
			array(
				'id'      => Keys::COUNTDOWN_SOURCE,
				'value'   => get_post_meta( $id, Keys::COUNTDOWN_SOURCE, true ),
				'label'   => __( '倒數依據', 'moforcoupon' ),
				'options' => array(
					'expires'  => __( '優惠券到期日', 'moforcoupon' ),
					'schedule' => __( '排程結束時間', 'moforcoupon' ),
				),
			)
		);
		woocommerce_wp_checkbox(
			array(
				'id'          => Keys::STOCK_SHOW,
				'value'       => get_post_meta( $id, Keys::STOCK_SHOW, true ),
				'label'       => __( '顯示剩餘張數', 'moforcoupon' ),
				'description' => __( '依「使用次數上限」顯示「僅剩 N 張」(需先設定使用次數上限)。', 'moforcoupon' ),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'                => Keys::STOCK_THRESHOLD,
				'value'             => get_post_meta( $id, Keys::STOCK_THRESHOLD, true ),
				'label'             => __( '剩餘張數提示門檻', 'moforcoupon' ),
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '1',
				),
				'desc_tip'          => true,
				'description'       => __( '剩餘張數低於或等於此數時才顯示(0 或留空 = 一律顯示)。', 'moforcoupon' ),
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

		update_post_meta( $post_id, Keys::COUNTDOWN_ENABLED, isset( $_POST[ Keys::COUNTDOWN_ENABLED ] ) ? 'yes' : '' );
		update_post_meta( $post_id, Keys::STOCK_SHOW, isset( $_POST[ Keys::STOCK_SHOW ] ) ? 'yes' : '' );

		$source = isset( $_POST[ Keys::COUNTDOWN_SOURCE ] ) ? sanitize_key( wp_unslash( $_POST[ Keys::COUNTDOWN_SOURCE ] ) ) : '';
		if ( in_array( $source, array( 'expires', 'schedule' ), true ) ) {
			update_post_meta( $post_id, Keys::COUNTDOWN_SOURCE, $source );
		} else {
			delete_post_meta( $post_id, Keys::COUNTDOWN_SOURCE );
		}

		$threshold = isset( $_POST[ Keys::STOCK_THRESHOLD ] ) ? absint( wp_unslash( $_POST[ Keys::STOCK_THRESHOLD ] ) ) : 0;
		if ( $threshold > 0 ) {
			update_post_meta( $post_id, Keys::STOCK_THRESHOLD, $threshold );
		} else {
			delete_post_meta( $post_id, Keys::STOCK_THRESHOLD );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}
}
