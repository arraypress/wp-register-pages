# Register Pages

Create the pages a plugin needs on activation, remember their IDs, and label
them in the admin list.

## Install

```bash
composer require arraypress/wp-register-pages
```

Requires PHP 8.3.

## Use

```php
add_action( 'init', function () {
	register_pages( 'myplugin', [
		'checkout' => [
			'title' => __( 'Checkout', 'my-plugin' ),
			'slug'  => 'checkout',
		],
		'receipt'  => [
			'title'   => __( 'Order receipt', 'my-plugin' ),
			'content' => '[myplugin_receipt]',
		],
	] );
} );
```

Safe to call on every request: a page whose id is stored and still exists is
left alone.

Then, wherever you need it:

```php
$url = get_registered_page_url( 'checkout' );
$id  = get_registered_page_id( 'checkout' );
```

Both return null when the page is gone, so a missing page is something your
code can notice rather than a link to nowhere.

### Options

| Option    | Type   | What it does                                            |
| --------- | ------ | ------------------------------------------------------- |
| `title`   | string | The page title.                                          |
| `content` | string | Its content. A shortcode, usually.                       |
| `slug`    | string | The URL. Taken from the title if omitted.                |
| `parent`  | int    | A parent page id.                                        |
| `status`  | string | `publish` by default.                                    |
| `label`   | string | What the admin list calls it. The title if omitted.      |

Either a title or content is required — WordPress refuses to create a post
with neither, and this reports that rather than storing an id it never got.

## What it gets right

Three things every plugin doing this by hand gets wrong at least once.

**Remembering.** The id is stored, so the next activation does not make a
second checkout page and leave the first one a mystery.

**Checking.** `get_post()` returns a post object for a page in the trash, so a
plugin that only checks existence carries on linking its customers at a page
nobody can reach. Trashed counts as gone here, and so does an id that now
belongs to something that is not a page — ids get reused after a database
restore.

**Saying so.** The pages are labelled on the pages list. Without that the
admin has several pages they dare not touch and nothing to tell them why. It
is two lines of code and it is the difference between a plugin that explains
itself and one that does not.

## Uninstall

```php
unregister_page( 'checkout' );        // forget it
unregister_page( 'checkout', true );  // forget it and bin the page
```

Forgetting does not delete by default. A page holds content somebody may have
edited, and a plugin deleting it on the way out destroys work it did not
create.

## Upgrading from 1.x

The API is smaller: `register_pages( $prefix, $pages )` rather than five
positional arguments and a pair of option callbacks.

Ids stored by 1.x as `[ 'value' => id, 'label' => title ]` are still read, so
an upgrade does not make every install create its pages again. New ids are
stored as plain integers.

## Testing

```bash
composer test          # phpunit
composer lint          # phpcs, defect sniffs
composer format:check  # phpcs, formatting
```
