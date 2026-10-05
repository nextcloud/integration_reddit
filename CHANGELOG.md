<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: CC0-1.0
-->
# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

### Changed

- Run the frontend unit tests in CI
- Drop moment from the dashboard widget, which no longer needed it

### Security

- Only fetch a post thumbnail from Reddit's own image host, instead of any host whose name contains it

### Fixed

- Stop the dashboard widget from polling once it has been removed
- Keep the dashboard widget polling after a transient failure, and report once Reddit cannot be reached
- Show the Chrome and Chromium instructions for registering the protocol handler again
- Report a dismissed password confirmation in the admin settings instead of saving nothing in silence
- Show the subreddit icon for a post whose thumbnail is one of Reddit's placeholders
- Keep a post created in the same second as one already shown
- Keep the dashboard widget's list of posts from growing for the lifetime of the page
- Show the newest post first, whichever order the answers arrive in
- Report an unreachable Reddit as a temporary failure instead of an expired login
- Answer a thumbnail request without a url instead of failing with a server error
- Report a Reddit that is overloaded or rate-limiting as a temporary failure, not as a refused login
- Keep the dashboard widget quiet after a failed login instead of reporting it again on every tab switch
- Keep showing a connected account when the server refused to disconnect it
- Clear the stored client secret along with the application id, which cannot authenticate without it
- Report an unreachable Reddit while exchanging the OAuth token instead of failing with a server error
- Ask Reddit for nothing when an avatar is requested without a user or a subreddit
- Show the subreddit icon in search results and link previews for a post with no thumbnail of its own
- Link a post the server passed through without a permalink to Reddit itself

## 2.4.0 - 2026-09-29
### Changed
- added support of NC36
- update Psalm and @nextcloud/eslint-config, and sync the lint workflows
- bump dependencies

### Fixed
- show the content of the dashboard widget again, it stayed empty because of an error when loading
- name the link under a full dashboard widget after the widget again, it read "More items …"

## 2.3.0 - 2026-07-27
### Changed
- added support of NC35
- bump dependencies

## 2.2.2 - 2026-04-20
### Changed
- added support of NC34, dropped NC30, NC31, and NC32
- minimum required PHP raised from 8.1 to 8.2
- bump dependencies

## 2.1.1 - 2025-11-10
### Changed
- added support of NC33
- bump dependencies

## 2.1.0 - 2025-07-22
### Changed
- minimum required NC raised from v28 to v30
- bump js libs

## 2.0.5 - 2024-11-18
### Changed
- bump js libs

### Fixed
- encrypt secrets in the database and not expose them to UI

## 2.0.4 - 2024-07-24
### Changed
- added support of NC30, minimum required NC raised from v27 to v28
- bump js libs

## 2.0.3 - 2024-03-08
### Changed
- added support of NC29, minimum required NC raised from v26 to v27
- bump js libs

## 2.0.2 - 2023-10-24
### Changed
- bump js libs

## 2.0.1 – 2023-06-30
### Fixed
- fallback to OpenGraph when not getting information about links

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
