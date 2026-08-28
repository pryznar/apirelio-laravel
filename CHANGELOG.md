# Changelog

All notable changes to `apirelio/laravel` are documented here.

## 1.0.0 - 2026-08-28

- Publish the first stable Laravel SDK on PHP Core 1.x.
- Add support for Laravel 13 on PHP 8.3 and newer.
- Test supported combinations through PHP 8.5 and PHPUnit 13.
- Report the published SDK version in telemetry.

## 0.2.1

- Use the active `https://apirelio.com` ingestion endpoint by default.

## 0.2.0

- Rebrand the package, namespace, middleware and configuration to Apirelio.
- Require the new `apirelio/php-core` package.
- Replace `TRACIUM_*` configuration with `APIRELIO_*`.

## 0.1.2

- Use `apirelio/php-core` for the shared event contract, privacy rules, error
  extraction, HTTP retries and local buffering.
- Preserve the existing Laravel data objects and transport contracts.

## 0.1.1

- Add support for PHP 8.2 and 8.3.
- Add support for Laravel 11.
- Test PHP 8.2–8.4 against Laravel 11 and 12.

## 0.1.0

- Initial Apirelio Laravel SDK release.
