# Laravel Postman Generator

Generate Postman collections + environments from Laravel routes and FormRequests.

## Requirements
- PHP 8.1+
- Laravel 10/11/12


## Installation

Install via Packagist:

```
composer require paws1234/laravel-postman-generator --dev
```

No need to modify your composer.json manually—Composer will fetch the package automatically.

## Usage

Generate Postman collection:

```
php artisan postman:generate
```

Clear generated files:

```
php artisan postman:clear
```

## Configuration

Publish and edit the config file:

```
php artisan vendor:publish --provider="paws1234\LaravelPostmanGenerator\PostmanGeneratorServiceProvider"
```

## Features
- Postman collection and environment generation
- FormRequest body inference
- Multi-auth support
- Grouping by prefix/controller
- Deterministic output
- CLI commands for generation and cleanup

## Requirements
- PHP 8.1+
- Laravel 10/11/12

## How It Works
- Scans routes and FormRequests
- Builds Postman collections and environments
- Supports advanced grouping and authentication

## Testing

Run tests with:

```
vendor/bin/phpunit --testdox
```

## Contributing

PRs and issues welcome! Please ensure new features include tests.
