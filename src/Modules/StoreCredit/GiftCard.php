<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\StoreCredit;

use MoksaWeb\Moforcoupon\Coupon\CouponService;
use MoksaWeb\Moforcoupon\Modules\CouponSend\SendService;
use MoksaWeb\Moforcoupon\Support\OrderOnce;
use MoksaWeb\Moforcoupon\Support\PersonalCoupon;

defined( 'ABSPATH' ) || exit;

/**
 * Sell store credit as a product. Flag a product as a "gift card" on its edit screen; when an order
 * containing it is paid, the amount becomes store credit for the recipient (an email entered at
 * checkout, or the buyer). A recipient who has no account instead receives a one-off gift coupon by
 * email, so the value is never lost. Reuses the wallet, the coupon issuer and the send service.
 */
final class GiftCard {

	public const PRODUCT_META    = '_moforcoupon_giftcard';
	private const RECIPIENT_META = '_moforcoupon_giftcard_recipient';
	private const ISSUED_META    = '_moforcoupon_giftcard_issued';

	public static function register(): void {
		add_action( 'woocommerce_product_options_general_product_data', array( self::class, 'product_field' ) );
		add_action( 'woocommerce_admin_process_product_object', array( self::class, 'save_product_field' ) );
		add_action( 'woocommerce_after_order_notes', array( self::class, 'checkout_field' ) );
		add_action( 'woocommerce_checkout_create_order', array( self::class, 'save_checkout_field' ) );
		add_action( 'woocommerce_order_status_completed', array( self::class, 'fulfil' ) );
		add_action( 'woocommerce_order_status_processing', array( self::class, 'fulfil' ) );
	}

	public static function product_field(): void {
		if ( function_exists( 'woocommerce_wp_checkbox' ) ) {
			woocommerce_wp_checkbox(
				array(
					'id'          => self::PRODUCT_META,
					'label'       => __( '禮品卡 / 儲值', 'moforcoupon' ),
					'description' => __( '購買此商品後,金額會成為顧客的儲值金(結帳時自動折抵)。', 'moforcoupon' ),
				)
			);
		}
	}

	/**
	 * @param mixed $product
	 */
	public static function save_product_field( $product ): void {
		if ( ! $product instanceof \WC_Product ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the product-save nonce before this hook fires.
		$product->update_meta_data( self::PRODUCT_META, isset( $_POST[ self::PRODUCT_META ] ) ? 'yes' : 'no' );
	}

	/**
	 * @param mixed $checkout
	 */
	public static function checkout_field( $checkout ): void {
		if ( function_exists( 'woocommerce_form_field' ) ) {
			woocommerce_form_field(
				self::RECIPIENT_META,
				array(
					'type'     => 'email',
					'label'    => __( '禮品卡收件人 Email(購買禮品卡時填;留空則加值給自己)', 'moforcoupon' ),
					'required' => false,
					'class'    => array( 'form-row-wide' ),
				),
				''
			);
		}
	}

	/**
	 * @param mixed $order
	 */
	public static function save_checkout_field( $order ): void {
		if ( ! $order instanceof \WC_Order ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the checkout nonce before this hook fires.
		$email = isset( $_POST[ self::RECIPIENT_META ] ) ? sanitize_email( wp_unslash( $_POST[ self::RECIPIENT_META ] ) ) : '';
		if ( '' !== $email && is_email( $email ) ) {
			$order->update_meta_data( self::RECIPIENT_META, $email );
		}
	}

	/**
	 * @param mixed $order_id
	 */
	public static function fulfil( $order_id ): void {
		$order = OrderOnce::get( $order_id );
		if ( null === $order || OrderOnce::done( $order, self::ISSUED_META ) ) {
			return;
		}

		$amount = 0.0;
		foreach ( $order->get_items() as $item ) {
			if ( $item instanceof \WC_Order_Item_Product
				&& 'yes' === get_post_meta( (int) $item->get_product_id(), self::PRODUCT_META, true ) ) {
				$amount += (float) $item->get_total() + (float) $item->get_total_tax();
			}
		}
		if ( $amount <= 0.0 ) {
			return;
		}

		$recipient = (string) $order->get_meta( self::RECIPIENT_META );
		$buyer     = (int) $order->get_customer_id();
		$note      = sprintf(
			/* translators: %s: order number. */
			__( '訂單 #%s 禮品卡加值', 'moforcoupon' ),
			$order->get_order_number()
		);

		if ( '' !== $recipient && is_email( $recipient ) ) {
			$user = get_user_by( 'email', $recipient );
			if ( $user ) {
				Wallet::credit( (int) $user->ID, $amount, $note );
			} else {
				self::email_gift_coupon( $recipient, $amount );
			}
		} elseif ( $buyer > 0 ) {
			Wallet::credit( $buyer, $amount, $note );
		} else {
			self::email_gift_coupon( (string) $order->get_billing_email(), $amount );
		}

		OrderOnce::mark( $order, self::ISSUED_META, wc_format_decimal( (string) $amount ) );
	}

	/** Recipient has no account → issue a one-off fixed-cart gift coupon and email it. */
	private static function email_gift_coupon( string $email, float $amount ): void {
		if ( '' === $email || ! is_email( $email ) ) {
			return;
		}
		$code = CouponService::unique_code( 'GIFT-' );
		if ( '' === $code ) {
			return;
		}
		$coupon = new \WC_Coupon( 0 );
		$coupon->set_code( $code );
		$coupon->set_discount_type( 'fixed_cart' );
		$coupon->set_amount( round( $amount, 2 ) );
		$coupon->set_description( __( '禮品卡', 'moforcoupon' ) );
		$coupon->set_email_restrictions( PersonalCoupon::merge_email( array(), $email ) );
		$new_id = $coupon->save();
		if ( ! $new_id ) {
			return;
		}
		$user = get_user_by( 'email', $email );
		if ( $user ) {
			update_post_meta( (int) $new_id, PersonalCoupon::OWNER_META, (int) $user->ID );
		}
		if ( class_exists( SendService::class ) ) {
			SendService::send( (int) $new_id, $email, __( '您收到一張禮品卡!', 'moforcoupon' ), false );
		}
	}
}
