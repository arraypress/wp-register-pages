<?php
/**
 * Pages
 *
 * @package     ArrayPress\RegisterPages
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       2.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterPages;

use WP_Post;

/**
 * The pages a plugin needs in order to work.
 *
 * A checkout, a receipt, an account screen. Every commerce plugin creates
 * them on activation and remembers which is which, and most of them get one
 * of three things wrong:
 *
 * - **Remembering.** The id has to be stored, or the next activation makes a
 *   second checkout page and the first one becomes a mystery.
 *
 * - **Checking.** `get_post()` returns a post object for a page in the trash,
 *   so a plugin that only checks existence carries on linking to a page
 *   nobody can reach. Trashed counts as gone here.
 *
 * - **Saying so.** Without a post state on the pages list, the admin has
 *   several pages they dare not touch and nothing to tell them why. It is
 *   two lines and it is the difference between a plugin that explains itself
 *   and one that does not.
 */
final class Pages {

	/**
	 * Registered pages, by key.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private static array $pages = [];

	/**
	 * The option prefix.
	 *
	 * @var string
	 */
	private static string $prefix = '';

	/**
	 * Whether the post-state filter is attached.
	 *
	 * @var bool
	 */
	private static bool $hooked = false;

	/**
	 * Register a page, creating it if it is not there.
	 *
	 * @param string               $key    How the plugin refers to it.
	 * @param array<string, mixed> $config title, content, slug, parent, status.
	 *
	 * @return int|null The page id, or null if it could not be made.
	 */
	public static function add( string $key, array $config ): ?int {
		if ( '' === $key ) {
			return null;
		}

		$config = array_merge(
			[
				'title'   => '',
				'content' => '',
				'slug'    => '',
				'parent'  => 0,
				'status'  => 'publish',
			],
			$config
		);

		self::$pages[ $key ] = $config;

		if ( ! self::$hooked ) {
			self::$hooked = true;

			add_filter( 'display_post_states', [ self::class, 'post_states' ], 10, 2 );
		}

		$existing = self::id( $key );

		if ( null !== $existing ) {
			return $existing;
		}

		return self::create( $key, $config );
	}

	/**
	 * Make the page and remember it.
	 *
	 * @param string               $key    Its key.
	 * @param array<string, mixed> $config Its configuration.
	 *
	 * @return int|null
	 */
	private static function create( string $key, array $config ): ?int {
		$args = [
			'post_title'     => (string) $config['title'],
			'post_content'   => (string) $config['content'],
			'post_status'    => (string) $config['status'],
			'post_type'      => 'page',
			'post_parent'    => (int) $config['parent'],
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
		];

		// A slug given outright, because the URL of a checkout page is not
		// something to leave to whatever the title happened to be.
		if ( '' !== (string) $config['slug'] ) {
			$args['post_name'] = sanitize_title( (string) $config['slug'] );
		}

		$id = wp_insert_post( $args, true );

		if ( is_wp_error( $id ) || ! $id ) {
			return null;
		}

		update_option( self::option( $key ), (int) $id, false );

		return (int) $id;
	}

	/**
	 * The id of a registered page, if it is still there.
	 *
	 * Null covers all three ways it might not be: never created, deleted, or
	 * in the trash. A trashed page is one nobody can reach, so a plugin
	 * treating it as present links its customers at a 404.
	 *
	 * @param string $key The page's key.
	 *
	 * @return int|null
	 */
	public static function id( string $key ): ?int {
		$stored = get_option( self::option( $key ) );

		// The id used to be stored as [ 'value' => id, 'label' => title ] to
		// suit a settings screen. Read both, write the simple one.
		if ( is_array( $stored ) ) {
			$stored = $stored['value'] ?? 0;
		}

		$id = (int) $stored;

		if ( $id < 1 ) {
			return null;
		}

		$post = get_post( $id );

		if ( ! $post instanceof WP_Post || 'page' !== $post->post_type || 'trash' === $post->post_status ) {
			return null;
		}

		return $id;
	}

	/**
	 * The permalink of a registered page.
	 *
	 * @param string $key The page's key.
	 *
	 * @return string|null
	 */
	public static function url( string $key ): ?string {
		$id = self::id( $key );

		return null === $id ? null : (string) get_permalink( $id );
	}

	/**
	 * Every registered page's id, by key.
	 *
	 * @return array<string, int>
	 */
	public static function ids(): array {
		$ids = [];

		foreach ( array_keys( self::$pages ) as $key ) {
			$id = self::id( $key );

			if ( null !== $id ) {
				$ids[ $key ] = $id;
			}
		}

		return $ids;
	}

	/**
	 * Forget a page, and optionally bin it.
	 *
	 * For uninstall. Not the default, because a page holds content somebody
	 * may have edited and a plugin deleting it on the way out is a plugin
	 * that destroys work it did not create.
	 *
	 * @param string $key    The page's key.
	 * @param bool   $delete Whether to trash the page as well as forget it.
	 *
	 * @return bool
	 */
	public static function remove( string $key, bool $delete = false ): bool {
		$id = self::id( $key );

		if ( $delete && null !== $id ) {
			wp_delete_post( $id );
		}

		unset( self::$pages[ $key ] );

		return delete_option( self::option( $key ) );
	}

	/**
	 * Label a plugin's pages on the pages list.
	 *
	 * @param string[] $states The states so far.
	 * @param WP_Post  $post   The page being listed.
	 *
	 * @return string[]
	 */
	public static function post_states( $states, $post ): array {
		$states = (array) $states;

		if ( ! $post instanceof WP_Post || 'page' !== $post->post_type ) {
			return $states;
		}

		foreach ( self::$pages as $key => $config ) {
			if ( self::id( $key ) === $post->ID ) {
				$states[ self::$prefix . '_' . $key ] = (string) ( $config['label'] ?? $config['title'] );
			}
		}

		return $states;
	}

	/**
	 * Set the prefix every option key is built from.
	 *
	 * @param string $prefix The plugin's prefix.
	 *
	 * @return void
	 */
	public static function set_prefix( string $prefix ): void {
		self::$prefix = trim( $prefix, '_' );
	}

	/**
	 * Where a page's id is stored.
	 *
	 * @param string $key The page's key.
	 *
	 * @return string
	 */
	private static function option( string $key ): string {
		return ( '' === self::$prefix ? '' : self::$prefix . '_' ) . $key . '_page';
	}

	/**
	 * Everything registered.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function all(): array {
		return self::$pages;
	}
}
