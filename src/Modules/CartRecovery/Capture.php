<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\CartRecovery;

defined( 'ABSPATH' ) || exit;

/**
 * Captures an in-progress cart the moment the shopper enters their email at checkout (WooCommerce
 * fires woocommerce_checkout_update_order_review with the form data as they edit), so a cart that is
 * later abandoned can be recovered. No custom JS needed.
 */
final class Capture {

	/**
	 * @param mixed $posted_data URL-encoded checkout form data.
	 */
	public static function capture( $posted_data ): void {
		if ( is_admin() || ! function_exists( 'WC' ) ) {
			return;
		}
		$fields = array();
		parse_str( (string) $posted_data, $fields );
		$email = isset( $fields['billing_email'] ) ? sanitize_email( (string) $fields['billing_email'] ) : '';
		if ( '' === $email || ! is_email( $email ) ) {
			return;
		}
		$cart = WC()->cart;
		if ( null === $cart || $cart->is_empty() ) {
			return;
		}

		$items = array();
		foreach ( $cart->get_cart() as $cart_item ) {
			$product = $cart_item['data'] ?? null;
			$items[] = array(
				'name' => $product instanceof \WC_Product ? $product->get_name() : '',
				'qty'  => (int) ( $cart_item['quantity'] ?? 1 ),
			);
		}
		Store::upsert( $email, $items, (float) $cart->get_cart_contents_total() );
	}
}
