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
- Load the app's settings only on the requests that read them, instead of on every request
- Refuse an admin setting the endpoint does not own, or a value that is not a string

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

### Security

- Only fetch a post thumbnail from Reddit's own image host, instead of any host whose name contains it

## 2.4.0 - 2026-09-29

### Added

- Added support for Nextcloud 36

### Changed

- Update Psalm and @nextcloud/eslint-config, and sync the lint workflows
- Update dependencies

### Fixed

- Show the content of the dashboard widget again, it stayed empty because of an error when loading
- Name the link under a full dashboard widget after the widget again, it read "More items …"

## 2.3.0 - 2026-07-27

### Added

- Added support for Nextcloud 35

### Changed

- Update dependencies

## 2.2.2 - 2026-04-20

### Added

- Added support for Nextcloud 34

### Changed

- Update dependencies

### Removed

- Nextcloud 30 to 32 and PHP 8.1 support

## 2.1.1 - 2025-11-10

### Added

- Added support for Nextcloud 33

### Changed

- Update dependencies

## 2.1.0 - 2025-07-22

### Added

- Added support for Nextcloud 32

### Changed

- Update dependencies

### Removed

- Nextcloud 28 and 29, and PHP 8.0 support

## 2.0.5 - 2024-11-18

### Added

- Added support for Nextcloud 31

### Changed

- Update dependencies

### Fixed

- Encrypt secrets in the database and do not expose them to the UI

## 2.0.4 - 2024-07-24

### Added

- Added support for Nextcloud 30

### Changed

- Update dependencies

### Removed

- Nextcloud 27 support

## 2.0.3 - 2024-03-08

### Added

- Added support for Nextcloud 29

### Changed

- Update dependencies

### Removed

- Nextcloud 26 support

## 2.0.2 - 2023-10-24

### Changed

- Update dependencies

## 2.0.1 - 2023-06-30

### Added

- Added support for Nextcloud 28

### Fixed

- Fall back to OpenGraph when not getting information about links

## 2.0.0 - 2023-04-21

### Added

- Added support for Nextcloud 27

### Changed

- Require PHP 8.0 or later
- Update dependencies and do maintenance

### Removed

- Nextcloud 22 to 25 support

## 1.0.7 - 2023-02-22

### Added

- Added support for Nextcloud 26

### Changed

- Lazy load the dashboard widget
- Use @nextcloud/vue 7.6.1

## 1.0.5 - 2022-08-29

### Added

- Added support for Nextcloud 24 and 25

### Changed

- Implement proper token refresh based on the expiration date
- Use Material icons everywhere
- Make the app ready for the Nextcloud 25 style changes
- Adjust to the new ESLint config
- Update dependencies

## 1.0.2 - 2021-09-13

### Changed

- Update dependencies

### Fixed

- Bug when OAuth fails and no error is provided in the redirection URL

## 1.0.1 - 2021-06-28

### Added

- Added support for Nextcloud 23

### Changed

- Stop polling the widget content when the document is hidden
- Get rid of all deprecated stuff
- Clean up the backend code
- Update dependencies

### Removed

- Nextcloud 20 and 21 support

## 1.0.0 - 2021-03-19

### Changed

- Update dependencies

## 0.0.11 - 2021-02-16

### Changed

- Republish to use the new app certificate

## 0.0.10 - 2021-02-12

### Added

- Added support for Nextcloud 22

### Changed

- Update dependencies

### Fixed

- Import the Nextcloud dialog style

## 0.0.9 - 2021-01-01

### Changed

- Update dependencies

### Fixed

- Browser detection

## 0.0.6 - 2020-12-10

### Changed

- Update dependencies

### Fixed

- Avoid a crash when the Accessibility app is not installed

## 0.0.5 - 2020-10-22

### Added

- Automatic releases

### Changed

- Use Webpack 5 and stylelint

### Fixed

- Possible problem with the redirect URI when generated on the server side

## 0.0.4 - 2020-10-12

### Fixed

- Don't expose the token to the settings UI

## 0.0.3 - 2020-10-02

### Added

- More hints about protocol registration
- Lots of translations

### Changed

- Improve the code quality
- Improve the settings screenshots
- Update dependencies

## 0.0.2 - 2020-09-21

### Changed

- Improve the authentication design
- Improve the widget empty content

## 0.0.1 - 2020-09-02

### Added

- Initial release for Nextcloud 20 and 21
