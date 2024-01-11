# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
