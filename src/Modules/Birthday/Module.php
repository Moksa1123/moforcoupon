<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\Birthday;

use MoksaWeb\Moforcoupon\Modules\AbstractModule;
use MoksaWeb\Moforcoupon\Support\Cron;

defined( 'ABSPATH' ) || exit;

/**
 * "生日優惠券" module — lazy-loaded, boots only when moforcoupon_birthday_enabled is 'yes'. Adds a
 * birthday field to the account page and, once a day, issues a birthday coupon (cloned from a
 * template) to customers whose birthday is today.
 */
final class Module extends AbstractModule {

	public function slug(): string {
		return 'birthday';
	}

	public function label(): string {
		return __( '生日優惠券', 'moforcoupon' );
	}

	public function category(): string {
		return 'coupon';
	}

	public function tagline(): string {
		return __( '顧客在帳戶填生日,生日當天自動發放一張專屬優惠券', 'moforcoupon' );
	}

	public function boot(): void {
		add_action( 'woocommerce_edit_account_form', array( AccountField::class, 'render' ) );
		add_action( 'woocommerce_save_account_details', array( AccountField::class, 'save' ) );
		add_action( Cron::HOOK, array( Runtime::class, 'run' ) );
	}
}
