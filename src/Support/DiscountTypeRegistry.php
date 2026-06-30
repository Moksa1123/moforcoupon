<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Support;

defined( 'ABSPATH' ) || exit;

/**
 * The single registry of every moforcoupon-aware coupon discount type. Slug, the three
 * display labels, and the "special-price" flag used to live in five hand-kept places
 * (CouponType, CouponCard::badge, SpecialPriceTypes::TYPES and each module's
 * Type::add_type) which drifted apart — e.g. the BOGO editor label read "買 X 送 Y(BOGO)"
 * while its card badge read "買 X 送 Y". Everything now reads from definitions() here, so a
 * type is described in exactly one row.
 *
 * Three label contexts are intentionally distinct (a row may differ in each):
 *  - label : the customer/report label (cards' long form, reports, emails).
 *  - badge : the compact pill on a coupon card (defaults to label).
 *  - admin : the coupon-editor "Discount type" dropdown label, which may carry the English
 *            mechanic name for admin clarity (defaults to label).
 */
final class DiscountTypeRegistry {

	/**
	 * @return array<int,array{slug:string,label:string,badge?:string,admin?:string,special?:bool}>
	 */
	private static function definitions(): array {
		return array(
			array(
				'slug'  => 'percent',
				'label' => __( '百分比折扣', 'moforcoupon' ),
			),
			array(
				'slug'  => 'fixed_cart',
				'label' => __( '購物車固定折抵', 'moforcoupon' ),
				'badge' => __( '購物車折抵', 'moforcoupon' ),
			),
			array(
				'slug'  => 'fixed_product',
				'label' => __( '商品固定折抵', 'moforcoupon' ),
				'badge' => __( '商品折抵', 'moforcoupon' ),
			),
			array(
				'slug'    => 'moforcoupon_bogo',
				'label'   => __( '買 X 送 Y', 'moforcoupon' ),
				'admin'   => __( '買 X 送 Y(BOGO)', 'moforcoupon' ),
				'special' => true,
			),
			array(
				'slug'    => 'moforcoupon_nth_item',
				'label'   => __( '第 N 件折扣', 'moforcoupon' ),
				'special' => true,
			),
			array(
				'slug'    => 'moforcoupon_mixmatch',
				'label'   => __( '任選優惠', 'moforcoupon' ),
				'admin'   => __( '任選優惠(Mix & Match)', 'moforcoupon' ),
				'special' => true,
			),
			array(
				'slug'  => 'moforcoupon_cashback',
				'label' => __( '回饋金', 'moforcoupon' ),
				'admin' => __( '回饋金 / 點數(Cashback)', 'moforcoupon' ),
			),
		);
	}

	/** @return array<string,array{slug:string,label:string,badge?:string,admin?:string,special?:bool}> slug => row. */
	private static function by_slug(): array {
		$out = array();
		foreach ( self::definitions() as $row ) {
			$out[ $row['slug'] ] = $row;
		}
		return $out;
	}

	/** @return array<string,string> slug => customer/report label. */
	public static function labels(): array {
		$out = array();
		foreach ( self::definitions() as $row ) {
			$out[ $row['slug'] ] = $row['label'];
		}
		return $out;
	}

	/**
	 * Customer/report label for a slug. Unknown slugs fall back to WooCommerce's own
	 * registered label, then to the raw slug as a last resort.
	 */
	public static function label( string $slug ): string {
		$rows = self::by_slug();
		if ( isset( $rows[ $slug ] ) ) {
			return $rows[ $slug ]['label'];
		}
		$wc = function_exists( 'wc_get_coupon_types' ) ? wc_get_coupon_types() : array();
		if ( isset( $wc[ $slug ] ) ) {
			return (string) $wc[ $slug ];
		}
		return '' !== $slug ? $slug : '—';
	}

	/** Compact card-badge label for a slug (defaults to the customer label). */
	public static function badge( string $slug ): string {
		$rows = self::by_slug();
		if ( ! isset( $rows[ $slug ] ) ) {
			return self::label( $slug );
		}
		return $rows[ $slug ]['badge'] ?? $rows[ $slug ]['label'];
	}

	/** Coupon-editor "Discount type" dropdown label for a slug (defaults to the customer label). */
	public static function admin_label( string $slug ): string {
		$rows = self::by_slug();
		if ( ! isset( $rows[ $slug ] ) ) {
			return self::label( $slug );
		}
		return $rows[ $slug ]['admin'] ?? $rows[ $slug ]['label'];
	}

	/** @return array<int,string> Slugs whose discount is applied via set_price (not WC's amount engine). */
	public static function special_slugs(): array {
		$out = array();
		foreach ( self::definitions() as $row ) {
			if ( ! empty( $row['special'] ) ) {
				$out[] = $row['slug'];
			}
		}
		return $out;
	}

	/** Whether a slug is a set_price special-price type. */
	public static function is_special_slug( string $slug ): bool {
		return in_array( $slug, self::special_slugs(), true );
	}

	/** @return array<int,string> Every slug the plugin knows about. */
	public static function known_slugs(): array {
		return array_keys( self::by_slug() );
	}

	/** The slug itself when the plugin knows it, otherwise the catch-all 'other'. */
	public static function type_key( string $slug ): string {
		return isset( self::by_slug()[ $slug ] ) ? $slug : 'other';
	}
}
