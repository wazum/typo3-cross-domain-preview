# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-09-26

The first release, for TYPO3 13.4 and 14.3 on PHP 8.2 and up.

### Added

- "View webpage", the context menu, "Save and view" and the live search send a
  preview of a page on another domain through the backend route
  `cross_domain_preview_session_transfer`. The backend session cookie belongs to
  the domain the editor logged in on, so a hidden page of a site on another domain
  showed "Page not found" ([Forge #110664](https://forge.typo3.org/issues/110664)).
- The route creates a single-use token, valid for 30 seconds and bound to the user,
  the target domain and the IP lock settings (`BE.lockIP`, `BE.lockIPv6`), and
  writes an entry to the system log. It refuses "switch user" sessions.
- A frontend middleware on the target domain redeems the token and starts a session
  there that only works for the frontend preview. The multi-factor authentication
  state of the editor is taken over. The backend on that domain ignores the session
  and still asks for a login, and an existing backend login there is kept.
- The tokens are stored in the new cache `cross_domain_preview`, which uses the
  database backend by default.
- The Preview module keeps linking to the page directly, because browsers do not
  store the session cookie in a cross-site iframe.

[Unreleased]: https://github.com/wazum/typo3-cross-domain-preview/compare/1.0.0...main
[1.0.0]: https://github.com/wazum/typo3-cross-domain-preview/releases/tag/1.0.0
