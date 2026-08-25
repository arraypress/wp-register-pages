<?php
/**
 * Page tests.
 *
 * @package ArrayPress\RegisterPages
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterPages\Tests;

use ArrayPress\RegisterPages\Pages;
use PHPUnit\Framework\TestCase;

/**
 * The pages a plugin needs in order to work.
 *
 * A checkout, a receipt, an account screen. Every commerce plugin creates
 * them on activation and most get one of three things wrong: not remembering
 * which page was which, not noticing when one has gone, and not telling the
 * admin why four pages must not be deleted.
 */
final class PagesTest extends TestCase {

	/**
	 * Empty the posts, options and registry.
	 */
	protected function setUp(): void {
		pages_reset_globals();

		Pages::set_prefix( 'myplugin' );
	}

	/**
	 * A page is created and its id remembered.
	 */
	public function test_a_page_is_created_and_remembered(): void {
		$id = Pages::add( 'checkout', [ 'title' => 'Checkout' ] );

		$this->assertNotNull( $id );
		$this->assertSame( $id, Pages::id( 'checkout' ) );
		$this->assertSame( $id, $GLOBALS['pages_options']['myplugin_checkout_page'] );
	}

	/**
	 * Registering again does not make a second page.
	 *
	 * The reason the id is stored at all: without it, every activation makes
	 * another checkout page and the first one becomes a mystery.
	 */
	public function test_registering_twice_makes_one_page(): void {
		$first  = Pages::add( 'checkout', [ 'title' => 'Checkout' ] );
		$second = Pages::add( 'checkout', [ 'title' => 'Checkout' ] );

		$this->assertSame( $first, $second );
		$this->assertCount( 1, $GLOBALS['pages_posts'] );
	}

	/**
	 * A trashed page counts as gone.
	 *
	 * get_post() returns a post object for something in the trash, so a
	 * plugin that only checks existence carries on linking its customers at
	 * a page nobody can reach.
	 */
	public function test_a_trashed_page_is_treated_as_missing(): void {
		$id = Pages::add( 'checkout', [ 'title' => 'Checkout' ] );

		wp_delete_post( $id );

		$this->assertNull( Pages::id( 'checkout' ), 'A trashed page was reported as present.' );

		// And registering again makes a new one, so the plugin works after
		// somebody has tidied up.
		$replacement = Pages::add( 'checkout', [ 'title' => 'Checkout' ] );

		$this->assertNotNull( $replacement );
		$this->assertNotSame( $id, $replacement );
	}

	/**
	 * A deleted page counts as gone.
	 */
	public function test_a_deleted_page_is_treated_as_missing(): void {
		Pages::add( 'checkout', [ 'title' => 'Checkout' ] );

		$GLOBALS['pages_posts'] = [];

		$this->assertNull( Pages::id( 'checkout' ) );
	}

	/**
	 * An id pointing at something that is not a page counts as gone.
	 *
	 * Ids are reused after a database restore, and a stored id that now
	 * belongs to somebody's blog post is worse than no id at all.
	 */
	public function test_an_id_that_is_not_a_page_is_treated_as_missing(): void {
		$id = Pages::add( 'checkout', [ 'title' => 'Checkout' ] );

		$GLOBALS['pages_posts'][ $id ]->post_type = 'post';

		$this->assertNull( Pages::id( 'checkout' ) );
	}

	/**
	 * A slug given is the slug used.
	 *
	 * The URL of a checkout page is not something to leave to whatever the
	 * title happened to be.
	 */
	public function test_a_slug_is_honoured(): void {
		$id = Pages::add( 'checkout', [ 'title' => 'Complete your purchase', 'slug' => 'checkout' ] );

		$this->assertSame( 'checkout', $GLOBALS['pages_posts'][ $id ]->post_name );
		$this->assertSame( 'https://example.test/checkout/', Pages::url( 'checkout' ) );
	}

	/**
	 * The old storage format still reads.
	 *
	 * Ids used to be stored as [ 'value' => id, 'label' => title ] to suit a
	 * settings screen. An upgrade that stopped reading that would make every
	 * existing install create its pages again.
	 */
	public function test_the_old_storage_format_is_still_read(): void {
		$id = wp_insert_post( [ 'post_title' => 'Checkout', 'post_type' => 'page' ] );

		$GLOBALS['pages_options']['myplugin_checkout_page'] = [ 'value' => $id, 'label' => 'Checkout' ];

		Pages::add( 'checkout', [ 'title' => 'Checkout' ] );

		$this->assertSame( $id, Pages::id( 'checkout' ) );
		$this->assertCount( 1, $GLOBALS['pages_posts'], 'The page was created again.' );
	}

	/**
	 * A page is labelled on the pages list.
	 *
	 * Without this the admin has several pages they dare not touch and
	 * nothing to tell them why.
	 */
	public function test_a_page_is_labelled_in_the_admin(): void {
		$id = Pages::add( 'checkout', [ 'title' => 'Checkout' ] );

		$states = Pages::post_states( [], $GLOBALS['pages_posts'][ $id ] );

		$this->assertContains( 'Checkout', $states );
	}

	/**
	 * Somebody else's page is left alone.
	 */
	public function test_another_page_is_not_labelled(): void {
		Pages::add( 'checkout', [ 'title' => 'Checkout' ] );

		$other = wp_insert_post( [ 'post_title' => 'About us', 'post_type' => 'page' ] );

		$this->assertSame( [], Pages::post_states( [], $GLOBALS['pages_posts'][ $other ] ) );
	}

	/**
	 * Existing states are kept.
	 *
	 * Core puts "Front Page" and "Privacy Policy" here, and a filter that
	 * returns only its own has taken those away.
	 */
	public function test_existing_states_are_kept(): void {
		$id = Pages::add( 'checkout', [ 'title' => 'Checkout' ] );

		$states = Pages::post_states( [ 'page_on_front' => 'Front Page' ], $GLOBALS['pages_posts'][ $id ] );

		$this->assertArrayHasKey( 'page_on_front', $states );
	}

	/**
	 * Forgetting a page leaves the page alone by default.
	 *
	 * A page holds content somebody may have edited, and a plugin deleting
	 * it on the way out destroys work it did not create.
	 */
	public function test_forgetting_a_page_does_not_delete_it(): void {
		$id = Pages::add( 'checkout', [ 'title' => 'Checkout' ] );

		Pages::remove( 'checkout' );

		$this->assertArrayNotHasKey( 'myplugin_checkout_page', $GLOBALS['pages_options'] );
		$this->assertSame( 'publish', $GLOBALS['pages_posts'][ $id ]->post_status );
	}

	/**
	 * Unless it is asked to.
	 */
	public function test_a_page_can_be_deleted_on_the_way_out(): void {
		$id = Pages::add( 'checkout', [ 'title' => 'Checkout' ] );

		Pages::remove( 'checkout', true );

		$this->assertSame( 'trash', $GLOBALS['pages_posts'][ $id ]->post_status );
	}

	/**
	 * A page WordPress refuses is reported as not made.
	 *
	 * wp_insert_post() will not create a post with no title and no content,
	 * and a library that returns an id it did not get is a library that
	 * stores nonsense.
	 */
	public function test_a_page_that_cannot_be_made_is_null(): void {
		$this->assertNull( Pages::add( 'nothing', [] ) );
		$this->assertArrayNotHasKey( 'myplugin_nothing_page', $GLOBALS['pages_options'] );
	}

	/**
	 * Every registered page can be listed.
	 */
	public function test_the_pages_can_be_listed(): void {
		Pages::add( 'checkout', [ 'title' => 'Checkout' ] );
		Pages::add( 'receipt', [ 'title' => 'Receipt' ] );

		$this->assertSame( [ 'checkout', 'receipt' ], array_keys( Pages::ids() ) );
	}

	/**
	 * The url of a page that is gone is null, not a broken link.
	 */
	public function test_the_url_of_a_missing_page_is_null(): void {
		$this->assertNull( Pages::url( 'never_made' ) );
	}
}
