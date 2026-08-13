# Recipes & How-To Guides

Common patterns and solutions for specific testing needs.

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
    $this->getPage()->route('**/*', function ($route) {
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

Instead of calling `$this->authenticate()` in every test, you can reuse a Playwright storage state (cookies +
localStorage).

```php
public function testWithStorageState(): void
{
    // Generate state once
    $context = static::getPlaywrightClient()->context();
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
    $this->getPage()->setViewportSize(390, 844);
    $this->visit('/');
    
    $this->assertSelectorVisible('.mobile-menu');
}
```
