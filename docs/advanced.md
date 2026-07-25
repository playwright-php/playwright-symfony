# Advanced Usage

## Authentication

`PlaywrightTestCase::loginUser()` uses Symfony's real firewall and matches the signature of
`KernelBrowser::loginUser()`. SecurityBundle is optional for the package, but must be installed and enabled before
calling this helper. Otherwise, `loginUser()` throws a `LogicException` with the command to install it:

```bash
composer require symfony/security-bundle
```

### Using the Symfony Firewall

```php
use App\Repository\UserRepository;

public function testAuthenticatedPage(): void
{
    $user = static::getContainer()
        ->get(UserRepository::class)
        ->findOneBy(['email' => 'user@example.com']);
    self::assertNotNull($user);

    $this->loginUser($user);

    $this->visit('/admin');
    self::assertResponseIsSuccessful();
}
```

For session-backed firewalls, the user must be serializable. The security token and session cookie are synchronized
with the intercepted browser request. If sessions are unavailable, the token remains in Symfony's untracked token
storage and no session cookie is created, matching the standard Symfony test client.

The older `authenticate()` helper remains available for applications that deliberately consume its custom JSON
`AUTH` cookie. It does not authenticate against Symfony Security by itself.

### Logging Out

```php
$this->logout('main');
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
