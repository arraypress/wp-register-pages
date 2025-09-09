<?php
/**
 * WordPress Page Registration
 *
 * Dead simple page registration for WordPress plugins.
 * Creates pages on activation and stores their IDs.
 *
 * @package ArrayPress\PageUtils
 * @since   1.0.0
 * @author  David Sherlock
 * @license GPL-2.0-or-later
 */

declare( strict_types=1 );

namespace ArrayPress\PageUtils;

use WP_Post;

/**
 * Register Class
 *
 * Simple page registration and management for WordPress plugins.
 * Creates pages if they don't exist and stores their IDs.
 *
 * Usage:
 * $register = new Register('myplugin');
 * $register->add('checkout', 'Checkout', 'Place your order here');
 * $register->install();
 */
class Register {

	/**
	 * Pages to register.
	 *
	 * @var array
	 */
	private array $pages = [];

	/**
	 * Plugin prefix.
	 *
	 * @var string
	 */
	private string $prefix;

	/**
	 * Custom function to get options.
	 *
	 * @var callable|null
	 */
	private $get_option_callback;

	/**
	 * Custom function to update options.
	 *
	 * @var callable|null
	 */
	private $update_option_callback;

	/**
	 * Constructor.
	 *
	 * @param string        $prefix                 Your plugin prefix.
	 * @param callable|null $get_option_callback    Optional custom function to get options.
	 * @param callable|null $update_option_callback Optional custom function to update options.
	 */
	public function __construct(
		string $prefix,
		?callable $get_option_callback = null,
		?callable $update_option_callback = null
	) {
		$this->prefix                 = $prefix;
		$this->get_option_callback    = $get_option_callback;
		$this->update_option_callback = $update_option_callback;
	}

	/**
	 * Add a page to register.
	 *
	 * @param string $key     Page key (e.g., 'checkout', 'account').
	 * @param string $title   Page title.
	 * @param string $content Page content (can include shortcodes).
	 * @param int    $parent  Parent page ID (optional).
	 *
	 * @return void
	 */
	public function add( string $key, string $title, string $content = '', int $parent = 0 ): void {
		$this->pages[ $key ] = [
			'title'   => $title,
			'content' => $content,
			'parent'  => $parent,
		];
	}

	/**
	 * Add multiple pages at once.
	 *
	 * @param array $pages Array of key => [title, content, parent].
	 *
	 * @return void
	 */
	public function add_multiple( array $pages ): void {
		foreach ( $pages as $key => $page ) {
			$this->add(
				$key,
				$page['title'] ?? '',
				$page['content'] ?? '',
				$page['parent'] ?? 0
			);
		}
	}

	/**
	 * Install/create the registered pages.
	 *
	 * @param bool $show_post_states Whether to show post states in admin.
	 *
	 * @return array Array of page IDs keyed by page key.
	 */
	public function install( bool $show_post_states = true ): array {
		$page_ids = [];

		foreach ( $this->pages as $key => $page ) {
			// Get existing page ID
			$existing_id = $this->get_stored_page_id( $key );

			// Check if page still exists
			if ( $existing_id && get_post( $existing_id ) ) {
				$page_ids[ $key ] = $existing_id;
				continue;
			}

			// Create the page
			$page_id = $this->create_page( $page );

			if ( $page_id ) {
				$page_ids[ $key ] = $page_id;
				$this->store_page_id( $key, $page_id, $page['title'] );
			}
		}

		// Setup post states display
		if ( $show_post_states && ! empty( $page_ids ) ) {
			$this->setup_post_states();
		}

		return $page_ids;
	}

	/**
	 * Create a single page.
	 *
	 * @param array $page Page configuration.
	 *
	 * @return int|null Page ID or null on failure.
	 */
	private function create_page( array $page ): ?int {
		$args = [
			'post_title'     => $page['title'],
			'post_content'   => $page['content'],
			'post_status'    => 'publish',
			'post_type'      => 'page',
			'post_parent'    => $page['parent'],
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
		];

		$page_id = wp_insert_post( $args );

		return is_wp_error( $page_id ) ? null : $page_id;
	}

	/**
	 * Get a stored page ID.
	 *
	 * @param string $key Page key.
	 *
	 * @return int|null Page ID or null if not found.
	 */
	private function get_stored_page_id( string $key ): ?int {
		$option_key = $this->prefix . '_' . $key . '_page';

		if ( $this->get_option_callback ) {
			$value = call_user_func( $this->get_option_callback, $option_key );

			// Handle both formats: just ID or ['value' => ID, 'label' => Title]
			if ( is_array( $value ) && isset( $value['value'] ) ) {
				return (int) $value['value'];
			}

			return $value ? (int) $value : null;
		}

		$value = get_option( $option_key );

		// Handle both formats
		if ( is_array( $value ) && isset( $value['value'] ) ) {
			return (int) $value['value'];
		}

		return $value ? (int) $value : null;
	}

	/**
	 * Store a page ID.
	 *
	 * @param string $key   Page key.
	 * @param int    $id    Page ID.
	 * @param string $title Page title.
	 *
	 * @return bool True on success.
	 */
	private function store_page_id( string $key, int $id, string $title ): bool {
		$option_key = $this->prefix . '_' . $key . '_page';

		// Store as array format for settings manager compatibility
		$value = [
			'value' => $id,
			'label' => $title
		];

		if ( $this->update_option_callback ) {
			return (bool) call_user_func( $this->update_option_callback, $option_key, $value );
		}

		return update_option( $option_key, $value );
	}

	/**
	 * Get all registered page IDs.
	 *
	 * @return array Array of page IDs keyed by page key.
	 */
	public function get_page_ids(): array {
		$page_ids = [];

		foreach ( array_keys( $this->pages ) as $key ) {
			$id = $this->get_stored_page_id( $key );
			if ( $id ) {
				$page_ids[ $key ] = $id;
			}
		}

		return $page_ids;
	}

	/**
	 * Get a single page ID.
	 *
	 * @param string $key Page key.
	 *
	 * @return int|null Page ID or null if not found.
	 */
	public function get_page_id( string $key ): ?int {
		return $this->get_stored_page_id( $key );
	}

	/**
	 * Get a page URL.
	 *
	 * @param string $key Page key.
	 *
	 * @return string|null Page URL or null if not found.
	 */
	public function get_page_url( string $key ): ?string {
		$page_id = $this->get_stored_page_id( $key );

		if ( ! $page_id ) {
			return null;
		}

		$url = get_permalink( $page_id );

		return $url ?: null;
	}

	/**
	 * Check if a page exists.
	 *
	 * @param string $key Page key.
	 *
	 * @return bool True if page exists.
	 */
	public function page_exists( string $key ): bool {
		$page_id = $this->get_stored_page_id( $key );

		return $page_id && get_post( $page_id );
	}

	/**
	 * Delete a page.
	 *
	 * @param string $key          Page key.
	 * @param bool   $force_delete Whether to bypass trash.
	 *
	 * @return bool True on success.
	 */
	public function delete_page( string $key, bool $force_delete = false ): bool {
		$page_id = $this->get_stored_page_id( $key );

		if ( ! $page_id ) {
			return false;
		}

		$deleted = wp_delete_post( $page_id, $force_delete );

		if ( $deleted ) {
			// Clear the stored option
			$option_key = $this->prefix . '_' . $key . '_page';

			if ( $this->update_option_callback ) {
				call_user_func( $this->update_option_callback, $option_key, null );
			} else {
				delete_option( $option_key );
			}

			return true;
		}

		return false;
	}

	/**
	 * Static helper to quickly register pages.
	 *
	 * @param array         $pages                  Array of pages to register.
	 * @param string        $prefix                 Plugin prefix.
	 * @param callable|null $get_option_callback    Optional custom function to get options.
	 * @param callable|null $update_option_callback Optional custom function to update options.
	 * @param bool          $show_post_states       Whether to show post states in admin.
	 *
	 * @return array Array of page IDs.
	 */
	public static function quick_install(
		array $pages,
		string $prefix,
		?callable $get_option_callback = null,
		?callable $update_option_callback = null,
		bool $show_post_states = true
	): array {
		$register = new self( $prefix, $get_option_callback, $update_option_callback );
		$register->add_multiple( $pages );

		return $register->install( $show_post_states );
	}

	/**
	 * Setup post states display in admin.
	 *
	 * @return void
	 */
	private function setup_post_states(): void {
		add_filter( 'display_post_states', [ $this, 'display_post_states' ], 10, 2 );
	}

	/**
	 * Display post states in the admin pages list.
	 *
	 * @param array    $post_states Current post states.
	 * @param WP_Post $post        Current post object.
	 *
	 * @return array Modified post states.
	 */
	public function display_post_states( array $post_states, WP_Post $post ): array {
		foreach ( $this->pages as $key => $page ) {
			$stored_id = $this->get_stored_page_id( $key );

			if ( $stored_id && $stored_id === $post->ID ) {
				// Format the label nicely
				$label = $page['title'];

				// Add prefix context if it makes sense
				if ( $this->prefix ) {
					$plugin_name                               = ucwords( str_replace( [
						'_',
						'-'
					], ' ', $this->prefix ) );
					$post_states[ $this->prefix . '_' . $key ] = sprintf( '%s — %s', $plugin_name, $label );
				} else {
					$post_states[ $key ] = $label;
				}
			}
		}

		return $post_states;
	}

}