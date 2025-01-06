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
	 * // Register pages with a prefix
	 * $page_ids = register_pages( $pages, 'my_plugin' );
	 * ```
	 *
	 * @since 1.0.0
	 *
	 * @param array  $pages  Array of pages to register
	 * @param string $prefix Optional. Option prefix for storing page IDs
	 *
	 * @return array Array of registered page IDs
	 */
	function register_pages( array $pages, string $prefix = '' ): array {
		return Pages::register( $pages, $prefix );
	}
endif;

if ( ! function_exists( 'get_registered_page_id' ) ):
	/**
	 * Get a registered page ID by its key.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key    Page identifier
	 * @param string $prefix Optional. Option prefix used during registration
	 *
	 * @return int|null Page ID if found, null otherwise
	 */
	function get_registered_page_id( string $key, string $prefix = '' ): ?int {
		return Pages::get_page_id( $key, $prefix );
	}
endif;

if ( ! function_exists( 'get_registered_page_url' ) ):
	/**
	 * Get a registered page URL by its key.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key    Page identifier
	 * @param string $prefix Optional. Option prefix used during registration
	 *
	 * @return string|null Page URL if found, null otherwise
	 */
	function get_registered_page_url( string $key, string $prefix = '' ): ?string {
		return Pages::get_page_url( $key, $prefix );
	}
endif;