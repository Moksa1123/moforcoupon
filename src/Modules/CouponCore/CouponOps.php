<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\CouponCore;

use MoksaWeb\Moforcoupon\Coupon\CouponService;
use MoksaWeb\Moforcoupon\Coupon\Meta\CouponSettings;
use MoksaWeb\Moforcoupon\Support\GuardedOps;
use MoksaWeb\Moforcoupon\Modules\AutoApply\AutoApplyMeta;
use MoksaWeb\Moforcoupon\Modules\Templates\Applier;

defined( 'ABSPATH' ) || exit;

/**
 * Destructive coupon operations as propose/apply pairs. The Abilities point their
 * execute_callback at *_prepare (proposal only, no writes); *_apply performs the
 * real change and is invoked solely by the in-dashboard confirm flow / REST after
 * an explicit human confirmation. Both ends re-check the capability.
 */
final class CouponOps {

	use GuardedOps;

	/* ---------------- create ---------------- */

	/**
	 * @param mixed $input
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function create_prepare( $input ) {
		if ( ! current_user_can( self::CAP ) ) {
			return self::denied();
		}
		$input  = is_array( $input ) ? $input : [];
		$fields = CouponService::normalize_and_validate( $input, false );
		if ( $fields instanceof \WP_Error ) {
			return $fields;
		}
		if ( empty( $fields['code'] ) ) {
			return new \WP_Error( 'moforcoupon_invalid_code', __( '優惠券代碼不可空白。', 'moforcoupon' ) );
		}
		if ( CouponService::find_id_by_code( $fields['code'] ) > 0 ) {
			return new \WP_Error(
				'moforcoupon_duplicate',
				/* translators: %s: coupon code. */
				sprintf( __( '優惠券代碼 %s 已存在,請換一個。', 'moforcoupon' ), $fields['code'] )
			);
		}
		return self::carry_extras(
			[
				'fields'  => $fields,
				'summary' => CouponService::build_summary( $fields, __( '建立', 'moforcoupon' ) ),
			],
			$input
		);
	}

	/**
	 * Carry optional cross-module extras from the AI input into the proposed params and
	 * note them on the confirm summary. Each is only carried when actually supplied:
	 * the full grouped `moforcoupon` settings object (schedule / conditions / BOGO /
	 * gift / shipping…), plus the three convenience shortcuts auto_apply / discount_cap
	 * / exclude_coupons (which override the grouped values at apply time).
	 *
	 * @param array<string,mixed> $result
	 * @param array<string,mixed> $input
	 * @return array<string,mixed>
	 */
	private static function carry_extras( array $result, array $input ): array {
		if ( array_key_exists( 'moforcoupon', $input ) && is_array( $input['moforcoupon'] ) ) {
			$result['settings'] = $input['moforcoupon'];
			if ( isset( $result['summary'] ) ) {
				$result['summary'] .= __( '、含進階設定', 'moforcoupon' );
			}
		}
		if ( array_key_exists( 'auto_apply', $input ) ) {
			$enable               = (bool) filter_var( $input['auto_apply'], FILTER_VALIDATE_BOOLEAN );
			$result['auto_apply'] = $enable;
			if ( $enable && isset( $result['summary'] ) ) {
				$result['summary'] .= __( '、自動套用', 'moforcoupon' );
			}
		}
		if ( array_key_exists( 'discount_cap', $input ) && is_numeric( $input['discount_cap'] ) ) {
			$cap                    = max( 0.0, (float) $input['discount_cap'] );
			$result['discount_cap'] = $cap;
			if ( $cap > 0 && isset( $result['summary'] ) ) {
				/* translators: %s: max discount amount. */
				$result['summary'] .= sprintf( __( '、最多折 %s', 'moforcoupon' ), $cap );
			}
		}
		if ( array_key_exists( 'exclude_coupons', $input ) ) {
			$exclude                   = (bool) filter_var( $input['exclude_coupons'], FILTER_VALIDATE_BOOLEAN );
			$result['exclude_coupons'] = $exclude;
			if ( $exclude && isset( $result['summary'] ) ) {
				$result['summary'] .= __( '、不可與其他券並用', 'moforcoupon' );
			}
		}
		return $result;
	}

	/** Persist carried extras after the coupon is saved (shared writers). */
	private static function apply_extras( int $coupon_id, array $params ): void {
		// Apply the full grouped settings first; the shortcuts below then override.
		if ( array_key_exists( 'settings', $params ) && is_array( $params['settings'] ) ) {
			CouponSettings::write( $coupon_id, $params['settings'] );
		}
		if ( array_key_exists( 'auto_apply', $params ) ) {
			AutoApplyMeta::write( $coupon_id, (bool) $params['auto_apply'] );
		}
		if ( array_key_exists( 'discount_cap', $params ) ) {
			$cap = max( 0.0, (float) $params['discount_cap'] );
			if ( $cap > 0 ) {
				update_post_meta( $coupon_id, \MoksaWeb\Moforcoupon\Coupon\Meta\Keys::DISCOUNT_CAP, (string) $cap );
			} else {
				delete_post_meta( $coupon_id, \MoksaWeb\Moforcoupon\Coupon\Meta\Keys::DISCOUNT_CAP );
			}
		}
		if ( array_key_exists( 'exclude_coupons', $params ) ) {
			update_post_meta( $coupon_id, \MoksaWeb\Moforcoupon\Coupon\Meta\Keys::STACK_EXCLUDE, $params['exclude_coupons'] ? 'yes' : '' );
		}
	}

	/**
	 * @param array<string,mixed> $params
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function create_apply( array $params ) {
		if ( ! current_user_can( self::CAP ) ) {
			return self::denied();
		}
		$fields = isset( $params['fields'] ) && is_array( $params['fields'] ) ? $params['fields'] : [];
		$coupon = CouponService::save( $fields );
		if ( $coupon instanceof \WP_Error ) {
			return $coupon;
		}
		self::apply_extras( $coupon->get_id(), $params );
		return [
			'id'    => $coupon->get_id(),
			'reply' => sprintf(
				/* translators: 1: coupon code, 2: coupon id. */
				__( '已建立優惠券 %1$s(#%2$d)。', 'moforcoupon' ),
				$coupon->get_code(),
				$coupon->get_id()
			),
		];
	}

	/* ---------------- update ---------------- */

	/**
	 * @param mixed $input
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function update_prepare( $input ) {
		if ( ! current_user_can( self::CAP ) ) {
			return self::denied();
		}
		$input = is_array( $input ) ? $input : [];
		$id    = CouponService::resolve_id( $input['code_or_id'] ?? '' );
		if ( ! $id ) {
			return new \WP_Error( 'moforcoupon_not_found', __( '找不到該優惠券。', 'moforcoupon' ) );
		}
		$fields = CouponService::normalize_and_validate( $input, true );
		if ( $fields instanceof \WP_Error ) {
			return $fields;
		}
		unset( $fields['code'] ); // Code changes are not allowed via update.
		if ( [] === $fields && ! array_key_exists( 'auto_apply', $input ) && ! array_key_exists( 'discount_cap', $input ) && ! array_key_exists( 'exclude_coupons', $input ) ) {
			return new \WP_Error( 'moforcoupon_nothing', __( '沒有可更新的欄位。', 'moforcoupon' ) );
		}
		return self::carry_extras(
			[
				'id'      => $id,
				'fields'  => $fields,
				'summary' => CouponService::build_summary(
					array_merge( [ 'code' => (string) ( CouponService::get( $id )['code'] ?? $id ) ], $fields ),
					__( '更新', 'moforcoupon' )
				),
			],
			$input
		);
	}

	/**
	 * @param array<string,mixed> $params
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function update_apply( array $params ) {
		if ( ! current_user_can( self::CAP ) ) {
			return self::denied();
		}
		$id     = (int) ( $params['id'] ?? 0 );
		$fields = isset( $params['fields'] ) && is_array( $params['fields'] ) ? $params['fields'] : [];
		$coupon = CouponService::save( $fields, $id );
		if ( $coupon instanceof \WP_Error ) {
			return $coupon;
		}
		self::apply_extras( $coupon->get_id(), $params );
		return [
			'id'    => $coupon->get_id(),
			/* translators: %s: coupon code. */
			'reply' => sprintf( __( '已更新優惠券 %s。', 'moforcoupon' ), $coupon->get_code() ),
		];
	}

	/* ---------------- toggle ---------------- */

	/**
	 * @param mixed $input
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function toggle_prepare( $input ) {
		if ( ! current_user_can( self::CAP ) ) {
			return self::denied();
		}
		$input  = is_array( $input ) ? $input : [];
		$id     = CouponService::resolve_id( $input['code_or_id'] ?? '' );
		$enable = ! empty( $input['enable'] );
		if ( ! $id ) {
			return new \WP_Error( 'moforcoupon_not_found', __( '找不到該優惠券。', 'moforcoupon' ) );
		}
		$data = CouponService::get( $id );
		return [
			'id'      => $id,
			'enable'  => $enable,
			'summary' => sprintf(
				/* translators: 1: enable/disable verb, 2: coupon code. */
				__( '%1$s優惠券 %2$s', 'moforcoupon' ),
				$enable ? __( '啟用', 'moforcoupon' ) : __( '停用', 'moforcoupon' ),
				(string) ( $data['code'] ?? $id )
			),
		];
	}

	/**
	 * @param array<string,mixed> $params
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function toggle_apply( array $params ) {
		if ( ! current_user_can( self::CAP ) ) {
			return self::denied();
		}
		$id = (int) ( $params['id'] ?? 0 );
		if ( ! CouponService::set_status( $id, ! empty( $params['enable'] ) ) ) {
			return new \WP_Error( 'moforcoupon_toggle_failed', __( '切換狀態失敗。', 'moforcoupon' ) );
		}
		return [
			'id'    => $id,
			'reply' => ! empty( $params['enable'] )
				? __( '已啟用優惠券。', 'moforcoupon' )
				: __( '已停用優惠券。', 'moforcoupon' ),
		];
	}

	/* ---------------- delete ---------------- */

	/**
	 * @param mixed $input
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function delete_prepare( $input ) {
		if ( ! current_user_can( self::CAP ) ) {
			return self::denied();
		}
		$input = is_array( $input ) ? $input : [];
		$id    = CouponService::resolve_id( $input['code_or_id'] ?? '' );
		if ( ! $id ) {
			return new \WP_Error( 'moforcoupon_not_found', __( '找不到該優惠券。', 'moforcoupon' ) );
		}
		$force = ! empty( $input['force'] );
		$data  = CouponService::get( $id );
		return [
			'id'      => $id,
			'force'   => $force,
			'summary' => sprintf(
				/* translators: 1: coupon code, 2: permanently/to trash. */
				__( '刪除優惠券 %1$s(%2$s)', 'moforcoupon' ),
				(string) ( $data['code'] ?? $id ),
				$force ? __( '永久刪除', 'moforcoupon' ) : __( '移到垃圾桶', 'moforcoupon' )
			),
		];
	}

	/**
	 * @param array<string,mixed> $params
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function delete_apply( array $params ) {
		if ( ! current_user_can( self::CAP ) ) {
			return self::denied();
		}
		$id = (int) ( $params['id'] ?? 0 );
		if ( ! CouponService::delete( $id, ! empty( $params['force'] ) ) ) {
			return new \WP_Error( 'moforcoupon_delete_failed', __( '刪除失敗。', 'moforcoupon' ) );
		}
		return [
			'id'    => $id,
			'reply' => __( '已刪除優惠券。', 'moforcoupon' ),
		];
	}

	/* ---------------- bulk generate ---------------- */

	/**
	 * @param mixed $input
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function bulk_generate_prepare( $input ) {
		if ( ! current_user_can( self::CAP ) ) {
			return self::denied();
		}
		$input = is_array( $input ) ? $input : [];
		$count = (int) ( $input['count'] ?? 0 );
		if ( $count < 1 || $count > 500 ) {
			return new \WP_Error( 'moforcoupon_bad_count', __( '數量須為 1–500。', 'moforcoupon' ) );
		}
		$fields = CouponService::normalize_and_validate( $input, true );
		if ( $fields instanceof \WP_Error ) {
			return $fields;
		}
		unset( $fields['code'] );
		$prefix = isset( $input['prefix'] ) ? strtoupper( sanitize_text_field( (string) $input['prefix'] ) ) : '';
		return self::carry_extras(
			[
				'count'   => $count,
				'prefix'  => $prefix,
				'fields'  => $fields,
				'summary' => sprintf(
					/* translators: 1: count, 2: prefix, 3: discount summary. */
					__( '量產 %1$d 張優惠券(前綴 "%2$s"):%3$s', 'moforcoupon' ),
					$count,
					$prefix,
					CouponService::build_summary( array_merge( [ 'code' => $prefix . '…' ], $fields ), '' )
				),
			],
			$input
		);
	}

	/**
	 * @param array<string,mixed> $params
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function bulk_generate_apply( array $params ) {
		if ( ! current_user_can( self::CAP ) ) {
			return self::denied();
		}
		$count  = (int) ( $params['count'] ?? 0 );
		$prefix = (string) ( $params['prefix'] ?? '' );
		$fields = isset( $params['fields'] ) && is_array( $params['fields'] ) ? $params['fields'] : [];

		$created = [];
		for ( $i = 0; $i < $count; $i++ ) {
			$code = self::unique_code( $prefix );
			if ( '' === $code ) {
				continue;
			}
			$coupon = CouponService::save( array_merge( $fields, [ 'code' => $code ] ) );
			if ( ! ( $coupon instanceof \WP_Error ) ) {
				self::apply_extras( $coupon->get_id(), $params );
				$created[] = $coupon->get_code();
			}
		}
		$made  = count( $created );
		$reply = sprintf(
			/* translators: %d: number of coupons created. */
			__( '已量產 %d 張優惠券。', 'moforcoupon' ),
			$made
		);
		// Don't silently under-deliver: surface the shortfall (code collisions / save errors).
		if ( $made < $count ) {
			$reply .= ' ' . sprintf(
				/* translators: 1: requested count, 2: shortfall count. */
				__( '(要求 %1$d 張,有 %2$d 張因代碼重複或儲存失敗未建立)', 'moforcoupon' ),
				$count,
				$count - $made
			);
		}
		return [
			'codes' => $created,
			'reply' => $reply,
		];
	}

	/* ---------------- extend expiry ---------------- */

	/**
	 * @param mixed $input
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function extend_expiry_prepare( $input ) {
		if ( ! current_user_can( self::CAP ) ) {
			return self::denied();
		}
		$input = is_array( $input ) ? $input : [];
		$refs  = isset( $input['codes_or_ids'] ) && is_array( $input['codes_or_ids'] ) ? $input['codes_or_ids'] : [];
		$date  = isset( $input['date_expires'] ) ? sanitize_text_field( (string) $input['date_expires'] ) : '';
		$ts    = '' === $date ? false : strtotime( $date );
		if ( false === $ts ) {
			return new \WP_Error( 'moforcoupon_invalid_date', __( '到期日格式無效,請用 YYYY-MM-DD。', 'moforcoupon' ) );
		}
		// "Extend" must move expiry forward — a past date would expire every coupon
		// immediately (and strtotime of odd input can land on 1970).
		if ( $ts < strtotime( 'today' ) ) {
			return new \WP_Error( 'moforcoupon_past_date', __( '延長的到期日不可早於今天。', 'moforcoupon' ) );
		}
		$ids = [];
		foreach ( $refs as $ref ) {
			$id = CouponService::resolve_id( $ref );
			if ( $id ) {
				$ids[] = $id;
			}
		}
		if ( [] === $ids ) {
			return new \WP_Error( 'moforcoupon_not_found', __( '找不到任何符合的優惠券。', 'moforcoupon' ) );
		}
		return [
			'ids'     => $ids,
			'date'    => gmdate( 'Y-m-d', $ts ),
			'summary' => sprintf(
				/* translators: 1: count, 2: date. */
				__( '將 %1$d 張優惠券的到期日延長至 %2$s', 'moforcoupon' ),
				count( $ids ),
				gmdate( 'Y-m-d', $ts )
			),
		];
	}

	/**
	 * @param array<string,mixed> $params
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function extend_expiry_apply( array $params ) {
		if ( ! current_user_can( self::CAP ) ) {
			return self::denied();
		}
		$ids  = isset( $params['ids'] ) && is_array( $params['ids'] ) ? $params['ids'] : [];
		$date = (string) ( $params['date'] ?? '' );
		$done = 0;
		foreach ( $ids as $id ) {
			$result = CouponService::save( [ 'date_expires' => $date ], (int) $id );
			if ( ! ( $result instanceof \WP_Error ) ) {
				++$done;
			}
		}
		return [
			'reply' => sprintf(
				/* translators: %d: number updated. */
				__( '已更新 %d 張優惠券的到期日。', 'moforcoupon' ),
				$done
			),
		];
	}

	/* ---------------- duplicate ---------------- */

	/**
	 * @param mixed $input
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function duplicate_prepare( $input ) {
		if ( ! current_user_can( self::CAP ) ) {
			return self::denied();
		}
		$input = is_array( $input ) ? $input : [];
		$id    = CouponService::resolve_id( $input['code_or_id'] ?? '' );
		if ( ! $id ) {
			return new \WP_Error( 'moforcoupon_not_found', __( '找不到該優惠券。', 'moforcoupon' ) );
		}
		$data = CouponService::get( $id );
		$code = (string) ( $data['code'] ?? $id );
		return [
			'source_id'   => $id,
			'source_code' => $code,
			/* translators: %s: source coupon code. */
			'summary'     => sprintf( __( '複製優惠券 %s 為新草稿(沿用所有設定與條件,使用次數歸零)', 'moforcoupon' ), $code ),
		];
	}

	/**
	 * @param array<string,mixed> $params
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function duplicate_apply( array $params ) {
		if ( ! current_user_can( self::CAP ) ) {
			return self::denied();
		}
		$source_id = (int) ( $params['source_id'] ?? 0 );
		$src       = new \WC_Coupon( $source_id );
		if ( ! $src->get_id() ) {
			return new \WP_Error( 'moforcoupon_not_found', __( '找不到該優惠券。', 'moforcoupon' ) );
		}

		$new_code = self::unique_code( strtoupper( $src->get_code() ) . '-COPY-' );
		if ( '' === $new_code ) {
			return new \WP_Error( 'moforcoupon_duplicate_failed', __( '無法產生唯一的新代碼。', 'moforcoupon' ) );
		}

		// New duplicate starts disabled (draft) for review before going live.
		$new_id = CouponService::duplicate( $source_id, $new_code, false );
		if ( is_wp_error( $new_id ) ) {
			return $new_id;
		}

		return [
			'id'    => (int) $new_id,
			'code'  => $new_code,
			'reply' => sprintf(
				/* translators: 1: source code, 2: new draft code. */
				__( '已將 %1$s 複製為草稿 %2$s(使用次數已歸零,啟用前可先檢查)。', 'moforcoupon' ),
				$src->get_code(),
				$new_code
			),
		];
	}

	/* ---------------- create tiered (friendly shortcut over create) ---------------- */

	/**
	 * Build a tiered percent coupon from a flat tiers spec and route it through create.
	 *
	 * @param mixed $input
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function create_tiered_prepare( $input ) {
		if ( ! current_user_can( self::CAP ) ) {
			return self::denied();
		}
		$input = is_array( $input ) ? $input : [];
		$rows  = isset( $input['tiers'] ) && is_array( $input['tiers'] ) ? $input['tiers'] : [];
		if ( [] === $rows ) {
			return new \WP_Error( 'moforcoupon_no_tiers', __( '請至少提供一階折扣(tiers)。', 'moforcoupon' ) );
		}
		$mode  = (string) ( $input['target_mode'] ?? 'cart' );
		$mode  = in_array( $mode, [ 'products', 'categories' ], true ) ? $mode : 'all';
		$basis = \MoksaWeb\Moforcoupon\Support\Tiers::basis( $input['basis'] ?? 'subtotal' );

		$create = [
			'code'          => (string) ( $input['code'] ?? '' ),
			'discount_type' => 'percent',
			'amount'        => 0,
			'moforcoupon'   => [
				'tiers' => [
					'enabled'           => true,
					'rows'              => $rows,
					'basis'             => $basis,
					'target_mode'       => $mode,
					'target_products'   => isset( $input['target_products'] ) && is_array( $input['target_products'] ) ? $input['target_products'] : [],
					'target_categories' => isset( $input['target_categories'] ) && is_array( $input['target_categories'] ) ? $input['target_categories'] : [],
				],
			],
		];
		foreach ( [ 'date_expires', 'usage_limit', 'usage_limit_per_user', 'description' ] as $k ) {
			if ( array_key_exists( $k, $input ) ) {
				$create[ $k ] = $input[ $k ];
			}
		}
		$result = self::create_prepare( $create );
		if ( is_array( $result ) && isset( $result['summary'] ) ) {
			$result['summary'] = sprintf(
				/* translators: 1: coupon code, 2: number of tiers. */
				__( '建立階梯折扣券 %1$s(%2$d 階,依購物車門檻給不同百分比)', 'moforcoupon' ),
				(string) ( $create['code'] ?: __( '(自動代碼)', 'moforcoupon' ) ),
				count( $rows )
			);
		}
		return $result;
	}

	/* ---------------- apply template ---------------- */

	/**
	 * @param mixed $input
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function apply_template_prepare( $input ) {
		if ( ! current_user_can( self::CAP ) ) {
			return self::denied();
		}
		$input = is_array( $input ) ? $input : [];
		$id    = isset( $input['template_id'] ) ? (string) $input['template_id'] : '';
		$tpl   = \MoksaWeb\Moforcoupon\Modules\Templates\Catalog::get( $id );
		if ( null === $tpl ) {
			return new \WP_Error( 'moforcoupon_template_unknown', __( '找不到指定的優惠券範本(用 list-templates 查可用 id)。', 'moforcoupon' ) );
		}
		$overrides = isset( $input['overrides'] ) && is_array( $input['overrides'] ) ? $input['overrides'] : [];
		return [
			'template_id' => $id,
			'overrides'   => $overrides,
			'summary'     => sprintf(
				/* translators: %s: template label. */
				__( '套用範本「%s」建立一張草稿優惠券', 'moforcoupon' ),
				(string) ( $tpl['label'] ?? $id )
			),
		];
	}

	/**
	 * @param array<string,mixed> $params
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function apply_template_apply( array $params ) {
		if ( ! current_user_can( self::CAP ) ) {
			return self::denied();
		}
		$id        = (string) ( $params['template_id'] ?? '' );
		$overrides = isset( $params['overrides'] ) && is_array( $params['overrides'] ) ? $params['overrides'] : [];
		$result    = Applier::apply( $id, $overrides );
		if ( $result instanceof \WP_Error ) {
			return $result;
		}
		$coupon = new \WC_Coupon( (int) $result );
		return [
			'id'    => (int) $result,
			'reply' => sprintf(
				/* translators: 1: coupon code, 2: coupon id. */
				__( '已依範本建立草稿優惠券 %1$s(#%2$d),啟用前可先檢查。', 'moforcoupon' ),
				$coupon->get_code(),
				(int) $result
			),
		];
	}

	/* ---------------- restore (untrash) ---------------- */

	/**
	 * @param mixed $input
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function restore_prepare( $input ) {
		if ( ! current_user_can( self::CAP ) ) {
			return self::denied();
		}
		$input = is_array( $input ) ? $input : [];
		$ref   = trim( (string) ( $input['code_or_id'] ?? '' ) );
		$id    = ctype_digit( $ref ) ? (int) $ref : 0;
		$post  = $id > 0 ? get_post( $id ) : null;
		if ( ! $post || 'shop_coupon' !== $post->post_type || 'trash' !== $post->post_status ) {
			return new \WP_Error( 'moforcoupon_not_trashed', __( '找不到垃圾桶中的該優惠券,請提供已刪除優惠券的數字 ID。', 'moforcoupon' ) );
		}
		return [
			'id'      => $id,
			'summary' => sprintf(
				/* translators: %s: coupon code. */
				__( '從垃圾桶還原優惠券 %s 為草稿(啟用前可先檢查)', 'moforcoupon' ),
				(string) ( $post->post_title ?: $id )
			),
		];
	}

	/**
	 * @param array<string,mixed> $params
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function restore_apply( array $params ) {
		if ( ! current_user_can( self::CAP ) ) {
			return self::denied();
		}
		$id = (int) ( $params['id'] ?? 0 );
		if ( $id <= 0 || ! wp_untrash_post( $id ) ) {
			return new \WP_Error( 'moforcoupon_restore_failed', __( '還原失敗。', 'moforcoupon' ) );
		}
		// Restore as a draft for review rather than whatever status it had before trashing.
		wp_update_post(
			[
				'ID'          => $id,
				'post_status' => 'draft',
			]
		);
		return [
			'id'    => $id,
			'reply' => __( '已從垃圾桶還原為草稿。', 'moforcoupon' ),
		];
	}

	/* ---------------- expire now ---------------- */

	/**
	 * @param mixed $input
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function expire_now_prepare( $input ) {
		if ( ! current_user_can( self::CAP ) ) {
			return self::denied();
		}
		$input = is_array( $input ) ? $input : [];
		$refs  = isset( $input['codes_or_ids'] ) && is_array( $input['codes_or_ids'] ) ? $input['codes_or_ids'] : [];
		$ids   = [];
		foreach ( $refs as $ref ) {
			$id = CouponService::resolve_id( $ref );
			if ( $id ) {
				$ids[] = $id;
			}
		}
		if ( [] === $ids ) {
			return new \WP_Error( 'moforcoupon_not_found', __( '找不到任何符合的優惠券。', 'moforcoupon' ) );
		}
		$yesterday = gmdate( 'Y-m-d', (int) strtotime( 'yesterday' ) );
		return [
			'ids'     => $ids,
			'date'    => $yesterday,
			'summary' => sprintf(
				/* translators: 1: count, 2: date. */
				__( '立即停用 %1$d 張優惠券(到期日設為 %2$s,即刻失效)', 'moforcoupon' ),
				count( $ids ),
				$yesterday
			),
		];
	}

	/**
	 * @param array<string,mixed> $params
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function expire_now_apply( array $params ) {
		if ( ! current_user_can( self::CAP ) ) {
			return self::denied();
		}
		$ids  = isset( $params['ids'] ) && is_array( $params['ids'] ) ? $params['ids'] : [];
		$date = (string) ( $params['date'] ?? gmdate( 'Y-m-d', (int) strtotime( 'yesterday' ) ) );
		$done = 0;
		foreach ( $ids as $id ) {
			$result = CouponService::save( [ 'date_expires' => $date ], (int) $id );
			if ( ! ( $result instanceof \WP_Error ) ) {
				++$done;
			}
		}
		return [
			'reply' => sprintf(
				/* translators: %d: number expired. */
				__( '已將 %d 張優惠券設為立即失效。', 'moforcoupon' ),
				$done
			),
		];
	}

	/* ---------------- bulk reschedule expiry (campaign management) ---------------- */

	/**
	 * @param mixed $input
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function bulk_reschedule_prepare( $input ) {
		if ( ! current_user_can( self::CAP ) ) {
			return self::denied();
		}
		$input = is_array( $input ) ? $input : [];
		$refs  = isset( $input['codes_or_ids'] ) && is_array( $input['codes_or_ids'] ) ? $input['codes_or_ids'] : [];
		$date  = trim( (string) ( $input['date_expires'] ?? '' ) );
		$ids   = [];
		foreach ( $refs as $ref ) {
			$id = CouponService::resolve_id( $ref );
			if ( $id ) {
				$ids[] = $id;
			}
		}
		if ( [] === $ids ) {
			return new \WP_Error( 'moforcoupon_not_found', __( '找不到任何符合的優惠券。', 'moforcoupon' ) );
		}
		if ( '' !== $date ) {
			$ts = strtotime( $date );
			if ( ! $ts ) {
				return new \WP_Error( 'moforcoupon_bad_date', __( '到期日格式不正確(請用 YYYY-MM-DD)。', 'moforcoupon' ) );
			}
			$date = gmdate( 'Y-m-d', $ts );
		}
		return [
			'ids'     => $ids,
			'date'    => $date,
			'summary' => '' === $date
				? sprintf(
					/* translators: %d: number of coupons. */
					__( '清除 %d 張優惠券的到期日(改為永久有效)', 'moforcoupon' ),
					count( $ids )
				)
				: sprintf(
					/* translators: 1: number of coupons, 2: new expiry date. */
					__( '將 %1$d 張優惠券的到期日設為 %2$s', 'moforcoupon' ),
					count( $ids ),
					$date
				),
		];
	}

	/**
	 * @param array<string,mixed> $params
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function bulk_reschedule_apply( array $params ) {
		if ( ! current_user_can( self::CAP ) ) {
			return self::denied();
		}
		$ids  = isset( $params['ids'] ) && is_array( $params['ids'] ) ? $params['ids'] : [];
		$date = (string) ( $params['date'] ?? '' );
		$done = 0;
		foreach ( $ids as $id ) {
			$result = CouponService::save( [ 'date_expires' => $date ], (int) $id );
			if ( ! ( $result instanceof \WP_Error ) ) {
				++$done;
			}
		}
		return [
			'reply' => sprintf(
				/* translators: %d: number updated. */
				__( '已更新 %d 張優惠券的到期日。', 'moforcoupon' ),
				$done
			),
		];
	}

	private static function unique_code( string $prefix ): string {
		return CouponService::unique_code( $prefix );
	}
}
