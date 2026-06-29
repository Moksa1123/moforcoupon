<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\NthItem;

use MoksaWeb\Moforcoupon\Coupon\Meta\Keys;
use MoksaWeb\Moforcoupon\Admin\FieldsSaveGuard;
use MoksaWeb\Moforcoupon\Admin\FieldsHelpers;

defined( 'ABSPATH' ) || exit;

/**
 * "第 N 件折扣" coupon edit-screen panel. Relevant only when the coupon's discount type is
 * moforcoupon_nth_item (nthitem-admin.js shows/hides the tab on #discount_type change). Uses its
 * OWN nonce so it coexists with the other panels. All persistence goes through NthItemMeta.
 */
final class Fields {

	use FieldsSaveGuard;

	private const CAP   = 'manage_woocommerce';
	private const NONCE = 'moforcoupon_nthitem_nonce';

	private function action( int $id ): string {
		return 'moforcoupon_save_nthitem_coupon_' . $id;
	}

	/**
	 * @return array<int,array{id:string,title:string,class:array<int,string>,render:callable}>
	 */
	public function sections(): array {
		return array(
			array(
				'id'     => 'moforcoupon_nth_item',
				'title'  => __( '第 N 件折扣', 'moforcoupon' ),
				'class'  => array( 'moforcoupon_nth_item_tab' ),
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
		$cfg = NthItemMeta::read( (int) $post->ID );

		echo '<div class="options_group">';
		echo '<p class="form-field"><strong>' . esc_html__( '適用商品(留空 = 全站)', 'moforcoupon' ) . '</strong></p>';
		FieldsHelpers::product_select( Keys::NTH_PRODUCT_IDS, __( '指定商品', 'moforcoupon' ), $cfg['product_ids'] );
		FieldsHelpers::category_select( Keys::NTH_CATEGORY_IDS, __( '指定商品分類', 'moforcoupon' ), $cfg['category_ids'] );
		woocommerce_wp_select(
			array(
				'id'      => Keys::NTH_GROUP_BY,
				'value'   => $cfg['group_by'],
				'label'   => __( '計算方式', 'moforcoupon' ),
				'options' => array(
					'cart'    => __( '整個購物車合併計算', 'moforcoupon' ),
					'product' => __( '每件商品各自計算', 'moforcoupon' ),
				),
			)
		);
		echo '</div>';

		echo '<div class="options_group">';
		woocommerce_wp_text_input(
			array(
				'id'                => Keys::NTH_N,
				'value'             => $cfg['n'],
				'label'             => __( '每幾件折一件(N)', 'moforcoupon' ),
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '2',
					'step' => '1',
				),
				'desc_tip'          => true,
				'description'       => __( '例:第二件折 → 填 2。', 'moforcoupon' ),
			)
		);
		woocommerce_wp_select(
			array(
				'id'      => Keys::NTH_REWARD_MODE,
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
				'id'                => Keys::NTH_REWARD_VALUE,
				'value'             => $cfg['reward_value'],
				'label'             => __( '折扣值', 'moforcoupon' ),
				'desc_tip'          => true,
				'description'       => __( '百分比為「折扣 %」:第二件六折 → 填 40(折 40%、付 60%)。每件固定:金額。免費免填。', 'moforcoupon' ),
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
				'id'      => Keys::NTH_DEAL_MODE,
				'value'   => $cfg['deal_mode'],
				'label'   => __( '套用次數', 'moforcoupon' ),
				'options' => array(
					'repeat' => __( '可重複(每滿 N 件折一件)', 'moforcoupon' ),
					'once'   => __( '只套用一次', 'moforcoupon' ),
				),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'                => Keys::NTH_REPEAT_LIMIT,
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
		woocommerce_wp_text_input(
			array(
				'id'          => Keys::NTH_NOTICE_MSG,
				'value'       => $cfg['notice_msg'],
				'label'       => __( '湊件提示訊息', 'moforcoupon' ),
				'desc_tip'    => true,
				'description' => __( '可用 {nth_n} {coupon_code}。留空用預設訊息。', 'moforcoupon' ),
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

		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified above; every value is sanitized in NthItemMeta::sanitize (absint id-lists, int/float scalars, sanitize_text_field text).
		$raw = array(
			'product_ids'  => isset( $_POST[ Keys::NTH_PRODUCT_IDS ] ) ? wp_unslash( $_POST[ Keys::NTH_PRODUCT_IDS ] ) : array(),
			'category_ids' => isset( $_POST[ Keys::NTH_CATEGORY_IDS ] ) ? wp_unslash( $_POST[ Keys::NTH_CATEGORY_IDS ] ) : array(),
			'group_by'     => isset( $_POST[ Keys::NTH_GROUP_BY ] ) ? sanitize_key( wp_unslash( $_POST[ Keys::NTH_GROUP_BY ] ) ) : 'cart',
			'n'            => isset( $_POST[ Keys::NTH_N ] ) ? wp_unslash( $_POST[ Keys::NTH_N ] ) : 2,
			'reward_mode'  => isset( $_POST[ Keys::NTH_REWARD_MODE ] ) ? sanitize_key( wp_unslash( $_POST[ Keys::NTH_REWARD_MODE ] ) ) : 'percent',
			'reward_value' => isset( $_POST[ Keys::NTH_REWARD_VALUE ] ) ? wp_unslash( $_POST[ Keys::NTH_REWARD_VALUE ] ) : 0,
			'deal_mode'    => isset( $_POST[ Keys::NTH_DEAL_MODE ] ) ? sanitize_key( wp_unslash( $_POST[ Keys::NTH_DEAL_MODE ] ) ) : 'repeat',
			'repeat_limit' => isset( $_POST[ Keys::NTH_REPEAT_LIMIT ] ) ? wp_unslash( $_POST[ Keys::NTH_REPEAT_LIMIT ] ) : 0,
			'notice_msg'   => isset( $_POST[ Keys::NTH_NOTICE_MSG ] ) ? wp_unslash( $_POST[ Keys::NTH_NOTICE_MSG ] ) : '',
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		NthItemMeta::write( $post_id, NthItemMeta::sanitize( $raw ) );
	}
}
