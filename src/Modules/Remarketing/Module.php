<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\Remarketing;

use MoksaWeb\Moforcoupon\Modules\AbstractModule;

defined( 'ABSPATH' ) || exit;

/**
 * "訂單完成後自動發券(再行銷)" module — lazy-loaded, boots only when
 * moforcoupon_remarketing_enabled is 'yes'. Completes the marketing loop: a completed order issues
 * the customer a personalised clone of a chosen template coupon, which then appears in their
 * "我的優惠券" account page (see the MyAccount module) and can optionally be emailed.
 */
final class Module extends AbstractModule {

	public function slug(): string {
		return 'remarketing';
	}

	public function label(): string {
		return __( '訂單完成後自動發券(再行銷)', 'moforcoupon' );
	}

	public function category(): string {
		return 'coupon';
	}

	public function tagline(): string {
		return __( '訂單完成後依條件把指定範本券複製成顧客專屬券,自動進入會員中心並可寄出', 'moforcoupon' );
	}

	public function boot(): void {
		Runtime::boot();
		add_action( 'wp_abilities_api_init', array( Ability::class, 'register' ) );
	}
}
