<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\NthItem;

use MoksaWeb\Moforcoupon\Support\SpecialPriceTypes;

defined( 'ABSPATH' ) || exit;

/**
 * Nth-item runtime: turns a 'moforcoupon_nth_item' coupon into a real discount by lowering the
 * price of the discounted units already in the cart (set_price), never via WC's coupon-amount
 * engine. Works for classic AND Block/Store-API because both run the standard WC_Cart calculation.
 *
 * Anti-compounding: the blended price is ALWAYS recomputed from the product's untouched catalog
 * price (regular/sale), memoized per cart-item-key, so the hook is idempotent across recalcs.
 *
 * Keeping the 0-nominal coupon applied: NO get_discount_amount filter (WC returns 0 for an unknown
 * type and does not drop a 0-discount coupon); is_valid only throws to enforce one such coupon
 * per cart, never for a 0 discount.
 */
final class Frontend {

	private const ORDER_META       = '_moforcoupon_nth_order_discounts';
	private const COUPON_LINE_META = '_moforcoupon_nth_coupon_discount';

	/** @var array<string,array<string,array{name:string,quantity:int,total:float}>> code => key => savings record. */
	private static array $price_display = array();

	/** @var array<string,string> code => "再買 X 件" notice text. */
	private static array $notices = array();

	/** @var array<string,float> Per cart-item-key base price memo (anti-compounding). */
	private static array $base_memo = array();

	public static function boot(): void {
		add_action( 'woocommerce_before_calculate_totals', array( self::class, 'apply' ), (int) apply_filters( 'moforcoupon_nthitem_priority', 11 ) );
		add_filter( 'woocommerce_coupon_is_valid', array( self::class, 'is_valid' ), 10, 2 );
		add_filter( 'woocommerce_cart_totals_coupon_html', array( self::class, 'coupon_html' ), 10, 3 );
		add_filter( 'moforcoupon_cart_savings_total', array( self::class, 'add_to_savings' ), 10, 1 );
		add_action( 'woocommerce_checkout_order_processed', array( self::class, 'on_order_processed' ), 10, 1 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( self::class, 'on_block_order' ), 10, 1 );
	}

	/* ---------------- apply (the engine) ---------------- */

	/**
	 * @param mixed $cart WC_Cart passed by the hook.
	 */
	public static function apply( $cart ): void {
		if ( ! $cart instanceof \WC_Cart ) {
			return;
		}
		$applied = $cart->get_applied_coupons();
		if ( empty( $applied ) ) {
			self::reset();
			return;
		}

		$code = '';
		foreach ( $applied as $applied_code ) {
			$coupon = new \WC_Coupon( $applied_code );
			if ( $coupon->is_type( NthItemMeta::TYPE ) ) {
				$code = $applied_code;
				break;
			}
		}
		if ( '' === $code ) {
			self::reset();
			return;
		}

		$coupon = new \WC_Coupon( $code );
		$cfg    = NthItemMeta::read( $coupon->get_id() );

		$lines = array();
		foreach ( $cart->get_cart() as $key => $item ) {
			$product = isset( $item['data'] ) ? $item['data'] : null;
			if ( ! $product instanceof \WC_Product ) {
				continue;
			}
			if ( ! self::in_set( $product, $cfg ) ) {
				continue;
			}
			$lines[] = array(
				'key'   => (string) $key,
				'qty'   => (int) $item['quantity'],
				'price' => self::base_price( (string) $key, $product ),
			);
		}

		$plan = NthItemCalc::compute(
			array(
				'n'            => $cfg['n'],
				'reward_mode'  => $cfg['reward_mode'],
				'reward_value' => $cfg['reward_value'],
				'deal_mode'    => $cfg['deal_mode'],
				'repeat_limit' => $cfg['repeat_limit'],
				'group_by'     => $cfg['group_by'],
			),
			$lines
		);

		$display = array();
		foreach ( $plan['rewards'] as $key => $reward ) {
			$item = $cart->get_cart_item( (string) $key );
			if ( ! $item || ! isset( $item['data'] ) || ! $item['data'] instanceof \WC_Product ) {
				continue;
			}
			$item['data']->set_price( $reward['blended_price'] );
			$display[ (string) $key ] = array(
				'name'     => $item['data']->get_name(),
				'quantity' => (int) $reward['disc_qty'],
				'total'    => (float) $reward['unit_discount'] * (int) $reward['disc_qty'],
			);
		}

		self::$price_display = array() === $display ? array() : array( $code => $display );
		self::$notices       = $plan['short'] ? array( $code => self::notice_text( $cfg, $coupon ) ) : array();
	}

	/**
	 * The product's catalog price, unaffected by our own set_price. Memoized per cart-item-key so
	 * the base stays stable across the multiple before_calculate_totals passes in a request.
	 */
	private static function base_price( string $key, \WC_Product $product ): float {
		if ( isset( self::$base_memo[ $key ] ) ) {
			return self::$base_memo[ $key ];
		}
		$base = $product->is_on_sale() ? $product->get_sale_price() : $product->get_regular_price();
		if ( '' === $base || ! is_numeric( $base ) ) {
			$base = $product->get_regular_price();
		}
		if ( '' === $base || ! is_numeric( $base ) ) {
			$base = $product->get_price();
		}
		$value                   = max( 0.0, (float) $base );
		self::$base_memo[ $key ] = $value;
		return $value;
	}

	/** Whether a product is in the coupon's set. Empty config (no products AND no categories) = all. */
	private static function in_set( \WC_Product $product, array $cfg ): bool {
		$product_ids  = $cfg['product_ids'];
		$category_ids = $cfg['category_ids'];
		if ( array() === $product_ids && array() === $category_ids ) {
			return true;
		}
		$pid       = $product->get_id();
		$parent_id = $product->get_parent_id();
		if ( in_array( $pid, $product_ids, true ) || ( $parent_id && in_array( $parent_id, $product_ids, true ) ) ) {
			return true;
		}
		if ( ! empty( $category_ids ) && function_exists( 'wc_get_product_cat_ids' ) ) {
			$terms = wc_get_product_cat_ids( $parent_id ? $parent_id : $pid );
			if ( array_intersect( $category_ids, $terms ) ) {
				return true;
			}
		}
		return false;
	}

	private static function reset(): void {
		self::$price_display = array();
		self::$notices       = array();
	}

	/* ---------------- validity (one per cart, keep 0-discount alive) ---------------- */

	/**
	 * @param mixed $valid
	 * @param mixed $coupon
	 * @return mixed
	 * @throws \Exception When another special-price coupon already holds the cart (one per cart).
	 */
	public static function is_valid( $valid, $coupon ) {
		if ( ! $coupon instanceof \WC_Coupon || ! $coupon->is_type( NthItemMeta::TYPE ) ) {
			return $valid;
		}
		// At most one set_price special-price coupon (BOGO / Nth-item / Mix & Match) per cart, so
		// overlapping item sets cannot clobber each other's prices.
		SpecialPriceTypes::assert_single( $coupon );
		return $valid;
	}

	/* ---------------- classic display ---------------- */

	/**
	 * @param mixed $html
	 * @param mixed $coupon
	 * @param mixed $discount_html
	 * @return mixed
	 */
	public static function coupon_html( $html, $coupon, $discount_html = '' ) {
		if ( ! $coupon instanceof \WC_Coupon || ! $coupon->is_type( NthItemMeta::TYPE ) ) {
			return $html;
		}
		$code = $coupon->get_code();

		if ( is_string( $html ) && '' !== (string) $discount_html && function_exists( 'WC' ) && WC()->cart instanceof \WC_Cart ) {
			$amount = (float) WC()->cart->get_coupon_discount_amount( $code, WC()->cart->display_cart_ex_tax );
			if ( 0.0 === $amount ) {
				$html = str_replace( $discount_html, '', $html );
			}
		}

		$summary = self::summary_html( $code );
		if ( '' !== $summary ) {
			return $html . $summary;
		}
		if ( isset( self::$notices[ $code ] ) ) {
			return $html . '<div class="moforcoupon-nth-hint" style="margin:6px 0 0;font-size:.9em;color:#996800;">' . esc_html( self::$notices[ $code ] ) . '</div>';
		}
		return $html;
	}

	/**
	 * @param mixed $total Running savings total.
	 * @return float
	 */
	public static function add_to_savings( $total ): float {
		$extra = 0.0;
		foreach ( self::$price_display as $records ) {
			foreach ( $records as $record ) {
				$extra += (float) ( $record['total'] ?? 0 );
			}
		}
		return (float) $total + $extra;
	}

	private static function summary_html( string $code ): string {
		if ( empty( self::$price_display[ $code ] ) ) {
			return '';
		}
		$rows = '';
		foreach ( self::$price_display[ $code ] as $record ) {
			$rows .= sprintf(
				'<li>%1$s × %2$d: −%3$s</li>',
				esc_html( $record['name'] ),
				(int) $record['quantity'],
				wp_kses_post( wc_price( (float) $record['total'] ) )
			);
		}
		return '<ul class="moforcoupon-nth-summary" style="margin:6px 0 0;font-size:.9em;list-style:none;padding:0;">' . $rows . '</ul>';
	}

	private static function notice_text( array $cfg, \WC_Coupon $coupon ): string {
		$msg = trim( (string) ( $cfg['notice_msg'] ?? '' ) );
		if ( '' === $msg ) {
			/* translators: %d: required item count N. */
			$msg = sprintf( __( '湊滿 %d 件即可享第 N 件折扣。', 'moforcoupon' ), (int) ( $cfg['n'] ?? 2 ) );
		}
		return str_replace(
			array( '{nth_n}', '{coupon_code}' ),
			array( (string) ( $cfg['n'] ?? 2 ), $coupon->get_code() ),
			$msg
		);
	}

	/* ---------------- order persistence (classic + block) ---------------- */

	/**
	 * @param mixed $order_id
	 */
	public static function on_order_processed( $order_id ): void {
		$order = wc_get_order( (int) $order_id );
		if ( $order instanceof \WC_Order ) {
			self::persist( $order );
		}
	}

	/**
	 * @param mixed $order
	 */
	public static function on_block_order( $order ): void {
		if ( $order instanceof \WC_Order ) {
			self::persist( $order );
		}
	}

	private static function persist( \WC_Order $order ): void {
		if ( array() === self::$price_display ) {
			return;
		}
		$order->update_meta_data( self::ORDER_META, array_values( self::$price_display ) );

		foreach ( $order->get_items( 'coupon' ) as $line ) {
			if ( ! $line instanceof \WC_Order_Item_Coupon ) {
				continue;
			}
			$code = $line->get_code();
			if ( isset( self::$price_display[ $code ] ) ) {
				$total = array_sum( array_column( self::$price_display[ $code ], 'total' ) );
				$line->update_meta_data( self::COUPON_LINE_META, wc_format_decimal( (string) $total ) );
				$line->save();
			}
		}
		$order->save();
		self::reset();
	}
}
