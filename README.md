<h1 align="center">Cross-domain preview</h1>
<p align="center"><em>Preview hidden pages of TYPO3 sites on other domains than the one you used to log in to the backend.</em></p>
<br>

<p align="center">
  <a href="https://github.com/wazum/typo3-cross-domain-preview/actions/workflows/tests.yml"><img src="https://img.shields.io/github/actions/workflow/status/wazum/typo3-cross-domain-preview/tests.yml?branch=main&style=for-the-badge&logo=githubactions&logoColor=white&label=tests&labelColor=24273a" alt="tests"></a>
  <a href="https://get.typo3.org"><img src="https://img.shields.io/badge/TYPO3-13.4%20%7C%2014.3-ffb997?style=for-the-badge&logo=typo3&logoColor=white&labelColor=24273a" alt="TYPO3 13.4 and 14.3"></a>
  <a href="https://www.php.net"><img src="https://img.shields.io/badge/PHP-8.2%2B-c3b1e1?style=for-the-badge&logo=php&logoColor=white&labelColor=24273a" alt="PHP 8.2 or newer"></a>
  <a href="LICENSE.txt"><img src="https://img.shields.io/badge/licence-GPL--2.0--or--later-ffc6d9?style=for-the-badge&logo=gnu&logoColor=white&labelColor=24273a" alt="GPL-2.0-or-later licence"></a>
</p>

## The problem

The backend session cookie belongs to the domain you logged in on. In an
installation with sites on several domains, "View webpage" for a hidden page
of another site shows "Page not found", because the browser sends no backend
session to that domain ([Forge #110664](https://forge.typo3.org/issues/110664)).

## How it works

<picture>
  <source media="(max-width: 700px)" srcset="diagrams/how-it-works-narrow.svg">
  <img width="720" src="diagrams/how-it-works.svg"
       alt="View webpage calls a backend route on domain A, which creates a single-use token and redirects to the page on domain B. Domain B refreshes once so the browser sends its own cookie, then redeems the token, keeps an existing login or starts a preview-only session, and the hidden page shows.">
</picture>

1. "View webpage" (and the context menu, "Save and view", live search) for a
   page on another domain first goes through a backend route on the current
   domain.
2. That route creates a single-use token, valid for 30 seconds and bound to
   the user, the target domain and the IP lock settings, and redirects to the
   page.
3. A frontend middleware on the target domain redeems the token and starts a
   session there.

The new session only works for the frontend preview. The backend on that
domain ignores it and still asks for a login. An existing backend login on the
target domain is kept.

Not handled on purpose:

- The Preview module, because browsers do not store the session cookie in a
  cross-site iframe.
- "Switch user" sessions.

## Installation

    composer require wazum/cross-domain-preview

Then update the database schema. The tokens are stored in the cache
`cross_domain_preview`, which uses the database by default. With several web
servers and a local cache backend, configure a shared backend for this cache.
