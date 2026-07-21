# Usage Guide

## The PlaywrightTestCase

To use Playwright in your tests, extend `Playwright\Symfony\Test\PlaywrightTestCase`.

This class provides:

- Automatic browser lifecycle management.
- An initialized `PlaywrightClient`.
- Magic access to the Playwright `Page` object.

## Navigating

Use the `visit()` method to navigate to your application.

```php
$this->visit('/my-route');
```

By default, this is prefixed with the `base_url` configuration.

## Interacting with the Page

The `$this->page` magic property gives you direct access to
the [Playwright Page API](https://playwright.dev/docs/api/class-page).

```php
// Fill an input
$this->page->locator('input[name="email"]')->fill('user@example.com');

// Click a button
$this->page->locator('button.btn-primary')->click();

// Wait for a selector to appear
$this->page->waitForSelector('.success-message');
```

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
$this->page->screenshot('var/screenshots/test.png');
```

Or use the helper:

```php
$this->screenshot('var/screenshots/test.png');
```
