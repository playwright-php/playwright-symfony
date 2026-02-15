# Playwright Symfony Bundle

This bundle provides a first-class integration of [Playwright](https://playwright.dev/) into the Symfony ecosystem. It allows you to run high-fidelity functional and E2E tests using real browsers while routing requests directly into the Symfony Kernel.

## Key Features

- **Real Browser Testing**: Fully supports JavaScript, CSS animations, and complex DOM interactions.
- **Fast Performance**: Optimized shared browser process architecture reduces startup overhead by reusing the same browser process across test methods.
- **Kernel Interception**: No local web server required. Browser requests are intercepted and handled directly by your Symfony application.
- **DomCrawler Bridge**: Continue using the familiar Symfony `Crawler`, `Link`, and `Form` APIs while driving a real browser.
- **Asset Serving**: Built-in support for serving static assets and AssetMapper content during in-process tests.
- **Seamless DX**: Modern assertions and magic properties that feel exactly like native `WebTestCase`.

## Documentation

- [Getting Started](getting-started.md): Installation and setup.
- [Configuration](configuration.md): Full reference of bundle options.
- [Testing Guide](usage.md): How to write and run tests.
- [DomCrawler Integration](dom-crawler.md): Deep dive into the Crawler/Playwright bridge.
- [Performance Optimization](performance.md): Understanding the shared browser architecture.
- [Continuous Integration](ci.md): Best practices for GitHub Actions and GitLab CI.
- [Advanced Usage](advanced.md): Interceptors, cookies, and multi-browser setups.
- [Recipes](recipes.md): Common patterns for specific testing needs.
- [Zenstruck Browser Compatibility](zenstruck-browser-compatibility.md): Integration with zenstruck/browser.

## Why Playwright Symfony?

Standard Symfony functional tests use `BrowserKit`, which lacks JavaScript support. `symfony/panther` provides JavaScript support but requires a running web server and uses WebDriver.

**Playwright Symfony** bridges this gap by allowing you to test real browser behavior (JavaScript, AJAX, SPAs) with the speed and ease of in-process Kernel testing.
