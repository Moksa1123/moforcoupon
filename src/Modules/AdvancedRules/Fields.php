<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\AdvancedRules;

use MoksaWeb\Moforcoupon\Coupon\Meta\Keys;
use MoksaWeb\Moforcoupon\Admin\FieldsSaveGuard;
use MoksaWeb\Moforcoupon\Support\Rules;

defined( 'ABSPATH' ) || exit;

/**
 * "進階規則" coupon edit-screen tab: a visual AND/OR rule builder. The builder JS reads /
 * writes a single hidden <textarea> holding the canonical JSON, so the field degrades to a
 * raw-JSON editor when JS is unavailable and saves identically either way.
 */
final class Fields {

	use FieldsSaveGuard;

	private const CAP   = 'manage_woocommerce';
	private const NONCE = 'moforcoupon_rules_nonce';
	private const FIELD = 'moforcoupon_rules_json';

	private function action( int $id ): string {
		return 'moforcoupon_save_rules_coupon_' . $id;
	}

	/**
	 * Type spec shared with the builder JS: type => { label, kind, ops:[op=>label] }. kind
	 * drives the value input (num|ids|country|payment|weekday|role|time|date). Single source
	 * of truth alongside Support\Rules::TYPES.
	 *
	 * @return array<string,array{label:string,kind:string,ops:array<string,string>}>
	 */
	public static function type_spec(): array {
		$numeric  = array(
			'gte' => __( '大於等於 ≥', 'moforcoupon' ),
			'lte' => __( '小於等於 ≤', 'moforcoupon' ),
			'gt'  => __( '大於 >', 'moforcoupon' ),
			'lt'  => __( '小於 <', 'moforcoupon' ),
			'eq'  => __( '等於 =', 'moforcoupon' ),
			'neq' => __( '不等於 ≠', 'moforcoupon' ),
		);
		$inset    = array(
			'in'     => __( '屬於', 'moforcoupon' ),
			'not_in' => __( '不屬於', 'moforcoupon' ),
		);
		$beforeaf = array(
			'gte' => __( '在(含)之後', 'moforcoupon' ),
			'lte' => __( '在(含)之前', 'moforcoupon' ),
		);
		$eqneq    = array(
			'eq'  => __( '等於', 'moforcoupon' ),
			'neq' => __( '不等於', 'moforcoupon' ),
		);
		return array(
			'subtotal'               => array(
				'label' => __( '購物車小計', 'moforcoupon' ),
				'kind'  => 'num',
				'ops'   => $numeric,
			),
			'quantity'               => array(
				'label' => __( '商品總件數', 'moforcoupon' ),
				'kind'  => 'num',
				'ops'   => $numeric,
			),
			'order_count'            => array(
				'label' => __( '顧客歷史訂單數', 'moforcoupon' ),
				'kind'  => 'num',
				'ops'   => $numeric,
			),
			'coupon_usage_count'     => array(
				'label' => __( '此券已被使用次數(可做前 N 名限定)', 'moforcoupon' ),
				'kind'  => 'num',
				'ops'   => $numeric,
			),
			'total_spent'            => array(
				'label' => __( '顧客累積消費', 'moforcoupon' ),
				'kind'  => 'num',
				'ops'   => $numeric,
			),
			'cart_weight'            => array(
				'label' => __( '購物車重量(kg)', 'moforcoupon' ),
				'kind'  => 'num',
				'ops'   => $numeric,
			),
			'product_quantity'       => array(
				'label' => __( '特定商品的件數', 'moforcoupon' ),
				'kind'  => 'pair',
				'ops'   => $numeric,
			),
			'category_spent'         => array(
				'label' => __( '某分類累積消費', 'moforcoupon' ),
				'kind'  => 'pair',
				'ops'   => $numeric,
			),
			'ordered_product'        => array(
				'label' => __( '買過特定商品', 'moforcoupon' ),
				'kind'  => 'ids',
				'ops'   => $inset,
			),
			'ordered_category'       => array(
				'label' => __( '買過某分類', 'moforcoupon' ),
				'kind'  => 'ids',
				'ops'   => $inset,
			),
			'hours_since_registered' => array(
				'label' => __( '註冊後經過小時數', 'moforcoupon' ),
				'kind'  => 'num',
				'ops'   => $numeric,
			),
			'hours_since_last_order' => array(
				'label' => __( '上次下單後經過小時數', 'moforcoupon' ),
				'kind'  => 'num',
				'ops'   => $numeric,
			),
			'coupon_applied'         => array(
				'label' => __( '購物車已套用優惠券', 'moforcoupon' ),
				'kind'  => 'codetext',
				'ops'   => $inset,
			),
			'stock_status'           => array(
				'label' => __( '購物車含庫存狀態', 'moforcoupon' ),
				'kind'  => 'stock',
				'ops'   => $inset,
			),
			'shipping_zone'          => array(
				'label' => __( '運送區域(Zone)', 'moforcoupon' ),
				'kind'  => 'zone',
				'ops'   => $inset,
			),
			'custom_taxonomy'        => array(
				'label' => __( '購物車含自訂分類法', 'moforcoupon' ),
				'kind'  => 'tax',
				'ops'   => $inset,
			),
			'custom_user_meta'       => array(
				'label' => __( '自訂使用者 Meta', 'moforcoupon' ),
				'kind'  => 'kv',
				'ops'   => $eqneq,
			),
			'custom_cart_item_meta'  => array(
				'label' => __( '購物車項目 Meta', 'moforcoupon' ),
				'kind'  => 'kv',
				'ops'   => $inset,
			),
			'product_in_cart'        => array(
				'label' => __( '購物車含商品', 'moforcoupon' ),
				'kind'  => 'ids',
				'ops'   => $inset,
			),
			'category_in_cart'       => array(
				'label' => __( '購物車含分類', 'moforcoupon' ),
				'kind'  => 'ids',
				'ops'   => $inset,
			),
			'shipping_country'       => array(
				'label' => __( '收件國家 / 地區', 'moforcoupon' ),
				'kind'  => 'country',
				'ops'   => $inset,
			),
			'payment_method'         => array(
				'label' => __( '付款方式', 'moforcoupon' ),
				'kind'  => 'payment',
				'ops'   => $inset,
			),
			'user_role'              => array(
				'label' => __( '會員角色', 'moforcoupon' ),
				'kind'  => 'role',
				'ops'   => $inset,
			),
			'weekday'                => array(
				'label' => __( '星期', 'moforcoupon' ),
				'kind'  => 'weekday',
				'ops'   => $inset,
			),
			'time_of_day'            => array(
				'label' => __( '時間(時:分)', 'moforcoupon' ),
				'kind'  => 'time',
				'ops'   => $beforeaf,
			),
			'date'                   => array(
				'label' => __( '日期時間', 'moforcoupon' ),
				'kind'  => 'date',
				'ops'   => $beforeaf,
			),
		);
	}

	/**
	 * @return array<int,array{id:string,title:string,render:callable}>
	 */
	public function sections(): array {
		return array(
			array(
				'id'     => 'moforcoupon_advrules',
				'title'  => __( '進階規則', 'moforcoupon' ),
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
		$id   = (int) $post->ID;
		$json = (string) get_post_meta( $id, Keys::RULES, true );
		$json = '' === $json ? '' : (string) Rules::canonical_json( $json );

		echo '<p class="description" style="margin:8px 12px;">'
			. esc_html__( '用「群組」與 AND/OR 自由組合條件。整體可設「符合全部 / 任一群組」,每個群組內也可設「符合全部 / 任一條件」。在既有的單項條件之外額外把關。付款方式條件於結帳時驗證。', 'moforcoupon' )
			. '</p>';
		woocommerce_wp_checkbox(
			array(
				'id'    => Keys::RULES_ENABLED,
				'value' => get_post_meta( $id, Keys::RULES_ENABLED, true ),
				'label' => __( '啟用進階規則', 'moforcoupon' ),
			)
		);

		echo '<div class="moforcoupon-rules-builder" style="margin:6px 12px;"></div>';
		echo '<p class="form-field"><label for="' . esc_attr( self::FIELD ) . '">' . esc_html__( '規則 JSON(進階 / 後備)', 'moforcoupon' ) . '</label>';
		echo '<textarea id="' . esc_attr( self::FIELD ) . '" name="' . esc_attr( self::FIELD ) . '" rows="4" class="moforcoupon-rules-json" style="width:100%;font-family:monospace;">' . esc_textarea( $json ) . '</textarea></p>';

		woocommerce_wp_text_input(
			array(
				'id'    => Keys::RULES_MSG,
				'value' => get_post_meta( $id, Keys::RULES_MSG, true ),
				'label' => __( '不符時顯示訊息', 'moforcoupon' ),
			)
		);
	}

	/** Enqueue the builder JS + its data on the coupon edit screen only. */
	public function enqueue(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'shop_coupon' !== $screen->id ) {
			return;
		}
		$rel  = 'src/Modules/AdvancedRules/assets/js/builder.js';
		$path = \MOFORCOUPON_PLUGIN_DIR . $rel;
		$ver  = file_exists( $path ) ? (string) filemtime( $path ) : \MOFORCOUPON_VERSION;
		// selectWoo (WC's select2 fork) + its style are registered by WooCommerce on the coupon
		// screen; depending on them makes the value pickers nice dropdowns instead of native
		// multi-row listboxes.
		$deps = array( 'jquery' );
		if ( wp_script_is( 'selectWoo', 'registered' ) ) {
			$deps[] = 'selectWoo';
		}
		// wc-enhanced-select carries wc_enhanced_select_params (ajax url + product/category search
		// nonces) so the product / category value pickers can use WooCommerce's own AJAX search.
		if ( wp_script_is( 'wc-enhanced-select', 'registered' ) ) {
			$deps[] = 'wc-enhanced-select';
		}
		wp_enqueue_script( 'moforcoupon-rules-builder', \MOFORCOUPON_PLUGIN_URL . $rel, $deps, $ver, true );
		wp_register_style( 'moforcoupon-rules-builder', false, array(), $ver );
		wp_enqueue_style( 'moforcoupon-rules-builder' );
		wp_add_inline_style( 'moforcoupon-rules-builder', self::css() );
		wp_localize_script(
			'moforcoupon-rules-builder',
			'moforcouponRules',
			array(
				'types'     => self::type_spec(),
				'field'     => self::FIELD,
				'idLabels'  => self::rule_id_labels(),
				'countries' => ( function_exists( 'WC' ) && WC()->countries ) ? WC()->countries->get_countries() : array(),
				'gateways'  => self::gateways(),
				'roles'     => self::roles(),
				'weekdays'  => array(
					'0' => __( '星期日', 'moforcoupon' ),
					'1' => __( '星期一', 'moforcoupon' ),
					'2' => __( '星期二', 'moforcoupon' ),
					'3' => __( '星期三', 'moforcoupon' ),
					'4' => __( '星期四', 'moforcoupon' ),
					'5' => __( '星期五', 'moforcoupon' ),
					'6' => __( '星期六', 'moforcoupon' ),
				),
				'zones'     => self::zones(),
				'stocks'    => array(
					'instock'     => __( '有庫存', 'moforcoupon' ),
					'outofstock'  => __( '無庫存', 'moforcoupon' ),
					'onbackorder' => __( '可延期交貨', 'moforcoupon' ),
				),
				'i18n'      => array(
					'matchAll'      => __( '符合全部', 'moforcoupon' ),
					'matchAny'      => __( '符合任一', 'moforcoupon' ),
					'ofGroups'      => __( '群組:', 'moforcoupon' ),
					'ofRules'       => __( '此群組條件:', 'moforcoupon' ),
					'addRule'       => __( '+ 新增條件', 'moforcoupon' ),
					'addGroup'      => __( '+ 新增群組', 'moforcoupon' ),
					'removeRule'    => __( '刪除', 'moforcoupon' ),
					'removeGroup'   => __( '刪除群組', 'moforcoupon' ),
					'idsHint'       => __( '輸入 ID,以逗號分隔', 'moforcoupon' ),
					'codesHint'     => __( '輸入優惠券代碼,以逗號分隔', 'moforcoupon' ),
					'pairId'        => __( 'ID', 'moforcoupon' ),
					'pairNum'       => __( '數值', 'moforcoupon' ),
					'taxSlug'       => __( '分類法代稱', 'moforcoupon' ),
					'taxTerms'      => __( '詞彙 ID(逗號)', 'moforcoupon' ),
					'metaKey'       => __( 'Meta 鍵', 'moforcoupon' ),
					'metaVal'       => __( 'Meta 值', 'moforcoupon' ),
					'pick'          => __( '選擇…', 'moforcoupon' ),
					'searchProduct' => __( '搜尋商品…', 'moforcoupon' ),
					'searchCat'     => __( '搜尋分類…', 'moforcoupon' ),
				),
			)
		);
	}

	/**
	 * Labels for the product / category ids already referenced in this coupon's saved rules, so
	 * the WooCommerce search dropdowns show names (not bare "#123") for the existing selections.
	 *
	 * @return array<string,string> id => label
	 */
	private static function rule_id_labels(): array {
		global $post;
		if ( ! $post instanceof \WP_Post ) {
			return array();
		}
		$labels = array();
		$set    = Rules::parse( (string) get_post_meta( (int) $post->ID, Keys::RULES, true ) );
		foreach ( $set['groups'] as $group ) {
			foreach ( ( is_array( $group ) ? ( $group['rules'] ?? array() ) : array() ) as $rule ) {
				$type  = is_array( $rule ) ? (string) ( $rule['type'] ?? '' ) : '';
				$value = is_array( $rule ) && isset( $rule['value'] ) && is_array( $rule['value'] ) ? $rule['value'] : array();
				if ( in_array( $type, array( 'product_in_cart', 'ordered_product' ), true ) ) {
					foreach ( $value as $pid ) {
						$pid     = (int) $pid;
						$product = ( $pid > 0 && ! isset( $labels[ (string) $pid ] ) && function_exists( 'wc_get_product' ) ) ? wc_get_product( $pid ) : null;
						if ( $product ) {
							$labels[ (string) $pid ] = wp_strip_all_tags( $product->get_formatted_name() );
						}
					}
				} elseif ( in_array( $type, array( 'category_in_cart', 'ordered_category' ), true ) ) {
					foreach ( $value as $tid ) {
						$tid  = (int) $tid;
						$term = ( $tid > 0 && ! isset( $labels[ (string) $tid ] ) ) ? get_term( $tid, 'product_cat' ) : null;
						if ( $term instanceof \WP_Term ) {
							$labels[ (string) $tid ] = $term->name;
						}
					}
				}
			}
		}
		return $labels;
	}

	/** Builder stylesheet — Advanced-Coupons-style group cards + clean two-line rule rows. */
	private static function css(): string {
		return '.moforcoupon-rules-builder{font-size:13px;}'
			// WooCommerce's .woocommerce_options_panel floats every select/input; undo that
			// inside the builder so the flex rows lay out correctly.
			. '.moforcoupon-rules-builder select,.moforcoupon-rules-builder input,.moforcoupon-rules-builder .select2-container{float:none!important;margin:0;}'
			. '.mfc-top{display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-bottom:10px;font-weight:600;}'
			. '.mfc-group{position:relative;border:1px solid #c3c4c7;border-radius:6px;background:#fff;padding:10px;margin:0 0 10px;}'
			. '.mfc-group-head{display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin:0 0 8px;padding-bottom:8px;border-bottom:1px solid #f0f0f1;}'
			. '.mfc-del-group{margin-inline-start:auto;}'
			// One compact, wrapping row per rule (AC-style): Type · Operator · Value · remove.
			. '.mfc-rule{display:flex;flex-wrap:wrap;align-items:center;gap:6px;padding:7px 0;border-top:1px solid #f0f0f1;}'
			. '.mfc-rule:first-of-type{border-top:0;}'
			. '.mfc-type{flex:1 1 150px;min-width:130px;box-sizing:border-box;}'
			. '.mfc-op{flex:0 1 116px;min-width:92px;box-sizing:border-box;}'
			. '.mfc-val{flex:2 1 170px;min-width:120px;}'
			. '.mfc-val>select,.mfc-val>input{width:100%;box-sizing:border-box;}'
			. '.mfc-val>span{display:flex;gap:4px;width:100%;}'
			. '.mfc-val>span>input{flex:1 1 0;min-width:0;box-sizing:border-box;}'
			. '.mfc-val .select2-container{width:100%!important;}'
			. '.mfc-del{flex:0 0 auto;border:0;background:transparent;color:#b32d2e;cursor:pointer;font-size:18px;line-height:1;padding:0 4px;}'
			. '.mfc-del:hover{color:#8a1f20;}'
			. '.mfc-addrule{margin-top:4px;}'
			. '.moforcoupon-rules-json{margin-top:6px;}';
	}

	/** @return array<string,string> gateway id => title. */
	private static function gateways(): array {
		$out = array();
		if ( function_exists( 'WC' ) && WC()->payment_gateways() ) {
			foreach ( WC()->payment_gateways()->payment_gateways() as $gateway ) {
				if ( is_object( $gateway ) && isset( $gateway->id ) ) {
					$gid         = (string) $gateway->id;
					$title       = method_exists( $gateway, 'get_title' ) ? wp_strip_all_tags( (string) $gateway->get_title() ) : $gid;
					$out[ $gid ] = '' !== $title ? $title : $gid;
				}
			}
		}
		return $out;
	}

	/** @return array<string,string> shipping zone id => name (incl. the "rest of the world" zone 0). */
	private static function zones(): array {
		$out = array();
		if ( class_exists( '\WC_Shipping_Zones' ) ) {
			foreach ( \WC_Shipping_Zones::get_zones() as $zone ) {
				if ( isset( $zone['id'], $zone['zone_name'] ) ) {
					$out[ (string) $zone['id'] ] = (string) $zone['zone_name'];
				}
			}
			$out['0'] = __( '其他地區(未涵蓋)', 'moforcoupon' );
		}
		return $out;
	}

	/** @return array<string,string> role slug => label. */
	private static function roles(): array {
		$out = array( 'guest' => __( '訪客(未登入)', 'moforcoupon' ) );
		if ( function_exists( 'wp_roles' ) ) {
			foreach ( wp_roles()->roles as $slug => $role ) {
				$out[ (string) $slug ] = translate_user_role( $role['name'] );
			}
		}
		return $out;
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
		update_post_meta( $post_id, Keys::RULES_ENABLED, isset( $_POST[ Keys::RULES_ENABLED ] ) ? 'yes' : '' );

		// The builder writes canonical JSON here; Rules::canonical_json parses + rebuilds it
		// (only known type/op/value survive), which IS the sanitization for this structured field.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- structural sanitisation via Rules::canonical_json below.
		$raw  = isset( $_POST[ self::FIELD ] ) ? wp_unslash( $_POST[ self::FIELD ] ) : '';
		$json = Rules::canonical_json( is_string( $raw ) ? $raw : '' );
		if ( '' === $json ) {
			delete_post_meta( $post_id, Keys::RULES );
		} else {
			update_post_meta( $post_id, Keys::RULES, $json );
		}

		$msg = isset( $_POST[ Keys::RULES_MSG ] ) ? sanitize_text_field( wp_unslash( $_POST[ Keys::RULES_MSG ] ) ) : '';
		if ( '' === trim( $msg ) ) {
			delete_post_meta( $post_id, Keys::RULES_MSG );
		} else {
			update_post_meta( $post_id, Keys::RULES_MSG, $msg );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}
}
