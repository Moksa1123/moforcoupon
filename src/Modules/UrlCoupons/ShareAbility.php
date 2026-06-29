<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\UrlCoupons;

use MoksaWeb\Moforcoupon\Coupon\CouponService;

defined( 'ABSPATH' ) || exit;

/**
 * Read ability moforcoupon/get-coupon-share: returns a coupon's shareable auto-apply
 * URL plus a qr_url pointer (the gated REST SVG route), so the in-dashboard AI
 * assistant and any external MCP client can hand back a scannable link. Inline SVG
 * is deliberately NOT returned here — a multi-KB string would bloat model context
 * on every call; qr_url is a cheap pointer the consumer fetches if it needs the image.
 */
final class ShareAbility {

	public const CATEGORY = 'moforcoupon';
	public const CAP      = 'manage_woocommerce';

	public static function register(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}
		wp_register_ability(
			'moforcoupon/get-coupon-share',
			array(
				'label'               => __( '取優惠券分享連結 / QR', 'moforcoupon' ),
				'description'         => __( '取得一張優惠券的「自動套用」分享網址與 QR Code 連結(顧客點擊或掃描即自動套用此券)。若該券尚未開啟網址功能,會回傳 enabled=false 與提示。唯讀。', 'moforcoupon' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'code_or_id' => array(
							'type'        => 'string',
							'description' => __( '優惠券代碼或 ID', 'moforcoupon' ),
						),
					),
					'required'             => array( 'code_or_id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'enabled'   => array( 'type' => 'boolean' ),
						'code'      => array( 'type' => 'string' ),
						'share_url' => array( 'type' => 'string' ),
						'qr_url'    => array( 'type' => 'string' ),
						'reason'    => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => array( self::class, 'execute' ),
				'permission_callback' => array( self::class, 'can_read' ),
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'mcp'          => array(
						'public' => true,
						'type'   => 'tool',
					),
				),
			)
		);
	}

	public static function can_read(): bool {
		return current_user_can( self::CAP );
	}

	/**
	 * @param mixed $input
	 * @return array<string,mixed>
	 */
	public static function execute( $input ): array {
		if ( ! self::can_read() ) {
			return array(
				'enabled'   => false,
				'share_url' => '',
				'qr_url'    => '',
				'reason'    => __( '權限不足。', 'moforcoupon' ),
			);
		}
		$ref = is_array( $input ) && isset( $input['code_or_id'] ) ? (string) $input['code_or_id'] : '';
		$id  = CouponService::resolve_id( $ref );
		if ( ! $id ) {
			return array(
				'enabled'   => false,
				'share_url' => '',
				'qr_url'    => '',
				'reason'    => __( '找不到該優惠券。', 'moforcoupon' ),
			);
		}
		$coupon = new \WC_Coupon( $id );
		$info   = ShareService::info( $coupon );
		return array(
			'enabled'   => $info['enabled'],
			'code'      => $coupon->get_code(),
			'share_url' => $info['share_url'],
			'qr_url'    => $info['enabled'] ? ShareService::qr_url( $id ) : '',
			'reason'    => $info['reason'],
		);
	}
}
