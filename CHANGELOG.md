# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-09-13

### Added

- Laravel package configuration for production and sandbox eCom, Forms,
  StatusTracking, and AddressClassifier services.
- Centralized endpoint-aware authentication, typed API errors, and safe retries
  restricted to `GET` and `HEAD` requests.
- Typed address, client, shipment, shipment-group, calculation, transfer,
  management, courier-pickup, document, and status-tracking APIs.
- Request and response DTOs plus PHP enums for documented API values.
- AddressClassifier lookup coverage with opt-in caching.
- Duplicate-safe client resolution by external ID or phone number.
- Local validation for address, parcel, postpay, tax identifier, shipment,
  management, and courier-order constraints.
- Binary handling for Ukrposhta PDF forms and labels.
- Status batching that preserves not-found barcodes and maps return-to-sender
  events by event reason.
- Opt-in end-to-end sandbox coverage for address, clients, shipment, and barcode
  creation.
- Laravel Pint, PHPStan max, Pest, and GitHub Actions quality gates.

[Unreleased]: https://github.com/sashalenz/ukrpost-api/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/sashalenz/ukrpost-api/releases/tag/v1.0.0
