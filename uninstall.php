<?php
/**
 * Uninstall cleanup for Moksa Coupons for WooCommerce.
 *
 * Removes every plugin option / transient (matched by the moforcoupon_ prefix, so new options are
 * covered automatically) and the plugin's own post-meta. Coupons themselves (shop_coupon CPT) are
 * intentionally left untouched — they are user content created via WooCommerce.
 *
 * @package MoksaWeb\Moforcoupon
 */

declare( strict_types=1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

/*
 * Prefix sweep of every plugin option + its transients. A prepared LIKE on the prefix removes all
 * moforcoupon_* options (module toggles, URL/cron/rewrite flags, remarketing config, AI settings…)
 * without an ever-stale hand-maintained list. Direct queries are the correct tool for one-shot
 * uninstall cleanup (no caching applies, and the data is being deleted).
 */
$moforcoupon_like    = $wpdb->esc_like( 'moforcoupon_' ) . '%';
$moforcoupon_t_like  = $wpdb->esc_like( '_transient_moforcoupon_' ) . '%';
$moforcoupon_tt_like = $wpdb->esc_like( '_transient_timeout_moforcoupon_' ) . '%';
foreach ( array( $moforcoupon_like, $moforcoupon_t_like, $moforcoupon_tt_like ) as $moforcoupon_pattern ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-shot uninstall cleanup; options table, prepared LIKE, nothing to cache.
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $moforcoupon_pattern ) );
}

/*
 * Delete the plugin's own coupon meta. uninstall.php has no PSR-4 autoloader, so load the Keys
 * class directly (guarded) and reuse its single source of truth.
 */
$moforcoupon_keys_file = __DIR__ . '/src/Coupon/Meta/Keys.php';
if ( is_readable( $moforcoupon_keys_file ) ) {
	require_once $moforcoupon_keys_file;
	if ( class_exists( \MoksaWeb\Moforcoupon\Coupon\Meta\Keys::class ) ) {
		foreach ( \MoksaWeb\Moforcoupon\Coupon\Meta\Keys::all() as $moforcoupon_meta_key ) {
			delete_post_meta_by_key( $moforcoupon_meta_key );
		}
	}
}

/*
 * Post-meta NOT in Keys::all(): the coupon-owner link (MyAccount) and the order-side stamps for
 * the remarketing + cashback runtimes.
 */
foreach ( array( '_moforcoupon_owner_user', '_moforcoupon_remarketing_issued', '_moforcoupon_cashback_awarded' ) as $moforcoupon_extra_meta ) {
	delete_post_meta_by_key( $moforcoupon_extra_meta );
}

// Per-user "recently used templates" list (stored as user meta on the templates page).
delete_metadata( 'user', 0, 'moforcoupon_recent_templates', '', true );

// Scheduled cron events.
wp_clear_scheduled_hook( 'moforcoupon_cron_daily' );

// Action Scheduler group cleanup (safe no-op if unused).
if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( '', array(), 'moforcoupon' );
}
