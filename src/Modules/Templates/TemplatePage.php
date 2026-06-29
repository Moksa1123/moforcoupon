<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\Templates;

use MoksaWeb\Moforcoupon\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * "優惠券範本" admin page, modelled on the Advanced Coupons templates UX: a single fast
 * client-rendered screen with a "最近使用" strip, a left category sidebar that filters
 * the card grid with no reload, and a "先預填、可微調再建立" quick-configure modal (code /
 * amount / expiry) — so you tune the key values up front instead of digging into the
 * editor afterwards. Apply posts to admin-post.php, creates a draft coupon, and
 * redirects to its editor.
 */
final class TemplatePage {

	private const SLUG        = 'moforcoupon-templates';
	private const CAP         = 'edit_shop_coupons';
	private const NONCE       = 'moforcoupon_apply_template';
	private const ACTION      = 'moforcoupon_apply_template';
	private const RECENT_META = 'moforcoupon_recent_templates';
	private const RECENT_MAX  = 4;

	/** Public accessor so the AdminMenu module can reparent this page. */
	public static function slug(): string {
		return self::SLUG;
	}

	/** Legacy registration under WooCommerce (used only when AdminMenu is off). */
	public static function register(): void {
		add_submenu_page(
			'woocommerce',
			__( '優惠券範本', 'moforcoupon' ),
			__( '優惠券範本', 'moforcoupon' ),
			self::CAP,
			self::SLUG,
			array( self::class, 'render' )
		);
	}

	public static function render(): void {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}

		$notice = isset( $_GET['moforcoupon_tpl_error'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			? sanitize_text_field( wp_unslash( (string) $_GET['moforcoupon_tpl_error'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			: '';

		$all    = Catalog::all();
		$by_cat = array();
		foreach ( $all as $tpl ) {
			$cat              = (string) ( $tpl['category'] ?? 'other' );
			$by_cat[ $cat ][] = $tpl;
		}
		$recent = self::recent_templates();

		echo '<div class="wrap moforcoupon-templates">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( '優惠券範本', 'moforcoupon' ) . '</h1>';
		echo '<hr class="wp-header-end">';
		echo '<p class="description">' . esc_html__( '挑一個範本即可快速建立優惠券。點「套用」可先微調代碼、折扣值與到期日,再一鍵建立草稿券。', 'moforcoupon' ) . '</p>';

		if ( '' !== $notice ) {
			echo '<div class="notice notice-error is-dismissible" role="alert"><p>' . esc_html( $notice ) . '</p></div>';
		}

		// ── 最近使用 ───────────────────────────────────────────────────
		if ( ! empty( $recent ) ) {
			echo '<section class="moforcoupon-tpl-recent">';
			echo '<h2 class="moforcoupon-tpl-h2">' . esc_html__( '最近使用', 'moforcoupon' ) . '</h2>';
			echo '<div class="moforcoupon-tpl-grid">';
			foreach ( $recent as $tpl ) {
				self::card( $tpl );
			}
			echo '</div>';
			echo '</section>';
		}

		// ── 可用範本:左分類側欄 + 右卡片 ──────────────────────────────
		echo '<section class="moforcoupon-tpl-available">';
		echo '<h2 class="moforcoupon-tpl-h2">' . esc_html__( '可用範本', 'moforcoupon' ) . '</h2>';
		echo '<div class="moforcoupon-tpl-layout">';

		self::sidebar( $by_cat, count( $all ) );

		echo '<div class="moforcoupon-tpl-main">';
		echo '<div class="moforcoupon-tpl-search" style="margin:0 0 14px;">'
			. '<input type="search" class="regular-text" style="width:100%;max-width:420px;" placeholder="'
			. esc_attr__( '搜尋範本(名稱 / 說明)…', 'moforcoupon' ) . '" aria-label="'
			. esc_attr__( '搜尋範本', 'moforcoupon' ) . '"></div>';
		echo '<div class="moforcoupon-tpl-grid">';
		foreach ( $all as $tpl ) {
			self::card( $tpl );
		}
		echo '</div>';
		echo '<p class="moforcoupon-tpl-empty" hidden>' . esc_html__( '找不到符合的範本。', 'moforcoupon' ) . '</p>';
		echo '</div>'; // .moforcoupon-tpl-main

		echo '</div>'; // .moforcoupon-tpl-layout
		echo '</section>';

		self::modal();
		self::styles();
		self::script();

		echo '</div>'; // .wrap
	}

	/**
	 * Left category sidebar (AC-style). Filtering is client-side via data-cat, so picking
	 * a category never reloads the page.
	 *
	 * @param array<string,array<int,array<string,mixed>>> $by_cat
	 */
	private static function sidebar( array $by_cat, int $total ): void {
		echo '<aside class="moforcoupon-tpl-cats">';
		echo '<ul>';
		echo '<li><a href="#" class="current" data-filter="all">'
			. esc_html__( '全部', 'moforcoupon' )
			. ' <span class="count">' . esc_html( (string) $total ) . '</span></a></li>';

		foreach ( Catalog::categories() as $cat_key => $cat_label ) {
			$items = $by_cat[ $cat_key ] ?? array();
			if ( empty( $items ) ) {
				continue;
			}
			echo '<li><a href="#" data-filter="' . esc_attr( $cat_key ) . '">'
				. esc_html( $cat_label )
				. ' <span class="count">' . esc_html( (string) count( $items ) ) . '</span></a></li>';
		}
		echo '</ul>';
		echo '</aside>';
	}

	/**
	 * @param array<string,mixed> $tpl
	 */
	private static function card( array $tpl ): void {
		$type_key = (string) ( $tpl['type_key'] ?? 'other' );
		$cat      = (string) ( $tpl['category'] ?? 'other' );
		$missing  = array();
		foreach ( Catalog::required_modules( $tpl ) as $slug ) {
			if ( ! Plugin::instance()->modules()->is_enabled( $slug ) ) {
				$missing[] = Catalog::module_label( $slug );
			}
		}
		$blocked = ! empty( $missing );

		$native      = is_array( $tpl['native'] ?? null ) ? $tpl['native'] : array();
		$amount      = (float) ( $native['amount'] ?? 0 );
		$is_native   = in_array( $type_key, array( 'percent', 'fixed_cart', 'fixed_product' ), true );
		$amount_ed   = $is_native && $amount > 0; // only show amount field where the value IS the amount.
		$unit        = ( 'percent' === $type_key ) ? '%' : get_woocommerce_currency_symbol();
		$usage_limit = isset( $native['usage_limit'] ) ? (string) (int) $native['usage_limit'] : '';
		$usage_pu    = isset( $native['usage_limit_per_user'] ) ? (string) (int) $native['usage_limit_per_user'] : '';
		$individual  = empty( $native['individual_use'] ) ? '0' : '1';
		$description = (string) ( $native['description'] ?? '' );

		$search = strtolower(
			trim(
				(string) ( $tpl['label'] ?? '' ) . ' '
				. (string) ( $tpl['desc'] ?? '' ) . ' '
				. (string) ( $tpl['id'] ?? '' ) . ' '
				. self::type_label( $type_key )
			)
		);
		echo '<div class="moforcoupon-tpl-card" data-cat="' . esc_attr( $cat ) . '" data-search="' . esc_attr( $search ) . '">';
		echo '<span class="badge">' . esc_html( self::type_label( $type_key ) ) . '</span>';
		echo '<h3>' . esc_html( (string) ( $tpl['label'] ?? '' ) ) . '</h3>';
		echo '<div class="d">' . esc_html( (string) ( $tpl['desc'] ?? '' ) ) . '</div>';

		if ( $blocked ) {
			echo '<p class="req">' . esc_html(
				sprintf(
					/* translators: %s: comma-separated required module labels. */
					__( '需先啟用「%s」模組', 'moforcoupon' ),
					implode( '、', $missing )
				)
			) . '</p>';
			echo '<button class="button" disabled>' . esc_html__( '套用此範本', 'moforcoupon' ) . '</button>';
		} else {
			printf(
				'<button type="button" class="button button-primary moforcoupon-tpl-apply"'
					. ' data-id="%1$s" data-label="%2$s" data-prefix="%3$s"'
					. ' data-amount="%4$s" data-amount-editable="%5$s" data-unit="%6$s"'
					. ' data-usage-limit="%7$s" data-usage-pu="%8$s" data-individual="%9$s" data-description="%10$s">%11$s</button>',
				esc_attr( (string) ( $tpl['id'] ?? '' ) ),
				esc_attr( (string) ( $tpl['label'] ?? '' ) ),
				esc_attr( (string) ( $tpl['prefix'] ?? '' ) ),
				esc_attr( (string) $amount ),
				esc_attr( $amount_ed ? '1' : '0' ),
				esc_attr( $unit ),
				esc_attr( $usage_limit ),
				esc_attr( $usage_pu ),
				esc_attr( $individual ),
				esc_attr( $description ),
				esc_html__( '套用此範本', 'moforcoupon' )
			);
		}

		echo '</div>';
	}

	/** Shared quick-configure modal — one per page, populated by JS from the clicked card. */
	private static function modal(): void {
		echo '<div class="moforcoupon-tpl-modal" hidden>';
		echo '<div class="moforcoupon-tpl-backdrop"></div>';
		echo '<div class="moforcoupon-tpl-dialog" role="dialog" aria-modal="true" aria-labelledby="moforcoupon-tpl-modal-title">';
		echo '<h2 id="moforcoupon-tpl-modal-title"></h2>';
		echo '<p class="description">' . esc_html__( '可先調整以下欄位,再建立優惠券草稿。', 'moforcoupon' ) . '</p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">';
		echo '<input type="hidden" name="template_id" value="">';
		wp_nonce_field( self::NONCE );

		echo '<div class="moforcoupon-tpl-fields">';

		echo '<div class="f"><label for="moforcoupon-tpl-code">' . esc_html__( '優惠券代碼', 'moforcoupon' ) . '</label>';
		echo '<input type="text" id="moforcoupon-tpl-code" name="code" autocomplete="off" placeholder="'
			. esc_attr__( '留空將自動產生', 'moforcoupon' ) . '"></div>';

		echo '<div class="f f-amount"><label for="moforcoupon-tpl-amount">' . esc_html__( '折扣值', 'moforcoupon' ) . ' <span class="unit"></span></label>';
		echo '<input type="number" id="moforcoupon-tpl-amount" name="amount" min="0" step="0.01"></div>';

		echo '<div class="f-row">';
		echo '<div class="f"><label for="moforcoupon-tpl-ul">' . esc_html__( '總使用次數上限', 'moforcoupon' ) . '</label>';
		echo '<input type="number" id="moforcoupon-tpl-ul" name="usage_limit" min="0" step="1" placeholder="'
			. esc_attr__( '不限', 'moforcoupon' ) . '"></div>';
		echo '<div class="f"><label for="moforcoupon-tpl-ulpu">' . esc_html__( '每人使用上限', 'moforcoupon' ) . '</label>';
		echo '<input type="number" id="moforcoupon-tpl-ulpu" name="usage_limit_per_user" min="0" step="1" placeholder="'
			. esc_attr__( '不限', 'moforcoupon' ) . '"></div>';
		echo '</div>';

		echo '<div class="f"><label for="moforcoupon-tpl-exp">' . esc_html__( '到期日(選填)', 'moforcoupon' ) . '</label>';
		echo '<input type="date" id="moforcoupon-tpl-exp" name="date_expires"></div>';

		echo '<div class="f"><label for="moforcoupon-tpl-desc">' . esc_html__( '優惠券說明(選填)', 'moforcoupon' ) . '</label>';
		echo '<textarea id="moforcoupon-tpl-desc" name="description" rows="2"></textarea></div>';

		echo '<div class="f f-check"><label><input type="checkbox" name="individual_use" value="yes"> '
			. esc_html__( '不可與其他優惠券並用', 'moforcoupon' ) . '</label></div>';

		echo '</div>'; // .moforcoupon-tpl-fields

		echo '<p class="moforcoupon-tpl-actions">';
		echo '<button type="submit" class="button button-primary">' . esc_html__( '建立優惠券', 'moforcoupon' ) . '</button> ';
		echo '<button type="button" class="button moforcoupon-tpl-cancel">' . esc_html__( '取消', 'moforcoupon' ) . '</button>';
		echo '</p>';
		echo '</form>';
		echo '</div>'; // dialog
		echo '</div>'; // modal
	}

	/** admin_post handler: validate, create the draft coupon, record recent, redirect. */
	public static function handle(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( '權限不足。', 'moforcoupon' ) );
		}
		check_admin_referer( self::NONCE );

		$template_id = isset( $_POST['template_id'] ) ? sanitize_key( (string) wp_unslash( $_POST['template_id'] ) ) : '';
		$overrides   = array(
			'code'                 => isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['code'] ) ) : '',
			'amount'               => isset( $_POST['amount'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['amount'] ) ) : '',
			'date_expires'         => isset( $_POST['date_expires'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['date_expires'] ) ) : '',
			'usage_limit'          => isset( $_POST['usage_limit'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['usage_limit'] ) ) : '',
			'usage_limit_per_user' => isset( $_POST['usage_limit_per_user'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['usage_limit_per_user'] ) ) : '',
			'description'          => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['description'] ) ) : '',
			// Checkbox: present only when ticked → explicit yes/no so unticking also takes effect.
			'individual_use'       => isset( $_POST['individual_use'] ) ? 'yes' : 'no',
		);

		$result = Applier::apply( $template_id, $overrides );

		if ( is_wp_error( $result ) ) {
			// add_query_arg already URL-encodes the value; do not pre-encode it.
			wp_safe_redirect(
				add_query_arg( 'moforcoupon_tpl_error', $result->get_error_message(), self::page_url() )
			);
			exit;
		}

		self::record_recent( $template_id );
		wp_safe_redirect( admin_url( 'post.php?post=' . (int) $result . '&action=edit' ) );
		exit;
	}

	// === Recently-used (per-user) ===

	/**
	 * Recently-applied templates for the current user, most-recent-first, filtered to
	 * templates that still exist.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function recent_templates(): array {
		$ids = get_user_meta( get_current_user_id(), self::RECENT_META, true );
		if ( ! is_array( $ids ) ) {
			return array();
		}
		$out = array();
		foreach ( $ids as $id ) {
			$tpl = Catalog::get( (string) $id );
			if ( null !== $tpl ) {
				$out[] = $tpl;
			}
		}
		return $out;
	}

	/** Push a template id onto the current user's recently-used list (dedup, capped). */
	private static function record_recent( string $template_id ): void {
		if ( '' === $template_id ) {
			return;
		}
		$user_id = get_current_user_id();
		$ids     = get_user_meta( $user_id, self::RECENT_META, true );
		$ids     = is_array( $ids ) ? array_values( array_filter( array_map( 'strval', $ids ) ) ) : array();

		array_unshift( $ids, $template_id );
		$ids = array_values( array_unique( $ids ) );
		$ids = array_slice( $ids, 0, self::RECENT_MAX );

		update_user_meta( $user_id, self::RECENT_META, $ids );
	}

	private static function page_url(): string {
		return admin_url( 'admin.php?page=' . self::SLUG );
	}

	private static function type_label( string $type_key ): string {
		$labels = \MoksaWeb\Moforcoupon\Support\CouponType::labels();
		return $labels[ $type_key ] ?? __( '其他', 'moforcoupon' );
	}

	private static function styles(): void {
		echo '<style>'
			. '.moforcoupon-tpl-h2{font-size:15px;margin:22px 0 12px;color:#1d2327}'
			. '.moforcoupon-tpl-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(270px,1fr));gap:16px}'
			. '.moforcoupon-tpl-layout{display:flex;gap:20px;align-items:flex-start}'
			. '.moforcoupon-tpl-cats{flex:0 0 180px;position:sticky;top:46px}'
			. '.moforcoupon-tpl-cats ul{margin:0}'
			. '.moforcoupon-tpl-cats li{margin:0}'
			. '.moforcoupon-tpl-cats a{display:flex;justify-content:space-between;align-items:center;text-decoration:none;color:#2c3338;padding:7px 12px;border-radius:6px;border-left:3px solid transparent}'
			. '.moforcoupon-tpl-cats a:hover{background:#f0f0f1}'
			. '.moforcoupon-tpl-cats a.current{background:#f0f6fc;border-left-color:#2271b1;color:#0a4b78;font-weight:600}'
			. '.moforcoupon-tpl-cats .count{font-size:11px;color:#646970;background:#f0f0f1;border-radius:9px;padding:0 7px;min-width:18px;text-align:center}'
			. '.moforcoupon-tpl-cats a.current .count{background:#c5d9ed;color:#0a4b78}'
			. '.moforcoupon-tpl-main{flex:1 1 auto;min-width:0}'
			. '.moforcoupon-tpl-card{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:18px 20px;display:flex;flex-direction:column;transition:box-shadow .15s,border-color .15s}'
			. '.moforcoupon-tpl-card:hover{border-color:#c3c4c7;box-shadow:0 1px 4px rgba(0,0,0,.06)}'
			. '.moforcoupon-tpl-card .badge{display:inline-block;align-self:flex-start;font-size:12px;font-weight:600;color:#3c434a;background:#f0f0f1;border:1px solid #dcdcde;border-radius:4px;padding:2px 8px;margin-bottom:8px}'
			. '.moforcoupon-tpl-card h3{font-size:16px;margin:0 0 6px;color:#1d2327}'
			. '.moforcoupon-tpl-card .d{color:#646970;font-size:13px;line-height:1.5;flex:1;margin-bottom:14px}'
			. '.moforcoupon-tpl-card .req{color:#b32d2e;font-size:12px;margin:0 0 10px}'
			. '.moforcoupon-tpl-card .button{align-self:flex-start}'
			. '.moforcoupon-tpl-empty{color:#646970;padding:24px 0}'
			// modal
			// NOTE: keep display off the base rule — an explicit display here would override the
			// [hidden] attribute (UA display:none) and the modal would show on page load. Only
			// apply flex when NOT hidden so the modal stays closed until a card opens it.
			. '.moforcoupon-tpl-modal{position:fixed;inset:0;z-index:100000;align-items:center;justify-content:center}'
			. '.moforcoupon-tpl-modal[hidden]{display:none}'
			. '.moforcoupon-tpl-modal:not([hidden]){display:flex}'
			. '.moforcoupon-tpl-backdrop{position:absolute;inset:0;background:rgba(0,0,0,.5)}'
			. '.moforcoupon-tpl-dialog{position:relative;background:#fff;border-radius:8px;padding:24px 26px;width:440px;max-width:94vw;max-height:90vh;overflow:auto;box-shadow:0 8px 30px rgba(0,0,0,.25)}'
			. '.moforcoupon-tpl-dialog h2{margin:0 0 4px;font-size:17px}'
			. '.moforcoupon-tpl-dialog>.description{margin:0 0 6px;color:#646970}'
			. '.moforcoupon-tpl-fields .f{margin:16px 0}'
			// the fix: label is a block with breathing room above the input (was flush).
			. '.moforcoupon-tpl-fields label{display:block;font-weight:600;color:#1d2327;margin-bottom:7px}'
			. '.moforcoupon-tpl-fields input,.moforcoupon-tpl-fields textarea{width:100%;box-sizing:border-box}'
			. '.moforcoupon-tpl-fields .unit{color:#646970;font-weight:400}'
			. '.moforcoupon-tpl-fields .f-row{display:flex;gap:14px}'
			. '.moforcoupon-tpl-fields .f-row .f{flex:1;margin-top:0}'
			. '.moforcoupon-tpl-fields .f-check{margin:14px 0 4px}'
			. '.moforcoupon-tpl-fields .f-check label{display:flex;align-items:center;gap:8px;margin:0;font-weight:500}'
			. '.moforcoupon-tpl-fields .f-check input{width:auto;margin:0}'
			. '.moforcoupon-tpl-actions{margin:6px 0 0}'
			. '@media(max-width:782px){.moforcoupon-tpl-layout{flex-direction:column}.moforcoupon-tpl-cats{position:static;flex-basis:auto;width:100%}.moforcoupon-tpl-cats ul{display:flex;flex-wrap:wrap;gap:6px}}'
			. '</style>';
	}

	private static function script(): void {
		echo '<script>(function(){'
			// category + keyword filter (combined)
			. "var cats=document.querySelector('.moforcoupon-tpl-cats');"
			. "var main=document.querySelector('.moforcoupon-tpl-main');"
			. 'if(main){'
			. "var links=cats?cats.querySelectorAll('a[data-filter]'):[];"
			. "var cards=main.querySelectorAll('.moforcoupon-tpl-card');"
			. "var empty=main.querySelector('.moforcoupon-tpl-empty');"
			. "var search=main.querySelector('.moforcoupon-tpl-search input');"
			. "var curCat='all',curQ='';"
			. 'function apply(){var shown=0;cards.forEach(function(c){'
			. "var okCat=(curCat==='all'||c.getAttribute('data-cat')===curCat);"
			. "var okQ=(curQ===''||(c.getAttribute('data-search')||'').indexOf(curQ)!==-1);"
			. "var ok=okCat&&okQ;c.style.display=ok?'':'none';if(ok)shown++;});"
			. 'if(empty)empty.hidden=shown>0;}'
			. "if(cats){cats.addEventListener('click',function(e){"
			. "var a=e.target.closest('a[data-filter]');if(!a)return;e.preventDefault();"
			. "curCat=a.getAttribute('data-filter');"
			. 'links.forEach(function(l){l.classList.toggle(\'current\',l===a);});apply();});}'
			. "if(search){search.addEventListener('input',function(){curQ=search.value.toLowerCase().trim();apply();});}"
			. '}'
			// quick-configure modal
			. "var modal=document.querySelector('.moforcoupon-tpl-modal');"
			. 'if(modal){'
			. "var dlg=modal.querySelector('.moforcoupon-tpl-dialog');"
			. "var title=modal.querySelector('#moforcoupon-tpl-modal-title');"
			. "var fId=modal.querySelector('input[name=template_id]');"
			. "var fCode=modal.querySelector('input[name=code]');"
			. "var fAmt=modal.querySelector('input[name=amount]');"
			. "var amtRow=modal.querySelector('.f-amount');"
			. "var unit=modal.querySelector('.unit');"
			. "var fUl=modal.querySelector('input[name=usage_limit]');"
			. "var fUlpu=modal.querySelector('input[name=usage_limit_per_user]');"
			. "var fDesc=modal.querySelector('textarea[name=description]');"
			. "var fIndiv=modal.querySelector('input[name=individual_use]');"
			. 'var lastFocus=null;'
			. 'function open(b){'
			. 'lastFocus=b;'
			. "fId.value=b.getAttribute('data-id');"
			. "title.textContent=b.getAttribute('data-label');"
			. "fCode.value='';fCode.placeholder=(b.getAttribute('data-prefix')||'')+'-… ('+'留空自動產生'+')';"
			. "if(b.getAttribute('data-amount-editable')==='1'){amtRow.hidden=false;fAmt.value=b.getAttribute('data-amount');unit.textContent=b.getAttribute('data-unit')||'';}"
			. "else{amtRow.hidden=true;fAmt.value='';}"
			. "fUl.value=b.getAttribute('data-usage-limit')||'';"
			. "fUlpu.value=b.getAttribute('data-usage-pu')||'';"
			. "fDesc.value=b.getAttribute('data-description')||'';"
			. "fIndiv.checked=b.getAttribute('data-individual')==='1';"
			. 'modal.hidden=false;fCode.focus();'
			. '}'
			. 'function close(){modal.hidden=true;if(lastFocus){lastFocus.focus();lastFocus=null;}}'
			. "document.querySelectorAll('.moforcoupon-tpl-apply').forEach(function(b){b.addEventListener('click',function(){open(b);});});"
			. "modal.querySelector('.moforcoupon-tpl-backdrop').addEventListener('click',close);"
			. "modal.querySelector('.moforcoupon-tpl-cancel').addEventListener('click',close);"
			. "document.addEventListener('keydown',function(e){if(e.key==='Escape'&&!modal.hidden)close();});"
			// Focus trap: keep Tab cycling inside the open dialog.
			. "dlg.addEventListener('keydown',function(e){"
			. "if(e.key!=='Tab'||modal.hidden)return;"
			. "var f=Array.prototype.filter.call(dlg.querySelectorAll('a[href],button:not([disabled]),input,select,textarea,[tabindex]:not([tabindex=\"-1\"])'),function(el){return el.offsetParent!==null;});"
			. 'if(!f.length)return;var first=f[0],last=f[f.length-1];'
			. 'if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus();}'
			. 'else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus();}'
			. '});'
			. '}'
			. '})();</script>';
	}
}
