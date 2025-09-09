# WordPress Page Registration

Dead simple page registration for WordPress plugins. Creates pages on activation, stores their IDs in a format compatible with settings managers, and shows post states in the admin.

## Install

```bash
composer require arraypress/wp-page-utils
```

## Basic Usage

```php
use ArrayPress\PageUtils\Register;

// Create registrar
$register = new Register('myplugin');

// Add pages
$register->add('checkout', 'Checkout', '[myplugin_checkout]');
$register->add('account', 'My Account', '[myplugin_account]');
$register->add('success', 'Order Complete', 'Thank you for your order!');

// Create the pages (post states are enabled by default)
$page_ids = $register->install();

// Now in WordPress admin, you'll see:
// Pages list: "Checkout — Myplugin Checkout"
// Pages list: "My Account — Myplugin Account"
```

## Post States Feature

When you register pages, they automatically show up with labels in the WordPress admin pages list:

```php
// Your pages will show in admin like:
// ✓ Checkout     — MyPlugin Checkout
// ✓ My Account   — MyPlugin Account  
// ✓ Thank You    — MyPlugin Success

// Disable post states if you don't want them
$register->install(false); // Pass false to disable

// Or with quick install
Register::quick_install($pages, 'myplugin', null, null, false);
```

## With Settings Manager

The library stores page IDs in a format compatible with your settings manager:

```php
use ArrayPress\PageUtils\Register;
use ArrayPress\SettingsUtils\Manager;

class MyPlugin {
    private Manager $settings;
    private Register $register;
    
    public function __construct() {
        $this->settings = new Manager('myplugin_settings');
        
        // Pass your settings callbacks
        $this->register = new Register(
            'myplugin',
            fn($key, $default = null) => $this->settings->get($key, $default),
            fn($key, $value) => $this->settings->update($key, $value)
        );
    }
    
    public function activate() {
        // Register pages on plugin activation
        $this->register->add_multiple([
            'checkout' => [
                'title'   => 'Checkout',
                'content' => '[myplugin_checkout]'
            ],
            'account' => [
                'title'   => 'My Account', 
                'content' => '[myplugin_account]'
            ],
            'success' => [
                'title'   => 'Thank You',
                'content' => 'Your order has been received!'
            ]
        ]);
        
        $this->register->install();
    }
}
```

## Storage Format

Pages are stored in a format compatible with settings managers and select fields:

```php
// Stored as:
[
    'value' => 123,        // Page ID
    'label' => 'Checkout'  // Page title
]

// This works perfectly with select fields in settings:
$settings->get('checkout_page'); // Returns 123 (the ID)
```

## Complete Example with Page Utils

Using both Register and Pages (detection) together:

```php
use ArrayPress\PageUtils\Register;
use ArrayPress\PageUtils\Pages;
use ArrayPress\SettingsUtils\Manager;

class MyShop {
    private Manager $settings;
    private Register $register;
    private Pages $pages;
    
    public function __construct() {
        // Settings manager
        $this->settings = new Manager('myshop_settings');
        
        // Page registration (for activation)
        $this->register = new Register(
            'myshop',
            fn($key, $default = null) => $this->settings->get($key, $default),
            fn($key, $value) => $this->settings->update($key, $value)
        );
        
        // Page detection (for runtime)
        $this->pages = new Pages(
            'myshop',
            fn($key, $default = null) => $this->settings->get($key, $default)
        );
        
        // Setup page detection
        $this->pages->add('checkout', 'checkout_page', ['myshop_checkout'], [], true);
        $this->pages->add('account', 'account_page', ['myshop_account'], [], true);
        $this->pages->add('success', 'success_page', ['myshop_success'], [], true);
    }
    
    public function activate() {
        // Create pages on activation
        $this->register->add('checkout', 'Checkout', '[myshop_checkout]');
        $this->register->add('account', 'My Account', '[myshop_account]');
        $this->register->add('success', 'Order Complete', 'Thank you!');
        
        $this->register->install();
    }
    
    public function init() {
        // Use page detection at runtime
        if ($this->pages->is('checkout')) {
            // Load checkout scripts
        }
        
        // Get URLs
        $checkout_url = $this->pages->get_url('checkout');
    }
}
```

## All Methods

```php
// Add a single page
$register->add('key', 'Page Title', 'Page content', $parent_id);

// Add multiple pages
$register->add_multiple([
    'checkout' => ['title' => 'Checkout', 'content' => '[shortcode]'],
    'account'  => ['title' => 'Account', 'content' => '[shortcode]']
]);

// Install/create pages
$page_ids = $register->install();

// Get a page ID
$id = $register->get_page_id('checkout');

// Get a page URL
$url = $register->get_page_url('checkout');

// Check if page exists
if ($register->page_exists('checkout')) {
    // Page exists
}

// Delete a page
$register->delete_page('checkout', true); // true = skip trash

// Get all page IDs
$all_ids = $register->get_page_ids();

// Quick one-liner
$ids = Register::quick_install($pages, 'myplugin', $get_callback, $update_callback);
```

## Default WordPress Options

If you don't use a custom settings manager, it works with standard WordPress options:

```php
// Without callbacks - uses get_option/update_option
$register = new Register('myplugin');
$register->add('checkout', 'Checkout', '[checkout_form]');
$register->install();

// Stores as: myplugin_checkout_page => ['value' => 123, 'label' => 'Checkout']
```

## Real World Usage

```php
// In your main plugin file
register_activation_hook(__FILE__, function() {
    $pages = [
        'shop'     => ['title' => 'Shop',     'content' => '[product_grid]'],
        'cart'     => ['title' => 'Cart',     'content' => '[shopping_cart]'],
        'checkout' => ['title' => 'Checkout', 'content' => '[checkout_form]'],
        'account'  => ['title' => 'Account',  'content' => '[user_account]'],
        'success'  => ['title' => 'Success',  'content' => 'Order complete!']
    ];
    
    Register::quick_install($pages, 'myshop');
});
```

## Key Features

- **Simple API** - Just `add()` and `install()`
- **Settings Manager Compatible** - Stores as `['value' => id, 'label' => title]`
- **Works with Page Utils** - Same prefix system for seamless integration
- **Custom Storage** - Use your own get/update callbacks
- **No Over-Engineering** - No MD5 hashes, no complex tracking, just simple page creation

## License

GPL-2.0-or-later