# Usage Guide

## The PlaywrightTestCase

To use Playwright in your tests, extend `Playwright\Symfony\Test\PlaywrightTestCase`.

This class provides:

- Automatic browser lifecycle management.
- A lazy primary `PlaywrightKernelClient`.
- Fresh clients with isolated contexts and pages.
- Direct access to the primary browser context.
- Optional per-test tracing and failure artifacts.

## Navigating

Use the `visit()` method to navigate to your application.

```php
$this->visit('/my-route');
```

By default, this is prefixed with the `base_url` configuration.

## Interacting with the Page

The `getPage()` helper gives you direct access to the
[Playwright Page API](https://playwright.dev/docs/api/class-page). The browser starts on the first call that needs it.

```php
// Fill an input
$this->getPage()->locator('input[name="email"]')->fill('user@example.com');

// Click a button
$this->getPage()->locator('button.btn-primary')->click();

// Wait for a selector to appear
$this->getPage()->waitForSelector('.success-message');
```

## Accessing the Browser Context

Use `getContext()` to access the primary client's `BrowserContextInterface`. The primary client is
created on first use.

```php
$context = $this->getContext();
```

For a fresh client created with `createPlaywrightClient()`, use that client's `context()` method
instead. `getContext()` always refers to the primary client.

## Custom Browser Launch Options

Override `browserLaunchOptions()` to configure the shared browser for a test class:

```php
protected function browserLaunchOptions(): array
{
    return [
        'args' => [
            '--disable-features=Translate',
            '--disable-translate',
        ],
    ];
}
```

Options are merged with those derived from the environment. Browser arguments are appended;
other explicitly provided options override the corresponding environment-derived values.
The shared browser is recreated when its configuration changes.

Launch arguments only affect locally launched browsers. They do not reconfigure an existing
browser connected through `PLAYWRIGHT_WS_ENDPOINT` or `PW_WS_ENDPOINT`.

## Built-in Assertions

`PlaywrightTestCase` includes several helper assertions to make your tests cleaner.

### Content Assertions

- `$this->assertPageContains(string $text)`
- `$this->assertPageNotContains(string $text)`
- `$this->assertSelectorTextContains(string $selector, string $text)`

### Visibility Assertions

- `$this->assertSelectorVisible(string $selector)`
- `$this->assertSelectorHidden(string $selector)`
- `$this->assertSelectorExists(string $selector)`
- `$this->assertSelectorNotExists(string $selector)`

### HTTP Assertions

These check the response returned by the Symfony Kernel during the last intercepted request.

- `$this->assertResponseIsSuccessful()`
- `$this->assertResponseStatusCode(int $code)`
- `$this->assertResponseIsRedirect()`

## Taking Screenshots

You can take screenshots at any point during your test:

```php
$this->getPage()->screenshot('var/screenshots/test.png');
```

Or use the helper:

```php
$this->screenshot('var/screenshots/test.png');
```

## Test Tracing and Failure Artifacts

Enable automatic per-test tracing through the PHP process's environment:

```dotenv
PW_TRACE=1
PW_TRACE_DIR=/app/var/playwright/traces
PW_TRACE_KEEP_SUCCESS=0
```

Tracing starts when each client is created, recording screenshots, DOM snapshots, and sources.
During teardown, tests with a failure or error save a trace archive and a separate page screenshot.
Set `PW_TRACE_KEEP_SUCCESS=1` to also save successful test traces; successful tests do not receive
a separate screenshot.

Artifacts include the test class, method, timestamp, and result:

```text
HomepageTest_testHomepage_20261108_120000_failed_trace.zip
HomepageTest_testHomepage_20261108_120000_failed_screenshot.png
```

If a test creates multiple clients, additional clients receive numbered suffixes such as
`_trace-2.zip` and `_screenshot-2.png`. A test that never creates a client produces no artifacts.

The default output directory is `traces` under the current working directory. Tracing is disabled
by default, and `PW_TRACE_KEEP_SUCCESS` has no effect unless `PW_TRACE` is enabled.
These settings are independent of the bundle's YAML browser tracing configuration.

See the [configuration reference](configuration.md#environment-variables) for all environment
variables and [CI examples](ci.md#retaining-failure-artifacts) for uploading artifacts.
