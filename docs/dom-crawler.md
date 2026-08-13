# DomCrawler Integration

The Playwright Symfony bundle provides full support for Symfony's `DomCrawler` component. This allows you to use the
familiar selection and extraction API from `WebTestCase` while driving a real, JavaScript-enabled browser.

## How it Works

The integration bridges the gap between the static HTML snapshots used by `DomCrawler` and the live, interactive DOM of
a real browser.

1. **Selection**: Use CSS or XPath selectors via the Crawler to find elements.
2. **Extraction**: Extract standard Symfony `Link` or `Form` objects.
3. **Interaction**: Pass these objects back to the client. The client automatically translates them into Playwright
   locators, performs the action in the browser, and returns a fresh Crawler for the new page state.

## Basic Usage

In a `PlaywrightTestCase`, the `$this->client` (and the magic `$this->page` property) work together to provide this
bridge.

```php
public function testNavigationWithDomCrawler(): void
{
    // Start by visiting a page
    $this->visit('/');

    // Get the crawler for the current page
    $crawler = $this->client->getCrawler();

    // Find a link using standard CSS selectors
    $link = $crawler->filter('a#dashboard-link')->link();

    // Click it using the BrowserKit API
    // This triggers a real click in Chromium/Firefox/WebKit
    $this->client->click($link);

    // Assert on the new state
    $this->assertStringContainsString('/dashboard', $this->client->getPage()->url());
}
```

## Form Handling

Interacting with complex forms is simplified by the `DomCrawler` integration. It handles all input types, including
selects, checkboxes, and file uploads.

```php
public function testFormSubmission(): void
{
    $crawler = $this->client->request('GET', '/registration');
    
    $form = $crawler->selectButton('Register')->form();
    
    // Fill the form using standard Symfony syntax
    $this->client->submit($form, [
        'user[email]' => 'test@example.com',
        'user[password]' => 'password123',
        'user[terms]' => true,
    ]);

    // The submission happens in the real browser, executing any JavaScript validation
    $this->assertPageContains('Welcome to your account');
}
```

## Benefits of the Bridge

### Familiar API

You don't need to learn a new selection API. All the power of `filter()`, `filterXPath()`, `selectLink()`, and
`selectButton()` is available.

### Real Browser Fidelity

Unlike standard `WebTestCase`, calling `click()` or `submit()` through the bridge:

- Triggers JavaScript `click` and `submit` events.
- Handles AJAX requests triggered by those events.
- Executes CSS transitions and animations.
- Respects `target="_blank"` and other browser-native behaviors.

### Cookies Come From the Browser

`getCookieJar()` returns a BrowserKit jar backed by the browser context. Cookies set by the browser (via JS) and
cookies set by Symfony (via headers) live in one store, so there is nothing to keep consistent.

### Asynchronous Resilience

The client includes built-in retry logic to handle cases where the Crawler attempts to retrieve content while the
browser is still actively navigating or rendering a page.
