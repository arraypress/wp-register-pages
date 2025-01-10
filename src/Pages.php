<?php
/**
 * WordPress Page Registration Manager
 *
 * A comprehensive solution for managing WordPress pages with features like:
 * - Automatic page creation and verification
 * - Simple version tracking
 * - Template support
 * - Parent-child relationships
 * - Installation state tracking
 * - Error handling
 * - Custom option storage support
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
	 * Library version
	 *
	 * @var string
	 */
	private const VERSION = '1.0.0';

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
	 * Page templates
	 *
	 * @var array
	 */
	private array $templates = [];

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
	 * Constructor
	 *
	 * @param string        $prefix         Optional. Option prefix for storing page data
	 * @param callable|null $get_handler    Optional. Custom handler for getting options
	 * @param callable|null $update_handler Optional. Custom handler for updating options
	 *

	 */
	public function __construct( string $prefix = '', ?callable $get_handler = null, ?callable $update_handler = null ) {
		$this->option_prefix  = $prefix;
		$this->get_handler    = $get_handler;
		$this->update_handler = $update_handler;
		$this->debug          = defined( 'WP_DEBUG' ) && WP_DEBUG;
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
			$result = $this->add_page( $key, $page );
			if ( is_wp_error( $result ) ) {
				$this->log( sprintf( 'Failed to register page %s: %s', $key, $result->get_error_message() ) );
			}
		}

		return $this;
	}

	/**
	 * Add a single page
	 *
	 * @param string $key  Unique identifier for the page
	 * @param array  $page Page configuration
	 *
	 * @return true|WP_Error True on success, WP_Error on failure
	 */
	public function add_page( string $key, array $page ) {
		if ( ! $this->is_valid_key( $key ) ) {
			return new WP_Error(
				'invalid_page_key',
				sprintf( 'Invalid page key: %s. Keys must contain only lowercase letters, numbers, underscores, and hyphens.', $key )
			);
		}

		$result = $this->validate_page( $page );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$this->pages[ $key ] = wp_parse_args(
			$this->prepare_page_attributes( $page ),
			$this->get_default_attributes()
		);

		return true;
	}

	/**
	 * Add a page template
	 *
	 * @param string $name     Template name
	 * @param array  $template Template configuration
	 *
	 * @return self
	 */
	public function add_template( string $name, array $template ): self {
		$this->templates[ $name ] = $template;

		return $this;
	}

	/**
	 * Create a page from a template
	 *
	 * @param string $key          Page identifier
	 * @param string $template     Template name
	 * @param array  $replacements Optional. Placeholder replacements
	 *
	 * @return true|WP_Error True on success, WP_Error on failure
	 */
	public function add_page_from_template( string $key, string $template, array $replacements = [] ) {
		if ( ! isset( $this->templates[ $template ] ) ) {
			return new WP_Error(
				'template_not_found',
				sprintf( 'Template %s not found', $template )
			);
		}

		$page = $this->templates[ $template ];

		foreach ( [ 'title', 'content' ] as $field ) {
			if ( isset( $page[ $field ] ) ) {
				$page[ $field ] = strtr( $page[ $field ], $replacements );
			}
		}

		return $this->add_page( $key, $page );
	}

	/**
	 * Install registered pages
	 *
	 * @return array Array of page IDs keyed by their identifiers
	 */
	public function install(): array {
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
			$new_page_id = wp_insert_post( $attributes );
			if ( ! is_wp_error( $new_page_id ) ) {
				$page_ids[ $key ] = $new_page_id;
				$this->set_page_version( $new_page_id );
				$this->store_page_config( $new_page_id, $attributes );
			}
		}

		if ( ! empty( $page_ids ) ) {
			$this->save_page_ids( $page_ids );
		}

		return $page_ids;
	}

	/**
	 * Set the version for a page
	 *
	 * @param int $page_id Page ID
	 *
	 * @return bool True on success, false on failure
	 */
	protected function set_page_version( int $page_id ): bool {
		return update_post_meta( $page_id, '_page_version', self::VERSION );
	}

	/**
	 * Store page configuration
	 *
	 * @param int   $page_id    Page ID
	 * @param array $attributes Page attributes
	 *
	 * @return bool True on success, false on failure
	 */
	protected function store_page_config( int $page_id, array $attributes ): bool {
		return update_post_meta( $page_id, '_page_config', $attributes );
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
		$current_version = get_post_meta( $page_id, '_page_version', true );
		if ( version_compare( $current_version, self::VERSION, '<' ) ) {
			$attributes['ID'] = $page_id;
			$result           = wp_update_post( $attributes );

			if ( ! is_wp_error( $result ) ) {
				$this->set_page_version( $page_id );
				$this->store_page_config( $page_id, $attributes );
				$this->log( sprintf( 'Updated page: %s', $key ) );
			}
		}
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
				$option_key = $this->get_option_key( $key . '_page' );
				$page_id    = call_user_func( $this->get_handler, $option_key );
				if ( $page_id ) {
					$pages[ $key ] = $page_id;
				}
			}

			return $pages;
		}

		$option_key = $this->get_option_key( 'pages' );

		return get_option( $option_key, [] );
	}

	/**
	 * Save page IDs
	 *
	 * @param array $page_ids Array of page IDs
	 *
	 * @return bool True on success, false on failure
	 */
	protected function save_page_ids( array $page_ids ): bool {
		if ( $this->update_handler ) {
			// Use custom option handler for each page
			$results = [];
			foreach ( $page_ids as $key => $page_id ) {
				$option_key = $this->get_option_key( $key . '_page' );
				$results[]  = (bool) call_user_func( $this->update_handler, $option_key, $page_id );
			}

			return ! in_array( false, $results, true );
		}

		// Default to WordPress options if no custom handler
		$option_key = $this->get_option_key( 'pages' );

		return update_option( $option_key, $page_ids );
	}

	/**
	 * Get prefixed option key
	 *
	 * @param string $key Option key
	 *
	 * @return string Prefixed option key
	 */
	protected function get_option_key( string $key ): string {
		return empty( $this->option_prefix ) ? $key : "{$this->option_prefix}_{$key}";
	}

	/**
	 * Validate page configuration
	 *
	 * @param array $page Page configuration
	 *
	 * @return true|WP_Error True if valid, WP_Error if invalid
	 */
	protected function validate_page( array $page ) {
		$required = [ 'title', 'content' ];

		foreach ( $required as $field ) {
			if ( empty( $page[ $field ] ) ) {
				return new WP_Error(
					'missing_required_field',
					sprintf( 'Missing required field: %s', $field )
				);
			}
		}

		return true;
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
	 * Static helper method to create and install pages.
	 *
	 * @param array         $pages          Array of pages
	 * @param string        $prefix         Optional. Option prefix
	 * @param callable|null $update_handler Optional. Custom handler for updating options
	 * @param callable|null $get_handler    Optional. Custom handler for getting options
	 *
	 * @return array Array of page IDs
	 */
	public static function register( array $pages, string $prefix = '', ?callable $update_handler = null, ?callable $get_handler = null ): array {
		$instance = new self( $prefix, $get_handler, $update_handler );

		return $instance->add_pages( $pages )->install();
	}

	/**
	 * Static helper method to get a page ID by its key
	 *
	 * @param string        $key         Page key
	 * @param string        $prefix      Optional. Option prefix
	 * @param callable|null $get_handler Optional. Custom handler for getting options
	 *
	 * @return int|null Page ID if found, null otherwise
	 */
	public static function get_page_id( string $key, string $prefix = '', ?callable $get_handler = null ): ?int {
		if ( $get_handler ) {
			$option_key = empty( $prefix ) ? "{$key}_page" : "{$prefix}_{$key}_page";
			$page_id    = call_user_func( $get_handler, $option_key );

			return $page_id ? (int) $page_id : null;
		}

		$stored = get_option(
			empty( $prefix ) ? 'pages' : "{$prefix}_pages",
			[]
		);

		return $stored[ $key ] ?? null;
	}

	/**
	 * Static helper method to get a page URL by its key
	 *
	 * @param string        $key         Page key
	 * @param string        $prefix      Optional. Option prefix
	 * @param callable|null $get_handler Optional. Custom handler for getting options
	 *
	 * @return string|null Page URL if found, null otherwise
	 */
	public static function get_page_url( string $key, string $prefix = '', ?callable $get_handler = null ): ?string {
		$page_id = self::get_page_id( $key, $prefix, $get_handler );

		return $page_id ? get_permalink( $page_id ) : null;
	}

}