<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\CouponSend;

use MoksaWeb\Moforcoupon\Modules\AbstractModule;

defined( 'ABSPATH' ) || exit;

/**
 * Send-coupon module — lazy-loaded, boots only when moforcoupon_send_enabled is 'yes'.
 * Registers the destructive `send-coupon` ability (command palette / REST / AI / MCP)
 * and wires it into the AI assistant. The ability is propose-only (execute_callback =
 * SendOps::send_prepare); the real send runs via the confirm flow (SendOps::send_apply).
 * MCP exposure of this destructive ability is governed by CouponCore's existing gate.
 */
final class Module extends AbstractModule {

	private const ABILITY = 'moforcoupon/send-coupon';

	public function slug(): string {
		return 'send';
	}

	public function label(): string {
		return __( '優惠券寄送', 'moforcoupon' );
	}

	public function category(): string {
		return 'coupon';
	}

	public function tagline(): string {
		return __( '用自然語言把優惠券寄給顧客,可鎖定只限該 Email 使用', 'moforcoupon' );
	}

	public function boot(): void {
		if ( function_exists( 'wp_register_ability' ) ) {
			add_action( 'wp_abilities_api_init', array( self::class, 'register_ability' ) );
		}
		add_filter( 'moforcoupon_ai_assistant_abilities', array( self::class, 'ai_abilities' ) );
		add_filter( 'moforcoupon_ai_destructive_handlers', array( self::class, 'ai_handlers' ) );
	}

	public static function register_ability(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}
		wp_register_ability(
			self::ABILITY,
			array(
				'label'               => __( '寄送優惠券', 'moforcoupon' ),
				'description'         => __( '把一張既有優惠券寄到指定 Email(可選擇鎖定只限該 Email 使用)。破壞性 —— 呼叫只會「提出」,使用者確認後才寄送。', 'moforcoupon' ),
				'category'            => 'moforcoupon',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'code_or_id'        => array(
							'type'        => 'string',
							'description' => __( '要寄送的優惠券代碼或 ID', 'moforcoupon' ),
						),
						'email'             => array(
							'type'        => 'string',
							'description' => __( '收件人 Email', 'moforcoupon' ),
						),
						'note'              => array(
							'type'        => 'string',
							'description' => __( '附加給顧客的訊息(選填)', 'moforcoupon' ),
						),
						'restrict_to_email' => array(
							'type'        => 'boolean',
							'description' => __( '是否鎖定此優惠券只限該 Email 使用(寫入 WooCommerce 的 Email 限制)', 'moforcoupon' ),
						),
					),
					'required'             => array( 'code_or_id', 'email' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array( 'summary' => array( 'type' => 'string' ) ),
				),
				'execute_callback'    => array( SendOps::class, 'send_prepare' ),
				'permission_callback' => array( self::class, 'can_send' ),
				'meta'                => array(
					'show_in_rest' => false,
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => false,
					),
					'mcp'          => array(
						'public' => true,
						'type'   => 'tool',
					),
				),
			)
		);
	}

	public static function can_send(): bool {
		return current_user_can( SendOps::CAP );
	}

	/**
	 * @param array<int,string> $abilities
	 * @return array<int,string>
	 */
	public static function ai_abilities( array $abilities ): array {
		$abilities[] = self::ABILITY;
		return $abilities;
	}

	/**
	 * @param array<string,array{prepare:callable,apply:callable}> $handlers
	 * @return array<string,array{prepare:callable,apply:callable}>
	 */
	public static function ai_handlers( array $handlers ): array {
		$handlers[ self::ABILITY ] = array(
			'prepare' => array( SendOps::class, 'send_prepare' ),
			'apply'   => array( SendOps::class, 'send_apply' ),
		);
		return $handlers;
	}
}
