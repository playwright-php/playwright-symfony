# Changelog

All notable changes to this project are documented in this file.

The project follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
Before 1.0, breaking changes are released in minor versions.

## [Unreleased]

### Added

- Add `PlaywrightTestCase::loginUser()` with the same signature and firewall token semantics as Symfony's `KernelBrowser::loginUser()`.
- Make Symfony's `WebTestCase` response, route, session, and DomCrawler assertions available to Playwright tests.

### Changed

- **BC break:** `PlaywrightTestCase` now extends `WebTestCase` instead of `KernelTestCase`. Subclasses that define members inherited from `WebTestCase`, such as `createClient()`, must use compatible signatures.
- **BC break:** `PlaywrightTestCase::logout()` now accepts an optional firewall context and returns `static` instead of `void`. Overrides must change their signature to `logout(string $firewallContext = 'main'): static`.
- **BC break:** `assertSelectorExists()`, `assertSelectorNotExists()`, `assertSelectorTextContains()`, and `assertResponseIsSuccessful()` now use the public static Symfony `WebTestCase` signatures. Overrides of the previous protected instance methods must be updated. Calls from tests remain compatible.
- `logout()` now clears the legacy `AUTH` cookie, Symfony token storage, the selected firewall token in the session, and the browser session cookie.
