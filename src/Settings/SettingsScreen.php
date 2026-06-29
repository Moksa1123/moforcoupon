<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Settings;

use MoksaWeb\Moforcoupon\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * The plugin's OWN settings screen — a card-styled page under the "Moksa 優惠券" menu,
 * NOT a tab buried under WooCommerce → Settings. Renders the module toggles grouped
 * into cards (matching the dashboard / templates look) and saves through our own
 * admin-post handler. The field ids ARE the option keys, so writes go straight to the
 * same `moforcoupon_*` options every module reads — no schema indirection.
 *
 * Always-on (registered by the core Plugin) so it stays reachable even when the
 * AdminMenu module is off — otherwise you could never reach the toggle that turns the
 * independent menu on. When AdminMenu is on, Menu.php renders this same screen as a
 * submenu of the top-level menu; when off, register_fallback() puts it under WooCommerce.
 */
final class SettingsScreen {

	public const SLUG   = 'moforcoupon-settings';
	private const CAP   = 'manage_woocommerce';
	private const NONCE = 'moforcoupon_save_settings';
	public const ACTION = 'moforcoupon_save_settings';

	/** @var array<string,string> redirect query flag => notice text */
	private const NOTICES = array(
		'1' => '設定已儲存。',
	);

	public static function slug(): string {
		return self::SLUG;
	}

	public static function url(): string {
		return admin_url( 'admin.php?page=' . self::SLUG );
	}

	/** Fallback menu placement when the independent AdminMenu module is off. */
	public static function register_fallback(): void {
		add_submenu_page(
			'woocommerce',
			__( '優惠券設定', 'moforcoupon' ),
			__( '優惠券設定', 'moforcoupon' ),
			self::CAP,
			self::SLUG,
			array( self::class, 'render' )
		);
	}

	/**
	 * Grouped setting definitions — single source for both render and save. Each field:
	 * id (option key) / type (checkbox|text|select) / default / title / desc / options.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function groups(): array {
		return array(
			array(
				'title'  => __( '核心優惠功能', 'moforcoupon' ),
				'desc'   => __( '優惠券的折扣機制與結帳驗證。每項預設關閉,需要才開。', 'moforcoupon' ),
				'fields' => array(
					self::toggle( 'moforcoupon_conditions_enabled', __( '優惠券條件', 'moforcoupon' ), __( '在優惠券編輯頁加入排程、角色限制、購物車最低、商品 / 分類、收件地區、付款方式、星期時段等條件,並於結帳時強制驗證。', 'moforcoupon' ) ),
					self::toggle( 'moforcoupon_advrules_enabled', __( '進階規則(AND/OR)', 'moforcoupon' ), __( '用群組與 AND/OR 自由組合條件的進階規則建構器(小計 / 件數 / 商品 / 分類 / 地區 / 付款 / 角色 / 星期 / 時間),在單項條件之外額外把關。', 'moforcoupon' ) ),
					self::toggle( 'moforcoupon_discountcap_enabled', __( '折扣上限', 'moforcoupon' ), __( '為百分比折扣優惠券設定最高折抵金額(例:打 8 折,但最多折 500)。', 'moforcoupon' ) ),
					self::toggle( 'moforcoupon_discounttiers_enabled', __( '階梯折扣', 'moforcoupon' ), __( '同一張百分比優惠券依購物車門檻(小計 / 件數)給不同折扣(例:未滿 1000 折 10%、滿 1000 折 20%),可限定商品 / 分類。', 'moforcoupon' ) ),
					self::toggle( 'moforcoupon_shipping_enabled', __( '運費覆寫', 'moforcoupon' ), __( '套用優惠券時改寫運費:免運、運費百分比折扣或固定折扣(覆寫所有運送方式)。', 'moforcoupon' ) ),
					self::toggle( 'moforcoupon_freegift_enabled', __( '加贈品 / 免費贈品', 'moforcoupon' ), __( '套用優惠券時自動把指定贈品加入購物車(免費或折扣),顧客無法改數量或移除。', 'moforcoupon' ) ),
					self::toggle( 'moforcoupon_bogo_enabled', __( '買 X 送 Y(BOGO)', 'moforcoupon' ), __( '新增「買 X 送 Y」折扣型別:買指定商品 / 分類達數量,贈品享免費 / 折扣。可用自然語言建立。', 'moforcoupon' ) ),
					self::toggle( 'moforcoupon_stacking_enabled', __( '疊加控制', 'moforcoupon' ), __( '控制優惠券能否與其他券並用:互斥券、允許 / 禁止並用的券碼清單,結帳時強制驗證。', 'moforcoupon' ) ),
					self::toggle( 'moforcoupon_autoapply_enabled', __( '自動套用優惠券', 'moforcoupon' ), __( '勾選「自動套用」的優惠券,顧客購物車一符合條件就自動帶入,免輸入代碼。', 'moforcoupon' ) ),
					self::toggle( 'moforcoupon_cashback_enabled', __( '回饋金 / 點數(Cashback)', 'moforcoupon' ), __( '新增「回饋金」折扣型別:訂單付款後依百分比 / 固定金額回饋,透過事件交給錢包 / 點數系統(可搭配下方「儲值金錢包」)。', 'moforcoupon' ) ),
					self::toggle( 'moforcoupon_storecredit_enabled', __( '儲值金 / 回饋金錢包', 'moforcoupon' ), __( '把回饋金記成顧客的儲值金餘額,結帳時自動折抵下次訂單,並在會員中心顯示餘額。需搭配上方「回饋金」型別。', 'moforcoupon' ) ),
				),
			),
			array(
				'title'  => __( '網址 / QR 套用', 'moforcoupon' ),
				'desc'   => __( '用專屬連結、QR 或查詢字串讓顧客一鍵套用優惠券。', 'moforcoupon' ),
				'fields' => array(
					self::toggle( 'moforcoupon_url_enabled', __( '優惠券網址 / QR', 'moforcoupon' ), __( '為優惠券產生專屬連結 /coupon/代碼 與伺服器端 QR Code,顧客點擊或掃描即自動套用。', 'moforcoupon' ) ),
					array(
						'id'       => 'moforcoupon_url_endpoint',
						'type'     => 'text',
						'default'  => 'coupon',
						'sanitize' => 'slug',
						'title'    => __( '優惠券網址路徑', 'moforcoupon' ),
						'desc'     => __( '專屬連結的路徑前段,例如 coupon → /coupon/代碼。變更後會重整永久連結。', 'moforcoupon' ),
					),
					self::toggle( 'moforcoupon_url_query_enabled', __( '允許以查詢字串套用', 'moforcoupon' ), __( '允許在任何頁面網址加上 ?coupon=代碼 來套用優惠券。預設關閉。', 'moforcoupon' ) ),
					array(
						'id'      => 'moforcoupon_url_query_redirect',
						'type'    => 'select',
						'default' => 'same_page',
						'title'   => __( '查詢字串套用後導向', 'moforcoupon' ),
						'desc'    => __( '以 ?coupon= 套用成功後要把顧客導向哪裡。', 'moforcoupon' ),
						'options' => array(
							'same_page' => __( '原頁面', 'moforcoupon' ),
							'cart'      => __( '購物車', 'moforcoupon' ),
							'checkout'  => __( '結帳頁', 'moforcoupon' ),
						),
					),
				),
			),
			array(
				'title'  => __( '後台介面與呈現', 'moforcoupon' ),
				'desc'   => __( '管理選單、範本、報表與優惠券編輯頁的版面呈現。', 'moforcoupon' ),
				'fields' => array(
					self::toggle( 'moforcoupon_adminmenu_enabled', __( '獨立優惠券管理選單', 'moforcoupon' ), __( '把優惠券管理收進獨立頂層選單「Moksa 優惠券」(全部券 / 新增 / 報表 / 設定),不再埋在 WooCommerce 之下。', 'moforcoupon' ) ),
					self::toggle( 'moforcoupon_templates_enabled', __( '優惠券範本', 'moforcoupon' ), __( '新增「優惠券範本」頁,提供預設好的優惠券範本(新客首購 / 滿額折抵 / 免運 / 買二送一…)一鍵建立草稿券。', 'moforcoupon' ) ),
					self::toggle( 'moforcoupon_reports_enabled', __( '優惠券報表', 'moforcoupon' ), __( '統計每張券的使用訂單數與折抵總額(啟用獨立選單時內嵌於儀表板)。', 'moforcoupon' ) ),
					self::toggle( 'moforcoupon_tabicons_enabled', __( '優惠券設定圖示', 'moforcoupon' ), __( '為優惠券編輯頁的各設定區塊(排程 / 角色 / 購物車 / 運費覆寫…)加上統一風格的單色圖示,分頁與 metabox 兩種呈現皆適用。', 'moforcoupon' ) ),
					self::toggle( 'moforcoupon_metaboxes_enabled', __( '獨立設定 Metabox', 'moforcoupon' ), __( '把優惠券各設定區塊(條件 / BOGO / 贈品 / 運費 / 疊加 / 網址 / 前台)集中成一個 WC 原生風格的分頁式 metabox。', 'moforcoupon' ) ),
					self::toggle( 'moforcoupon_couponlist_enabled', __( '優惠券列表工具', 'moforcoupon' ), __( '在優惠券列表加入啟用 / 停用狀態欄、批次啟停與一鍵複製等管理工具。', 'moforcoupon' ) ),
					self::toggle( 'moforcoupon_importexport_enabled', __( '匯入 / 匯出(CSV)', 'moforcoupon' ), __( '以 CSV 匯入 / 匯出優惠券(含階梯、進階規則 JSON 與行銷活動),便於備份、稽核與批次編輯。', 'moforcoupon' ) ),
					self::toggle( 'moforcoupon_summary_enabled', __( '編輯頁即時摘要', 'moforcoupon' ), __( '在優惠券編輯頁顯示即時摘要 metabox:折扣機制、已啟用功能與衝突警告,隨輸入即時更新。', 'moforcoupon' ) ),
				),
			),
			array(
				'title'  => __( '前台與寄送', 'moforcoupon' ),
				'desc'   => __( '在前台呈現可用優惠券,或把優惠券寄送給顧客。', 'moforcoupon' ),
				'fields' => array(
					self::toggle( 'moforcoupon_frontend_enabled', __( '前台優惠券顯示', 'moforcoupon' ), __( '提供 [moforcoupon_coupons] 短代碼,在前台以卡片顯示商家勾選的可用優惠券(含複製與套用)。', 'moforcoupon' ) ),
					self::toggle( 'moforcoupon_savings_enabled', __( '購物車省下金額提示', 'moforcoupon' ), __( '在購物車 / 結帳顯示「您總共省了 NT$X」的提示,強化折扣感受。', 'moforcoupon' ) ),
					self::toggle( 'moforcoupon_nudge_enabled', __( '免運門檻提示', 'moforcoupon' ), __( '在購物車 / 結帳顯示「再買 NT$X 即可免運」,推動客單價(讀取店家設定的免運門檻)。', 'moforcoupon' ) ),
					self::toggle( 'moforcoupon_send_enabled', __( '優惠券寄送', 'moforcoupon' ), __( '新增「寄送優惠券」能力:用自然語言把券寄給顧客 Email,可鎖定只限該 Email 使用。', 'moforcoupon' ) ),
					self::toggle( 'moforcoupon_myaccount_enabled', __( '會員中心優惠券', 'moforcoupon' ), __( '在 WooCommerce「我的帳戶」加一個「我的優惠券」分頁,列出發放給該顧客(綁定其帳號或 Email)的專屬優惠券,可複製或一鍵套用。', 'moforcoupon' ) ),
				),
			),
			array(
				'title'  => __( '再行銷 / 回購券', 'moforcoupon' ),
				'desc'   => __( '訂單完成後,自動把一張「範本優惠券」複製成顧客專屬券(沿用範本的折扣與所有條件),放進顧客的會員中心並可寄出,鼓勵回購。需同時啟用上方「會員中心優惠券」才看得到。', 'moforcoupon' ),
				'fields' => array(
					self::toggle( 'moforcoupon_remarketing_enabled', __( '啟用訂單完成後自動發券', 'moforcoupon' ), __( '訂單狀態變為「已完成」時,依下列設定發放回購券。', 'moforcoupon' ) ),
					array(
						'id'      => 'moforcoupon_remarketing_source',
						'type'    => 'text',
						'default' => '',
						'title'   => __( '範本優惠券代碼', 'moforcoupon' ),
						'desc'    => __( '先建立一張正常的優惠券當「範本」(任何券型 / 條件都可),把它的代碼填這裡。系統會在訂單完成後複製它成為顧客專屬的唯一券。建議範本設定「每位顧客限用一次」。', 'moforcoupon' ),
					),
					array(
						'id'      => 'moforcoupon_remarketing_condition',
						'type'    => 'select',
						'default' => 'all',
						'title'   => __( '發放條件', 'moforcoupon' ),
						'desc'    => __( '哪些已完成訂單要發券。', 'moforcoupon' ),
						'options' => array(
							'all'         => __( '每筆已完成訂單', 'moforcoupon' ),
							'first_order' => __( '僅顧客首筆訂單(需登入顧客)', 'moforcoupon' ),
							'min_total'   => __( '訂單金額達門檻', 'moforcoupon' ),
						),
					),
					array(
						'id'      => 'moforcoupon_remarketing_min_total',
						'type'    => 'text',
						'default' => '0',
						'title'   => __( '訂單金額門檻', 'moforcoupon' ),
						'desc'    => __( '發放條件選「達門檻」時生效:訂單總額需 ≥ 此金額。', 'moforcoupon' ),
					),
					array(
						'id'      => 'moforcoupon_remarketing_expiry_days',
						'type'    => 'text',
						'default' => '30',
						'title'   => __( '回購券有效天數', 'moforcoupon' ),
						'desc'    => __( '發出後幾天內有效(自發放當下起算)。填 0 = 沿用範本本身的到期設定。', 'moforcoupon' ),
					),
					self::toggle( 'moforcoupon_remarketing_email', __( '同時 Email 通知顧客', 'moforcoupon' ), __( '發券時一併寄送含代碼與一鍵套用連結的 Email 給顧客(需站台郵件設定正常)。', 'moforcoupon' ) ),
					self::toggle( 'moforcoupon_expiry_enabled', __( '優惠券到期提醒', 'moforcoupon' ), __( '每天自動檢查顧客的專屬優惠券,即將到期時 Email 提醒,鼓勵在期限前使用。', 'moforcoupon' ) ),
					array(
						'id'      => 'moforcoupon_expiry_days',
						'type'    => 'text',
						'default' => '3',
						'title'   => __( '到期前幾天提醒', 'moforcoupon' ),
						'desc'    => __( '在優惠券到期日的前幾天寄出提醒(1–60,預設 3)。', 'moforcoupon' ),
					),
				),
			),
			array(
				'title'  => __( '生日優惠券', 'moforcoupon' ),
				'desc'   => __( '顧客在「帳戶詳細資料」填寫生日後,生日當天自動發放一張專屬優惠券(複製指定範本券),每年一次。需同時啟用「會員中心優惠券」。', 'moforcoupon' ),
				'fields' => array(
					self::toggle( 'moforcoupon_birthday_enabled', __( '啟用生日優惠券', 'moforcoupon' ), __( '在帳戶頁加入生日欄位,並於顧客生日當天自動發券。', 'moforcoupon' ) ),
					array(
						'id'      => 'moforcoupon_birthday_template',
						'type'    => 'text',
						'default' => '',
						'title'   => __( '生日券範本代碼', 'moforcoupon' ),
						'desc'    => __( '生日當天要發放的範本優惠券代碼(會複製成顧客專屬券)。', 'moforcoupon' ),
					),
					array(
						'id'      => 'moforcoupon_birthday_expiry_days',
						'type'    => 'text',
						'default' => '30',
						'title'   => __( '生日券有效天數', 'moforcoupon' ),
						'desc'    => __( '發出後幾天內有效(填 0 = 沿用範本本身的到期設定)。', 'moforcoupon' ),
					),
				),
			),
			array(
				'title'  => __( '推薦好友(Referral)', 'moforcoupon' ),
				'desc'   => __( '顧客分享專屬連結,朋友完成首次訂單後,雙方各得一張回饋券(複製指定的範本券)。需同時啟用「會員中心優惠券」才看得到券。', 'moforcoupon' ),
				'fields' => array(
					self::toggle( 'moforcoupon_referral_enabled', __( '啟用推薦好友券', 'moforcoupon' ), __( '在會員中心提供專屬推薦連結,並於被推薦的朋友完成訂單後發放回饋券。', 'moforcoupon' ) ),
					array(
						'id'      => 'moforcoupon_referral_referrer_template',
						'type'    => 'text',
						'default' => '',
						'title'   => __( '推薦人回饋範本代碼', 'moforcoupon' ),
						'desc'    => __( '朋友完成訂單後,要發給「推薦人」的範本優惠券代碼(會複製成推薦人專屬券)。', 'moforcoupon' ),
					),
					array(
						'id'      => 'moforcoupon_referral_friend_template',
						'type'    => 'text',
						'default' => '',
						'title'   => __( '好友歡迎範本代碼(選填)', 'moforcoupon' ),
						'desc'    => __( '同時要發給「被推薦的朋友」的範本優惠券代碼;留空則只回饋推薦人。', 'moforcoupon' ),
					),
					array(
						'id'      => 'moforcoupon_referral_expiry_days',
						'type'    => 'text',
						'default' => '30',
						'title'   => __( '回饋券有效天數', 'moforcoupon' ),
						'desc'    => __( '發出後幾天內有效(填 0 = 沿用範本本身的到期設定)。', 'moforcoupon' ),
					),
				),
			),
			array(
				'title'  => __( 'AI 與對外開放(MCP)', 'moforcoupon' ),
				'desc'   => __( '自然語言助手與對外 MCP 介接。預設全部關閉、唯讀優先。', 'moforcoupon' ),
				'fields' => array(
					self::toggle( 'moforcoupon_ai_enabled', __( 'AI 優惠券助手', 'moforcoupon' ), __( '在後台用自然語言建立 / 查詢優惠券(需 WordPress 7.0+ 的 AI Client 與已設定的 Connector)。', 'moforcoupon' ) ),
					self::toggle( 'moforcoupon_mcp_server_enabled', __( '對外 MCP 伺服器', 'moforcoupon' ), __( '把優惠券能力開放給外部 AI 工具(MCP)。預設關閉。', 'moforcoupon' ) ),
					self::toggle( 'moforcoupon_mcp_expose_destructive', __( '允許外部 MCP 執行變更', 'moforcoupon' ), __( '允許破壞性能力(建立 / 更新 / 刪除優惠券)暴露給外部 MCP。預設關閉(僅唯讀)。', 'moforcoupon' ) ),
				),
			),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function toggle( string $id, string $title, string $desc ): array {
		return array(
			'id'      => $id,
			'type'    => 'checkbox',
			'default' => 'no',
			'title'   => $title,
			'desc'    => $desc,
		);
	}

	public static function render(): void {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}

		echo '<div class="wrap moforcoupon-settings-screen">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( '優惠券設定', 'moforcoupon' ) . '</h1>';
		echo '<hr class="wp-header-end">';
		echo '<p class="description">' . esc_html__( '啟用 / 關閉各項優惠券功能模組。每項預設關閉,唯讀優先。', 'moforcoupon' ) . '</p>';

		$flag = isset( $_GET['moforcoupon_saved'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['moforcoupon_saved'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( self::NOTICES[ $flag ] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( '設定已儲存。', 'moforcoupon' ) . '</p></div>';
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">';
		wp_nonce_field( self::NONCE );

		echo '<div class="moforcoupon-set-grid">';
		foreach ( self::groups() as $group ) {
			self::group_card( $group );
		}
		echo '</div>';

		echo '<p class="submit"><button type="submit" class="button button-primary button-hero">'
			. esc_html__( '儲存設定', 'moforcoupon' ) . '</button></p>';
		echo '</form>';

		self::styles();
		echo '</div>';
	}

	/**
	 * @param array<string,mixed> $group
	 */
	private static function group_card( array $group ): void {
		echo '<section class="moforcoupon-set-card">';
		echo '<h2>' . esc_html( (string) ( $group['title'] ?? '' ) ) . '</h2>';
		if ( ! empty( $group['desc'] ) ) {
			echo '<p class="card-desc">' . esc_html( (string) $group['desc'] ) . '</p>';
		}
		$fields = is_array( $group['fields'] ?? null ) ? $group['fields'] : array();
		foreach ( $fields as $field ) {
			self::field_row( $field );
		}
		echo '</section>';
	}

	/**
	 * @param array<string,mixed> $field
	 */
	private static function field_row( array $field ): void {
		$id    = (string) ( $field['id'] ?? '' );
		$type  = (string) ( $field['type'] ?? 'checkbox' );
		$title = (string) ( $field['title'] ?? '' );
		$desc  = (string) ( $field['desc'] ?? '' );
		$value = get_option( $id, $field['default'] ?? '' );

		echo '<div class="set-row set-row-' . esc_attr( $type ) . '">';

		if ( 'checkbox' === $type ) {
			$checked = 'yes' === $value;
			echo '<label class="set-toggle">';
			echo '<input type="checkbox" name="' . esc_attr( $id ) . '" value="yes"' . checked( $checked, true, false ) . '>';
			echo '<span class="set-label"><span class="set-title">' . esc_html( $title ) . '</span>';
			echo '<span class="set-desc">' . esc_html( $desc ) . '</span></span>';
			echo '</label>';
		} elseif ( 'select' === $type ) {
			echo '<label class="set-field"><span class="set-title">' . esc_html( $title ) . '</span>';
			echo '<span class="set-desc">' . esc_html( $desc ) . '</span>';
			echo '<select name="' . esc_attr( $id ) . '">';
			$options = is_array( $field['options'] ?? null ) ? $field['options'] : array();
			foreach ( $options as $opt_val => $opt_label ) {
				echo '<option value="' . esc_attr( (string) $opt_val ) . '"' . selected( (string) $value, (string) $opt_val, false ) . '>'
					. esc_html( (string) $opt_label ) . '</option>';
			}
			echo '</select></label>';
		} else { // text
			echo '<label class="set-field"><span class="set-title">' . esc_html( $title ) . '</span>';
			echo '<span class="set-desc">' . esc_html( $desc ) . '</span>';
			echo '<input type="text" class="regular-text" name="' . esc_attr( $id ) . '" value="' . esc_attr( (string) $value ) . '">';
			echo '</label>';
		}

		echo '</div>';
	}

	/** admin_post handler: verify, whitelist-save every known field, redirect with notice. */
	public static function handle(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( '權限不足。', 'moforcoupon' ) );
		}
		check_admin_referer( self::NONCE );

		foreach ( self::groups() as $group ) {
			$fields = is_array( $group['fields'] ?? null ) ? $group['fields'] : array();
			foreach ( $fields as $field ) {
				self::save_field( $field );
			}
		}

		wp_safe_redirect( add_query_arg( 'moforcoupon_saved', '1', self::url() ) );
		exit;
	}

	/**
	 * @param array<string,mixed> $field
	 */
	private static function save_field( array $field ): void {
		$id   = (string) ( $field['id'] ?? '' );
		$type = (string) ( $field['type'] ?? 'checkbox' );
		if ( '' === $id ) {
			return;
		}

		// The nonce is verified in handle() before this runs; phpcs can't follow that
		// across method boundaries, so the NonceVerification ignores below are intentional.
		if ( 'checkbox' === $type ) {
			// Unchecked boxes are absent from POST → store 'no'.
			$checked = isset( $_POST[ $id ] ) ? sanitize_text_field( wp_unslash( $_POST[ $id ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			update_option( $id, 'yes' === $checked ? 'yes' : 'no' );
			return;
		}

		$raw = isset( $_POST[ $id ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ $id ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( 'select' === $type ) {
			$options = is_array( $field['options'] ?? null ) ? $field['options'] : array();
			$clean   = array_key_exists( $raw, $options ) ? $raw : (string) ( $field['default'] ?? '' );
			update_option( $id, $clean );
			return;
		}

		// text
		$clean = ( 'slug' === ( $field['sanitize'] ?? '' ) ) ? sanitize_title( $raw ) : sanitize_text_field( $raw );
		if ( '' === $clean ) {
			$clean = (string) ( $field['default'] ?? '' );
		}
		update_option( $id, $clean );
	}

	private static function styles(): void {
		echo '<style>'
			. '.moforcoupon-set-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(420px,1fr));gap:18px;margin:16px 0 8px}'
			. '.moforcoupon-set-card{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:18px 22px}'
			. '.moforcoupon-set-card h2{font-size:15px;margin:0 0 4px;color:#1d2327}'
			. '.moforcoupon-set-card .card-desc{color:#646970;font-size:13px;margin:0 0 12px;padding-bottom:10px;border-bottom:1px solid #f0f0f1}'
			. '.moforcoupon-set-card .set-row{padding:9px 0;border-bottom:1px solid #f6f7f7}'
			. '.moforcoupon-set-card .set-row:last-child{border-bottom:0}'
			. '.set-toggle{display:flex;gap:10px;align-items:flex-start;cursor:pointer}'
			. '.set-toggle input{margin-top:3px}'
			. '.set-label,.set-field{display:flex;flex-direction:column}'
			. '.set-title{font-weight:600;color:#1d2327}'
			. '.set-desc{color:#646970;font-size:12px;line-height:1.5;margin-top:2px}'
			. '.set-field{gap:4px}.set-field input,.set-field select{margin-top:4px;max-width:320px}'
			. '</style>';
	}
}
