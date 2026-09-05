<div align="center">
    <h1>Laravel Trace</h1>
</div>

<p align="center">
    <a href="https://packagist.org/packages/adilazhari/laravel-trace"><img src="https://img.shields.io/packagist/v/adilazhari/laravel-trace.svg?style=flat-square" alt="Packagist"></a>
    <a href="https://packagist.org/packages/adilazhari/laravel-trace"><img src="https://img.shields.io/packagist/php-v/adilazhari/laravel-trace.svg?style=flat-square" alt="PHP from Packagist"></a>
    <a href="https://packagist.org/packages/adilazhari/laravel-trace"><img src="https://badge.laravel.cloud/badge/adilazhari/laravel-trace?style=flat" alt="Laravel versions"></a>
    <a href="https://github.com/adilazhari/laravel-trace/actions"><img alt="GitHub Workflow Status (main)" src="https://img.shields.io/github/actions/workflow/status/adilazhari/laravel-trace/tests.yml?branch=main&label=Tests&style=flat-square"></a>
    <a href="https://packagist.org/packages/adilazhari/laravel-trace"><img src="https://img.shields.io/packagist/dt/adilazhari/laravel-trace.svg?style=flat-square" alt="Total Downloads"></a>
</p>



## Installation

You can install the package via Composer:

```bash
composer require adilazhari/laravel-trace
```

You may publish all of the package's resources at once:

```bash
php artisan vendor:publish --tag="laravel-trace"
```

Or, you may publish each resource individually:

### Publishing the Configuration File

```bash
php artisan vendor:publish --tag="laravel-trace-config"
```

### Publishing and Running the Migrations

```bash
php artisan vendor:publish --tag="laravel-trace-migrations"
php artisan migrate
```

### Publishing the Views

```bash
php artisan vendor:publish --tag="laravel-trace-views"
```

### Publishing the Translations

```bash
php artisan vendor:publish --tag="laravel-trace-lang"
```

### Publishing the Public Assets

```bash
php artisan vendor:publish --tag="laravel-trace-assets"
```

## Usage

<!-- Add a basic usage example here. -->

### Database storage

By default, traces and spans are held in memory for the lifetime of the
request. To persist them, publish and run the migrations (above), then set
the storage driver to `database`:

```php
// config/laravel-trace.php
'storage' => [
    'driver' => 'database',

    'database' => [
        'connection' => null, // defaults to your app's default connection
        'swallow_exceptions' => true,
    ],
],
```

A storage write failure is logged and swallowed by default, so a database
outage or a missing table never breaks the host application - set
`swallow_exceptions` to `false` while debugging your setup to let it throw
instead.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Thank you for considering contributing to Laravel Trace! Please review our [contributing guide](.github/CONTRIBUTING.md) to get started.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Adil Azhari](https://github.com/adilazhari)
- [All Contributors](../../contributors)

## License

Laravel Trace is open-sourced software licensed under the [MIT license](LICENSE.md).
