<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules\AiAssistant;

defined( 'ABSPATH' ) || exit;

/**
 * WordPress 7.0 AI Client agentic loop — exposes the coupon abilities as tools and
 * runs "generate → execute ability → feed back → generate" until a text answer.
 * Destructive abilities are intercepted and returned as a "needs confirmation"
 * proposal rather than executed inside the loop.
 *
 * Security: (1) ability whitelist (the model may only call those passed in),
 * (2) each ability's own permission_callback (core-enforced), (3) destructive
 * actions always go through human confirmation (intercepted here, never run in
 * the loop).
 *
 * Always returns a structured array:
 *   ['type'=>'text','reply'=>string]
 *   ['type'=>'confirm','token'=>string,'summary'=>string]
 *   ['type'=>'error','message'=>string]
 */
final class Agent {

	public const MAX_TURNS = 8;
	public const MODELS    = [ 'gemini-2.5-flash', 'claude-sonnet-4-6', 'gpt-4o-mini' ];

	/**
	 * @param string            $user_text User question.
	 * @param array<int,string> $abilities Ability whitelist exposed to the AI.
	 * @param string            $system    System instruction.
	 * @param array<int,mixed>  $prior     Prior conversation turns.
	 * @return array<string,mixed>
	 */
	public static function run( string $user_text, array $abilities, string $system, array $prior = [] ): array {
		if ( ! function_exists( 'wp_ai_client_prompt' ) || empty( $abilities ) ) {
			return self::err( __( 'AI Client 不可用(需 WordPress 7.0)。', 'moforcoupon' ) );
		}

		$resolver = new \WP_AI_Client_Ability_Function_Resolver( ...$abilities );

		$destructive = [];
		foreach ( Config::destructive_abilities() as $name ) {
			$destructive[ \WP_AI_Client_Ability_Function_Resolver::ability_name_to_function_name( $name ) ] = $name;
		}

		$history = self::seed_history( $prior );
		$current = $user_text;
		$models  = self::MODELS;

		for ( $turn = 0; $turn < self::MAX_TURNS; $turn++ ) {
			// Per-turn model failover: a provider failing (e.g. empty content / dead
			// connector) falls through to the next rather than failing the request.
			$result   = null;
			$last_err = '';
			foreach ( $models as $model ) {
				$builder = wp_ai_client_prompt( $current )
					->using_system_instruction( $system )
					->using_abilities( ...$abilities )
					->using_model_preference( $model );
				if ( ! empty( $history ) ) {
					$builder = $builder->with_history( ...$history );
				}
				$attempt = $builder->generate_text_result();
				if ( ! is_wp_error( $attempt ) ) {
					$result = $attempt;
					break;
				}
				$last_err = $attempt->get_error_message();
			}
			if ( null === $result ) {
				return self::err( '' !== $last_err ? $last_err : __( 'AI 暫時無法回應,請稍後再試。', 'moforcoupon' ) );
			}

			$assistant = $result->toMessage();

			$dcall = self::find_destructive_call( $assistant, $destructive );
			if ( null !== $dcall ) {
				// Let feature modules deterministically rewrite the model's args
				// (e.g. enforce Taiwan "N 折" → percent amount) before confirmation.
				$args = (array) apply_filters( 'moforcoupon_ai_rewrite_call_args', $dcall['args'], $dcall['ability'], $user_text );
				return self::prepare_confirm( $dcall['ability'], $args );
			}

			if ( ! $resolver->has_ability_calls( $assistant ) ) {
				$text = '';
				try {
					$text = (string) $result->toText();
				} catch ( \Throwable $e ) {
					$text = '';
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- debug-gated diagnostic for a swallowed AI-response parse error.
						error_log( 'moforcoupon AI toText() failed: ' . $e->getMessage() );
					}
				}
				if ( '' !== trim( $text ) ) {
					return self::text( $text );
				}
				$history[] = is_string( $current )
					? new \WordPress\AiClient\Messages\DTO\UserMessage( [ new \WordPress\AiClient\Messages\DTO\MessagePart( $current ) ] )
					: $current;
				$current   = new \WordPress\AiClient\Messages\DTO\UserMessage(
					[ new \WordPress\AiClient\Messages\DTO\MessagePart( '請根據前面工具查到的資料,用繁體中文文字簡短回答我的問題。' ) ]
				);
				// Empty content + no tool call: rotate this provider to the back.
				$models[] = array_shift( $models );
				continue;
			}

			$tool_response = $resolver->execute_abilities( $assistant );
			$history[]     = is_string( $current )
				? new \WordPress\AiClient\Messages\DTO\UserMessage( [ new \WordPress\AiClient\Messages\DTO\MessagePart( $current ) ] )
				: $current;
			$history[]     = $assistant;
			$current       = $tool_response;
		}

		return self::err( __( 'AI 多次嘗試後仍未完成,請換個問法。', 'moforcoupon' ) );
	}

	/**
	 * @param array<int,array{role?:string,text?:string}> $prior
	 * @return array<int,object>
	 */
	private static function seed_history( array $prior ): array {
		$turns = [];
		foreach ( $prior as $turn ) {
			if ( ! is_array( $turn ) || empty( $turn['text'] ) ) {
				continue;
			}
			$role = ( isset( $turn['role'] ) && 'assistant' === $turn['role'] ) ? 'assistant' : 'user';
			$text = trim( (string) $turn['text'] );
			if ( '' === $text ) {
				continue;
			}
			if ( empty( $turns ) && 'assistant' === $role ) {
				continue;
			}
			$n = count( $turns );
			if ( $n > 0 && $turns[ $n - 1 ]['role'] === $role ) {
				$turns[ $n - 1 ]['text'] .= "\n" . $text;
			} else {
				$turns[] = [
					'role' => $role,
					'text' => $text,
				];
			}
		}
		while ( ! empty( $turns ) && 'user' === $turns[ array_key_last( $turns ) ]['role'] ) {
			array_pop( $turns );
		}

		$out = [];
		foreach ( $turns as $t ) {
			$part  = new \WordPress\AiClient\Messages\DTO\MessagePart( $t['text'] );
			$out[] = ( 'assistant' === $t['role'] )
				? new \WordPress\AiClient\Messages\DTO\ModelMessage( [ $part ] )
				: new \WordPress\AiClient\Messages\DTO\UserMessage( [ $part ] );
		}
		return $out;
	}

	/**
	 * @param object               $assistant
	 * @param array<string,string> $destructive function-name => ability-name.
	 * @return array{ability:string,args:array}|null
	 */
	private static function find_destructive_call( $assistant, array $destructive ): ?array {
		foreach ( $assistant->getParts() as $part ) {
			if ( ! $part->getType()->isFunctionCall() ) {
				continue;
			}
			$fc = $part->getFunctionCall();
			$fn = ( is_object( $fc ) && method_exists( $fc, 'getName' ) ) ? $fc->getName() : '';
			if ( isset( $destructive[ $fn ] ) ) {
				$args = ( is_object( $fc ) && method_exists( $fc, 'getArgs' ) ) ? (array) $fc->getArgs() : [];
				return [
					'ability' => $destructive[ $fn ],
					'args'    => $args,
				];
			}
		}
		return null;
	}

	/**
	 * @param string             $ability
	 * @param array<string,mixed> $args
	 * @return array<string,mixed>
	 */
	private static function prepare_confirm( string $ability, array $args ): array {
		$handlers = Config::destructive_handlers();
		if ( ! isset( $handlers[ $ability ]['prepare'] ) || ! is_callable( $handlers[ $ability ]['prepare'] ) ) {
			return self::err( __( '不支援的操作。', 'moforcoupon' ) );
		}

		$prepared = call_user_func( $handlers[ $ability ]['prepare'], $args );
		if ( is_wp_error( $prepared ) ) {
			return self::err( $prepared->get_error_message() );
		}
		if ( ! is_array( $prepared ) || empty( $prepared['summary'] ) ) {
			return self::err( __( '無法準備此操作。', 'moforcoupon' ) );
		}

		$token = wp_generate_password( 24, false, false );
		set_transient(
			'moforcoupon_ai_confirm_' . $token,
			[
				'user'    => get_current_user_id(),
				'ability' => $ability,
				'params'  => $prepared,
			],
			5 * MINUTE_IN_SECONDS
		);

		return [
			'type'    => 'confirm',
			'token'   => $token,
			'summary' => (string) $prepared['summary'],
			'fields'  => self::diff_preview( $prepared ),
		];
	}

	/**
	 * A structured field-level preview of a prepared write, so the confirm card can show a
	 * readable diff instead of only a one-line summary. Empty for abilities that carry no
	 * field set (toggle / delete / duplicate …) — those rely on the summary.
	 *
	 * @param array<string,mixed> $prepared
	 * @return array<int,array{key:string,value:string}>
	 */
	private static function diff_preview( array $prepared ): array {
		$labels = self::field_labels();
		$rows   = [];
		if ( isset( $prepared['fields'] ) && is_array( $prepared['fields'] ) ) {
			foreach ( $prepared['fields'] as $key => $value ) {
				if ( 'status' === $key ) {
					continue;
				}
				$rows[] = [
					'key'   => $labels[ $key ] ?? (string) $key,
					'value' => self::stringify( (string) $key, $value ),
				];
			}
		}
		if ( ! empty( $prepared['settings'] ) ) {
			$rows[] = [
				'key'   => __( '進階設定', 'moforcoupon' ),
				'value' => __( '是', 'moforcoupon' ),
			];
		}
		if ( array_key_exists( 'auto_apply', $prepared ) ) {
			$rows[] = [
				'key'   => __( '自動套用', 'moforcoupon' ),
				'value' => $prepared['auto_apply'] ? __( '是', 'moforcoupon' ) : __( '否', 'moforcoupon' ),
			];
		}
		if ( isset( $prepared['discount_cap'] ) && (float) $prepared['discount_cap'] > 0 ) {
			$rows[] = [
				'key'   => __( '折扣上限', 'moforcoupon' ),
				'value' => (string) $prepared['discount_cap'],
			];
		}
		if ( ! empty( $prepared['exclude_coupons'] ) ) {
			$rows[] = [
				'key'   => __( '不可與其他券並用', 'moforcoupon' ),
				'value' => __( '是', 'moforcoupon' ),
			];
		}
		return $rows;
	}

	/**
	 * @return array<string,string>
	 */
	private static function field_labels(): array {
		return [
			'code'                        => __( '代碼', 'moforcoupon' ),
			'discount_type'               => __( '折扣類型', 'moforcoupon' ),
			'amount'                      => __( '折扣值', 'moforcoupon' ),
			'description'                 => __( '說明', 'moforcoupon' ),
			'date_expires'                => __( '到期日', 'moforcoupon' ),
			'usage_limit'                 => __( '使用次數上限', 'moforcoupon' ),
			'usage_limit_per_user'        => __( '每人使用上限', 'moforcoupon' ),
			'minimum_amount'              => __( '購物車最低', 'moforcoupon' ),
			'maximum_amount'              => __( '購物車最高', 'moforcoupon' ),
			'individual_use'              => __( '不可與其他券並用', 'moforcoupon' ),
			'free_shipping'               => __( '含免運', 'moforcoupon' ),
			'product_ids'                 => __( '指定商品', 'moforcoupon' ),
			'excluded_product_ids'        => __( '排除商品', 'moforcoupon' ),
			'product_categories'          => __( '指定分類', 'moforcoupon' ),
			'excluded_product_categories' => __( '排除分類', 'moforcoupon' ),
		];
	}

	/**
	 * @param string $key   Field key (drives value formatting, e.g. discount_type labels).
	 * @param mixed  $value Raw field value.
	 */
	private static function stringify( string $key, $value ): string {
		if ( 'discount_type' === $key ) {
			return \MoksaWeb\Moforcoupon\Support\CouponType::label( (string) $value );
		}
		if ( is_bool( $value ) ) {
			return $value ? __( '是', 'moforcoupon' ) : __( '否', 'moforcoupon' );
		}
		if ( is_array( $value ) ) {
			return implode( ', ', array_map( 'strval', $value ) );
		}
		return (string) $value;
	}

	private static function text( string $reply ): array {
		return [
			'type'  => 'text',
			'reply' => $reply,
		];
	}

	private static function err( string $message ): array {
		return [
			'type'    => 'error',
			'message' => $message,
		];
	}
}
