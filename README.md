# IconScoop - find your favicons

[![Latest Version on Packagist](https://img.shields.io/packagist/v/luminarix/iconscoop.svg?style=flat-square)](https://packagist.org/packages/luminarix/iconscoop)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/luminarix/iconscoop/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/luminarix/iconscoop/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/luminarix/iconscoop/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/luminarix/iconscoop/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/luminarix/iconscoop.svg?style=flat-square)](https://packagist.org/packages/luminarix/iconscoop)

IconScoop helps you retrieve the favicon for any website by parsing HTML <link> tags, inspecting web app manifests, or falling back to the `/favicon.ico` path. It even supports Google’s favicon service for protected domains.
The service returns a `Favicon` object containing the icon URL (accessible via `__toString()`), the content location, and the HTTP status code.
You can use the provided Laravel facade or instantiate the service directly.

## Installation

You can install the package via composer:

```bash
composer require luminarix/iconscoop
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="iconscoop-config"
```

You can publish the assets with:
```bash
php artisan vendor:publish --tag="iconscoop-assets"
```


## Usage

```php
use Luminarix\IconScoop\Facades\IconScoop;

// Fetch the favicon
$favicon = IconScoop::find('https://x.com');

echo $favicon->imageUrl;    // e.g. 'https://www.facebook.com/favicon.ico'
echo $favicon->statusCode;  // e.g. 200
echo $favicon;              // string cast outputs the icon URL
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Credits

- [Luminarix Labs](https://github.com/luminarix)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
