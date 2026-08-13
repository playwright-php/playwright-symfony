# Architecture Overview

Playwright Symfony bridges the gap between high-fidelity browser automation and Symfony's in-process testing speed.

## Core Design

The bundle's architecture is built on the principle of **Kernel Interception**. Instead of the browser making network
requests to a web server, Playwright intercepts all outgoing requests and routes them directly into the Symfony
`HttpKernel`.

### High-Level Component Map

1. **Test Layer (`PlaywrightTestCase`)**: Orchestrates the lifecycle. Manages a **Shared Browser** process that is
   launched once per class.
2. **Client Layer (`PlaywrightKernelClient`)**: The central hub. It implements the `BrowserKit` API and coordinates the
   interception.
3. **Bridge Utilities**:
    - `RequestConverter`: Translates Playwright requests (headers, body, cookies) to Symfony `HttpFoundation` requests.
    - `ResponseConverter`: Translates Symfony responses back to Playwright `fulfill` options.
    - `CookieJarSync`: Keeps the browser's cookies in sync with Symfony's `CookieJar`.
4. **Browser Registry (`BrowserRegistry`)**: Browser lifecycle manager that handles process management and
   `BrowserContext` isolation.
5. **Asset Layer (`AssetServer`)**: A high-performance bypassing layer that serves static files and AssetMapper assets
   directly from the filesystem, avoiding Kernel overhead for non-application logic.

## Extension API

`BrowserRegistry`, `BrowserSessionInterface`, and `PlaywrightKernelClient` are public integration points. Code that
creates sessions directly should depend on `BrowserSessionInterface` and release each session with
`BrowserRegistry::closeSession()`.

`BrowserSession` is the registry's internal implementation and may change without preserving backward compatibility.

## Request Lifecycle

When you call `$this->visit('/dashboard')`:

1. **Navigation**: The `PlaywrightKernelClient` calls `page->goto('http://localhost/dashboard')`.
2. **Interception**: Playwright triggers a route handler registered by the client.
3. **Asset Check**: The `AssetServer` checks if the URL matches an asset prefix (e.g., `/assets/`). If so, it fulfills
   the request immediately from disk.
4. **Kernel Handling**: If it's an application route:
    - `RequestConverter` builds a Symfony `Request`.
    - `beforeRequest()` hooks are executed.
    - The `HttpKernel` handles the request and returns a `Response`.
    - `afterResponse()` hooks are executed.
    - `ResponseConverter` encodes the response (including base64 for binaries).
5. **Fulfillment**: The browser receives the response and renders the DOM.
6. **DomCrawler Sync**: The client builds a fresh `Crawler` from the browser's live `page->content()`.

## Service Container Integration

The bundle optimizes the DI container for testing:

- **Autowiring**: `BrowserContextInterface` is automatically available for injection in your tests, aliased to the
  default configured browser.
- **Named Browsers**: Every browser defined in `playwright.yaml` is registered as a unique service (e.g.,
  `playwright.browser.firefox_debug`).
- **Prototypes**: The `PlaywrightKernelClient` is instantiated manually in the test case to ensure it always has the
  latest Kernel instance (crucial for `WebTestCase` style isolation).

## Performance Optimizations

### Shared Browser Process

Launching a browser process takes ~1000ms. The process starts lazily and is shared across tests in the same class,
while isolated contexts are created on demand and closed after each test.

### In-Process Execution

By bypassing the network stack, we eliminate socket overhead and allow for advanced features like database transaction
sharing and direct profiler inspection.

### Asset Bypassing

The `AssetServer` uses optimized locators to serve assets. This ensures that even if your application is slow, your CSS
and JS are served at lightning speed to the browser.
