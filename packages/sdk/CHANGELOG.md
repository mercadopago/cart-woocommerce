# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## V2.10.0
### Added
- Allow update site_id on seller funnel

## V2.9.1
### Changed
- Adjusts the way to obtain response headers

## V2.9.0
### Added
- Add forgotten attributes on PHP docs of SellerBaseFunnel

## V2.8.0
### Added
- Is possible using empty constructor on Sdk
- Add new fields on seller base funnel: site_id, platform_version, plugin_version and is_disabled

## V2.7.0
### Added
- Added `id`, `status`, `payment_type_id` parameters on Payment response

## V2.6.3
### Added
- Added `qr_code_base64` and `qr_code` parameter to TransactionData

## V2.6.2
### Added
- Added `external_resource_url` parameter to TransactionDetails
### Fixed
- unsetting variables from save method on Payment class to allow payment creation

## V2.6.1
### Fixed
- Added `total_paid_amount` and `installment_amount` as properties of TransactionDetails

## V2.6.0
### Added
- Added `total_paid_amount` and `installment_amount` parameters to TransactionDetails

## V2.5.0
### Added
- Added seller configuration funnel entity to update id

## V2.4.0
### Added
- Added seller configuration funnel entity to create new id

## V2.3.0
### Added
- Get Payment by ID

## V2.2.0
### Added
- Create Merchant Order entity to get one or more merchant orders

## V2.1.0
### Added
- Get Preferences Implementation

## V2.0.1
### Removed
- Remove forgotten var_dump on request manager class

## V2.0.0
### Added
- Adds the `public_key` parameter to the sdk constructor.
- `PaymentMethods` entity to get payment methods using the Core Payment Methods service API
- Create `getPaymentMethods` and `getPaymentMethodsByGroupBy` methods in `PaymentMethods` class
- Integration tests for PaymentMethods scenario
- Adds the possibility of passing query strings in requests
- Create `setHeadersAsKeyAndValueMap`, `isHeadersAsKeyAndValueMap`, and `normalizeHeaders` methods in Manager class

## V1.12.0
### Added
- `DatadogEvent` entity to register events in Datadog using the Core Monitor service API
- Integration tests for datadogEvent scenario
- Create saveWithParams method in AbstractEntity and RequesterEntityInterface

### Changed
- Remove `MelidataError` entity

## V1.11.0
### Added
- `RegisterErrorLog` entity to register errors log using the Core Monitor service API
- Integration tests for registerLog scenario

## V1.10.0
### Added
- `MelidataError` entity to register errors in Melidata using the Core Monitor service API
- Integration tests for melidataError scenario
### Changed
- Change the platform_ids used in integrated tests with the Core P&P platform_id (`ppcoreinternal`)

## V1.9.1
### Changed
- Changed second credit card informations in `CardToken` and `MultipaymentTest` from "amex" to "visa"

## V1.9.0
### Added
- Complementary test scenarios for multipayment with different payments on response
- New environment variable needed for 3DS validation layer e2e tests

## V1.8.0
### Added
- added 3DS validation layer.

## V1.7.1
### Added
- adjustment to the visibility of the customHeader attribute in payment

## V1.7.0
### Added
- adding custom header in payments

## V1.6.0
### Added
- Add new payments /v2.1 to Remedies

## V1.5.7
### Added
- Integration tests for notification scenario

## V1.5.6
### Added
- Integration tests for preference scenario

## V1.5.5
### Added
- Integration tests for multipayment scenario
### Changed
- Modify composer.json in autoload-dev to load all class in /tests

## V1.5.4
### Added
- Adds a step to the script that runs via GitHub Actions to create a version tag for the internal repository

## V1.5.3
### Added
- Adds to the autoload-dev of the composer.json file the necessary package to run the integration tests

## V1.5.2
### Changed
- Add Docs for Asgard Services Integration

## V1.5.1
### Added
- Add Tests of integration on Payment
