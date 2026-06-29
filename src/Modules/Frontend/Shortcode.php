<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * The [moforcoupon_coupons] shortcode: a card wall of the merchant's advertised
 * coupons (code + type badge + discount + expiry + copy button + apply link).
 */
final class Shortcode {

	private const HANDLE = 'moforcoupon-coupon-cards';

	public static function register(): void {
		add_shortcode( 'moforcoupon_coupons', array( self::class, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'register_assets' ) );
	}

	public static function register_assets(): void {
		$css_rel = 'src/Modules/Frontend/assets/css/coupon-cards.css';
		$js_rel  = 'src/Modules/Frontend/assets/js/coupon-cards.js';
		wp_register_style(
			self::HANDLE,
			MOFORCOUPON_PLUGIN_URL . $css_rel,
			array(),
			self::ver( $css_rel )
		);
		wp_register_script(
			self::HANDLE,
			MOFORCOUPON_PLUGIN_URL . $js_rel,
			array(),
			self::ver( $js_rel ),
			true
		);
	}

	private static function ver( string $rel ): string {
		$path = MOFORCOUPON_PLUGIN_DIR . $rel;
		return file_exists( $path ) ? (string) filemtime( $path ) : MOFORCOUPON_VERSION;
	}

	/**
	 * @param mixed $atts
	 * @return string
	 */
	public static function render( $atts ): string {
		$atts  = shortcode_atts( array( 'limit' => 20 ), is_array( $atts ) ? $atts : array(), 'moforcoupon_coupons' );
		$limit = (int) $atts['limit'];

		// Assets are cheap and must register regardless of the cache outcome.
		wp_enqueue_style( self::HANDLE );
		wp_enqueue_script( self::HANDLE );

		// The card wall is identical for every visitor → serve from cache when warm,
		// skipping the meta_query + N WC_Coupon loads + per-card work entirely. Bypass the
		// cache when a multi-currency switcher filters the currency per request (the cards
		// embed wc_price() markup, which would otherwise be frozen to one currency).
		$cacheable = ! has_filter( 'woocommerce_currency' );

		if ( $cacheable ) {
			$cached = CardsCache::get( $limit );
			if ( null !== $cached ) {
				return $cached;
			}
		}

		$items = Catalog::query( $limit );

		if ( array() === $items ) {
			$empty = '<div class="moforcoupon-coupons moforcoupon-coupons--empty">' . esc_html__( '目前沒有可用的優惠券。', 'moforcoupon' ) . '</div>';
			if ( $cacheable ) {
				CardsCache::set( $limit, $empty, CardsCache::ttl_for( array(), time() ) );
			}
			return $empty;
		}

		$now          = time();
		$valid_untils = array();
		$cards        = '';
		foreach ( $items as $coupon ) {
			$expires        = $coupon->get_date_expires();
			$valid_untils[] = $expires ? $expires->getTimestamp() + DAY_IN_SECONDS : null;
			$cards         .= CouponCard::render( $coupon );
		}

		$html = '<div class="moforcoupon-coupons">' . $cards . '</div>';
		if ( $cacheable ) {
			CardsCache::set( $limit, $html, CardsCache::ttl_for( $valid_untils, $now ) );
		}
		return $html;
	}
}
