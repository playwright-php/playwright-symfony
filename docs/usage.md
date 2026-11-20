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

A path is prefixed with the `base_url` configuration. An absolute URL is visited as it is, so a test can start on any of
the `intercepted_hosts`:

```yaml
# config/packages/test/playwright.yaml
playwright:
    base_url: 'http://shop.example.test'
    intercepted_hosts: ['shop.example.test', 'admin.example.test']
```

```php
$this->visit('/checkout');                        // http://shop.example.test/checkout
$this->visit('http://admin.example.test/orders'); // another intercepted host
```

An absolute URL on a host that is not intercepted throws an `InvalidArgumentException` instead of reaching the network.

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

The visibility helpers and Symfony's selector, text, count, title, input, checkbox and form assertions retry for up to
5 seconds. Symfony assertions read a fresh browser document on each attempt while preserving their signatures and
comparison rules. No explicit sleep or `waitForSelector()` is needed before these assertions.

```php
self::assertSelectorExists('.success-message');
self::assertSelectorTextContains('.success-message', 'Saved');
self::assertSelectorNotExists('.spinner');
```

Override `protected static function getDomAssertionTimeout(): int` to change the Symfony DOM assertion timeout in
milliseconds. Use `$this->expect($locator)->withTimeout(1000)` for an individual Playwright expectation.

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
