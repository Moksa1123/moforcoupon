<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\CartRecovery;

defined( 'ABSPATH' ) || exit;

/**
 * Storage for in-progress / abandoned carts as a private CPT — one post per email (the post title),
 * upserted as the shopper edits checkout. Status meta drives the recovery flow: pending → emailed →
 * recovered. Kept off the normal admin UI; purged after a while.
 */
final class Store {

	public const CPT = 'mfc_abandoned_cart';

	private const STATUS_META = '_moforcoupon_acart_status';
	private const ITEMS_META  = '_moforcoupon_acart_items';
	private const TOTAL_META  = '_moforcoupon_acart_total';

	public static function register_cpt(): void {
		register_post_type(
			self::CPT,
			array(
				'public'              => false,
				'show_ui'             => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'supports'            => array( 'title' ),
			)
		);
	}

	/** Find the (single) cart record for an email, or 0. */
	public static function find_by_email( string $email ): int {
		$email = strtolower( sanitize_email( $email ) );
		if ( '' === $email ) {
			return 0;
		}
		$ids = get_posts(
			array(
				'post_type'        => self::CPT,
				'post_status'      => 'private',
				'title'            => $email,
				'numberposts'      => 1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => false,
			)
		);
		return $ids ? (int) $ids[0] : 0;
	}

	/**
	 * Create / refresh the cart record for an email (resets to pending while still in progress).
	 *
	 * @param string                                 $email
	 * @param array<int,array{name:string,qty:int}> $items
	 * @param float                                  $total
	 */
	public static function upsert( string $email, array $items, float $total ): void {
		$email = strtolower( sanitize_email( $email ) );
		if ( '' === $email || ! is_email( $email ) ) {
			return;
		}
		$id      = self::find_by_email( $email );
		$postarr = array(
			'post_type'   => self::CPT,
			'post_title'  => $email,
			'post_status' => 'private',
		);
		if ( $id > 0 ) {
			$postarr['ID'] = $id;
		}
		$id = wp_insert_post( $postarr, true );
		if ( is_wp_error( $id ) || ! $id ) {
			return;
		}
		update_post_meta( $id, self::ITEMS_META, wp_json_encode( array_slice( $items, 0, 30 ) ) );
		update_post_meta( $id, self::TOTAL_META, wc_format_decimal( (string) $total ) );
		// Active again → back to pending (unless already recovered).
		if ( 'recovered' !== get_post_meta( $id, self::STATUS_META, true ) ) {
			update_post_meta( $id, self::STATUS_META, 'pending' );
		}
	}

	/**
	 * Pending carts last touched more than $seconds ago (i.e. abandoned).
	 *
	 * @return array<int,int>
	 */
	public static function pending_abandoned( int $seconds, int $limit = 50 ): array {
		$ids = get_posts(
			array(
				'post_type'   => self::CPT,
				'post_status' => 'private',
				'numberposts' => $limit,
				'fields'      => 'ids',
				'orderby'     => 'modified',
				'order'       => 'ASC',
				'date_query'  => array(
					array(
						'column' => 'post_modified_gmt',
						'before' => gmdate( 'Y-m-d H:i:s', time() - $seconds ),
					),
				),
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- hourly cron, bounded limit.
				'meta_query'  => array(
					array(
						'key'   => self::STATUS_META,
						'value' => 'pending',
					),
				),
			)
		);
		return array_map( 'intval', (array) $ids );
	}

	public static function email_of( int $id ): string {
		// Raw title — get_the_title() would prepend "Private: " for a private post and break is_email().
		return (string) get_post_field( 'post_title', $id );
	}

	/** @return array<int,array{name:string,qty:int}> */
	public static function items_of( int $id ): array {
		$raw     = (string) get_post_meta( $id, self::ITEMS_META, true );
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	public static function set_status( int $id, string $status ): void {
		update_post_meta( $id, self::STATUS_META, $status );
	}

	public static function mark_recovered_by_email( string $email ): void {
		$id = self::find_by_email( $email );
		if ( $id > 0 ) {
			self::set_status( $id, 'recovered' );
		}
	}

	/** Delete cart records older than N days (any status). */
	public static function purge( int $days = 30 ): void {
		$ids = get_posts(
			array(
				'post_type'   => self::CPT,
				'post_status' => 'private',
				'numberposts' => 200,
				'fields'      => 'ids',
				'date_query'  => array(
					array(
						'column' => 'post_modified_gmt',
						'before' => gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ),
					),
				),
			)
		);
		foreach ( array_map( 'intval', (array) $ids ) as $id ) {
			wp_delete_post( $id, true );
		}
	}
}
