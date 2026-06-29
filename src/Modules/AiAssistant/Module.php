<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\AiAssistant;

use MoksaWeb\Moforcoupon\Modules\AbstractModule;

defined( 'ABSPATH' ) || exit;

/**
 * In-dashboard coupon AI assistant. Uses the WordPress 7.0 AI Client
 * (wp_ai_client_prompt + using_abilities) to let merchants create / query coupons
 * in natural language. Boots only when the 'ai' module option is enabled (the
 * registry gate) and the AI Client is present.
 */
final class Module extends AbstractModule {

	public function slug(): string {
		return 'ai';
	}

	public function label(): string {
		return __( 'Moksa 優惠券 AI — 用一句話建立 / 查詢優惠券', 'moforcoupon' );
	}

	public function category(): string {
		return 'ai';
	}

	public function name(): string {
		return Config::NAME;
	}

	public function tagline(): string {
		return __( '需 WordPress 7.0 並在「設定 → Connectors」設定 AI 金鑰', 'moforcoupon' );
	}

	public function boot(): void {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return;
		}
		add_action( 'rest_api_init', [ Rest::class, 'register' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_chat' ] );
	}

	/**
	 * The floating chat is an admin-wide widget (like a support bubble), so it has
	 * no single screen gate; it is gated by capability (least privilege).
	 */
	public static function enqueue_chat(): void {
		if ( ! current_user_can( Config::CAP ) ) {
			return;
		}
		wp_enqueue_style( 'dashicons' );
		$rel  = 'src/Modules/AiAssistant/assets/js/floating-chat.js';
		$path = MOFORCOUPON_PLUGIN_DIR . $rel;
		$ver  = file_exists( $path ) ? (string) filemtime( $path ) : MOFORCOUPON_VERSION;
		wp_enqueue_script(
			'moforcoupon-ai-chat',
			MOFORCOUPON_PLUGIN_URL . $rel,
			[ 'wp-api-fetch' ],
			$ver,
			true
		);

		$greeting = (string) get_option(
			'moforcoupon_ai_greeting',
			__( '嗨,我是優惠券助手。試試:「建立一張 8 折券 SUMMER20,8/31 到期」或「列出啟用中的優惠券」。', 'moforcoupon' )
		);
		$ex_raw   = (string) get_option(
			'moforcoupon_ai_examples',
			__( '建立 9 折券 VIP10,列出啟用中的優惠券,量產 50 張 SALE- 折 100', 'moforcoupon' )
		);
		$examples = array_values( array_filter( array_map( 'trim', explode( ',', $ex_raw ) ) ) );

		wp_localize_script(
			'moforcoupon-ai-chat',
			'moforcouponAi',
			[
				'name'        => Config::NAME,
				'userId'      => get_current_user_id(),
				'greeting'    => $greeting,
				'placeholder' => __( '例如:建立一張 85 折券 AUTUMN15', 'moforcoupon' ),
				'examples'    => $examples,
				'sendLabel'   => __( '送出', 'moforcoupon' ),
				'thinking'    => __( '處理中', 'moforcoupon' ),
				'clearLabel'  => __( '清除', 'moforcoupon' ),
				'errorPrefix' => __( '發生錯誤', 'moforcoupon' ),
				'emptyReply'  => __( '(無回覆)', 'moforcoupon' ),
				'confirmYes'  => __( '確認執行', 'moforcoupon' ),
				'confirmNo'   => __( '取消', 'moforcoupon' ),
				'cancelled'   => __( '已取消。', 'moforcoupon' ),
				'running'     => __( '執行中…', 'moforcoupon' ),
			]
		);
	}
}
