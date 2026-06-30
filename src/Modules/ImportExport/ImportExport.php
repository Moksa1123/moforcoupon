<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\ImportExport;

use MoksaWeb\Moforcoupon\Coupon\CouponService;
use MoksaWeb\Moforcoupon\Coupon\Meta\Keys;

defined( 'ABSPATH' ) || exit;

/**
 * CSV import / export of coupons — a backup / audit / bulk-edit surface WooCommerce lacks.
 * Exports native WC_Coupon fields plus the most-used plugin settings (including the tiered
 * and advanced-rule JSON) for a full round-trip; import creates new coupons or updates
 * existing ones matched by code. Capability- and nonce-checked on both paths.
 */
final class ImportExport {

	private const CAP          = 'manage_woocommerce';
	private const SLUG         = 'moforcoupon-import-export';
	private const NONCE_EXPORT = 'moforcoupon_export_coupons';
	private const NONCE_IMPORT = 'moforcoupon_import_coupons';

	/** Native WC_Coupon fields (get_/set_). */
	private const NATIVE = array(
		'code',
		'discount_type',
		'amount',
		'description',
		'date_expires',
		'usage_limit',
		'usage_limit_per_user',
		'individual_use',
		'free_shipping',
		'minimum_amount',
		'maximum_amount',
		'product_ids',
		'excluded_product_ids',
		'product_categories',
		'status',
	);

	/** Extra plugin-meta columns: csv header => meta key. */
	private const META = array(
		'campaign'     => Keys::CAMPAIGN,
		'auto_apply'   => Keys::AUTO_APPLY,
		'discount_cap' => Keys::DISCOUNT_CAP,
		'min_subtotal' => Keys::MIN_SUBTOTAL,
		'tiers_json'   => Keys::TIERS,
		'rules_json'   => Keys::RULES,
	);

	public static function boot(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ), 20 );
		add_action( 'admin_post_moforcoupon_export_coupons', array( self::class, 'handle_export' ) );
		add_action( 'admin_post_moforcoupon_import_coupons', array( self::class, 'handle_import' ) );
		add_action( 'admin_notices', array( self::class, 'notices' ) );
	}

	public static function menu(): void {
		add_submenu_page(
			'moforcoupon',
			__( '匯入 / 匯出', 'moforcoupon' ),
			__( '匯入 / 匯出', 'moforcoupon' ),
			self::CAP,
			self::SLUG,
			array( self::class, 'render' )
		);
	}

	private static function columns(): array {
		return array_merge( self::NATIVE, array_keys( self::META ) );
	}

	/* ---------------- page ---------------- */

	public static function render(): void {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		echo '<div class="wrap"><h1>' . esc_html__( '優惠券匯入 / 匯出', 'moforcoupon' ) . '</h1>';

		echo '<h2>' . esc_html__( '匯出', 'moforcoupon' ) . '</h2>';
		echo '<p class="description">' . esc_html__( '將所有優惠券(含階梯 / 進階規則設定)匯出成 CSV,可用於備份、稽核或大量編輯。', 'moforcoupon' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="moforcoupon_export_coupons">';
		wp_nonce_field( self::NONCE_EXPORT );
		submit_button( __( '下載 CSV', 'moforcoupon' ), 'primary', 'submit', false );
		echo '</form>';

		echo '<hr><h2>' . esc_html__( '匯入', 'moforcoupon' ) . '</h2>';
		echo '<p class="description">' . esc_html__( '上傳同格式的 CSV。以「code」欄比對:已存在則更新,不存在則新建為草稿。', 'moforcoupon' ) . '</p>';
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="moforcoupon_import_coupons">';
		wp_nonce_field( self::NONCE_IMPORT );
		echo '<input type="file" name="csv" accept=".csv,text/csv" required> ';
		submit_button( __( '匯入 CSV', 'moforcoupon' ), 'secondary', 'submit', false );
		echo '</form>';

		echo '</div>';
	}

	/* ---------------- export ---------------- */

	public static function handle_export(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( '權限不足。', 'moforcoupon' ) );
		}
		check_admin_referer( self::NONCE_EXPORT );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="moforcoupon-coupons-' . gmdate( 'Ymd-His' ) . '.csv"' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- streaming CSV to the response, not the filesystem.
		$out = fopen( 'php://output', 'w' );
		if ( false === $out ) {
			exit;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- UTF-8 BOM to php://output so Excel reads Chinese correctly.
		fwrite( $out, "\xEF\xBB\xBF" );
		fputcsv( $out, self::columns() );
		foreach ( self::all_coupon_ids() as $id ) {
			$coupon = new \WC_Coupon( $id );
			if ( $coupon->get_id() ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv -- writing to php://output.
				fputcsv( $out, self::row( $coupon, $id ) );
			}
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- closing php://output stream.
		fclose( $out );
		exit;
	}

	/**
	 * @return array<int,string>
	 */
	private static function row( \WC_Coupon $coupon, int $id ): array {
		$row = array();
		foreach ( self::NATIVE as $field ) {
			$row[] = self::native_value( $coupon, $field );
		}
		foreach ( self::META as $meta_key ) {
			$value = get_post_meta( $id, $meta_key, true );
			$row[] = is_array( $value ) ? implode( '|', array_map( 'strval', $value ) ) : (string) $value;
		}
		return $row;
	}

	private static function native_value( \WC_Coupon $coupon, string $field ): string {
		switch ( $field ) {
			case 'date_expires':
				$date = $coupon->get_date_expires();
				return $date ? $date->date( 'Y-m-d' ) : '';
			case 'individual_use':
			case 'free_shipping':
				return $coupon->{"get_$field"}() ? 'yes' : 'no';
			case 'product_ids':
			case 'excluded_product_ids':
			case 'product_categories':
				return implode( '|', array_map( 'strval', (array) $coupon->{"get_$field"}() ) );
			case 'status':
				return (string) get_post_status( $coupon->get_id() );
			default:
				return (string) $coupon->{"get_$field"}();
		}
	}

	/**
	 * @return array<int,int>
	 */
	private static function all_coupon_ids(): array {
		$query = new \WP_Query(
			array(
				'post_type'              => 'shop_coupon',
				'post_status'            => 'any',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		return array_map( 'intval', $query->posts );
	}

	/* ---------------- import ---------------- */

	public static function handle_import(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( '權限不足。', 'moforcoupon' ) );
		}
		check_admin_referer( self::NONCE_IMPORT );
		$back = add_query_arg( 'page', self::SLUG, admin_url( 'admin.php' ) );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- tmp_name validated by is_uploaded_file below; it is a server path, not user text.
		$tmp = isset( $_FILES['csv']['tmp_name'] ) ? (string) $_FILES['csv']['tmp_name'] : '';
		if ( '' === $tmp || ! is_uploaded_file( $tmp ) ) {
			wp_safe_redirect( add_query_arg( 'moforcoupon_import', 'nofile', $back ) );
			exit;
		}

		// Server-side type whitelist: only accept a real CSV / plain-text upload (never trust the
		// client). The file is then read purely as CSV rows — never executed — and every value is
		// re-sanitized below.
		$file_name = isset( $_FILES['csv']['name'] ) ? sanitize_file_name( wp_unslash( (string) $_FILES['csv']['name'] ) ) : '';
		$file_type = wp_check_filetype_and_ext( $tmp, $file_name );
		if ( ! in_array( (string) ( $file_type['ext'] ?? '' ), array( 'csv', 'txt' ), true ) ) {
			wp_safe_redirect( add_query_arg( 'moforcoupon_import', 'badtype', $back ) );
			exit;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- reading the just-uploaded temp file row by row.
		$handle = fopen( $tmp, 'r' );
		if ( false === $handle ) {
			wp_safe_redirect( add_query_arg( 'moforcoupon_import', 'nofile', $back ) );
			exit;
		}

		$header = fgetcsv( $handle );
		if ( ! is_array( $header ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			wp_safe_redirect( add_query_arg( 'moforcoupon_import', 'empty', $back ) );
			exit;
		}
		$header = array_map( static fn( $h ): string => trim( str_replace( "\xEF\xBB\xBF", '', (string) $h ) ), $header );

		$created = 0;
		$updated = 0;
		$failed  = 0;
		while ( true ) {
			$data = fgetcsv( $handle );
			if ( false === $data || null === $data ) {
				break;
			}
			if ( array( null ) === $data ) {
				continue; // blank line.
			}
			$assoc = self::associate( $header, $data );
			$code  = isset( $assoc['code'] ) ? trim( (string) $assoc['code'] ) : '';
			if ( '' === $code ) {
				continue;
			}
			$result = self::import_row( $assoc );
			if ( 'created' === $result ) {
				++$created;
			} elseif ( 'updated' === $result ) {
				++$updated;
			} else {
				++$failed;
			}
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		wp_safe_redirect(
			add_query_arg(
				array(
					'moforcoupon_import' => 'ok',
					'c'                  => $created,
					'u'                  => $updated,
					'f'                  => $failed,
				),
				$back
			)
		);
		exit;
	}

	/**
	 * @param array<int,string> $header
	 * @param array<int,string> $data
	 * @return array<string,string>
	 */
	private static function associate( array $header, array $data ): array {
		$assoc = array();
		foreach ( $header as $i => $key ) {
			$assoc[ $key ] = isset( $data[ $i ] ) ? (string) $data[ $i ] : '';
		}
		return $assoc;
	}

	/**
	 * @param array<string,string> $assoc
	 * @return string created|updated|failed
	 */
	private static function import_row( array $assoc ): string {
		$code = trim( (string) $assoc['code'] );
		// Match across ALL statuses (wc_get_coupon_id_by_code only finds published) so
		// re-importing a draft updates it instead of creating a duplicate.
		$existing = self::find_any_status_by_code( $code );

		$fields = array( 'code' => $code );
		foreach ( self::NATIVE as $field ) {
			if ( 'code' === $field || ! array_key_exists( $field, $assoc ) ) {
				continue;
			}
			$raw = trim( (string) $assoc[ $field ] );
			switch ( $field ) {
				case 'individual_use':
				case 'free_shipping':
					$fields[ $field ] = ( 'yes' === strtolower( $raw ) );
					break;
				case 'product_ids':
				case 'excluded_product_ids':
				case 'product_categories':
					$fields[ $field ] = '' === $raw ? array() : array_values( array_filter( array_map( 'absint', explode( '|', $raw ) ) ) );
					break;
				case 'usage_limit':
				case 'usage_limit_per_user':
					$fields[ $field ] = (int) $raw;
					break;
				case 'date_expires':
					if ( '' !== $raw ) {
						$fields['date_expires'] = $raw;
					}
					break;
				case 'status':
					$fields['status'] = ( 'publish' === $raw ) ? 'publish' : 'draft';
					break;
				default:
					if ( '' !== $raw ) {
						$fields[ $field ] = $raw;
					}
			}
		}

		$coupon = CouponService::save( $fields, $existing > 0 ? $existing : 0 );
		if ( $coupon instanceof \WP_Error || ! $coupon->get_id() ) {
			return 'failed';
		}
		$id = (int) $coupon->get_id();

		// Plugin-meta columns run through the registered sanitize callbacks on write.
		foreach ( self::META as $column => $meta_key ) {
			if ( ! array_key_exists( $column, $assoc ) ) {
				continue;
			}
			$raw = trim( (string) $assoc[ $column ] );
			if ( '' === $raw ) {
				delete_post_meta( $id, $meta_key );
			} else {
				update_post_meta( $id, $meta_key, wp_slash( $raw ) );
			}
		}

		return $existing > 0 ? 'updated' : 'created';
	}

	/** Coupon id for a code across any post status (codes are stored lowercased). */
	private static function find_any_status_by_code( string $code ): int {
		$code = function_exists( 'wc_format_coupon_code' ) ? wc_format_coupon_code( $code ) : strtolower( trim( $code ) );
		if ( '' === $code ) {
			return 0;
		}
		$ids = get_posts(
			array(
				'post_type'              => 'shop_coupon',
				'post_status'            => 'any',
				'title'                  => $code,
				'numberposts'            => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		return ( is_array( $ids ) && isset( $ids[0] ) ) ? (int) $ids[0] : 0;
	}

	public static function notices(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || false === strpos( (string) $screen->id, self::SLUG ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only result flags from a redirect.
		if ( ! isset( $_GET['moforcoupon_import'] ) ) {
			return;
		}
		$state = sanitize_key( wp_unslash( $_GET['moforcoupon_import'] ) );
		if ( 'ok' === $state ) {
			$created = isset( $_GET['c'] ) ? (int) $_GET['c'] : 0;
			$updated = isset( $_GET['u'] ) ? (int) $_GET['u'] : 0;
			$failed  = isset( $_GET['f'] ) ? (int) $_GET['f'] : 0;
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(
				sprintf(
					/* translators: 1: created count, 2: updated count, 3: failed count. */
					__( '匯入完成:新建 %1$d、更新 %2$d、失敗 %3$d。', 'moforcoupon' ),
					$created,
					$updated,
					$failed
				)
			) . '</p></div>';
		} else {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( '匯入失敗:請選擇有效的 CSV 檔。', 'moforcoupon' ) . '</p></div>';
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}
}
