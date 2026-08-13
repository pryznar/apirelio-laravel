# Apirelio quickstart

This is the smallest integration path for the package. It uses the synthetic customer `customer_42`; replace that resolver with your authenticated account lookup before production.

```bash
export APIRELIO_API_KEY=apr_live_your_project_key
composer create-project laravel/laravel demo && cd demo && composer require apirelio/laravel && php artisan vendor:publish --tag=apirelio-config
```

Copy `routes-api.php` to `routes/api.php`, add the environment values from the main README, then run `php artisan serve`.

Generate one request to the example endpoint, wait for the asynchronous batch to flush, then open the [live demo](https://apirelio.com/demo?utm_source=github&utm_medium=example&utm_campaign=laravel) to understand the resulting customer-aware views.

The SDK never captures request or response payloads. Do not put secrets or personal data into customer identity or custom metadata.
