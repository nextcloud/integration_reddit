# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

## 2.0.1 – 2023-06-30

### Fixed

- Fallback to OpenGraph when not getting information about links

## 2.0.0 – 2023-04-21
### Changed
- dependency update and maintenance
- supported php>=8.0

## 1.0.7 – 2023-02-22
### Changed
- add 26 compat
- lazy load dashboard widget
- use @nextcloud/vue 7.6.1

## 1.0.5 – 2022-08-29
### Changed
- implement proper token refresh based on expiration date
- use material icons everywhere
- make the app ready for NC 25 style changes
- bump js libs, asjut to new eslint config

## 1.0.2 – 2021-09-13
### Changed
- bump js libs

### Fixed
- bug when OAuth fails and no error provided in redirection URL
[#19](https://github.com/nextcloud/integration_reddit/issues/19) @bionicworx

## 1.0.1 – 2021-06-28
### Changed
- stop polling widget content when document is hidden
- bump js libs
- get rid of all deprecated stuff
- bump min NC version to 22
- cleanup backend code

## 1.0.0 – 2021-03-19
### Changed
- bump js libs

## 0.0.11 – 2021-02-16
### Changed
- app certificate

## 0.0.10 – 2021-02-12
### Changed
- bump js libs
- bump max NC version

### Fixed
- import nc dialog style

## 0.0.9 – 2021-01-01
### Changed
- bump js libs

### Fixed
- browser detection

## 0.0.6 – 2020-12-10
### Changed
- bump js libs

### Fixed
- avoid crash when accessibility app is not installed

## 0.0.5 – 2020-10-22
### Added
- automatic releases

### Changed
- use Webpack 5 and style lint

### Fixed
- possible problem with redirect URI when generated on server side

## 0.0.4 – 2020-10-12
### Fixed
- don't expose token to settings UI

## 0.0.3 – 2020-10-02
### Added
- more hints about protocol registration
- lots of translations

### Changed
- improve code quality
- improve settings screenshots
- bump libs

## 0.0.2 – 2020-09-21
### Changed
* improve authentication design
* improve widget empty content

## 0.0.1 – 2020-09-02
### Added
* the app
