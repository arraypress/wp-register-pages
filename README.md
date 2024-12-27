# WordPress Custom Page Registration Manager

A comprehensive PHP library for managing WordPress pages with versioning, templates, and advanced features. This library provides a robust solution for programmatically creating, updating, and managing pages in WordPress.

## Features

- 🚀 Automatic page creation and verification
- 🔄 Version tracking and updates
- 📝 Template support with placeholder replacements
- 🎯 Parent-child page relationships
- 🏷️ Metadata management
- 📊 Menu position management
- 🔧 Installation state tracking
- 🛠️ Debug logging
- 💾 Backup and restore functionality
- ✅ Key validation and sanitization

## Requirements

- PHP 7.4 or higher
- WordPress 6.7.1 or higher

## Installation

You can install the package via composer:

```bash
composer require arraypress/wp-register-custom-pages
```

## Basic Usage

Here's a simple example of how to register pages:

```php
use ArrayPress\WP\Register\CustomPages;

// Initialize the page manager
$manager = new CustomPages( 'my_plugin', [
	'version' => '1.0.0',
	'debug'   => true
] );

// Define your pages
$pages = [
	'about'   => [
		'title'   => 'About Us',
		'content' => '<!-- wp:paragraph -->Welcome to our company<!-- /wp:paragraph -->',
		'status'  => 'publish'
	],
	'contact' => [
		'title'   => 'Contact Us',
		'content' => '<!-- wp:paragraph -->Get in touch<!-- /wp:paragraph -->',
		'parent'  => 'about' // Reference parent by key
	]
];

// Register and install pages
$page_ids = $manager->register( $pages )->install();
```

## Advanced Usage

### Working with Templates

```php
// Add a reusable template
$manager->add_template( 'service', [
	'title'   => 'Service: {{name}}',
	'content' => '<!-- wp:paragraph -->{{description}}<!-- /wp:paragraph -->',
	'status'  => 'publish'
] );

// Create a page using the template
$manager->add_page_from_template( 'service-web', 'service', [
	'{{name}}'        => 'Web Development',
	'{{description}}' => 'Professional web development services'
] );
```

### Managing Page Metadata

```php
// Add metadata to a page
$manager->add_page_meta( 'about', '_custom_header', 'modern' );
```

### Setting Menu Positions

```php
// Arrange pages in menu
$manager->set_menu_positions( [
	'about'   => 1,
	'contact' => 2
] );
```

### Backup and Restore

```php
// Create a backup before making changes
$backup = $manager->backup_pages();

// Make your changes
$manager->update_pages();

// Restore if needed
if ( $something_went_wrong ) {
	$manager->restore_from_backup();
}
```

## Configuration Options

The RegisterPages constructor accepts two parameters:

```php
new RegisterPages( string $option_prefix, array $config );
```

### Available Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| version | string | '2.0.0' | Version for tracking updates |
| debug | boolean | WP_DEBUG | Enable debug logging |
| option_key | string | {prefix}_pages | Option key for storage |
| defaults | array | [...] | Default page attributes |

### Default Page Attributes

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| status | string | 'publish' | Post status |
| type | string | 'page' | Post type |
| author | int | current_user_id | Page author ID |
| comment_status | string | 'closed' | Comment status |
| ping_status | string | 'closed' | Ping status |
| parent | int/string | 0 | Parent page ID or key |
| menu_order | int | 0 | Menu position |

## Error Handling

The library uses exceptions for error handling:

```php
try {
	$manager->add_page( 'invalid-key!', [
		'title'   => 'Test Page',
		'content' => 'Test content'
	] );
} catch ( InvalidArgumentException $e ) {
	// Handle invalid key error
	error_log( $e->getMessage() );
}
```

## Upgrading Pages

The library automatically handles version changes:

```php
// Pages will automatically backup and reinstall when version changes
$manager = new RegisterPages( 'my_plugin', [
	'version' => '2.0.0' // Version change triggers update
] );

// Force reinstallation if needed
$manager->reset_installation();
$manager->install();
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request. For major changes, please open an issue first to discuss what you would like to change.

## License

This project is licensed under the GPL2+ License. See the LICENSE file for details.

## Credits

Developed and maintained by ArrayPress Limited.

## Support

For support, please use the [issue tracker](https://github.com/arraypress/wp-register-custom-pages/issues).