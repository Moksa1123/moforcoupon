<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\MixMatch;

use MoksaWeb\Moforcoupon\Coupon\Meta\Keys;
use MoksaWeb\Moforcoupon\Admin\FieldsSaveGuard;
use MoksaWeb\Moforcoupon\Admin\FieldsHelpers;

defined( 'ABSPATH' ) || exit;

/**
 * "任選優惠(Mix & Match)" coupon edit-screen panel. Relevant only when the coupon's discount type
 * is moforcoupon_mixmatch (mixmatch-admin.js shows/hides the tab on #discount_type change). Uses its
 * OWN nonce. All persistence goes through MixMatchMeta.
 */
final class Fields {

	use FieldsSaveGuard;

	private const CAP   = 'manage_woocommerce';
	private const NONCE = 'moforcoupon_mixmatch_nonce';

	private function action( int $id ): string {
		return 'moforcoupon_save_mixmatch_coupon_' . $id;
	}

	/**
	 * @return array<int,array{id:string,title:string,class:array<int,string>,render:callable}>
	 */
	public function sections(): array {
		return array(
			array(
				'id'     => 'moforcoupon_mixmatch',
				'title'  => __( '任選優惠', 'moforcoupon' ),
				'class'  => array( 'moforcoupon_mixmatch_tab' ),
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
		$cfg = MixMatchMeta::read( (int) $post->ID );

		echo '<div class="options_group">';
		echo '<p class="form-field"><strong>' . esc_html__( '可任選的商品(留空 = 全站)', 'moforcoupon' ) . '</strong></p>';
		FieldsHelpers::product_select( Keys::MIXMATCH_PRODUCT_IDS, __( '指定商品', 'moforcoupon' ), $cfg['product_ids'] );
		FieldsHelpers::category_select( Keys::MIXMATCH_CATEGORY_IDS, __( '指定商品分類', 'moforcoupon' ), $cfg['category_ids'] );
		echo '</div>';

		echo '<div class="options_group">';
		woocommerce_wp_text_input(
			array(
				'id'                => Keys::MIXMATCH_QTY,
				'value'             => $cfg['qty'],
				'label'             => __( '任選件數(N)', 'moforcoupon' ),
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '1',
					'step' => '1',
				),
				'desc_tip'          => true,
				'description'       => __( '任選 3 件 → 填 3。', 'moforcoupon' ),
			)
		);
		woocommerce_wp_select(
			array(
				'id'      => Keys::MIXMATCH_PRICE_MODE,
				'value'   => $cfg['price_mode'],
				'label'   => __( '定價方式', 'moforcoupon' ),
				'options' => array(
					'fixed_total' => __( '整組固定總價', 'moforcoupon' ),
					'percent'     => __( '整組百分比折扣', 'moforcoupon' ),
				),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'                => Keys::MIXMATCH_PRICE_VALUE,
				'value'             => $cfg['price_value'],
				'label'             => __( '價格 / 折扣值', 'moforcoupon' ),
				'desc_tip'          => true,
				'description'       => __( '固定總價:整組 N 件的總價(任選3件$299 → 填 299)。百分比:0–100(任選5件75折 → 填 25)。', 'moforcoupon' ),
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '0.01',
				),
			)
		);
		echo '</div>';

		echo '<div class="options_group">';
		woocommerce_wp_select(
			array(
				'id'      => Keys::MIXMATCH_DEAL_MODE,
				'value'   => $cfg['deal_mode'],
				'label'   => __( '套用次數', 'moforcoupon' ),
				'options' => array(
					'repeat' => __( '可重複(每滿 N 件再套一次)', 'moforcoupon' ),
					'once'   => __( '只套用一次', 'moforcoupon' ),
				),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'                => Keys::MIXMATCH_REPEAT_LIMIT,
				'value'             => $cfg['repeat_limit'],
				'label'             => __( '重複上限', 'moforcoupon' ),
				'desc_tip'          => true,
				'description'       => __( '0 = 不限。僅在「可重複」時有效。', 'moforcoupon' ),
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '1',
				),
			)
		);
		woocommerce_wp_textarea_input(
			array(
				'id'          => Keys::MIXMATCH_NOTICE_MSG,
				'value'       => $cfg['notice_msg'],
				'label'       => __( '湊件提示訊息', 'moforcoupon' ),
				'desc_tip'    => true,
				'description' => __( '可用 {mixmatch_qty} {coupon_code}。留空用預設訊息。', 'moforcoupon' ),
			)
		);
		echo '</div>';
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

		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified above; every value is sanitized in MixMatchMeta::sanitize (absint id-lists, int/float scalars, sanitize_textarea_field text).
		$raw = array(
			'product_ids'  => isset( $_POST[ Keys::MIXMATCH_PRODUCT_IDS ] ) ? wp_unslash( $_POST[ Keys::MIXMATCH_PRODUCT_IDS ] ) : array(),
			'category_ids' => isset( $_POST[ Keys::MIXMATCH_CATEGORY_IDS ] ) ? wp_unslash( $_POST[ Keys::MIXMATCH_CATEGORY_IDS ] ) : array(),
			'qty'          => isset( $_POST[ Keys::MIXMATCH_QTY ] ) ? wp_unslash( $_POST[ Keys::MIXMATCH_QTY ] ) : 1,
			'price_mode'   => isset( $_POST[ Keys::MIXMATCH_PRICE_MODE ] ) ? sanitize_key( wp_unslash( $_POST[ Keys::MIXMATCH_PRICE_MODE ] ) ) : 'fixed_total',
			'price_value'  => isset( $_POST[ Keys::MIXMATCH_PRICE_VALUE ] ) ? wp_unslash( $_POST[ Keys::MIXMATCH_PRICE_VALUE ] ) : 0,
			'deal_mode'    => isset( $_POST[ Keys::MIXMATCH_DEAL_MODE ] ) ? sanitize_key( wp_unslash( $_POST[ Keys::MIXMATCH_DEAL_MODE ] ) ) : 'repeat',
			'repeat_limit' => isset( $_POST[ Keys::MIXMATCH_REPEAT_LIMIT ] ) ? wp_unslash( $_POST[ Keys::MIXMATCH_REPEAT_LIMIT ] ) : 0,
			'notice_msg'   => isset( $_POST[ Keys::MIXMATCH_NOTICE_MSG ] ) ? wp_unslash( $_POST[ Keys::MIXMATCH_NOTICE_MSG ] ) : '',
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		MixMatchMeta::write( $post_id, MixMatchMeta::sanitize( $raw ) );
	}
}
