<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\Bogo;

defined( 'ABSPATH' ) || exit;

/**
 * BOGO runtime: turns a 'moforcoupon_bogo' coupon into a real discount by lowering
 * the price of reward line items already in the cart (set_price), never via WC's
 * coupon-amount engine. Works for classic AND Block/Store-API because both run the
 * standard WC_Cart calculation.
 *
 * Anti-compounding: the blended reward price is ALWAYS recomputed from the product's
 * untouched catalog price (regular/sale), which set_price() never mutates, so the
 * hook is idempotent no matter how many times WooCommerce recalculates in a request.
 *
 * Keeping the 0-nominal coupon applied: we register NO get_discount_amount filter
 * (WC returns 0 for an unknown type and does not drop a 0-discount coupon) and never
 * throw in is_valid for a 0 discount — only to enforce one BOGO coupon per cart.
 */
final class Frontend {

	private const ORDER_META       = '_moforcoupon_bogo_order_discounts';
	private const COUPON_LINE_META = '_moforcoupon_bogo_coupon_discount';

	/** @var array<string,array<string,array{name:string,quantity:int,total:float}>> code => key => savings record. */
	private static array $price_display = array();

	/** @var array<string,string> code => eligibility-notice text (trigger met, reward missing). */
	private static array $notices = array();

	public static function boot(): void {
		add_action( 'woocommerce_before_calculate_totals', array( self::class, 'apply' ), (int) apply_filters( 'moforcoupon_bogo_priority', 11 ) );
		add_filter( 'woocommerce_coupon_is_valid', array( self::class, 'is_valid' ), 10, 2 );
		add_filter( 'woocommerce_cart_totals_coupon_html', array( self::class, 'coupon_html' ), 10, 3 );
		// BOGO savings come from set_price (not a coupon discount line), so feed them into
		// the shared savings summary when that module is on.
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
			self::$price_display = array();
			self::$notices       = array();
			return;
		}

		// First applied BOGO coupon wins (one per cart — is_valid rejects the rest).
		$code = '';
		foreach ( $applied as $applied_code ) {
			$coupon = new \WC_Coupon( $applied_code );
			if ( $coupon->is_type( BogoMeta::TYPE ) ) {
				$code = $applied_code;
				break;
			}
		}
		if ( '' === $code ) {
			self::$price_display = array();
			self::$notices       = array();
			return;
		}

		$coupon = new \WC_Coupon( $code );
		$cfg    = BogoMeta::read( $coupon->get_id() );

		$lines = array();
		foreach ( $cart->get_cart() as $key => $item ) {
			$product = isset( $item['data'] ) ? $item['data'] : null;
			if ( ! $product instanceof \WC_Product ) {
				continue;
			}
			$role = self::classify( $product, $cfg );
			if ( 'none' === $role ) {
				continue;
			}
			$lines[] = array(
				'key'   => (string) $key,
				'qty'   => (int) $item['quantity'],
				'price' => self::base_price( (string) $key, $product ),
				'role'  => $role,
			);
		}

		$plan = BogoCalc::compute(
			array(
				'trigger_qty'  => $cfg['trigger_qty'],
				'reward_qty'   => $cfg['reward_qty'],
				'reward_mode'  => $cfg['reward_mode'],
				'reward_value' => $cfg['reward_value'],
				'deal_mode'    => $cfg['deal_mode'],
				'repeat_limit' => $cfg['repeat_limit'],
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
		self::$notices       = $plan['reward_short'] ? array( $code => self::notice_text( $cfg, $coupon ) ) : array();
	}

	/**
	 * The product's catalog price, unaffected by our own set_price (which sets only the
	 * 'price' prop). Memoized per cart-item-key for the request: regular/sale prices are
	 * never mutated by set_price, but the last-resort get_price() fallback (custom product
	 * types with no regular/sale price) WOULD read our mutated price on a later
	 * before_calculate_totals pass and compound — the memo captures it on the first
	 * (untouched) pass so the base stays stable across recalcs.
	 *
	 * @var array<string,float>
	 */
	private static array $base_memo = array();

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

	private static function classify( \WC_Product $product, array $cfg ): string {
		$pid       = $product->get_id();
		$parent_id = $product->get_parent_id();
		if ( self::matches( $pid, $parent_id, $cfg['trigger_product_ids'], $cfg['trigger_category_ids'] ) ) {
			return 'trigger';
		}
		if ( self::matches( $pid, $parent_id, $cfg['reward_product_ids'], $cfg['reward_category_ids'] ) ) {
			return 'reward';
		}
		return 'none';
	}

	/**
	 * @param int            $pid          Product ID.
	 * @param int            $parent_id    Parent product ID (variations), or 0.
	 * @param array<int,int> $product_ids  Configured product IDs.
	 * @param array<int,int> $category_ids Configured category term IDs.
	 */
	private static function matches( int $pid, int $parent_id, array $product_ids, array $category_ids ): bool {
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

	/* ---------------- validity (one BOGO per cart, keep 0-discount alive) ---------------- */

	/**
	 * @param mixed $valid
	 * @param mixed $coupon
	 * @return mixed
	 * @throws \Exception When a second concurrent BOGO coupon is rejected (one per cart).
	 */
	public static function is_valid( $valid, $coupon ) {
		if ( ! $coupon instanceof \WC_Coupon || ! $coupon->is_type( BogoMeta::TYPE ) ) {
			return $valid;
		}
		if ( function_exists( 'WC' ) && WC()->cart instanceof \WC_Cart ) {
			foreach ( WC()->cart->get_applied_coupons() as $applied_code ) {
				// get_applied_coupons() returns normalized (lowercased) codes; compare
				// case-insensitively so a mixed-case stored code still matches "self".
				if ( strtolower( (string) $applied_code ) === strtolower( $coupon->get_code() ) ) {
					break; // Reached self first → this is the kept one.
				}
				$other = new \WC_Coupon( $applied_code );
				if ( $other->is_type( BogoMeta::TYPE ) ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- esc_html applied.
					throw new \Exception( esc_html__( '每次只能使用一張買 X 送 Y 優惠券。', 'moforcoupon' ) );
				}
			}
		}
		return $valid; // 0 nominal discount is fine — never throw for that.
	}

	/* ---------------- classic display ---------------- */

	/**
	 * @param mixed $html
	 * @param mixed $coupon
	 * @param mixed $discount_html
	 * @return mixed
	 */
	public static function coupon_html( $html, $coupon, $discount_html = '' ) {
		if ( ! $coupon instanceof \WC_Coupon || ! $coupon->is_type( BogoMeta::TYPE ) ) {
			return $html;
		}
		$code = $coupon->get_code();

		// Strip the cosmetic $0.00 amount when the BOGO coupon contributes no WC discount line.
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
			return $html . '<div class="moforcoupon-bogo-hint" style="margin:6px 0 0;font-size:.9em;color:#996800;">' . esc_html( self::$notices[ $code ] ) . '</div>';
		}
		return $html;
	}

	/**
	 * Contribute this request's BOGO savings (set_price reductions, which never appear as a
	 * coupon discount line) to the shared savings-summary total.
	 *
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
		return '<ul class="moforcoupon-bogo-summary" style="margin:6px 0 0;font-size:.9em;list-style:none;padding:0;">' . $rows . '</ul>';
	}

	private static function notice_text( array $cfg, \WC_Coupon $coupon ): string {
		$msg = trim( (string) ( $cfg['notice_msg'] ?? '' ) );
		if ( '' === $msg ) {
			/* translators: %s: coupon code. */
			$msg = sprintf( __( '您已符合「%s」的買 X 送 Y 資格 —— 把贈品加入購物車即可享有折扣。', 'moforcoupon' ), $coupon->get_code() );
		}
		return str_replace(
			array( '{bogo_qty}', '{coupon_code}' ),
			array( (string) ( $cfg['reward_qty'] ?? 1 ), $coupon->get_code() ),
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

		self::$price_display = array();
		self::$notices       = array();
	}
}
