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

You can manage cookies directly via the client. These cookies will be synchronized with both the browser and the Symfony
Kernel.

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

Each named browser is exposed as a named autowiring alias for `BrowserContextInterface`. Inject it
in any autowired service by naming the constructor argument after the browser (camelCase):

```php
// src/Testing/FirefoxSmokeChecker.php
use Playwright\Browser\BrowserContextInterface;

final class FirefoxSmokeChecker
{
    public function __construct(
        private BrowserContextInterface $firefoxDebug,
    ) {
    }

    public function check(string $url): string
    {
        $page = $this->firefoxDebug->newPage();
        $page->goto($url);

        return $page->title();
    }
}
```

> **Note**
> PHPUnit does not autowire test method arguments: type-hinted parameters on a test method are
> treated as data provider values, not services. Inject named browsers into services, not into
> test methods.
