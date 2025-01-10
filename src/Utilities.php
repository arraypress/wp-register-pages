<?php
/**
 * Pages Registration Helper Functions
 *
 * @package     ArrayPress\WP\Register
 * @copyright   Copyright (c) 2024, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

use ArrayPress\WP\Register\Pages;

if ( ! function_exists( 'register_pages' ) ):
	/**
	 * Helper function to register WordPress pages.
	 *
	 * Example usage:
	 * ```php
	 * $pages = [
	 *     'contact' => [
	 *         'title'   => 'Contact Us',
	 *         'content' => 'Contact page content here...',
	 *         'status'  => 'publish'
	 *     ],
	 *     'about' => [
	 *         'title'   => 'About Us',
	 *         'content' => 'About page content here...',
	 *         'parent'  => 'contact'  // Reference to another page key
	 *     ]
	 * ];
	 *
	 * // Register pages with WordPress options
	 * $page_ids = register_pages( $pages, 'my_plugin' );
	 *
	 * // Register pages with custom option handlers (e.g., EDD)
	 * $page_ids = register_pages(
	 *     $pages,
	 *     'edd_guest_downloads',
	 *     'edd_update_option',
	 *     'edd_get_option'
	 * );
	 * ```
	 *
	 * @param array         $pages          Array of pages to register
	 * @param string        $prefix         Optional. Option prefix for storing page IDs
	 * @param callable|null $update_handler Optional. Custom handler for updating options
	 * @param callable|null $get_handler    Optional. Custom handler for getting options
	 *
	 * @return array Array of registered page IDs
	 *
	 */
	function register_pages(
		array $pages,
		string $prefix = '',
		?callable $update_handler = null,
		?callable $get_handler = null
	): array {
		return Pages::register( $pages, $prefix, $update_handler, $get_handler );
	}
endif;

if ( ! function_exists( 'get_registered_page_id' ) ):
	/**
	 * Get a registered page ID by its key.
	 *
	 * @param string        $key         Page identifier
	 * @param string        $prefix      Optional. Option prefix used during registration
	 * @param callable|null $get_handler Optional. Custom handler for getting options
	 *
	 * @return int|null Page ID if found, null otherwise
	 *
	 */
	function get_registered_page_id( string $key, string $prefix = '', ?callable $get_handler = null ): ?int {
		return Pages::get_page_id( $key, $prefix, $get_handler );
	}
endif;

if ( ! function_exists( 'get_registered_page_url' ) ):
	/**
	 * Get a registered page URL by its key.
	 *
	 * @param string        $key         Page identifier
	 * @param string        $prefix      Optional. Option prefix used during registration
	 * @param callable|null $get_handler Optional. Custom handler for getting options
	 *
	 * @return string|null Page URL if found, null otherwise
	 *
	 */
	function get_registered_page_url( string $key, string $prefix = '', ?callable $get_handler = null ): ?string {
		return Pages::get_page_url( $key, $prefix, $get_handler );
	}
endif;