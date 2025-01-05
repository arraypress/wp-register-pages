# WordPress Page Registration Library

A comprehensive PHP library for registering and managing WordPress pages programmatically. This library provides a robust solution for creating, managing, and maintaining WordPress pages with version tracking and template support.

## Features

- 🚀 Automatic page creation and verification
- 📝 Template support for reusable page layouts
- 🔄 Version tracking with meta storage
- 👨‍👧‍👦 Parent-child page relationships
- 🛠️ Simple utility functions for quick implementation
- ✅ Comprehensive error handling
- 🔍 Debug logging support

## Requirements

- PHP 7.4 or higher
- WordPress 5.0 or higher

## Installation

You can install the package via composer:

```bash
composer require arraypress/wp-register-pages
```

## Basic Usage

Here's a simple example of registering pages:

```php
// Define your pages
$pages = [
    'contact' => [
        'title'   => 'Contact Us',
        'content' => 'Contact page content here...',
        'status'  => 'publish'
    ],
    'about' => [
        'title'   => 'About Us',
        'content' => 'About page content...',
        'parent'  => 'contact'  // Reference to another page key
    ]
];

// Register pages with a prefix
$page_ids = register_pages($pages, 'my_plugin');

// Get page URLs
$contact_url = get_registered_page_url('contact', 'my_plugin');
$about_url = get_registered_page_url('about', 'my_plugin');
```

## Using Templates

You can create reusable page templates:

```php
use ArrayPress\WP\Register\Pages;

$pages = Pages::instance();

// Add a template
$pages->add_template('basic_page', [
    'title'   => '%title%',
    'content' => '<h1>%heading%</h1><div>%content%</div>'
]);

// Create page from template
$pages->add_page_from_template('new-page', 'basic_page', [
    '%title%'   => 'New Page',
    '%heading%' => 'Welcome!',
    '%content%' => 'This is the page content.'
]);

// Install pages
$pages->install();
```

## Configuration Options

Each page can be configured with:

| Option | Type | Description |
|--------|------|-------------|
| title | string | Page title (required) |
| content | string | Page content (required) |
| status | string | Page status (default: 'publish') |
| parent | string/int | Parent page ID or key |
| menu_order | int | Menu order for the page |
| author | int | Page author ID |

## Utility Functions

Global helper functions for easy access:

```php
// Register pages
register_pages($pages, 'prefix');

// Get page ID
$page_id = get_registered_page_id('page-key', 'prefix');

// Get page URL
$page_url = get_registered_page_url('page-key', 'prefix');
```

## Error Handling

The library uses WordPress's WP_Error for error handling:

```php
use ArrayPress\WP\Register\Pages;

$pages = Pages::instance();
$result = $pages->add_page('invalid!key', [
    'title'   => 'Test Page',
    'content' => 'Content'
]);

if (is_wp_error($result)) {
    error_log($result->get_error_message());
}
```

## Version Management

Pages are tracked individually with version meta:

```php
// Version is stored in post meta as '_page_version'
// Configuration is stored as '_page_config'
// Updates are handled automatically during installation
```

## Debug Mode

Debug logging is enabled when WP_DEBUG is true:

```php
// Logs will include:
// - Page creation
// - Updates
// - Errors
// - Version changes
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request. For major changes, please open an issue first to discuss what you would like to change.

## License

This project is licensed under the GPL2+ License. See the LICENSE file for details.

## Credits

Developed and maintained by ArrayPress Limited.

## Support

For support, please use the [issue tracker](https://github.com/arraypress/wp-register-pages/issues).