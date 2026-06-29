<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\Bogo;

use MoksaWeb\Moforcoupon\Coupon\Meta\Keys;
use MoksaWeb\Moforcoupon\Admin\FieldsSaveGuard;
use MoksaWeb\Moforcoupon\Admin\FieldsHelpers;

defined( 'ABSPATH' ) || exit;

/**
 * "買 X 送 Y(BOGO)" coupon edit-screen panel. Relevant only when the coupon's
 * discount type is moforcoupon_bogo (bogo-admin.js shows/hides the tab on
 * #discount_type change). Uses its OWN nonce (moforcoupon_bogo_nonce) so it coexists
 * with the conditions and url panels without duplicate DOM ids or a dropped save.
 * All persistence goes through BogoMeta so the admin and AI write paths cannot drift.
 */
final class Fields {

	use FieldsSaveGuard;

	private const CAP   = 'manage_woocommerce';
	private const NONCE = 'moforcoupon_bogo_nonce';

	private function action( int $id ): string {
		return 'moforcoupon_save_bogo_coupon_' . $id;
	}

	/**
	 * The BOGO panel only applies to the moforcoupon_bogo discount type — bogo-admin.js
	 * shows/hides it on #discount_type change (the tab in tab mode, the postbox in
	 * metabox mode). The moforcoupon_bogo_tab class is the JS hook for tab mode.
	 *
	 * @return array<int,array{id:string,title:string,class:array<int,string>,render:callable}>
	 */
	public function sections(): array {
		return array(
			array(
				'id'     => 'moforcoupon_bogo',
				'title'  => __( '買 X 送 Y', 'moforcoupon' ),
				'class'  => array( 'moforcoupon_bogo_tab' ),
				'render' => function (): void {
					$this->render_bogo_panel();
				},
			),
		);
	}

	public function render_nonce(): void {
		global $post;
		if ( ! $post instanceof \WP_Post ) {
			return;
		}
		wp_nonce_field( $this->action( (int) $post->ID ), self::NONCE, false );
	}

	private function render_bogo_panel(): void {
		global $post;
		if ( ! $post instanceof \WP_Post ) {
			return;
		}
		$cfg = BogoMeta::read( (int) $post->ID );

		echo '<div class="options_group">';
		echo '<p class="form-field"><strong>' . esc_html__( '購買條件(買 X)', 'moforcoupon' ) . '</strong></p>';
		FieldsHelpers::product_select( Keys::BOGO_TRIGGER_PRODUCT_IDS, __( '指定商品', 'moforcoupon' ), $cfg['trigger_product_ids'] );
		FieldsHelpers::category_select( Keys::BOGO_TRIGGER_CATEGORY_IDS, __( '指定商品分類', 'moforcoupon' ), $cfg['trigger_category_ids'] );
		woocommerce_wp_text_input(
			array(
				'id'                => Keys::BOGO_TRIGGER_QTY,
				'value'             => $cfg['trigger_qty'],
				'label'             => __( '需購買數量', 'moforcoupon' ),
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '1',
					'step' => '1',
				),
			)
		);
		echo '</div>';

		echo '<div class="options_group">';
		echo '<p class="form-field"><strong>' . esc_html__( '贈品 / 折扣(送 Y)', 'moforcoupon' ) . '</strong></p>';
		FieldsHelpers::product_select( Keys::BOGO_REWARD_PRODUCT_IDS, __( '贈品商品', 'moforcoupon' ), $cfg['reward_product_ids'] );
		FieldsHelpers::category_select( Keys::BOGO_REWARD_CATEGORY_IDS, __( '贈品商品分類', 'moforcoupon' ), $cfg['reward_category_ids'] );
		woocommerce_wp_text_input(
			array(
				'id'                => Keys::BOGO_REWARD_QTY,
				'value'             => $cfg['reward_qty'],
				'label'             => __( '贈送 / 折扣數量', 'moforcoupon' ),
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '1',
					'step' => '1',
				),
			)
		);
		woocommerce_wp_select(
			array(
				'id'      => Keys::BOGO_REWARD_MODE,
				'value'   => $cfg['reward_mode'],
				'label'   => __( '折扣方式', 'moforcoupon' ),
				'options' => array(
					'free'           => __( '免費(100% 折扣)', 'moforcoupon' ),
					'percent'        => __( '百分比折扣', 'moforcoupon' ),
					'fixed_per_item' => __( '每件固定折扣', 'moforcoupon' ),
				),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'                => Keys::BOGO_REWARD_VALUE,
				'value'             => $cfg['reward_value'],
				'label'             => __( '折扣值', 'moforcoupon' ),
				'desc_tip'          => true,
				'description'       => __( '百分比:0–100;每件固定:金額。選「免費」時免填。', 'moforcoupon' ),
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
				'id'      => Keys::BOGO_DEAL_MODE,
				'value'   => $cfg['deal_mode'],
				'label'   => __( '套用次數', 'moforcoupon' ),
				'options' => array(
					'once'   => __( '只套用一次', 'moforcoupon' ),
					'repeat' => __( '可重複(購物車滿足幾組就送幾組)', 'moforcoupon' ),
				),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'                => Keys::BOGO_REPEAT_LIMIT,
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
				'id'          => Keys::BOGO_NOTICE_MSG,
				'value'       => $cfg['notice_msg'],
				'label'       => __( '提示訊息(贈品未加入時)', 'moforcoupon' ),
				'desc_tip'    => true,
				'description' => __( '可用 {bogo_qty} {coupon_code}。留空用預設訊息。', 'moforcoupon' ),
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

		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified above; every value is sanitized in BogoMeta::sanitize (absint id-lists, int/float scalars, sanitize_text_field text).
		$raw = array(
			'trigger_product_ids'  => isset( $_POST[ Keys::BOGO_TRIGGER_PRODUCT_IDS ] ) ? wp_unslash( $_POST[ Keys::BOGO_TRIGGER_PRODUCT_IDS ] ) : array(),
			'trigger_category_ids' => isset( $_POST[ Keys::BOGO_TRIGGER_CATEGORY_IDS ] ) ? wp_unslash( $_POST[ Keys::BOGO_TRIGGER_CATEGORY_IDS ] ) : array(),
			'trigger_qty'          => isset( $_POST[ Keys::BOGO_TRIGGER_QTY ] ) ? wp_unslash( $_POST[ Keys::BOGO_TRIGGER_QTY ] ) : 1,
			'reward_product_ids'   => isset( $_POST[ Keys::BOGO_REWARD_PRODUCT_IDS ] ) ? wp_unslash( $_POST[ Keys::BOGO_REWARD_PRODUCT_IDS ] ) : array(),
			'reward_category_ids'  => isset( $_POST[ Keys::BOGO_REWARD_CATEGORY_IDS ] ) ? wp_unslash( $_POST[ Keys::BOGO_REWARD_CATEGORY_IDS ] ) : array(),
			'reward_qty'           => isset( $_POST[ Keys::BOGO_REWARD_QTY ] ) ? wp_unslash( $_POST[ Keys::BOGO_REWARD_QTY ] ) : 1,
			'reward_mode'          => isset( $_POST[ Keys::BOGO_REWARD_MODE ] ) ? sanitize_key( wp_unslash( $_POST[ Keys::BOGO_REWARD_MODE ] ) ) : 'percent',
			'reward_value'         => isset( $_POST[ Keys::BOGO_REWARD_VALUE ] ) ? wp_unslash( $_POST[ Keys::BOGO_REWARD_VALUE ] ) : 0,
			'deal_mode'            => isset( $_POST[ Keys::BOGO_DEAL_MODE ] ) ? sanitize_key( wp_unslash( $_POST[ Keys::BOGO_DEAL_MODE ] ) ) : 'once',
			'repeat_limit'         => isset( $_POST[ Keys::BOGO_REPEAT_LIMIT ] ) ? wp_unslash( $_POST[ Keys::BOGO_REPEAT_LIMIT ] ) : 0,
			'notice_msg'           => isset( $_POST[ Keys::BOGO_NOTICE_MSG ] ) ? wp_unslash( $_POST[ Keys::BOGO_NOTICE_MSG ] ) : '',
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		BogoMeta::write( $post_id, BogoMeta::sanitize( $raw ) );
	}
}
