<?php
/**
 * Plugin Name:        Moksa Coupons for WooCommerce
 * Plugin URI:         https://moksaweb.com/moforcoupon
 * Description:        AI-powered WooCommerce coupon toolkit. Create coupons with natural language, plus BOGO, cart conditions, role restrictions, scheduling and URL coupons. Exposes coupon abilities to the WordPress Abilities API, AI Client and MCP.
 * Version:            0.5.0
 * Requires at least:  7.0
 * Tested up to:       7.0
 * Requires PHP:       8.2
 * Requires Plugins:   woocommerce
 * WC requires at least: 10.7
 * WC tested up to:    10.9
 * Author:             MoksaWeb
 * Author URI:         https://moksaweb.com/
 * License:            GPLv3
 * License URI:        https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:        moforcoupon
 * Domain Path:        /languages
 *
 * @package MoksaWeb\Moforcoupon
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/* Constants */
const MOFORCOUPON_VERSION    = '0.5.0';
const MOFORCOUPON_MIN_PHP    = '8.2';
const MOFORCOUPON_MIN_WP     = '7.0';
const MOFORCOUPON_MIN_WC     = '10.7';
const MOFORCOUPON_TEXTDOMAIN = 'moforcoupon';

define( 'MOFORCOUPON_PLUGIN_FILE', __FILE__ );
define( 'MOFORCOUPON_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MOFORCOUPON_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MOFORCOUPON_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/*
 * Autoload — prefer Composer's autoloader (dev / extra deps); otherwise fall back
 * to a built-in PSR-4 autoloader so core coupon features work without Composer.
 */
$moforcoupon_autoload = MOFORCOUPON_PLUGIN_DIR . 'vendor/autoload.php';
if ( is_readable( $moforcoupon_autoload ) ) {
	require_once $moforcoupon_autoload;
} else {
	spl_autoload_register(
		static function ( string $class_name ): void {
			$prefix = 'MoksaWeb\\Moforcoupon\\';
			$length = strlen( $prefix );
			if ( strncmp( $prefix, $class_name, $length ) !== 0 ) {
				return;
			}
			$relative = substr( $class_name, $length );
			$path     = MOFORCOUPON_PLUGIN_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';
			if ( is_readable( $path ) ) {
				require_once $path;
			}
		}
	);
}

/* HPOS + Block Checkout compatibility — must run before woocommerce_init */
add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				MOFORCOUPON_PLUGIN_FILE,
				true
			);
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'cart_checkout_blocks',
				MOFORCOUPON_PLUGIN_FILE,
				true
			);
		}
	}
);

/* Boot */
add_action(
	'plugins_loaded',
	static function (): void {
		\MoksaWeb\Moforcoupon\Plugin::instance()->boot();
	},
	5
);

/*
 * Deactivation: drop the URL-coupon /<endpoint>/ rewrite rule (flush_rewrite_rules
 * is impossible at uninstall, so it must happen here) and clear its version stamp.
 */
register_deactivation_hook(
	__FILE__,
	static function (): void {
		\MoksaWeb\Moforcoupon\Modules\UrlCoupons\Lifecycle::on_deactivate();
		\MoksaWeb\Moforcoupon\Support\Cron::clear();
	}
);
