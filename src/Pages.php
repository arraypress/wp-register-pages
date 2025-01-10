<?php
/**
 * WordPress Page Registration Manager
 *
 * A simplified solution for managing WordPress pages with features like:
 * - Automatic page creation and verification
 * - Parent-child relationships
 * - Custom option storage support
 * - Error handling and logging
 *
 * @package     ArrayPress\WP\Register
 * @copyright   Copyright (c) 2024, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\WP\Register;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

use WP_Error;
use WP_Post;

/**
 * Class Pages
 *
 * @package ArrayPress\WP\Register
 * @since   1.0.0
 */
class Pages {

	/**
	 * Default page settings
	 *
	 * @var array
	 */
	protected const DEFAULTS = [
		'status'         => 'publish',
		'type'           => 'page',
		'author'         => 0,
		'comment_status' => 'closed',
		'ping_status'    => 'closed',
		'parent'         => 0,
		'menu_order'     => 0,
	];

	/**
	 * Collection of pages to be registered
	 *
	 * @var array
	 */
	private array $pages = [];

	/**
	 * Option prefix for storing page data
	 *
	 * @var string
	 */
	private string $option_prefix = '';

	/**
	 * Option get handler callback
	 *
	 * @var callable|null
	 */
	private $get_handler = null;

	/**
	 * Option update handler callback
	 *
	 * @var callable|null
	 */
	private $update_handler = null;

	/**
	 * Debug mode status
	 *
	 * @var bool
	 */
	private bool $debug = false;

	/**
	 * Base flag for initialization tracking
	 *
	 * @var string
	 */
	private string $installed_flag = 'pages_initialized';

	/**
	 * Constructor
	 *
	 * @param string        $prefix         Optional. Option prefix for storing page data
	 * @param callable|null $get_handler    Optional. Custom handler for getting options
	 * @param callable|null $update_handler Optional. Custom handler for updating options
	 */
	public function __construct(
		string $prefix = '',
		?callable $get_handler = null,
		?callable $update_handler = null
	) {
		$this->option_prefix  = $prefix;
		$this->get_handler    = $get_handler;
		$this->update_handler = $update_handler;
		$this->debug          = defined( 'WP_DEBUG' ) && WP_DEBUG;
	}

	/**
	 * Get unique initialization key based on registered pages
	 *
	 * @return string
	 */
	private function get_initialization_key(): string {
		$page_keys = array_keys( $this->pages );
		sort( $page_keys ); // Ensure consistent order
		$unique_hash = md5( implode( '_', $page_keys ) );

		return $this->installed_flag . '_' . $unique_hash;
	}

	/**
	 * Check if pages have been initialized
	 *
	 * @return bool
	 */
	protected function is_initialized(): bool {
		$init_key = $this->get_initialization_key();

		if ( $this->get_handler ) {
			return (bool) call_user_func( $this->get_handler, $init_key );
		}

		return (bool) get_option( $this->get_option_key( $init_key ), false );
	}

	/**
	 * Mark pages as initialized
	 *
	 * @return bool
	 */
	protected function mark_initialized(): bool {
		$init_key = $this->get_initialization_key();

		if ( $this->update_handler ) {
			return (bool) call_user_func( $this->update_handler, $init_key, true );
		}

		return update_option( $this->get_option_key( $init_key ), true );
	}

	/**
	 * Force installation by clearing the initialized flag
	 *
	 * @return self
	 */
	public function force_reinstall(): self {
		$init_key = $this->get_initialization_key();

		if ( $this->update_handler ) {
			call_user_func( $this->update_handler, $init_key, false );
		} else {
			delete_option( $this->get_option_key( $init_key ) );
		}

		return $this;
	}

	/**
	 * Register multiple pages
	 *
	 * @param array $pages Array of pages to register
	 *
	 * @return self
	 */
	public function add_pages( array $pages ): self {
		foreach ( $pages as $key => $page ) {
			if ( ! $this->is_valid_key( $key ) ) {
				$this->log( sprintf( 'Invalid page key: %s', $key ) );
				continue;
			}

			if ( ! $this->validate_page( $page ) ) {
				$this->log( sprintf( 'Invalid page configuration for: %s', $key ) );
				continue;
			}

			$this->pages[ $key ] = wp_parse_args(
				$this->prepare_page_attributes( $page ),
				$this->get_default_attributes()
			);
		}

		return $this;
	}

	/**
	 * Install registered pages
	 *
	 * @return array Array of page IDs keyed by their identifiers
	 */
	public function install(): array {
		// Quick check using initialization flag
		if ( $this->is_initialized() ) {
			$this->log( 'Pages already initialized, skipping installation' );

			return $this->get_stored_pages();
		}

		$this->log( 'Starting page installation' );

		$page_ids = [];
		$stored   = $this->get_stored_pages();

		foreach ( $this->pages as $key => $attributes ) {
			// Check if page already exists
			$page_id = $stored[ $key ] ?? null;
			if ( $page_id && get_post( $page_id ) instanceof WP_Post ) {
				$page_ids[ $key ] = $page_id;
				$this->maybe_update_page( $page_id, $attributes, $key );
				continue;
			}

			// Create new page
			$this->log( sprintf( 'Creating new page: %s', $key ) );
			$new_page_id = wp_insert_post( $attributes );

			if ( ! is_wp_error( $new_page_id ) ) {
				$page_ids[ $key ] = $new_page_id;
				$this->log( sprintf( 'Created page: %s (ID: %d)', $key, $new_page_id ) );
			}
		}

		if ( ! empty( $page_ids ) ) {
			$this->save_page_ids( $page_ids );
			$this->mark_initialized();
		}

		return $page_ids;
	}

	/**
	 * Get stored page IDs
	 *
	 * @return array Array of stored page IDs
	 */
	protected function get_stored_pages(): array {
		if ( $this->get_handler ) {
			$pages = [];
			foreach ( array_keys( $this->pages ) as $key ) {
				$page_id = call_user_func( $this->get_handler, $key . '_page' );
				if ( $page_id ) {
					$pages[ $key ] = (int) $page_id;
				}
			}

			return $pages;
		}

		$stored = get_option( $this->get_option_key( 'pages' ), [] );

		return array_map( 'intval', $stored );
	}

	/**
	 * Save page IDs
	 *
	 * @param array $page_ids Array of page IDs
	 *
	 * @return bool True on success, false on failure
	 */
	protected function save_page_ids( array $page_ids ): bool {
		$page_ids = array_map( 'intval', $page_ids );

		if ( $this->update_handler ) {
			$results = [];
			foreach ( $page_ids as $key => $page_id ) {
				$results[] = (bool) call_user_func(
					$this->update_handler,
					$key . '_page',
					$page_id
				);
			}

			return ! in_array( false, $results, true );
		}

		return update_option( $this->get_option_key( 'pages' ), $page_ids );
	}

	/**
	 * Check if a page needs updating
	 *
	 * @param int    $page_id    Page ID
	 * @param array  $attributes New page attributes
	 * @param string $key        Page identifier
	 *
	 * @return void
	 */
	protected function maybe_update_page( int $page_id, array $attributes, string $key ): void {
		$post = get_post( $page_id );

		if ( ! $post ) {
			return;
		}

		$needs_update = $post->post_title !== $attributes['post_title'] ||
		                $post->post_content !== $attributes['post_content'] ||
		                $post->post_status !== $attributes['post_status'];

		if ( $needs_update ) {
			$attributes['ID'] = $page_id;
			$result           = wp_update_post( $attributes );

			if ( ! is_wp_error( $result ) ) {
				$this->log( sprintf( 'Updated page: %s', $key ) );
			}
		}
	}

	/**
	 * Get prefixed option key
	 *
	 * @param string $key Option key
	 *
	 * @return string Option key (prefixed only if no custom handlers)
	 */
	protected function get_option_key( string $key ): string {
		if ( $this->get_handler || $this->update_handler ) {
			return $key;
		}

		return empty( $this->option_prefix ) ? $key : "{$this->option_prefix}_{$key}";
	}

	/**
	 * Validate page configuration
	 *
	 * @param array $page Page configuration
	 *
	 * @return bool Whether the page configuration is valid
	 */
	protected function validate_page( array $page ): bool {
		return ! empty( $page['title'] ) && ! empty( $page['content'] );
	}

	/**
	 * Prepare page attributes for WordPress
	 *
	 * @param array $page Page configuration
	 *
	 * @return array Prepared attributes
	 */
	protected function prepare_page_attributes( array $page ): array {
		$mapping = [
			'title'      => 'post_title',
			'content'    => 'post_content',
			'parent'     => 'post_parent',
			'status'     => 'post_status',
			'type'       => 'post_type',
			'author'     => 'post_author',
			'menu_order' => 'menu_order',
		];

		$prepared = [];
		foreach ( $page as $key => $value ) {
			$wp_key              = $mapping[ $key ] ?? $key;
			$prepared[ $wp_key ] = $value;
		}

		return $prepared;
	}

	/**
	 * Get default page attributes
	 *
	 * @return array Default attributes
	 */
	protected function get_default_attributes(): array {
		$defaults = self::DEFAULTS;

		if ( empty( $defaults['author'] ) ) {
			$defaults['author'] = get_current_user_id();
		}

		return array_combine(
			array_map( fn( $key ) => "post_{$key}", array_keys( $defaults ) ),
			array_values( $defaults )
		);
	}

	/**
	 * Validate a page key
	 *
	 * @param string $key Page identifier
	 *
	 * @return bool Whether the key is valid
	 */
	protected function is_valid_key( string $key ): bool {
		return (bool) preg_match( '/^[a-z0-9_-]+$/', $key );
	}

	/**
	 * Log a message if debugging is enabled
	 *
	 * @param string $message Message to log
	 * @param array  $context Optional. Additional context
	 *
	 * @return void
	 */
	protected function log( string $message, array $context = [] ): void {
		if ( $this->debug ) {
			$prefix = $this->option_prefix ? "[{$this->option_prefix}] " : '';
			error_log( sprintf(
				'%sPages: %s %s',
				$prefix,
				$message,
				$context ? json_encode( $context ) : ''
			) );
		}
	}

	/**
	 * Enable debug mode
	 *
	 * @return self
	 */
	public function enable_debug(): self {
		$this->debug = true;

		return $this;
	}

	/**
	 * Static helper method to create and install pages.
	 *
	 * @param array         $pages          Array of pages
	 * @param string        $prefix         Optional. Option prefix for storing page data
	 * @param callable|null $update_handler Optional. Custom handler for updating options
	 * @param callable|null $get_handler    Optional. Custom handler for getting options
	 *
	 * @return array Array of page IDs
	 */
	public static function register(
		array $pages,
		string $prefix = '',
		?callable $update_handler = null,
		?callable $get_handler = null
	): array {
		$instance = new self( $prefix, $get_handler, $update_handler );

		return $instance->add_pages( $pages )->install();
	}

	/**
	 * Static helper method to get a page ID by its key
	 *
	 * @param string        $key         Page key
	 * @param string        $prefix      Optional. Option prefix used during registration
	 * @param callable|null $get_handler Optional. Custom handler for getting options
	 *
	 * @return int|null Page ID if found, null otherwise
	 */
	public static function get_page_id(
		string $key,
		string $prefix = '',
		?callable $get_handler = null
	): ?int {
		if ( $get_handler ) {
			$page_id = call_user_func( $get_handler, $key . '_page' );

			return $page_id ? (int) $page_id : null;
		}

		$stored = get_option(
			empty( $prefix ) ? 'pages' : "{$prefix}_pages",
			[]
		);

		return isset( $stored[ $key ] ) ? (int) $stored[ $key ] : null;
	}

	/**
	 * Static helper method to get a page URL by its key
	 *
	 * @param string        $key         Page key
	 * @param string        $prefix      Optional. Option prefix used during registration
	 * @param callable|null $get_handler Optional. Custom handler for getting options
	 *
	 * @return string|null Page URL if found, null otherwise
	 */
	public static function get_page_url(
		string $key,
		string $prefix = '',
		?callable $get_handler = null
	): ?string {
		$page_id = self::get_page_id( $key, $prefix, $get_handler );

		return $page_id ? get_permalink( $page_id ) : null;
	}
}