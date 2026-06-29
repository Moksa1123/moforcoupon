<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\DiscountTiers;

use MoksaWeb\Moforcoupon\Coupon\Meta\Keys;
use MoksaWeb\Moforcoupon\Admin\FieldsSaveGuard;
use MoksaWeb\Moforcoupon\Support\Tiers;
use MoksaWeb\Moforcoupon\Admin\FieldsHelpers;

defined( 'ABSPATH' ) || exit;

/**
 * "階梯折扣" coupon edit-screen tab: one percent coupon, different percent-off per cart tier,
 * optionally limited to chosen products / categories. Dedicated nonce. Rows are added/removed
 * with a small enqueued script (graceful no-JS fallback: the saved rows + a few starter rows
 * still render and submit); blank rows are dropped on save. The engine has no row cap.
 */
final class Fields {

	use FieldsSaveGuard;

	private const CAP   = 'manage_woocommerce';
	private const NONCE = 'moforcoupon_tiers_nonce';

	private function action( int $id ): string {
		return 'moforcoupon_save_tiers_coupon_' . $id;
	}

	/**
	 * @return array<int,array{id:string,title:string,render:callable}>
	 */
	public function sections(): array {
		return array(
			array(
				'id'     => 'moforcoupon_tiers',
				'title'  => __( '階梯折扣', 'moforcoupon' ),
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
		$id    = (int) $post->ID;
		$tiers = Tiers::parse( (string) get_post_meta( $id, Keys::TIERS, true ) );

		echo '<p class="description" style="margin:8px 0;">'
			. esc_html__( '同一張優惠券依「階梯依據」(購物車小計 / 件數 / 重量)分級給不同折扣;每一階可選百分比或固定金額,也可以混用。啟用後完全依下表計算,優惠券原本的折扣值會被忽略。', 'moforcoupon' )
			. '</p>';

		woocommerce_wp_checkbox(
			array(
				'id'          => Keys::TIERS_ENABLED,
				'value'       => get_post_meta( $id, Keys::TIERS_ENABLED, true ),
				'label'       => __( '啟用階梯折扣', 'moforcoupon' ),
				'description' => __( '勾選後此優惠券依下方階梯計算折扣。', 'moforcoupon' ),
			)
		);

		woocommerce_wp_select(
			array(
				'id'      => Keys::TIERS_BASIS,
				'value'   => Tiers::basis( get_post_meta( $id, Keys::TIERS_BASIS, true ) ),
				'label'   => __( '階梯依據', 'moforcoupon' ),
				'options' => array(
					'subtotal' => __( '購物車小計', 'moforcoupon' ),
					'quantity' => __( '購物車件數', 'moforcoupon' ),
					'weight'   => __( '購物車重量(kg)', 'moforcoupon' ),
				),
			)
		);

		echo '<div class="moforcoupon-tiers-builder">';
		echo '<table class="widefat striped moforcoupon-tiers-table" style="margin:8px 0;width:100%;">'
			. '<caption class="screen-reader-text">' . esc_html__( '階梯折扣設定表', 'moforcoupon' ) . '</caption><thead><tr>'
			. '<th scope="col" style="width:42px;">' . esc_html__( '階梯', 'moforcoupon' ) . '</th>'
			. '<th scope="col" class="moforcoupon-tier-th-threshold">' . esc_html__( '門檻 ≥(依上方依據)', 'moforcoupon' ) . '</th>'
			. '<th scope="col" style="width:120px;">' . esc_html__( '折扣方式', 'moforcoupon' ) . '</th>'
			. '<th scope="col">' . esc_html__( '折扣值', 'moforcoupon' ) . '</th>'
			. '<th scope="col" style="width:36px;"><span class="screen-reader-text">' . esc_html__( '動作', 'moforcoupon' ) . '</span></th>'
			. '</tr></thead><tbody class="moforcoupon-tiers-rows">';

		$render_rows = $tiers;
		if ( array() === $render_rows ) {
			$initial = max( 1, (int) apply_filters( 'moforcoupon_tiers_ui_initial_rows', 3 ) );
			for ( $i = 0; $i < $initial; $i++ ) {
				$render_rows[] = array(
					'threshold' => '',
					'kind'      => 'percent',
					'value'     => '',
				);
			}
		}
		$num = 1;
		foreach ( $render_rows as $row ) {
			$this->print_tier_row( $num, $row );
			++$num;
		}
		echo '</tbody></table>';
		echo '<p style="margin:0 0 10px;"><button type="button" class="button moforcoupon-tier-add">'
			. esc_html__( '+ 新增階梯', 'moforcoupon' ) . '</button></p>';
		// Inert template the script clones to add a row (its contents are never submitted).
		echo '<template class="moforcoupon-tier-template">';
		$this->print_tier_row(
			0,
			array(
				'threshold' => '',
				'kind'      => 'percent',
				'value'     => '',
			)
		);
		echo '</template>';
		echo '</div>';
		echo '<p class="description" style="margin:0 0 8px;">'
			. esc_html__( '門檻達到才套用該階;同時符合多階時,取「折抵金額最大」的一階。折扣值留空 = 該階不啟用。百分比填 10 = 折 10%(9 折);固定金額填 200 = 折 200 元。', 'moforcoupon' )
			. '</p>';

		woocommerce_wp_select(
			array(
				'id'      => Keys::TIERS_TARGET_MODE,
				'value'   => (string) get_post_meta( $id, Keys::TIERS_TARGET_MODE, true ),
				'label'   => __( '折扣套用範圍', 'moforcoupon' ),
				'options' => array(
					'all'        => __( '整張購物車', 'moforcoupon' ),
					'products'   => __( '只限指定商品', 'moforcoupon' ),
					'categories' => __( '只限指定分類', 'moforcoupon' ),
				),
			)
		);
		FieldsHelpers::product_select( Keys::TIERS_TARGET_PRODUCTS, __( '指定商品', 'moforcoupon' ), FieldsHelpers::int_list( get_post_meta( $id, Keys::TIERS_TARGET_PRODUCTS, true ) ) );
		FieldsHelpers::category_select( Keys::TIERS_TARGET_CATEGORIES, __( '指定分類', 'moforcoupon' ), FieldsHelpers::int_list( get_post_meta( $id, Keys::TIERS_TARGET_CATEGORIES, true ) ) );
		echo '<p class="description" style="margin:8px 0;">'
			. esc_html__( '門檻一律以整車的「階梯依據」判斷;「折扣套用範圍」只決定折扣算在哪些商品上(固定金額會按比例分攤到範圍內的商品)。會員角色限制請用「條件」分頁。', 'moforcoupon' )
			. '</p>';
	}

	/**
	 * Print one tier row. Index 0 is used for the JS clone template; the script renumbers
	 * on add/remove. Values are escaped; empty / zero thresholds render blank.
	 *
	 * @param int                                       $index Display index (1-based; 0 for the template).
	 * @param array{threshold:mixed,kind:mixed,value:mixed} $row Tier row.
	 */
	private function print_tier_row( int $index, array $row ): void {
		$threshold = ( '' === $row['threshold'] || 0.0 === (float) $row['threshold'] ) ? '' : (string) $row['threshold'];
		$kind      = ( ( $row['kind'] ?? 'percent' ) === 'fixed' ) ? 'fixed' : 'percent';
		$value     = ( '' === $row['value'] ) ? '' : (string) $row['value'];
		printf(
			'<tr class="moforcoupon-tier-row"><td class="moforcoupon-tier-idx">%1$d</td>'
			. '<td><input type="number" min="0" step="0.01" name="moforcoupon_tier_threshold[]" value="%2$s" aria-label="%10$s" /></td>'
			. '<td><select name="moforcoupon_tier_kind[]" aria-label="%11$s">'
			. '<option value="percent"%3$s>%4$s</option>'
			. '<option value="fixed"%5$s>%6$s</option></select></td>'
			. '<td><input type="number" min="0" step="0.01" name="moforcoupon_tier_value[]" value="%7$s" placeholder="%8$s" aria-label="%12$s" /></td>'
			. '<td class="moforcoupon-tier-actions"><button type="button" class="button-link moforcoupon-tier-remove" title="%9$s" aria-label="%9$s">&times;</button></td></tr>',
			(int) $index,
			esc_attr( $threshold ),
			selected( $kind, 'percent', false ),
			esc_html__( '百分比 %', 'moforcoupon' ),
			selected( $kind, 'fixed', false ),
			esc_html__( '固定金額', 'moforcoupon' ),
			esc_attr( $value ),
			esc_attr__( '10=折10% / 200=折200元', 'moforcoupon' ),
			esc_attr__( '刪除此階', 'moforcoupon' ),
			esc_attr__( '門檻', 'moforcoupon' ),
			esc_attr__( '折扣方式', 'moforcoupon' ),
			esc_attr__( '折扣值', 'moforcoupon' )
		);
	}

	/** Enqueue the row add/remove script on the coupon edit screen only. */
	public function enqueue(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'shop_coupon' !== $screen->id ) {
			return;
		}
		$rel  = 'src/Modules/DiscountTiers/assets/js/tiers.js';
		$path = \MOFORCOUPON_PLUGIN_DIR . $rel;
		$ver  = file_exists( $path ) ? (string) filemtime( $path ) : \MOFORCOUPON_VERSION;
		wp_enqueue_script( 'moforcoupon-tiers', \MOFORCOUPON_PLUGIN_URL . $rel, array(), $ver, true );
		wp_localize_script(
			'moforcoupon-tiers',
			'moforcouponTiers',
			array(
				'maxRows'      => (int) apply_filters( 'moforcoupon_tiers_ui_max_rows', 50 ),
				'basisId'      => Keys::TIERS_BASIS,
				'thresholdHdr' => array(
					/* translators: %s: the basis unit (cart subtotal). */
					'subtotal' => sprintf( __( '門檻 ≥(%s)', 'moforcoupon' ), __( '購物車小計', 'moforcoupon' ) ),
					/* translators: %s: the basis unit (item count). */
					'quantity' => sprintf( __( '門檻 ≥(%s)', 'moforcoupon' ), __( '件數', 'moforcoupon' ) ),
					/* translators: %s: the basis unit (weight kg). */
					'weight'   => sprintf( __( '門檻 ≥(%s)', 'moforcoupon' ), __( '重量 kg', 'moforcoupon' ) ),
				),
			)
		);
		wp_register_style( 'moforcoupon-tiers', false, array(), $ver );
		wp_enqueue_style( 'moforcoupon-tiers' );
		wp_add_inline_style(
			'moforcoupon-tiers',
			'.moforcoupon-tier-idx{text-align:center;}'
			. '.moforcoupon-tier-actions{text-align:center;}'
			// box-sizing so the full-width inputs/selects never overflow the narrow <td> under
			// WooCommerce's input padding.
			. '.moforcoupon-tier-row input,.moforcoupon-tier-row select{box-sizing:border-box;width:100%;}'
			. '.moforcoupon-tier-remove{color:#b32d2e;font-size:20px;line-height:1;text-decoration:none;cursor:pointer;}'
			. '.moforcoupon-tier-remove:hover{color:#8a2424;}'
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
		// Store 'yes' / '' (not 'no') to match every other feature's enable flag convention.
		update_post_meta( $post_id, Keys::TIERS_ENABLED, isset( $_POST[ Keys::TIERS_ENABLED ] ) ? 'yes' : '' );

		$basis = isset( $_POST[ Keys::TIERS_BASIS ] ) ? sanitize_key( wp_unslash( $_POST[ Keys::TIERS_BASIS ] ) ) : 'subtotal';
		update_post_meta( $post_id, Keys::TIERS_BASIS, Tiers::basis( $basis ) );

		$thresholds = isset( $_POST['moforcoupon_tier_threshold'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['moforcoupon_tier_threshold'] ) ) : array();
		$kinds      = isset( $_POST['moforcoupon_tier_kind'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['moforcoupon_tier_kind'] ) ) : array();
		$values     = isset( $_POST['moforcoupon_tier_value'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['moforcoupon_tier_value'] ) ) : array();

		$rows = array();
		foreach ( $values as $i => $value ) {
			$rows[] = array(
				'threshold' => (float) wc_format_decimal( (string) ( $thresholds[ $i ] ?? '' ) ),
				'kind'      => ( 'fixed' === ( $kinds[ $i ] ?? 'percent' ) ) ? 'fixed' : 'percent',
				'value'     => (float) wc_format_decimal( (string) $value ),
			);
		}
		$json = Tiers::canonical_json( $rows );
		if ( '' === $json ) {
			delete_post_meta( $post_id, Keys::TIERS );
		} else {
			update_post_meta( $post_id, Keys::TIERS, $json );
		}

		$mode = isset( $_POST[ Keys::TIERS_TARGET_MODE ] ) ? sanitize_key( wp_unslash( $_POST[ Keys::TIERS_TARGET_MODE ] ) ) : 'all';
		update_post_meta( $post_id, Keys::TIERS_TARGET_MODE, in_array( $mode, array( 'products', 'categories' ), true ) ? $mode : 'all' );

		$this->save_id_list( $post_id, Keys::TIERS_TARGET_PRODUCTS );
		$this->save_id_list( $post_id, Keys::TIERS_TARGET_CATEGORIES );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	private function save_id_list( int $post_id, string $key ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- caller verified the nonce.
		$raw  = isset( $_POST[ $key ] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST[ $key ] ) ) : array();
		$list = FieldsHelpers::int_list( $raw );
		if ( array() === $list ) {
			delete_post_meta( $post_id, $key );
		} else {
			update_post_meta( $post_id, $key, $list );
		}
	}
}
