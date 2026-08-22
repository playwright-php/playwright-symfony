# Changelog

All notable changes to this project are documented in this file.

The project follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
Before 1.0, breaking changes are released in minor versions.

## [Unreleased]

## v0.10.0 

### Added

- `catchExceptions()` on the kernel client, so a test can let an application exception propagate instead of being converted to a response (#47).
- `enableProfiler()` on the kernel client, applying to the next request only, as `HttpKernelBrowser` does (#38).
- `getContainer()` on the kernel client, returning the test container when the kernel exposes one (#36).
- `BrowserRegistry::createSession()` and `closeSession()`, so several isolated contexts can share one browser process (#48, #49).
- Kernel redirects are handled natively: document navigations are routed through the browser, and `fetch()` redirects are replayed client-side so `redirected` and `url` stay correct (#33).
- `followRedirects(false)` now stops the browser on a redirect response instead of following it, leaving the status and `Location` on `getLastSymfonyResponse()`. `followRedirect()` follows a stopped redirect one hop at a time.
- Add `PlaywrightTestCase::loginUser()` with the same signature and firewall token semantics as Symfony's `KernelBrowser::loginUser()`.
- Make Symfony's `WebTestCase` response, route, session, and DomCrawler assertions available to Playwright tests.

### Changed

- **BC break:** `PlaywrightTestCase` now creates its primary client lazily through `getPlaywrightClient()`, while `createPlaywrightClient()` always returns a fresh client. The `$client`, `$browser`, `$baseUrl`, and magic `$page` instance properties have been removed.
- `BrowserRegistry` and `PlaywrightKernelClient` are now public API. `BrowserSessionInterface` is the public session abstraction; its concrete implementation remains internal.
- **BC break:** `PlaywrightTestCase` now extends `WebTestCase` instead of `KernelTestCase`. Subclasses that define members inherited from `WebTestCase`, such as `createClient()`, must use compatible signatures.
- **BC break:** `PlaywrightTestCase::logout()` now accepts an optional firewall context and returns `static` instead of `void`. Overrides must change their signature to `logout(string $firewallContext = 'main'): static`.
- **BC break:** `assertSelectorExists()`, `assertSelectorNotExists()`, `assertSelectorTextContains()`, and `assertResponseIsSuccessful()` now use the public static Symfony `WebTestCase` signatures. Overrides of the previous protected instance methods must be updated. Calls from tests remain compatible.
- **BC break:** exceptions thrown while handling an intercepted request are caught and converted to a response by default. Call `catchExceptions(false)` for the previous behaviour (#47).
- **BC break:** the `PLAYWRIGHT_E2E` environment flag is gone. Select Playwright tests with PHPUnit groups or suites instead (#29).
- **BC break:** following popups is opt-in through `setFollowPopups(true)`, and no longer waits for a popup on every click (#13).
- PHP 8.2 and Symfony 6.4 are supported (#39).
- One browser process is reused across contexts instead of being relaunched for each one (#31).
- `$kernel->terminate()` is called after each intercepted request, which is what writes a profile to storage (#37).
- Browser type, headless mode and launch options configured for the bundle are passed through to the browser (#15).
- `logout()` now clears the legacy `AUTH` cookie, Symfony token storage, the selected firewall token in the session, and the browser session cookie.

### Fixed

- `enableProfiler()` took effect for the first intercepted request of a test only, and a profiled request lost its token to the requests that followed it (#53).
- The Node process was left running when `stop()` could not close the context (#41).
- TypeScript assets were served with the wrong MIME type (#46).
- Repeated `name[]` fields in a multipart body were overwritten instead of appended (#25).
- `REMOTE_ADDR` was missing from synthetic requests (#24).
- Binary request bodies were corrupted in transit (#27).
- `postData()` returning null now falls back to `postDataBuffer()` (#4).
- Numeric cookie `expires` values from Playwright were rejected by BrowserKit (#10, #17).
- Multiple `Set-Cookie` headers were merged into one (#11).
- A `+html` structured MIME suffix is treated as text (#6).
- Checking a radio group selected the first input rather than the one matching the value (#12).
- `base_url` no longer requires `PLAYWRIGHT_BASE_URL` to be set (#9).
- Container compilation failed when the bundle was disabled (#8).
