<?php
/**
 * Page Registration Manager for WordPress
 *
 * A comprehensive solution for managing WordPress pages with features like:
 * - Automatic page creation and verification
 * - Version tracking and updates
 * - Template support
 * - Metadata management
 * - Backup and restore
 * - Parent-child relationships
 * - Installation state tracking
 *
 * @package     ArrayPress/Utils/Register
 * @copyright   Copyright (c) 2024, ArrayPress Limited
 * @license     GPL2+
 * @version     2.0.0
 * @author      ArrayPress
 */

declare( strict_types=1 );

namespace ArrayPress\WP;

use InvalidArgumentException;
use WP_Post;

/**
 * Class RegisterPages
 */
class RegisterPages {

	/**
	 * Library version
	 */
	private const VERSION = '2.0.0';

	/**
	 * Default page settings
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
	 * Collection of pages
	 *
	 * @var array
	 */
	protected array $pages = [];

	/**
	 * Page templates
	 *
	 * @var array
	 */
	protected array $templates = [];

	/**
	 * Stored page IDs for relationships
	 *
	 * @var array
	 */
	protected array $stored_page_ids = [];

	/**
	 * Option prefix for storing page IDs
	 *
	 * @var string
	 */
	protected string $option_prefix;

	/**
	 * Configuration settings
	 *
	 * @var array
	 */
	protected array $config;

	/**
	 * Initialize the Page Manager
	 *
	 * @param string $option_prefix Option prefix for storing page IDs
	 * @param array  $config        Configuration settings including version
	 */
	public function __construct( string $option_prefix = '', array $config = [] ) {
		$this->option_prefix = $option_prefix;
		$this->config        = wp_parse_args( $config, [
			'option_key' => $this->get_option_key( 'pages' ),
			'version'    => self::VERSION,
			'defaults'   => self::DEFAULTS,
			'debug'      => defined( 'WP_DEBUG' ) && WP_DEBUG,
		] );

		// Set default author if not provided
		if ( empty( $this->config['defaults']['author'] ) ) {
			$this->config['defaults']['author'] = get_current_user_id();
		}

		// Check for version changes
		$this->maybe_upgrade();
	}

	/**
	 * Check if version has changed and handle upgrades
	 */
	protected function maybe_upgrade(): void {
		$stored_version = get_option( $this->get_option_key( 'version' ) );

		if ( $stored_version !== $this->config['version'] ) {
			$this->log( 'Version change detected', [
				'old' => $stored_version,
				'new' => $this->config['version']
			] );

			// Backup existing pages before upgrade
			$this->backup_pages();

			// Reset installation to trigger updates
			$this->reset_installation();

			// Update version
			update_option( $this->get_option_key( 'version' ), $this->config['version'] );
		}
	}

	/**
	 * Set option prefix
	 *
	 * @param string $prefix Option prefix
	 *
	 * @return self
	 */
	public function set_option_prefix( string $prefix ): self {
		$this->option_prefix        = $prefix;
		$this->config['option_key'] = $this->get_option_key( 'pages' );

		return $this;
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
	 * @param array  $replacements Placeholder replacements
	 *
	 * @return self
	 */
	public function add_page_from_template( string $key, string $template, array $replacements = [] ): self {
		if ( ! isset( $this->templates[ $template ] ) ) {
			throw new InvalidArgumentException( "Template '{$template}' not found." );
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
	 * Get prefixed option key
	 *
	 * @param string $key Option key
	 *
	 * @return string Prefixed option key
	 */
	protected function get_option_key( string $key ): string {
		if ( empty( $this->option_prefix ) ) {
			return $key;
		}

		return $this->option_prefix . '_' . $key;
	}

	/**
	 * Register multiple pages
	 *
	 * @param array $pages Array of pages
	 *
	 * @return self
	 */
	public function register( array $pages ): self {
		foreach ( $pages as $key => $page ) {
			$this->add_page( $key, $page );
		}

		return $this;
	}

	/**
	 * Add a single page
	 *
	 * @param string $key  Page identifier
	 * @param array  $page Page attributes
	 *
	 * @return self
	 * @throws InvalidArgumentException If key is invalid
	 */
	public function add_page( string $key, array $page ): self {
		if ( ! $this->is_valid_key( $key ) ) {
			throw new InvalidArgumentException(
				sprintf( 'Invalid page key: %s. Keys must contain only lowercase letters, numbers, underscores, and hyphens.', $key )
			);
		}

		$this->validate_page_attributes( $page );

		// Convert simplified attributes to WP post attributes
		$page                = $this->prepare_page_attributes( $page );
		$this->pages[ $key ] = wp_parse_args( $page, $this->get_default_attributes() );

		return $this;
	}

	/**
	 * Validate required page attributes
	 *
	 * @param array $page Page attributes
	 *
	 * @throws InvalidArgumentException If required fields are missing
	 */
	protected function validate_page_attributes( array $page ): void {
		$required = [ 'title', 'content' ];
		foreach ( $required as $field ) {
			if ( empty( $page[ $field ] ) ) {
				throw new InvalidArgumentException( "Missing required field: {$field}" );
			}
		}
	}

	/**
	 * Convert simplified attributes to WordPress post attributes
	 *
	 * @param array $page Page attributes
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
		$defaults = $this->config['defaults'];

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
	 * Check if pages are already installed
	 *
	 * @return bool Whether pages are installed
	 */
	public function is_installed(): bool {
		return (bool) get_option( $this->get_option_key( 'completed' ), false );
	}

	/**
	 * Reset installation status
	 *
	 * @return bool Success
	 */
	public function reset_installation(): bool {
		return delete_option( $this->get_option_key( 'completed' ) );
	}

	/**
	 * Install registered pages
	 *
	 * @return array Array of page IDs
	 */
	public function install(): array {
		// Check if already installed
		if ( $this->is_installed() ) {
			return $this->get_page_ids();
		}

		$this->log( 'Starting page installation' );

		$current_options = get_option( $this->config['option_key'], [] );
		$page_ids        = [];
		$changed         = false;

		foreach ( $this->pages as $key => $attributes ) {
			// Check existing page
			$stored_id   = $current_options[ $key ] ?? 0;
			$existing_id = $this->page_exists( $attributes['post_title'], $stored_id );

			if ( $existing_id ) {
				$page_ids[ $key ]              = $existing_id;
				$this->stored_page_ids[ $key ] = $existing_id;
				continue;
			}

			// Handle parent reference
			if ( isset( $attributes['post_parent'] ) && is_string( $attributes['post_parent'] ) ) {
				$attributes['post_parent'] = $this->stored_page_ids[ $attributes['post_parent'] ] ?? 0;
			}

			// Create new page
			$new_page_id = wp_insert_post( $attributes );

			if ( ! is_wp_error( $new_page_id ) ) {
				$page_ids[ $key ]              = $new_page_id;
				$this->stored_page_ids[ $key ] = $new_page_id;
				$changed                       = true;

				$this->log( 'Created new page', [
					'key' => $key,
					'id'  => $new_page_id
				] );
			}
		}

		if ( $changed ) {
			update_option( $this->config['option_key'], $page_ids );
			update_option( $this->get_option_key( 'completed' ), true );

			$this->log( 'Installation completed', [
				'pages' => count( $page_ids )
			] );
		}

		return $page_ids;
	}

	/**
	 * Check if a page exists
	 *
	 * @param string $title   Page title
	 * @param int    $post_id Optional post ID
	 *
	 * @return int|null Post ID if exists
	 */
	protected function page_exists( string $title, int $post_id = 0 ): ?int {
		if ( $post_id > 0 && get_post( $post_id ) instanceof WP_Post ) {
			return $post_id;
		}

		$existing = get_posts( [
			'post_type'              => 'page',
			'post_status'            => [ 'publish', 'draft', 'pending' ],
			'title'                  => $title,
			'posts_per_page'         => 1,
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false,
			'orderby'                => 'post_date ID',
			'order'                  => 'ASC',
		] );

		return ! empty( $existing ) ? $existing[0]->ID : null;
	}

	/**
	 * Update existing pages
	 *
	 * @param bool $force Force update even if unchanged
	 *
	 * @return array Updated page IDs
	 */
	public function update_pages( bool $force = false ): array {
		$updated = [];

		foreach ( $this->pages as $key => $attributes ) {
			$page_id = $this->get_page_id( $key );
			if ( $page_id ) {
				$attributes['ID'] = $page_id;
				$result           = wp_update_post( $attributes );
				if ( ! is_wp_error( $result ) ) {
					$updated[ $key ] = $result;
				}
			}
		}

		return $updated;
	}

	/**
	 * Add metadata to a page
	 *
	 * @param string $key        Page identifier
	 * @param string $meta_key   Metadata key
	 * @param mixed  $meta_value Metadata value
	 *
	 * @return self
	 */
	public function add_page_meta( string $key, string $meta_key, $meta_value ): self {
		if ( $page_id = $this->get_page_id( $key ) ) {
			update_post_meta( $page_id, $meta_key, $meta_value );
		}

		return $this;
	}

	/**
	 * Get a page ID
	 *
	 * @param string $key Page identifier
	 *
	 * @return int|null Page ID or null if not found
	 */
	public function get_page_id( string $key ): ?int {
		$options = get_option( $this->config['option_key'], [] );
		$page_id = $options[ $key ] ?? null;

		if ( $page_id && get_post( $page_id ) instanceof WP_Post ) {
			return $page_id;
		}

		return null;
	}

	/**
	 * Get all page IDs
	 *
	 * @param bool $verify Whether to verify existence
	 *
	 * @return array Array of page IDs
	 */
	public function get_page_ids( bool $verify = true ): array {
		$options = get_option( $this->config['option_key'], [] );

		if ( ! $verify ) {
			return $options;
		}

		// Verify each page exists
		foreach ( $options as $key => $id ) {
			if ( ! get_post( $id ) instanceof WP_Post ) {
				unset( $options[ $key ] );
			}
		}

		return $options;
	}

	/**
	 * Check if a page exists by key
	 *
	 * @param string $key Page identifier
	 *
	 * @return bool Whether the page exists
	 */
	public function has_page( string $key ): bool {
		return $this->get_page_id( $key ) !== null;
	}

	/**
	 * Get page status
	 *
	 * @param string $key Page identifier
	 *
	 * @return string|null Page status or null if not found
	 */
	public function get_page_status( string $key ): ?string {
		if ( $page_id = $this->get_page_id( $key ) ) {
			$post = get_post( $page_id );

			return $post ? $post->post_status : null;
		}

		return null;
	}

	/**
	 * Backup current pages
	 *
	 * @return array Backup data
	 */
	public function backup_pages(): array {
		$backup = [];
		foreach ( $this->get_page_ids() as $key => $page_id ) {
			$post = get_post( $page_id );
			if ( $post ) {
				$backup[ $key ] = [
					'title'   => $post->post_title,
					'content' => $post->post_content,
					'status'  => $post->post_status,
					'meta'    => get_post_meta( $page_id ),
				];
			}
		}

		update_option( $this->get_option_key( 'backup' ), $backup );
		$this->log( 'Created pages backup', [ 'count' => count( $backup ) ] );

		return $backup;
	}

	/**
	 * Restore from backup
	 *
	 * @return bool Success
	 */
	public function restore_from_backup(): bool {
		$backup = get_option( $this->get_option_key( 'backup' ), [] );
		if ( empty( $backup ) ) {
			$this->log( 'No backup found to restore' );

			return false;
		}

		$this->log( 'Starting backup restoration', [ 'pages' => count( $backup ) ] );
		$this->reset_installation();
		$this->register( $backup )->install();

		return true;
	}

	/**
	 * Set menu positions for pages
	 *
	 * @param array $positions Array of positions keyed by page identifier
	 *
	 * @return self
	 */
	public function set_menu_positions( array $positions ): self {
		foreach ( $positions as $key => $position ) {
			if ( $page_id = $this->get_page_id( $key ) ) {
				wp_update_post( [
					'ID'         => $page_id,
					'menu_order' => (int) $position
				] );
			}
		}

		return $this;
	}

	/**
	 * Delete all pages and cleanup
	 *
	 * @param bool $force Whether to permanently delete
	 *
	 * @return bool Success
	 */
	public function uninstall( bool $force = false ): bool {
		$this->log( 'Starting uninstallation', [
			'force' => $force
		] );

		$page_ids = $this->get_page_ids( false );

		foreach ( $page_ids as $page_id ) {
			wp_delete_post( $page_id, $force );
		}

		// Clean up all related options
		delete_option( $this->config['option_key'] );
		delete_option( $this->get_option_key( 'completed' ) );
		delete_option( $this->get_option_key( 'version' ) );
		delete_option( $this->get_option_key( 'backup' ) );

		$this->log( 'Uninstallation completed' );

		return true;
	}

	/**
	 * Log a message if debugging is enabled
	 *
	 * @param string $message Message to log
	 * @param array  $context Additional context
	 */
	protected function log( string $message, array $context = [] ): void {
		if ( $this->config['debug'] ) {
			$prefix = $this->option_prefix ? "[{$this->option_prefix}] " : '';
			error_log( sprintf( '%sRegisterPages: %s - %s',
				$prefix,
				$message,
				json_encode( $context )
			) );
		}
	}

}