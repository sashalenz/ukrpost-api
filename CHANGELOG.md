# Changelog

All notable changes to this project will be documented in this file.

## Unreleased

- Added typed Address, Client, and Shipment API models for the complete Wave 2 scope.
- Added request and response DTOs plus Ukrposhta enums for shipment creation.
- Added fail-fast address, client, parcel, post-pay, tax identifier, and tracking-route validation.
- Added duplicate-safe client resolution by external ID or phone number.
- Added opt-in end-to-end sandbox coverage for address → clients → shipment → barcode.
- Added StatusTracking endpoints with dedicated bearer authentication and no counterparty token.
- Added 50/100-item status batching that preserves not-found barcodes.
- Added event-code mapping, including the `41000` + reason `10` return-to-sender case.
- Added unsupported international barcode filtering before HTTP requests.
