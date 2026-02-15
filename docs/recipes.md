# Recipes & How-To Guides

Common patterns and solutions for specific testing needs.

## Using Two Separate Clients

Sometimes you need to simulate two different users interacting with your site simultaneously (e.g., a chat application or a peer-to-review process).

```php
public function testTwoUsersInteraction(): void
{
    // User A (The default client provided by the test case)
    $this->authenticate('alice@example.com');
    $this->visit('/projects/1');

    // User B (Create a new standalone client)
    $browserB = $this->browser->getContext()->newPage();
    $clientB = clone $this->client; // Clones config but needs its own page
    // Note: Creating a truly separate user session requires a separate BrowserContext
}
```

*Better approach for full isolation:* Use named browsers in your configuration.

## Configure Playwright per Environment

You may want different timeout or `slowmo` settings in CI vs local development.

```yaml
# config/packages/test/playwright.yaml
playwright:
    browsers:
        default:
            # Local: headless off, slowmo on
            headless: '%env(bool:default:false:PLAYWRIGHT_HEADLESS)%'
            slowmo_ms: '%env(int:default:0:PLAYWRIGHT_SLOWMO)%'
```

## Caching Responses

If your tests crawl external websites, you can speed them up by caching responses or serving local fixtures.

```php
public function testWithMockedExternalApi(): void
{
    $this->browser->setupRouting(function ($route) {
        $url = $route->request()->url();
        
        if (str_contains($url, 'external-api.com/data')) {
            $route->fulfill([
                'status' => 200,
                'contentType' => 'application/json',
                'body' => json_encode(['mock' => 'data']),
            ]);
            return;
        }
        
        $route->continue();
    });

    $this->visit('/dashboard'); // Dashboard calls the external API
}
```

## Persistent Storage (Authentication)

Instead of calling `$this->authenticate()` in every test, you can reuse a Playwright storage state (cookies + localStorage).

```php
public function testWithStorageState(): void
{
    // Generate state once
    $context = $this->browser->getContext();
    // ... perform login ...
    $context->saveStorageState('var/storage/admin.json');

    // Reuse in other tests/classes
    // Note: This requires custom BrowserContext initialization in your test
}
```

## Testing Mobile Viewports

You can configure a specific browser for mobile testing in your `playwright.yaml`.

```yaml
playwright:
    browsers:
        iphone:
            type: 'webkit'
            args:
                - '--viewport-width=390'
                - '--viewport-height=844'
```

Then in your test:
```php
public function testMobileLayout(): void
{
    // Use the page helper to set viewport at runtime
    $this->page->setViewportSize(390, 844);
    $this->visit('/');
    
    $this->assertSelectorVisible('.mobile-menu');
}
```
