<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\CouponConditions;

use MoksaWeb\Moforcoupon\Coupon\Meta\Keys;
use MoksaWeb\Moforcoupon\Admin\FieldsSaveGuard;
use MoksaWeb\Moforcoupon\Admin\FieldsHelpers;

defined( 'ABSPATH' ) || exit;

/**
 * Coupon edit-screen UI for conditions, using WooCommerce-native coupon data tabs
 * + panels + woocommerce_coupon_options_save. Uses a dedicated nonce field (not
 * the shared _wpnonce) to survive other plugins rewriting it, mirroring Advanced
 * Coupons 4.7.3. Custom _moforcoupon_* meta is written directly with
 * update_post_meta (WC_Coupon does not know these props).
 */
final class Fields {

	use FieldsSaveGuard;

	private const CAP   = 'manage_woocommerce';
	private const NONCE = 'moforcoupon_conditions_nonce';

	private function action( int $id ): string {
		return 'moforcoupon_save_coupon_' . $id;
	}

	public function render_nonce(): void {
		global $post;
		if ( ! $post instanceof \WP_Post ) {
			return;
		}
		wp_nonce_field( $this->action( (int) $post->ID ), self::NONCE, false );
	}

	/**
	 * Six coupon-condition sections for the CouponSections coordinator. Each renders
	 * as a coupon-data tab by default, or as its own metabox when enabled.
	 *
	 * @return array<int,array{id:string,title:string,render:callable}>
	 */
	public function sections(): array {
		return array(
			array(
				'id'     => 'moforcoupon_schedule',
				'title'  => __( '排程', 'moforcoupon' ),
				'render' => function (): void {
					$this->render_schedule_panel();
				},
			),
			array(
				'id'     => 'moforcoupon_roles',
				'title'  => __( '角色限制', 'moforcoupon' ),
				'render' => function (): void {
					$this->render_roles_panel();
				},
			),
			array(
				'id'     => 'moforcoupon_cart',
				'title'  => __( '購物車條件', 'moforcoupon' ),
				'render' => function (): void {
					$this->render_cart_panel();
				},
			),
			array(
				'id'     => 'moforcoupon_customer',
				'title'  => __( '顧客條件', 'moforcoupon' ),
				'render' => function (): void {
					$this->render_customer_panel();
				},
			),
			array(
				'id'     => 'moforcoupon_products',
				'title'  => __( '商品條件', 'moforcoupon' ),
				'render' => function (): void {
					$this->render_products_panel();
				},
			),
			array(
				'id'     => 'moforcoupon_shipregion',
				'title'  => __( '收件地區', 'moforcoupon' ),
				'render' => function (): void {
					$this->render_shipregion_panel();
				},
			),
			array(
				'id'     => 'moforcoupon_payment',
				'title'  => __( '付款方式', 'moforcoupon' ),
				'render' => function (): void {
					$this->render_payment_panel();
				},
			),
			array(
				'id'     => 'moforcoupon_daytime',
				'title'  => __( '星期 / 時段', 'moforcoupon' ),
				'render' => function (): void {
					$this->render_daytime_panel();
				},
			),
		);
	}

	private function render_schedule_panel(): void {
		global $post;
		if ( ! $post instanceof \WP_Post ) {
			return;
		}
		$id = (int) $post->ID;

		woocommerce_wp_checkbox(
			array(
				'id'          => Keys::SCHEDULE_ENABLED,
				'value'       => get_post_meta( $id, Keys::SCHEDULE_ENABLED, true ),
				'label'       => __( '啟用排程', 'moforcoupon' ),
				'description' => __( '僅在下方起訖時間內(站台時區)可使用此優惠券。', 'moforcoupon' ),
			)
		);
		$this->datetime_field( Keys::SCHEDULE_START, __( '開始時間', 'moforcoupon' ), (string) get_post_meta( $id, Keys::SCHEDULE_START, true ) );
		$this->datetime_field( Keys::SCHEDULE_END, __( '結束時間', 'moforcoupon' ), (string) get_post_meta( $id, Keys::SCHEDULE_END, true ) );
		woocommerce_wp_text_input(
			array(
				'id'    => Keys::SCHEDULE_MSG_START,
				'value' => get_post_meta( $id, Keys::SCHEDULE_MSG_START, true ),
				'label' => __( '尚未開始的訊息', 'moforcoupon' ),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'    => Keys::SCHEDULE_MSG_END,
				'value' => get_post_meta( $id, Keys::SCHEDULE_MSG_END, true ),
				'label' => __( '已過期的訊息', 'moforcoupon' ),
			)
		);
	}

	private function render_roles_panel(): void {
		global $post;
		if ( ! $post instanceof \WP_Post ) {
			return;
		}
		$id = (int) $post->ID;

		woocommerce_wp_checkbox(
			array(
				'id'    => Keys::ROLE_ENABLED,
				'value' => get_post_meta( $id, Keys::ROLE_ENABLED, true ),
				'label' => __( '啟用角色限制', 'moforcoupon' ),
			)
		);
		woocommerce_wp_select(
			array(
				'id'      => Keys::ROLE_TYPE,
				'value'   => get_post_meta( $id, Keys::ROLE_TYPE, true ) ? get_post_meta( $id, Keys::ROLE_TYPE, true ) : 'allowed',
				'label'   => __( '限制類型', 'moforcoupon' ),
				'options' => array(
					'allowed'    => __( '僅允許這些角色', 'moforcoupon' ),
					'disallowed' => __( '排除這些角色', 'moforcoupon' ),
				),
			)
		);
		$this->roles_field( (array) get_post_meta( $id, Keys::ROLE_LIST, true ) );
		woocommerce_wp_text_input(
			array(
				'id'    => Keys::ROLE_MSG,
				'value' => get_post_meta( $id, Keys::ROLE_MSG, true ),
				'label' => __( '無權限時的訊息', 'moforcoupon' ),
			)
		);
	}

	private function render_cart_panel(): void {
		global $post;
		if ( ! $post instanceof \WP_Post ) {
			return;
		}
		$id = (int) $post->ID;

		woocommerce_wp_text_input(
			array(
				'id'          => Keys::MIN_SUBTOTAL,
				'value'       => get_post_meta( $id, Keys::MIN_SUBTOTAL, true ),
				'label'       => __( '最低購物車小計', 'moforcoupon' ),
				'data_type'   => 'price',
				'desc_tip'    => true,
				'description' => __( '注意:此與 WooCommerce 原生「最低消費」語意不同(可選含稅與否),請勿同時設定兩者。', 'moforcoupon' ),
			)
		);
		woocommerce_wp_checkbox(
			array(
				'id'    => Keys::MIN_SUBTOTAL_INCL_TAX,
				'value' => get_post_meta( $id, Keys::MIN_SUBTOTAL_INCL_TAX, true ),
				'label' => __( '小計含稅計算', 'moforcoupon' ),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'                => Keys::MIN_QTY,
				'value'             => get_post_meta( $id, Keys::MIN_QTY, true ),
				'label'             => __( '最低商品數量', 'moforcoupon' ),
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '1',
				),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'    => Keys::CART_MSG,
				'value' => get_post_meta( $id, Keys::CART_MSG, true ),
				'label' => __( '未達條件時的訊息', 'moforcoupon' ),
			)
		);
	}

	private function render_customer_panel(): void {
		global $post;
		if ( ! $post instanceof \WP_Post ) {
			return;
		}
		$id = (int) $post->ID;

		woocommerce_wp_checkbox(
			array(
				'id'          => Keys::CUST_ENABLED,
				'value'       => get_post_meta( $id, Keys::CUST_ENABLED, true ),
				'label'       => __( '啟用顧客條件', 'moforcoupon' ),
				'description' => __( '依顧客的歷史訂單數 / 累積消費限制使用(僅對登入顧客生效;訪客視為無消費紀錄)。', 'moforcoupon' ),
			)
		);
		woocommerce_wp_checkbox(
			array(
				'id'          => Keys::CUST_FIRST_ONLY,
				'value'       => get_post_meta( $id, Keys::CUST_FIRST_ONLY, true ),
				'label'       => __( '僅限首次消費', 'moforcoupon' ),
				'description' => __( '只有尚未完成過任何訂單的顧客可使用。注意:未登入訪客視為首購(以訪客結帳的回頭客可能繞過)。', 'moforcoupon' ),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'                => Keys::CUST_MIN_ORDERS,
				'value'             => get_post_meta( $id, Keys::CUST_MIN_ORDERS, true ),
				'label'             => __( '最少歷史訂單數', 'moforcoupon' ),
				'type'              => 'number',
				'desc_tip'          => true,
				'description'       => __( '留空=不限。顧客已完成的訂單數須達此值。', 'moforcoupon' ),
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '1',
				),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'                => Keys::CUST_MAX_ORDERS,
				'value'             => get_post_meta( $id, Keys::CUST_MAX_ORDERS, true ),
				'label'             => __( '最多歷史訂單數', 'moforcoupon' ),
				'type'              => 'number',
				'desc_tip'          => true,
				'description'       => __( '留空=不限。顧客訂單數超過此值即不可用。', 'moforcoupon' ),
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '1',
				),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'          => Keys::CUST_MIN_SPENT,
				'value'       => get_post_meta( $id, Keys::CUST_MIN_SPENT, true ),
				'label'       => __( '最低累積消費', 'moforcoupon' ),
				'data_type'   => 'price',
				'desc_tip'    => true,
				'description' => __( '留空=不限。顧客累積消費金額須達此值。', 'moforcoupon' ),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'          => Keys::CUST_MAX_SPENT,
				'value'       => get_post_meta( $id, Keys::CUST_MAX_SPENT, true ),
				'label'       => __( '最高累積消費', 'moforcoupon' ),
				'data_type'   => 'price',
				'desc_tip'    => true,
				'description' => __( '留空=不限。顧客累積消費超過此值即不可用。', 'moforcoupon' ),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'    => Keys::CUST_MSG,
				'value' => get_post_meta( $id, Keys::CUST_MSG, true ),
				'label' => __( '不符條件時的訊息', 'moforcoupon' ),
			)
		);
	}

	private function render_products_panel(): void {
		global $post;
		if ( ! $post instanceof \WP_Post ) {
			return;
		}
		$id = (int) $post->ID;

		echo '<p class="description" style="margin:8px 12px;">' . esc_html__( '購物車需含下列商品 / 分類才能使用此券(與 WooCommerce 原生「適用商品」不同:這是使用門檻,不是折扣範圍)。留空=不限。', 'moforcoupon' ) . '</p>';
		FieldsHelpers::product_select( Keys::REQ_PRODUCTS, __( '需含商品', 'moforcoupon' ), FieldsHelpers::int_list( get_post_meta( $id, Keys::REQ_PRODUCTS, true ) ) );
		woocommerce_wp_select(
			array(
				'id'      => Keys::REQ_PRODUCTS_MODE,
				'value'   => 'all' === get_post_meta( $id, Keys::REQ_PRODUCTS_MODE, true ) ? 'all' : 'any',
				'label'   => __( '商品比對方式', 'moforcoupon' ),
				'options' => array(
					'any' => __( '含任一即可', 'moforcoupon' ),
					'all' => __( '需全部包含', 'moforcoupon' ),
				),
			)
		);
		FieldsHelpers::category_select( Keys::REQ_CATEGORIES, __( '需含分類', 'moforcoupon' ), FieldsHelpers::int_list( get_post_meta( $id, Keys::REQ_CATEGORIES, true ) ) );
		woocommerce_wp_select(
			array(
				'id'      => Keys::REQ_CATEGORIES_MODE,
				'value'   => 'all' === get_post_meta( $id, Keys::REQ_CATEGORIES_MODE, true ) ? 'all' : 'any',
				'label'   => __( '分類比對方式', 'moforcoupon' ),
				'options' => array(
					'any' => __( '含任一即可', 'moforcoupon' ),
					'all' => __( '需全部包含', 'moforcoupon' ),
				),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'    => Keys::PRODUCT_MSG,
				'value' => get_post_meta( $id, Keys::PRODUCT_MSG, true ),
				'label' => __( '不符商品條件時的訊息', 'moforcoupon' ),
			)
		);
		echo '<p class="description" style="margin:8px 12px;">' . esc_html__( '購物車「含」下列商品 / 分類時不可使用此券(例:特定品不適用)。', 'moforcoupon' ) . '</p>';
		FieldsHelpers::product_select( Keys::EXCL_PRODUCTS, __( '排除商品', 'moforcoupon' ), FieldsHelpers::int_list( get_post_meta( $id, Keys::EXCL_PRODUCTS, true ) ) );
		FieldsHelpers::category_select( Keys::EXCL_CATEGORIES, __( '排除分類', 'moforcoupon' ), FieldsHelpers::int_list( get_post_meta( $id, Keys::EXCL_CATEGORIES, true ) ) );
		woocommerce_wp_text_input(
			array(
				'id'    => Keys::EXCL_MSG,
				'value' => get_post_meta( $id, Keys::EXCL_MSG, true ),
				'label' => __( '含排除商品時的訊息', 'moforcoupon' ),
			)
		);
	}

	private function render_daytime_panel(): void {
		global $post;
		if ( ! $post instanceof \WP_Post ) {
			return;
		}
		$id = (int) $post->ID;

		woocommerce_wp_checkbox(
			array(
				'id'          => Keys::DAYTIME_ENABLED,
				'value'       => get_post_meta( $id, Keys::DAYTIME_ENABLED, true ),
				'label'       => __( '啟用星期 / 時段限制', 'moforcoupon' ),
				'description' => __( '僅在下列星期與時段(站台時區)可使用此優惠券。', 'moforcoupon' ),
			)
		);
		$this->days_field( FieldsHelpers::int_list( get_post_meta( $id, Keys::DAYTIME_DAYS, true ) ) );
		$this->time_field( Keys::DAYTIME_START, __( '開始時間', 'moforcoupon' ), (string) get_post_meta( $id, Keys::DAYTIME_START, true ) );
		$this->time_field( Keys::DAYTIME_END, __( '結束時間', 'moforcoupon' ), (string) get_post_meta( $id, Keys::DAYTIME_END, true ) );
		echo '<p class="description" style="margin:8px 12px;">' . esc_html__( '時段留空=整天。結束早於開始視為跨夜(例 22:00–02:00)。星期不選=不限。', 'moforcoupon' ) . '</p>';
		woocommerce_wp_text_input(
			array(
				'id'    => Keys::DAYTIME_MSG,
				'value' => get_post_meta( $id, Keys::DAYTIME_MSG, true ),
				'label' => __( '不在時段時的訊息', 'moforcoupon' ),
			)
		);
	}

	/**
	 * @param array<int,int> $selected Selected weekday numbers (0=Sun..6=Sat).
	 */
	private function days_field( array $selected ): void {
		echo '<p class="form-field"><label for="' . esc_attr( Keys::DAYTIME_DAYS ) . '">' . esc_html__( '可使用的星期', 'moforcoupon' ) . '</label>';
		echo '<select id="' . esc_attr( Keys::DAYTIME_DAYS ) . '" name="' . esc_attr( Keys::DAYTIME_DAYS ) . '[]" multiple="multiple" class="wc-enhanced-select" style="width:50%;">';
		foreach ( $this->weekdays() as $num => $name ) {
			echo '<option value="' . esc_attr( (string) $num ) . '"' . selected( in_array( $num, $selected, true ), true, false ) . '>' . esc_html( $name ) . '</option>';
		}
		echo '</select></p>';
	}

	private function time_field( string $key, string $label, string $value ): void {
		echo '<p class="form-field"><label for="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label>';
		echo '<input type="time" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" /></p>';
	}

	/** @return array<int,string> weekday number (0=Sun..6=Sat) => localized label. */
	private function weekdays(): array {
		global $wp_locale;
		$out = array();
		for ( $i = 0; $i < 7; $i++ ) {
			$out[ $i ] = ( $wp_locale instanceof \WP_Locale ) ? $wp_locale->get_weekday( $i ) : (string) $i;
		}
		return $out;
	}


	private function datetime_field( string $key, string $label, string $value ): void {
		$attr  = esc_attr( $key );
		$shown = '' !== $value ? esc_attr( str_replace( ' ', 'T', substr( $value, 0, 16 ) ) ) : '';
		echo '<p class="form-field"><label for="' . esc_attr( $attr ) . '">' . esc_html( $label ) . '</label>';
		echo '<input type="datetime-local" id="' . esc_attr( $attr ) . '" name="' . esc_attr( $attr ) . '" value="' . esc_attr( $shown ) . '" /></p>';
	}

	/**
	 * @param array<int,string> $selected
	 */
	private function roles_field( array $selected ): void {
		echo '<p class="form-field"><label for="' . esc_attr( Keys::ROLE_LIST ) . '">' . esc_html__( '使用者角色', 'moforcoupon' ) . '</label>';
		echo '<select id="' . esc_attr( Keys::ROLE_LIST ) . '" name="' . esc_attr( Keys::ROLE_LIST ) . '[]" multiple="multiple" class="wc-enhanced-select" style="width:50%;">';
		foreach ( $this->roles() as $slug => $name ) {
			echo '<option value="' . esc_attr( $slug ) . '"' . selected( in_array( $slug, $selected, true ), true, false ) . '>' . esc_html( $name ) . '</option>';
		}
		echo '</select></p>';
	}

	/** @return array<string,string> role slug => label (incl. a synthetic guest role). */
	private function roles(): array {
		$out = array( 'guest' => __( '訪客(未登入)', 'moforcoupon' ) );
		if ( function_exists( 'wp_roles' ) ) {
			foreach ( wp_roles()->roles as $slug => $role ) {
				$out[ (string) $slug ] = translate_user_role( $role['name'] );
			}
		}
		return $out;
	}

	private function render_shipregion_panel(): void {
		global $post;
		if ( ! $post instanceof \WP_Post ) {
			return;
		}
		$id = (int) $post->ID;
		echo '<p class="description" style="margin:8px 12px;">' . esc_html__( '依「收件地址的國家」限制此優惠券(購物車階段即驗證)。與「運費覆寫」不同——那是改運費,這是限地區。', 'moforcoupon' ) . '</p>';
		woocommerce_wp_checkbox(
			array(
				'id'    => Keys::SHIPREGION_ENABLED,
				'value' => get_post_meta( $id, Keys::SHIPREGION_ENABLED, true ),
				'label' => __( '啟用收件地區限制', 'moforcoupon' ),
			)
		);
		woocommerce_wp_select(
			array(
				'id'      => Keys::SHIPREGION_MODE,
				'value'   => 'disallow' === get_post_meta( $id, Keys::SHIPREGION_MODE, true ) ? 'disallow' : 'allow',
				'label'   => __( '限制方式', 'moforcoupon' ),
				'options' => array(
					'allow'    => __( '只允許下列國家 / 地區', 'moforcoupon' ),
					'disallow' => __( '排除下列國家 / 地區', 'moforcoupon' ),
				),
			)
		);
		$this->countries_field( $this->upper_codes( get_post_meta( $id, Keys::SHIPREGION_COUNTRIES, true ) ) );
		woocommerce_wp_text_input(
			array(
				'id'    => Keys::SHIPREGION_MSG,
				'value' => get_post_meta( $id, Keys::SHIPREGION_MSG, true ),
				'label' => __( '不符時顯示訊息', 'moforcoupon' ),
			)
		);
	}

	private function render_payment_panel(): void {
		global $post;
		if ( ! $post instanceof \WP_Post ) {
			return;
		}
		$id = (int) $post->ID;
		echo '<p class="description" style="margin:8px 12px;">' . esc_html__( '限制此優惠券可搭配的付款方式。付款方式在結帳時才確定,因此於結帳驗證(不符會擋下結帳)。', 'moforcoupon' ) . '</p>';
		woocommerce_wp_checkbox(
			array(
				'id'    => Keys::PAYMENT_ENABLED,
				'value' => get_post_meta( $id, Keys::PAYMENT_ENABLED, true ),
				'label' => __( '啟用付款方式限制', 'moforcoupon' ),
			)
		);
		woocommerce_wp_select(
			array(
				'id'      => Keys::PAYMENT_MODE,
				'value'   => 'disallow' === get_post_meta( $id, Keys::PAYMENT_MODE, true ) ? 'disallow' : 'allow',
				'label'   => __( '限制方式', 'moforcoupon' ),
				'options' => array(
					'allow'    => __( '只允許下列付款方式', 'moforcoupon' ),
					'disallow' => __( '排除下列付款方式', 'moforcoupon' ),
				),
			)
		);
		$this->gateways_field( $this->string_codes( get_post_meta( $id, Keys::PAYMENT_METHODS, true ) ) );
		woocommerce_wp_text_input(
			array(
				'id'    => Keys::PAYMENT_MSG,
				'value' => get_post_meta( $id, Keys::PAYMENT_MSG, true ),
				'label' => __( '不符時顯示訊息', 'moforcoupon' ),
			)
		);
	}

	/**
	 * @param array<int,string> $selected Uppercase country codes.
	 */
	private function countries_field( array $selected ): void {
		$countries = ( function_exists( 'WC' ) && WC()->countries ) ? WC()->countries->get_countries() : array();
		echo '<p class="form-field"><label for="' . esc_attr( Keys::SHIPREGION_COUNTRIES ) . '">' . esc_html__( '國家 / 地區', 'moforcoupon' ) . '</label>';
		echo '<select id="' . esc_attr( Keys::SHIPREGION_COUNTRIES ) . '" name="' . esc_attr( Keys::SHIPREGION_COUNTRIES ) . '[]" multiple="multiple" class="wc-enhanced-select" style="width:50%;" data-placeholder="' . esc_attr__( '選擇國家 / 地區…', 'moforcoupon' ) . '">';
		foreach ( $countries as $code => $name ) {
			echo '<option value="' . esc_attr( (string) $code ) . '"' . selected( in_array( (string) $code, $selected, true ), true, false ) . '>' . esc_html( (string) $name ) . '</option>';
		}
		echo '</select></p>';
	}

	/**
	 * @param array<int,string> $selected Gateway ids.
	 */
	private function gateways_field( array $selected ): void {
		$gateways = ( function_exists( 'WC' ) && WC()->payment_gateways() ) ? WC()->payment_gateways()->payment_gateways() : array();
		echo '<p class="form-field"><label for="' . esc_attr( Keys::PAYMENT_METHODS ) . '">' . esc_html__( '付款方式', 'moforcoupon' ) . '</label>';
		echo '<select id="' . esc_attr( Keys::PAYMENT_METHODS ) . '" name="' . esc_attr( Keys::PAYMENT_METHODS ) . '[]" multiple="multiple" class="wc-enhanced-select" style="width:50%;" data-placeholder="' . esc_attr__( '選擇付款方式…', 'moforcoupon' ) . '">';
		foreach ( $gateways as $gateway ) {
			if ( ! is_object( $gateway ) || ! isset( $gateway->id ) ) {
				continue;
			}
			$gid   = (string) $gateway->id;
			$title = method_exists( $gateway, 'get_title' ) ? wp_strip_all_tags( (string) $gateway->get_title() ) : $gid;
			echo '<option value="' . esc_attr( $gid ) . '"' . selected( in_array( $gid, $selected, true ), true, false ) . '>' . esc_html( '' !== $title ? $title : $gid ) . '</option>';
		}
		echo '</select></p>';
	}

	/**
	 * @param mixed $value
	 * @return array<int,string>
	 */
	private function upper_codes( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}
		return array_values( array_filter( array_map( static fn( $v ): string => strtoupper( (string) $v ), $value ), static fn( string $v ): bool => '' !== $v ) );
	}

	/**
	 * @param mixed $value
	 * @return array<int,string>
	 */
	private function string_codes( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}
		return array_values( array_filter( array_map( 'strval', $value ), static fn( string $v ): bool => '' !== $v ) );
	}

	/**
	 * @param int $post_id
	 * @param \WC_Coupon $coupon
	 */
	public function save( $post_id, $coupon ): void {
		$post_id = (int) $post_id;
		if ( ! $this->verify_save( $post_id, self::NONCE ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified above.
		// Schedule.
		update_post_meta( $post_id, Keys::SCHEDULE_ENABLED, isset( $_POST[ Keys::SCHEDULE_ENABLED ] ) ? 'yes' : '' );
		foreach ( array( Keys::SCHEDULE_START, Keys::SCHEDULE_END ) as $key ) {
			$raw  = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
			$norm = $this->normalize_datetime( $raw );
			if ( '' === $norm ) {
				delete_post_meta( $post_id, $key );
			} else {
				update_post_meta( $post_id, $key, $norm );
			}
		}
		$this->save_text( $post_id, Keys::SCHEDULE_MSG_START );
		$this->save_text( $post_id, Keys::SCHEDULE_MSG_END );

		// Role.
		update_post_meta( $post_id, Keys::ROLE_ENABLED, isset( $_POST[ Keys::ROLE_ENABLED ] ) ? 'yes' : '' );
		$type = isset( $_POST[ Keys::ROLE_TYPE ] ) ? sanitize_key( wp_unslash( $_POST[ Keys::ROLE_TYPE ] ) ) : 'allowed';
		update_post_meta( $post_id, Keys::ROLE_TYPE, in_array( $type, array( 'allowed', 'disallowed' ), true ) ? $type : 'allowed' );
		$valid_roles = array_keys( $this->roles() );
		$roles       = ( isset( $_POST[ Keys::ROLE_LIST ] ) && is_array( $_POST[ Keys::ROLE_LIST ] ) )
			? array_values( array_intersect( array_map( 'sanitize_key', wp_unslash( $_POST[ Keys::ROLE_LIST ] ) ), $valid_roles ) )
			: array();
		update_post_meta( $post_id, Keys::ROLE_LIST, $roles );
		$this->save_text( $post_id, Keys::ROLE_MSG );

		// Cart.
		$subtotal = isset( $_POST[ Keys::MIN_SUBTOTAL ] ) ? wc_format_decimal( sanitize_text_field( wp_unslash( $_POST[ Keys::MIN_SUBTOTAL ] ) ) ) : '';
		if ( '' === $subtotal ) {
			delete_post_meta( $post_id, Keys::MIN_SUBTOTAL );
		} else {
			update_post_meta( $post_id, Keys::MIN_SUBTOTAL, $subtotal );
		}
		update_post_meta( $post_id, Keys::MIN_SUBTOTAL_INCL_TAX, isset( $_POST[ Keys::MIN_SUBTOTAL_INCL_TAX ] ) ? 'yes' : '' );
		update_post_meta( $post_id, Keys::MIN_QTY, isset( $_POST[ Keys::MIN_QTY ] ) ? max( 0, absint( wp_unslash( $_POST[ Keys::MIN_QTY ] ) ) ) : 0 );
		$this->save_text( $post_id, Keys::CART_MSG );

		// Customer history.
		update_post_meta( $post_id, Keys::CUST_ENABLED, isset( $_POST[ Keys::CUST_ENABLED ] ) ? 'yes' : '' );
		update_post_meta( $post_id, Keys::CUST_FIRST_ONLY, isset( $_POST[ Keys::CUST_FIRST_ONLY ] ) ? 'yes' : '' );
		$this->save_int( $post_id, Keys::CUST_MIN_ORDERS );
		$this->save_int( $post_id, Keys::CUST_MAX_ORDERS );
		$this->save_price( $post_id, Keys::CUST_MIN_SPENT );
		$this->save_price( $post_id, Keys::CUST_MAX_SPENT );
		$this->save_text( $post_id, Keys::CUST_MSG );

		// Product / category cart-presence conditions.
		$this->save_id_list( $post_id, Keys::REQ_PRODUCTS );
		$this->save_mode( $post_id, Keys::REQ_PRODUCTS_MODE );
		$this->save_id_list( $post_id, Keys::REQ_CATEGORIES );
		$this->save_mode( $post_id, Keys::REQ_CATEGORIES_MODE );
		$this->save_text( $post_id, Keys::PRODUCT_MSG );
		$this->save_id_list( $post_id, Keys::EXCL_PRODUCTS );
		$this->save_id_list( $post_id, Keys::EXCL_CATEGORIES );
		$this->save_text( $post_id, Keys::EXCL_MSG );

		// Shipping-region condition.
		update_post_meta( $post_id, Keys::SHIPREGION_ENABLED, isset( $_POST[ Keys::SHIPREGION_ENABLED ] ) ? 'yes' : '' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in save().
		$sr_mode = isset( $_POST[ Keys::SHIPREGION_MODE ] ) ? sanitize_key( wp_unslash( $_POST[ Keys::SHIPREGION_MODE ] ) ) : 'allow';
		update_post_meta( $post_id, Keys::SHIPREGION_MODE, 'disallow' === $sr_mode ? 'disallow' : 'allow' );
		$this->save_code_list( $post_id, Keys::SHIPREGION_COUNTRIES );
		$this->save_text( $post_id, Keys::SHIPREGION_MSG );

		// Payment-method condition.
		update_post_meta( $post_id, Keys::PAYMENT_ENABLED, isset( $_POST[ Keys::PAYMENT_ENABLED ] ) ? 'yes' : '' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in save().
		$pm_mode = isset( $_POST[ Keys::PAYMENT_MODE ] ) ? sanitize_key( wp_unslash( $_POST[ Keys::PAYMENT_MODE ] ) ) : 'allow';
		update_post_meta( $post_id, Keys::PAYMENT_MODE, 'disallow' === $pm_mode ? 'disallow' : 'allow' );
		$this->save_key_list( $post_id, Keys::PAYMENT_METHODS );
		$this->save_text( $post_id, Keys::PAYMENT_MSG );

		// Day-of-week / time-of-day window.
		update_post_meta( $post_id, Keys::DAYTIME_ENABLED, isset( $_POST[ Keys::DAYTIME_ENABLED ] ) ? 'yes' : '' );
		$this->save_days( $post_id );
		$this->save_time( $post_id, Keys::DAYTIME_START );
		$this->save_time( $post_id, Keys::DAYTIME_END );
		$this->save_text( $post_id, Keys::DAYTIME_MSG );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	private function save_days( int $post_id ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified in save(); each element is cast to int and range-checked (0–6) below.
		$raw  = ( isset( $_POST[ Keys::DAYTIME_DAYS ] ) && is_array( $_POST[ Keys::DAYTIME_DAYS ] ) ) ? wp_unslash( $_POST[ Keys::DAYTIME_DAYS ] ) : array();
		$days = array();
		foreach ( $raw as $d ) {
			$n = (int) $d;
			if ( $n >= 0 && $n <= 6 && ! in_array( $n, $days, true ) ) {
				$days[] = $n;
			}
		}
		sort( $days );
		if ( array() === $days ) {
			delete_post_meta( $post_id, Keys::DAYTIME_DAYS );
		} else {
			update_post_meta( $post_id, Keys::DAYTIME_DAYS, $days );
		}
	}

	private function save_time( int $post_id, string $key ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in save().
		$raw = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
		if ( null === Validator::hhmm_to_min( $raw ) ) {
			delete_post_meta( $post_id, $key );
		} else {
			update_post_meta( $post_id, $key, $raw );
		}
	}

	private function save_id_list( int $post_id, string $key ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified in save(); every element is cast through absint on the next line.
		$raw = ( isset( $_POST[ $key ] ) && is_array( $_POST[ $key ] ) ) ? wp_unslash( $_POST[ $key ] ) : array();
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $raw ) ) ) );
		if ( array() === $ids ) {
			delete_post_meta( $post_id, $key );
		} else {
			update_post_meta( $post_id, $key, $ids );
		}
	}

	private function save_code_list( int $post_id, string $key ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified in save(); each element is reduced to an uppercase 2-letter code below.
		$raw   = ( isset( $_POST[ $key ] ) && is_array( $_POST[ $key ] ) ) ? wp_unslash( $_POST[ $key ] ) : array();
		$codes = array();
		foreach ( $raw as $v ) {
			$code = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $v ) ?? '' );
			if ( 2 === strlen( $code ) && ! in_array( $code, $codes, true ) ) {
				$codes[] = $code;
			}
		}
		if ( array() === $codes ) {
			delete_post_meta( $post_id, $key );
		} else {
			update_post_meta( $post_id, $key, $codes );
		}
	}

	private function save_key_list( int $post_id, string $key ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified in save(); every element runs through sanitize_key below.
		$raw  = ( isset( $_POST[ $key ] ) && is_array( $_POST[ $key ] ) ) ? wp_unslash( $_POST[ $key ] ) : array();
		$keys = array_values( array_unique( array_filter( array_map( 'sanitize_key', $raw ) ) ) );
		if ( array() === $keys ) {
			delete_post_meta( $post_id, $key );
		} else {
			update_post_meta( $post_id, $key, $keys );
		}
	}

	private function save_mode( int $post_id, string $key ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in save().
		$raw = isset( $_POST[ $key ] ) ? sanitize_key( wp_unslash( $_POST[ $key ] ) ) : 'any';
		update_post_meta( $post_id, $key, 'all' === $raw ? 'all' : 'any' );
	}

	private function save_int( int $post_id, string $key ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in save().
		$raw = isset( $_POST[ $key ] ) ? trim( sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) ) : '';
		if ( '' === $raw ) {
			delete_post_meta( $post_id, $key );
		} else {
			update_post_meta( $post_id, $key, (string) max( 0, (int) $raw ) );
		}
	}

	private function save_price( int $post_id, string $key ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in save().
		$raw = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
		if ( '' === trim( $raw ) ) {
			delete_post_meta( $post_id, $key );
		} else {
			update_post_meta( $post_id, $key, wc_format_decimal( $raw ) );
		}
	}

	private function save_text( int $post_id, string $key ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in save().
		$value = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
		if ( '' === $value ) {
			delete_post_meta( $post_id, $key );
		} else {
			update_post_meta( $post_id, $key, $value );
		}
	}

	/**
	 * Normalize the datetime-local value to 'Y-m-d H:i:s' wall-clock (site tz) via the
	 * shared SiteTime parser — single source of truth with the REST/AI write path.
	 */
	private function normalize_datetime( string $raw ): string {
		return \MoksaWeb\Moforcoupon\Support\SiteTime::normalize( $raw );
	}
}
