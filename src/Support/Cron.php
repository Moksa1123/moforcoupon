<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Support;

defined( 'ABSPATH' ) || exit;

/**
 * The plugin's single daily WP-Cron heartbeat. Scheduling is always-on (registered by the core
 * Plugin) so the moforcoupon_cron_daily action always fires; individual modules attach their
 * time-based work (expiry reminders, birthday coupons, cleanup…) to that action only while they
 * are enabled. Keeping one shared event avoids each feature scheduling its own.
 */
final class Cron {

	/** Action fired once a day; modules hook this. */
	public const HOOK = 'moforcoupon_cron_daily';

	public static function register(): void {
		add_action( 'init', array( self::class, 'ensure_scheduled' ) );
	}

	/** Schedule the daily event if it isn't already queued. */
	public static function ensure_scheduled(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK );
		}
	}

	/** Remove the scheduled event (on deactivation / uninstall). */
	public static function clear(): void {
		wp_clear_scheduled_hook( self::HOOK );
	}
}
