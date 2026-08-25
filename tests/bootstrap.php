<?php
/**
 * PHPUnit bootstrap.
 *
 * Posts and options live in globals, so a test can create a page, trash it,
 * and ask what the library makes of that — which is where the interesting
 * behaviour is.
 *
 * @package ArrayPress\RegisterPages
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! class_exists( 'WP_Post' ) ) {
	class WP_Post {
		public int $ID = 0;
		public string $post_type = 'page';
		public string $post_status = 'publish';
		public string $post_title = '';
		public string $post_name = '';
		public int $post_parent = 0;
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public function __construct( public string $code = '', public string $message = '' ) {
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
		$GLOBALS['pages_hooks'][ $hook ][] = $callback;

		return true;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default = false ) {
		return $GLOBALS['pages_options'][ $key ] ?? $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $key, $value, $autoload = null ) {
		$GLOBALS['pages_options'][ $key ] = $value;

		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $key ) {
		$existed = isset( $GLOBALS['pages_options'][ $key ] );

		unset( $GLOBALS['pages_options'][ $key ] );

		return $existed;
	}
}

if ( ! function_exists( 'wp_insert_post' ) ) {
	function wp_insert_post( $args, $wp_error = false ) {
		if ( '' === ( $args['post_title'] ?? '' ) && '' === ( $args['post_content'] ?? '' ) ) {
			return $wp_error ? new WP_Error( 'empty_content', 'Content, title, and excerpt are empty.' ) : 0;
		}

		$id = count( $GLOBALS['pages_posts'] ?? [] ) + 100;

		$post              = new WP_Post();
		$post->ID          = $id;
		$post->post_type   = $args['post_type'] ?? 'post';
		$post->post_status = $args['post_status'] ?? 'publish';
		$post->post_title  = $args['post_title'] ?? '';
		$post->post_name   = $args['post_name'] ?? sanitize_title( $post->post_title );
		$post->post_parent = (int) ( $args['post_parent'] ?? 0 );

		$GLOBALS['pages_posts'][ $id ] = $post;

		return $id;
	}
}

if ( ! function_exists( 'get_post' ) ) {
	function get_post( $id = null ) {
		return $GLOBALS['pages_posts'][ (int) $id ] ?? null;
	}
}

if ( ! function_exists( 'wp_delete_post' ) ) {
	function wp_delete_post( $id, $force = false ) {
		if ( $force ) {
			unset( $GLOBALS['pages_posts'][ (int) $id ] );
		} elseif ( isset( $GLOBALS['pages_posts'][ (int) $id ] ) ) {
			$GLOBALS['pages_posts'][ (int) $id ]->post_status = 'trash';
		}

		return true;
	}
}

if ( ! function_exists( 'get_permalink' ) ) {
	function get_permalink( $id = 0 ) {
		$post = get_post( $id );

		return $post ? 'https://example.test/' . $post->post_name . '/' : false;
	}
}

if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $title ) {
		return trim( (string) preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $title ) ), '-' );
	}
}

/**
 * Forget everything a previous test set up.
 *
 * @return void
 */
function pages_reset_globals(): void {
	$GLOBALS['pages_hooks']   = [];
	$GLOBALS['pages_options'] = [];
	$GLOBALS['pages_posts']   = [];

	( new ReflectionProperty( 'ArrayPress\RegisterPages\Pages', 'pages' ) )->setValue( null, [] );
	( new ReflectionProperty( 'ArrayPress\RegisterPages\Pages', 'prefix' ) )->setValue( null, '' );
	( new ReflectionProperty( 'ArrayPress\RegisterPages\Pages', 'hooked' ) )->setValue( null, false );
}
