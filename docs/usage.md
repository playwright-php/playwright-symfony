# Usage Guide

## The PlaywrightTestCase

To use Playwright in your tests, extend `Playwright\Symfony\Test\PlaywrightTestCase`.

This class provides:

- Automatic browser lifecycle management.
- A lazy primary `PlaywrightKernelClient`.
- Fresh clients with isolated contexts and pages.

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

## Built-in Assertions

`PlaywrightTestCase` includes several helper assertions to make your tests cleaner.

Use `$this->expect($locator)` or `$this->expect($page)` for fluent assertions that retry against the live DOM. These
expectations are counted by PHPUnit and included in Playwright traces when tracing is active.

```php
$this->expect($this->getPage()->locator('.success-message'))->toBeVisible();
$this->expect($this->getPage())->toHaveTitle('Dashboard');
```

### Content Assertions

- `$this->assertPageContains(string $text)`
- `$this->assertPageNotContains(string $text)`
- `$this->assertSelectorTextContains(string $selector, string $text)`

### Visibility Assertions

- `$this->assertSelectorVisible(string $selector)`
- `$this->assertSelectorHidden(string $selector)`
- `$this->assertSelectorExists(string $selector)`
- `$this->assertSelectorNotExists(string $selector)`

The visibility helpers retry against the live DOM. The selector existence and text helpers are inherited from Symfony
and inspect a one-time snapshot of the current page HTML. They do not retry; use `expect()` when JavaScript updates must
be awaited.

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
