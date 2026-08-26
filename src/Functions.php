<?php
/**
 * Registration
 *
 * @package     ArrayPress\RegisterPages
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       2.0.0
 */

declare( strict_types=1 );

use ArrayPress\RegisterPages\Pages;

if ( ! function_exists( 'register_pages' ) ) {
	/**
	 * Create the pages a plugin needs, and remember them.
	 *
	 *     register_pages( 'myplugin', [
	 *         'checkout' => [ 'title' => 'Checkout', 'slug' => 'checkout' ],
	 *         'receipt'  => [ 'title' => 'Order receipt', 'content' => '[myplugin_receipt]' ],
	 *     ] );
	 *
	 * Safe to call on every request: a page whose id is stored and still
	 * exists is left alone.
	 *
	 * @param string                              $prefix The plugin's option prefix.
	 * @param array<string, array<string, mixed>> $pages  Pages, by key.
	 *
	 * @return array<string, int> The ids that exist, by key.
	 */
	function register_pages( string $prefix, array $pages ): array {
		Pages::set_prefix( $prefix );

		foreach ( $pages as $key => $config ) {
			Pages::add( (string) $key, (array) $config );
		}

		return Pages::ids();
	}
}

if ( ! function_exists( 'get_registered_page_id' ) ) {
	/**
	 * The id of a registered page, or null if it is gone or in the trash.
	 *
	 * @param string $key The page's key.
	 *
	 * @return int|null
	 */
	function get_registered_page_id( string $key ): ?int {
		return Pages::id( $key );
	}
}

if ( ! function_exists( 'get_registered_page_url' ) ) {
	/**
	 * The permalink of a registered page.
	 *
	 * @param string $key The page's key.
	 *
	 * @return string|null
	 */
	function get_registered_page_url( string $key ): ?string {
		return Pages::url( $key );
	}
}

if ( ! function_exists( 'unregister_page' ) ) {
	/**
	 * Forget a page, and optionally bin it.
	 *
	 * @param string $key    The page's key.
	 * @param bool   $delete Whether to delete the page as well as forget it.
	 *
	 * @return bool
	 */
	function unregister_page( string $key, bool $delete = false ): bool {
		return Pages::remove( $key, $delete );
	}
}
