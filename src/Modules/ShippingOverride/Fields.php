<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\ShippingOverride;

use MoksaWeb\Moforcoupon\Coupon\Meta\Keys;
use MoksaWeb\Moforcoupon\Admin\FieldsSaveGuard;

defined( 'ABSPATH' ) || exit;

/**
 * "運費覆寫" coupon edit-screen tab: when applied, rewrite shipping rates to free /
 * percent off / fixed off. Dedicated nonce. Distinct from WC core's free_shipping flag.
 */
final class Fields {

	use FieldsSaveGuard;

	private const CAP   = 'manage_woocommerce';
	private const NONCE = 'moforcoupon_shipping_nonce';

	private function action( int $id ): string {
		return 'moforcoupon_save_shipping_coupon_' . $id;
	}

	/**
	 * Coupon-settings sections for the CouponSections coordinator (rendered as a
	 * coupon-data tab by default, or as a standalone metabox when enabled).
	 *
	 * @return array<int,array{id:string,title:string,render:callable}>
	 */
	public function sections(): array {
		return array(
			array(
				'id'     => 'moforcoupon_shipping',
				'title'  => __( '運費覆寫', 'moforcoupon' ),
				'render' => function (): void {
					$this->render_shipping_panel();
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

	private function render_shipping_panel(): void {
		global $post;
		if ( ! $post instanceof \WP_Post ) {
			return;
		}
		$cfg = ShipConfig::read( new \WC_Coupon( (int) $post->ID ) );

		echo '<p class="description" style="margin:8px 12px;">' . esc_html__( '套用此優惠券時改寫所有運送方式的運費。與 WooCommerce 原生「提供免費運送」不同(那只啟用專屬的免運方式)。', 'moforcoupon' ) . '</p>';
		woocommerce_wp_select(
			array(
				'id'      => Keys::SHIP_MODE,
				'value'   => $cfg['mode'],
				'label'   => __( '運費覆寫方式', 'moforcoupon' ),
				'options' => array(
					'none'    => __( '不覆寫', 'moforcoupon' ),
					'free'    => __( '免運費', 'moforcoupon' ),
					'percent' => __( '運費百分比折扣', 'moforcoupon' ),
					'fixed'   => __( '運費固定折扣', 'moforcoupon' ),
				),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'                => Keys::SHIP_VALUE,
				'value'             => $cfg['value'],
				'label'             => __( '折扣值', 'moforcoupon' ),
				'desc_tip'          => true,
				'description'       => __( '百分比:0–100;固定:折抵金額。選「免運費」或「不覆寫」時免填。', 'moforcoupon' ),
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '0.01',
				),
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
		$mode = isset( $_POST[ Keys::SHIP_MODE ] ) ? sanitize_key( wp_unslash( $_POST[ Keys::SHIP_MODE ] ) ) : 'none';
		update_post_meta( $post_id, Keys::SHIP_MODE, ShipConfig::mode( $mode ) );

		$value = isset( $_POST[ Keys::SHIP_VALUE ] ) ? (float) wc_format_decimal( sanitize_text_field( wp_unslash( $_POST[ Keys::SHIP_VALUE ] ) ) ) : 0.0;
		update_post_meta( $post_id, Keys::SHIP_VALUE, (string) max( 0.0, $value ) );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}
}
