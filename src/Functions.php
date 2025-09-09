<?php
/**
 * Page Registration Helper Functions
 *
 * Simple helper functions for page registration.
 *
 * @package ArrayPress\PageUtils
 * @since   1.0.0
 * @author  David Sherlock
 * @license GPL-2.0-or-later
 */

declare( strict_types=1 );

use ArrayPress\PageUtils\Register;

if ( ! function_exists( 'register_custom_pages' ) ):
	/**
	 * Register custom pages for your plugin.
	 *
	 * Simple helper to quickly register pages on plugin activation.
	 *
	 * Example usage:
	 * ```php
	 * // Basic usage with WordPress options
	 * register_activation_hook( __FILE__, function() {
	 *     $pages = [
	 *         'checkout' => [
	 *             'title'   => 'Checkout',
	 *             'content' => '[myplugin_checkout]'
	 *         ],
	 *         'account' => [
	 *             'title'   => 'My Account',
	 *             'content' => '[myplugin_account]'
	 *         ]
	 *     ];
	 *
	 *     register_custom_pages( $pages, 'myplugin' );
	 * });
	 *
	 * // With custom settings manager
	 * register_custom_pages(
	 *     $pages,
	 *     'myplugin',
	 *     fn($key, $default) => $settings->get($key, $default),
	 *     fn($key, $value) => $settings->update($key, $value)
	 * );
	 * ```
	 *
	 * @param array         $pages                  Array of pages to register.
	 * @param string        $prefix                 Plugin prefix for option names.
	 * @param callable|null $get_option_callback    Optional custom function to get options.
	 * @param callable|null $update_option_callback Optional custom function to update options.
	 * @param bool          $show_post_states       Whether to show post states in admin (default true).
	 *
	 * @return array Array of created page IDs.
	 */
	function register_custom_pages(
		array $pages,
		string $prefix = '',
		?callable $get_option_callback = null,
		?callable $update_option_callback = null,
		bool $show_post_states = true
	): array {
		return Register::quick_install( $pages, $prefix, $get_option_callback, $update_option_callback, $show_post_states );
	}
endif;