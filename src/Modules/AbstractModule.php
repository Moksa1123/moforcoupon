<?php

declare( strict_types=1 );

namespace MoksaWeb\Moforcoupon\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Single entry point for every feature module. A module must declare its slug,
 * label and category, and wire its hooks in boot().
 */
abstract class AbstractModule {

	abstract public function slug(): string;

	abstract public function label(): string;

	abstract public function category(): string;

	abstract public function boot(): void;

	public function name(): string {
		return ucfirst( str_replace( '_', ' ', $this->slug() ) );
	}

	public function tagline(): string {
		return '';
	}

	/** @return array<int,string> */
	public function methods(): array {
		return [];
	}

	public function settings_section(): string {
		return '';
	}
}
