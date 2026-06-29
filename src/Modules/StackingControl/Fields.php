<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\StackingControl;

use MoksaWeb\Moforcoupon\Coupon\Meta\Keys;
use MoksaWeb\Moforcoupon\Admin\FieldsSaveGuard;

defined( 'ABSPATH' ) || exit;

/**
 * "疊加控制" coupon edit-screen tab: exclude other coupons, plus allow / disallow
 * lists of coupon codes. Dedicated nonce. Codes are stored normalized (lowercased,
 * comma-separated) via StackConfig::parse_codes.
 */
final class Fields {

	use FieldsSaveGuard;

	private const CAP   = 'manage_woocommerce';
	private const NONCE = 'moforcoupon_stacking_nonce';

	private function action( int $id ): string {
		return 'moforcoupon_save_stacking_coupon_' . $id;
	}

	/**
	 * @return array<int,array{id:string,title:string,render:callable}>
	 */
	public function sections(): array {
		return array(
			array(
				'id'     => 'moforcoupon_stacking',
				'title'  => __( '疊加控制', 'moforcoupon' ),
				'render' => function (): void {
					$this->render_stacking_panel();
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

	private function render_stacking_panel(): void {
		global $post;
		if ( ! $post instanceof \WP_Post ) {
			return;
		}
		$id = (int) $post->ID;

		woocommerce_wp_checkbox(
			array(
				'id'          => Keys::STACK_EXCLUDE,
				'value'       => get_post_meta( $id, Keys::STACK_EXCLUDE, true ),
				'label'       => __( '不可與其他優惠券並用', 'moforcoupon' ),
				'description' => __( '勾選後,購物車已有其他優惠券時無法再套用此券(反向亦然);下方「允許並用」清單為例外。', 'moforcoupon' ),
			)
		);
		woocommerce_wp_textarea_input(
			array(
				'id'          => Keys::STACK_ALLOWED,
				'value'       => get_post_meta( $id, Keys::STACK_ALLOWED, true ),
				'label'       => __( '允許並用的券碼', 'moforcoupon' ),
				'desc_tip'    => true,
				'description' => __( '僅在勾選上方「不可並用」時生效:清單內的券碼仍可與此券一起使用。以逗號或換行分隔。', 'moforcoupon' ),
			)
		);
		woocommerce_wp_textarea_input(
			array(
				'id'          => Keys::STACK_DISALLOWED,
				'value'       => get_post_meta( $id, Keys::STACK_DISALLOWED, true ),
				'label'       => __( '禁止並用的券碼', 'moforcoupon' ),
				'desc_tip'    => true,
				'description' => __( '這些券碼不可與此券一起使用(不論是否勾選「不可並用」)。以逗號或換行分隔。', 'moforcoupon' ),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'    => Keys::STACK_MSG,
				'value' => get_post_meta( $id, Keys::STACK_MSG, true ),
				'label' => __( '衝突時顯示的訊息', 'moforcoupon' ),
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
		update_post_meta( $post_id, Keys::STACK_EXCLUDE, isset( $_POST[ Keys::STACK_EXCLUDE ] ) ? 'yes' : '' );
		self::save_codes( $post_id, Keys::STACK_ALLOWED );
		self::save_codes( $post_id, Keys::STACK_DISALLOWED );

		$msg = isset( $_POST[ Keys::STACK_MSG ] ) ? sanitize_text_field( wp_unslash( $_POST[ Keys::STACK_MSG ] ) ) : '';
		if ( '' === $msg ) {
			delete_post_meta( $post_id, Keys::STACK_MSG );
		} else {
			update_post_meta( $post_id, Keys::STACK_MSG, $msg );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	private static function save_codes( int $post_id, string $key ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in save().
		$raw   = isset( $_POST[ $key ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) ) : '';
		$codes = StackConfig::parse_codes( $raw );
		if ( array() === $codes ) {
			delete_post_meta( $post_id, $key );
		} else {
			update_post_meta( $post_id, $key, implode( ', ', $codes ) );
		}
	}
}
