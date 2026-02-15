# Advanced Usage

## Authentication

The `PlaywrightClient` provides a simple way to authenticate users in your tests.

### Using the Helper

```php
public function testAuthenticatedPage(): void
{
    // Authenticates the user (sets an AUTH cookie)
    $this->authenticate('user@example.com', ['roles' => ['ROLE_ADMIN']]);

    $this->visit('/admin');
    $this->assertResponseIsSuccessful();
}
```

### Logging Out

```php
$this->logout();
$this->visit('/admin');
$this->assertResponseStatusCode(403);
```

## Cookie Management

You can manage cookies directly via the client. These cookies will be synchronized with both the browser and the Symfony Kernel.

```php
// Set a cookie
$this->setCookie('name', 'value', ['domain' => 'localhost']);

// Get a cookie value
$value = $this->getCookie('name');

// Clear all cookies
$this->clearCookies();
```

## Inspecting the Kernel Request/Response

You can access the actual Symfony Request and Response objects from the last intercepted navigation.

```php
$this->visit('/some-page');

$request = $this->getLastRequest();  // Symfony\Component\HttpFoundation\Request
$response = $this->getLastResponse(); // Symfony\Component\HttpFoundation\Response

// Perfect for checking headers or internal application state
$this->assertSame('text/html', $response->headers->get('Content-Type'));
```

## Using Multiple Browsers

You can define multiple browser configurations in `playwright.yaml` and autowire them in your tests.

```yaml
# config/packages/test/playwright.yaml
playwright:
    browsers:
        firefox_debug:
            type: 'firefox'
            headless: false
```

In your test:
```php
public function testWithFirefox(\Playwright\Browser\BrowserContextInterface $firefoxDebug): void
{
    $page = $firefoxDebug->newPage();
    $page->goto('http://localhost/');
    // ...
}
```
