# WordPress Page Registration Manager

A comprehensive solution for managing WordPress pages programmatically. This library makes it easy to create, update, and manage pages with features like version tracking, templates, and custom storage options.

## Requirements

- PHP 7.4 or higher
- WordPress 5.0 or higher

## Installation

You can install the package via composer:

```bash
composer require arraypress/wp-register-pages
```

## Quick Start

Register pages with just a few lines of code:

```php
// Define your pages
$pages = [
	'contact' => [
		'title'   => 'Contact Us',
		'content' => 'Contact page content here...'
	],
	'about'   => [
		'title'   => 'About Us',
		'content' => 'About page content...',
		'parent'  => 'contact'  // Will be set as child of contact page
	]
];

// Register pages (returns array of page IDs)
$page_ids = register_pages( $pages, 'my_plugin' );

// Get page URLs
$contact_url = get_registered_page_url( 'contact', 'my_plugin' );
$about_url   = get_registered_page_url( 'about', 'my_plugin' );
```

## Utility Functions

The library provides simple helper functions for common tasks:

### Register Pages

```php
// Basic registration
$page_ids = register_pages( $pages );

// With a prefix for option storage
$page_ids = register_pages( $pages, 'my_plugin' );

// With custom option handlers (e.g., for EDD integration)
$page_ids = register_pages(
	$pages,
	'edd_pages',
	'edd_update_option',
	'edd_get_option'
);
```

### Get Page ID

```php
// Get a page ID by its key
$page_id = get_registered_page_id( 'contact', 'my_plugin' );
```

### Get Page URL

```php
// Get a page URL by its key
$page_url = get_registered_page_url( 'contact', 'my_plugin' );
```

## Page Configuration

Each page can be configured with these options:

| Option | Type | Description | Default |
|--------|------|-------------|---------|
| title | string | Page title (required) | - |
| content | string | Page content (required) | - |
| status | string | Publication status | 'publish' |
| type | string | Post type | 'page' |
| author | int | Author ID | current user |
| comment_status | string | Comment status | 'closed' |
| ping_status | string | Ping status | 'closed' |
| parent | int/string | Parent page ID or key | 0 |
| menu_order | int | Menu order | 0 |

## Features

- 🚀 Automatic page creation and verification
- 📝 Template support with placeholder replacements
- 🔄 Version tracking and management
- 👨‍👧‍👦 Parent-child page relationships
- 🎛️ Custom option storage support
- ✅ Comprehensive error handling
- 🔍 Debug mode with detailed logging
- 🛡️ Type-safe implementation

## Advanced Usage

### Using the Pages Class

For more advanced usage, you can use the Pages class directly:

```php
use ArrayPress\WP\Register\Pages;

$manager = new Pages( 'my_plugin' );

// Add multiple pages
$manager->add_pages( [
	'privacy' => [
		'title'   => 'Privacy Policy',
		'content' => 'Privacy policy content...'
	]
] )->install();

// Add a single page
$manager->add_page( 'terms', [
	'title'   => 'Terms of Service',
	'content' => 'Terms content...'
] );
```

### Using Templates

Create reusable page templates with placeholders:

```php
use ArrayPress\WP\Register\Pages;

$manager = new Pages( 'my_plugin' );

// Add a template
$manager->add_template( 'basic_page', [
	'title'   => '%title%',
	'content' => '<h1>%heading%</h1><div>%content%</div>',
	'status'  => 'publish'
] );

// Create a page from the template
$manager->add_page_from_template( 'new-page', 'basic_page', [
	'%title%'   => 'New Page',
	'%heading%' => 'Welcome!',
	'%content%' => 'This is the page content.'
] );

// Install all pages
$manager->install();
```

### Custom Option Storage

For custom option handling (e.g., EDD integration):

```php
$manager = new Pages(
	'edd_pages',
	'edd_get_option',    // Get handler
	'edd_update_option'  // Update handler
);
```

### Error Handling

The library uses WordPress's WP_Error for error handling:

```php
$result = $manager->add_page( 'invalid!key', [
	'title'   => 'Test Page',
	'content' => 'Content'
] );

if ( is_wp_error( $result ) ) {
	error_log( $result->get_error_message() );
}
```

## Debug Mode

Debug logging is automatically enabled when `WP_DEBUG` is true:

```php
// Logs will include:
// - Page registration events
// - Page updates
// - Error messages
// - Version changes
// - Option storage operations
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request. For major changes, please open an issue first to discuss what you would like to change.

## License

This project is licensed under the GPL2+ License. See the LICENSE file for details.

## Support

For support, please use the [issue tracker](https://github.com/arraypress/wp-register-pages/issues).