<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\FreeGift;

use MoksaWeb\Moforcoupon\Coupon\Meta\Keys;
use MoksaWeb\Moforcoupon\Admin\FieldsSaveGuard;

defined( 'ABSPATH' ) || exit;

/**
 * "贈品" coupon edit-screen tab: pick one product to auto-add when the coupon is
 * applied, its quantity, and its price (free / percent / fixed). Dedicated nonce.
 */
final class Fields {

	use FieldsSaveGuard;

	private const CAP   = 'manage_woocommerce';
	private const NONCE = 'moforcoupon_gift_nonce';

	private function action( int $id ): string {
		return 'moforcoupon_save_gift_coupon_' . $id;
	}

	/**
	 * @return array<int,array{id:string,title:string,render:callable}>
	 */
	public function sections(): array {
		return array(
			array(
				'id'     => 'moforcoupon_gift',
				'title'  => __( '贈品', 'moforcoupon' ),
				'render' => function (): void {
					$this->render_gift_panel();
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

	private function render_gift_panel(): void {
		global $post;
		if ( ! $post instanceof \WP_Post ) {
			return;
		}
		$cfg = GiftConfig::read( (int) $post->ID );

		woocommerce_wp_checkbox(
			array(
				'id'          => Keys::GIFT_ENABLED,
				'value'       => $cfg['enabled'] ? 'yes' : '',
				'label'       => __( '啟用贈品', 'moforcoupon' ),
				'description' => __( '套用此優惠券時,自動把下方贈品加入購物車(顧客無法改數量或移除;移除優惠券時自動撤回)。', 'moforcoupon' ),
			)
		);
		$this->product_field( Keys::GIFT_PRODUCT_ID, __( '贈品商品', 'moforcoupon' ), $cfg['product_id'] );
		woocommerce_wp_text_input(
			array(
				'id'                => Keys::GIFT_QTY,
				'value'             => $cfg['qty'],
				'label'             => __( '數量', 'moforcoupon' ),
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '1',
					'step' => '1',
				),
			)
		);
		woocommerce_wp_select(
			array(
				'id'      => Keys::GIFT_MODE,
				'value'   => $cfg['mode'],
				'label'   => __( '贈品定價', 'moforcoupon' ),
				'options' => array(
					'free'    => __( '免費', 'moforcoupon' ),
					'percent' => __( '百分比折扣', 'moforcoupon' ),
					'fixed'   => __( '每件固定折扣', 'moforcoupon' ),
				),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'                => Keys::GIFT_VALUE,
				'value'             => $cfg['value'],
				'label'             => __( '折扣值', 'moforcoupon' ),
				'desc_tip'          => true,
				'description'       => __( '百分比:0–100;每件固定:折抵金額。選「免費」時免填。', 'moforcoupon' ),
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '0.01',
				),
			)
		);
	}

	private function product_field( string $id, string $label, int $selected ): void {
		echo '<p class="form-field"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
		echo '<select class="wc-product-search" style="width:50%;" id="' . esc_attr( $id ) . '" name="' . esc_attr( $id ) . '" data-placeholder="' . esc_attr__( '搜尋商品…', 'moforcoupon' ) . '" data-action="woocommerce_json_search_products_and_variations" data-allow_clear="true">';
		if ( $selected > 0 ) {
			$product = function_exists( 'wc_get_product' ) ? wc_get_product( $selected ) : null;
			if ( $product ) {
				echo '<option value="' . esc_attr( (string) $selected ) . '" selected="selected">' . esc_html( wp_strip_all_tags( $product->get_formatted_name() ) ) . '</option>';
			}
		}
		echo '</select></p>';
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
		update_post_meta( $post_id, Keys::GIFT_ENABLED, isset( $_POST[ Keys::GIFT_ENABLED ] ) ? 'yes' : '' );

		$pid = isset( $_POST[ Keys::GIFT_PRODUCT_ID ] ) ? absint( wp_unslash( $_POST[ Keys::GIFT_PRODUCT_ID ] ) ) : 0;
		if ( $pid > 0 ) {
			update_post_meta( $post_id, Keys::GIFT_PRODUCT_ID, $pid );
		} else {
			delete_post_meta( $post_id, Keys::GIFT_PRODUCT_ID );
		}

		update_post_meta( $post_id, Keys::GIFT_QTY, isset( $_POST[ Keys::GIFT_QTY ] ) ? max( 1, absint( wp_unslash( $_POST[ Keys::GIFT_QTY ] ) ) ) : 1 );

		$mode = isset( $_POST[ Keys::GIFT_MODE ] ) ? sanitize_key( wp_unslash( $_POST[ Keys::GIFT_MODE ] ) ) : 'free';
		update_post_meta( $post_id, Keys::GIFT_MODE, GiftConfig::mode( $mode ) );

		$value = isset( $_POST[ Keys::GIFT_VALUE ] ) ? (float) wc_format_decimal( sanitize_text_field( wp_unslash( $_POST[ Keys::GIFT_VALUE ] ) ) ) : 0.0;
		update_post_meta( $post_id, Keys::GIFT_VALUE, (string) max( 0.0, $value ) );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}
}
