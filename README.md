# Register Pages

Create the pages a plugin needs, remember which ones they are, and mark them
in the admin list so nobody deletes one by accident.

## What it does

A plugin that needs a checkout page usually creates it on activation, stores
the id in an option, and then has to cope with every way that can come apart:
the page trashed, the option missing, the page created twice because
activation ran again.

This does the creating and the remembering, gives you the id or URL back by
name, and labels the page in Pages so it is obvious it belongs to something.

## Features

* Create the pages a plugin needs, without duplicating them on reactivation
* Get a page's id or URL by name, rather than reading an option
* Set the slug, title and initial content, including a shortcode
* Show a label in the admin list, so nobody deletes one wondering what it is
* Recreate a page that was trashed, without touching the ones that are fine
* Forget a page, or delete it outright, at uninstall

## Installation

```bash
composer require arraypress/wp-register-pages
```

## Quick start

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

Then, wherever you need to link to one:

```php
$url = get_registered_page_url( 'checkout' );
```

## Requirements

* PHP 8.3 or later
* WordPress 7.1 or later

## License

GPL-2.0-or-later
