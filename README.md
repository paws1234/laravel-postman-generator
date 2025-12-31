# Laravel Postman Generator

Generate Postman collections + environments from Laravel routes and FormRequests.

## Requirements
- PHP 8.1+
- Laravel 10/11/12

## Install (local dev)
In your Laravel app's `composer.json`:

```json
{
  "repositories": [
    { "type": "path", "url": "../laravel-postman-generator" }
  ],
  "require-dev": {
    "your-vendor/laravel-postman-generator": "*"
  }
}
# laravel-postman-generator
