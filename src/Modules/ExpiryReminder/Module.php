<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\ExpiryReminder;

use MoksaWeb\Moforcoupon\Modules\AbstractModule;
use MoksaWeb\Moforcoupon\Support\Cron;

defined( 'ABSPATH' ) || exit;

/**
 * "優惠券到期提醒" module — lazy-loaded, boots only when moforcoupon_expiry_enabled is 'yes'.
 * Once a day (via the shared cron heartbeat) it emails customers whose personal coupons are about
 * to expire, driving urgency on the coupons that the MyAccount / remarketing flows handed them.
 */
final class Module extends AbstractModule {

	public function slug(): string {
		return 'expiry';
	}

	public function label(): string {
		return __( '優惠券到期提醒', 'moforcoupon' );
	}

	public function category(): string {
		return 'coupon';
	}

	public function tagline(): string {
		return __( '每天自動 Email 提醒顧客「專屬優惠券即將到期」,鼓勵在期限前使用', 'moforcoupon' );
	}

	public function boot(): void {
		add_action( Cron::HOOK, array( Runtime::class, 'run' ) );
	}
}
