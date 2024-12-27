<?php
/**
 * Asset Registration Helper
 *
 * Provides a simplified interface for registering WordPress scripts and styles.
 * This helper function wraps the RegisterAssets class to provide a quick way to register
 * multiple assets at once with error handling and validation.
 *
 * Example usage:
 * ```php
 * $assets = [
 *     [
 *         'handle' => 'my-script',
 *         'src'    => 'js/script.js',     // Will auto-detect as script
 *         'deps'   => ['jquery'],
 *         'async'  => true
 *     ],
 *     [
 *         'handle' => 'my-style',
 *         'src'    => 'css/style.css',    // Will auto-detect as style
 *         'media'  => 'all'
 *     ]
 * ];
 *
 * $config = [
 *     'debug'          => WP_DEBUG,       // Enable debug mode
 *     'minify'         => true,           // Enable minification
 *     'assets_url'     => 'dist/assets',  // Custom assets directory
 *     'version'        => '1.0.0',        // Asset version
 * ];
 *
 * register_assets( __FILE__, $assets, $config );
 * ```
 *
 * @package     ArrayPress/Utils/Register
 * @copyright   Copyright (c) 2024, ArrayPress Limited
 * @license     GPL2+
 * @version     2.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

use ArrayPress\Register\CustomPages;

if ( ! function_exists( 'register_custom_pages' ) ) {
	/**
	 * Global utility function to register custom pages
	 *
	 * @param array  $pages         Array of pages to register
	 * @param string $option_prefix Option prefix for storage
	 * @param array  $config        Additional configuration
	 *
	 * @return array Array of page IDs
	 */
	function register_custom_pages( array $pages, string $option_prefix = '', array $config = [] ): array {
		$manager = new CustomPages( $option_prefix, $config );

		return $manager->register( $pages )->install();
	}
}