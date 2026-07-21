<div align="center">
<img src="https://github.com/playwright-php/.github/raw/main/profile/playwright-php.png" alt="Playwright for PHP" />
</div>

# Playwright PHP Symfony Bundle

**E2E testing for Symfony with real browsers and in-process request handling.**

Run Playwright browser tests while intercepting HTTP requests and routing them through your Symfony kernel in the same PHP process, giving you full access to services, the profiler, and application state.

> [!IMPORTANT]
> **Alpha Status**
> This bundle is in active development. Core features work well, but the API may change before stable release.

## Why This Bundle?

Traditional E2E testing requires running a separate web server. This bundle eliminates that overhead:

- **In-Process Request Handling**: HTTP requests from the browser are handled by your Symfony kernel in the same PHP process
- **DomCrawler Integration**: Use familiar Symfony `Crawler`, `Link`, and `Form` objects while driving a real browser
- **Full Symfony Integration**: Access services, the profiler, events, and database state during tests
- **Fast Asset Serving**: Static assets and AssetMapper files bypass the kernel for optimal performance
- **Real Browser Testing**: Test with Chromium, Firefox, or WebKit using Playwright
- **Shared Browser Session**: Optimized architecture reuses the same browser process across tests for maximum speed

## Installation

```bash
composer require --dev playwright-php/playwright-symfony
```

### Prerequisites

**Requirements:**
- PHP 8.3+
- Symfony 7.0+ or 8.0+
- Node.js 20+ (for Playwright browser communication)

**Install Playwright:**

The bundle includes a helper to set up the Playwright environment and download browsers:

```bash
vendor/bin/playwright-install
vendor/bin/playwright-install --browsers
```

### Bundle Setup

The bundle auto-registers in `config/bundles.php` (test environment only):

```php
return [
    // ...
    Playwright\Symfony\PlaywrightSymfonyBundle::class => ['test' => true],
];
```

**Optional configuration** in `config/packages/test/playwright.yaml`:

```yaml
playwright:
    base_url: 'http://localhost'
    intercepted_hosts: ['localhost', '127.0.0.1']
    debug_logging: false
```

> [!TIP]
> You can override these values per test run via environment variables, e.g. `PLAYWRIGHT_BASE_URL`, `PLAYWRIGHT_E2E`, `PLAYWRIGHT_VERBOSE`, and `PLAYWRIGHT_HEADLESS`.

See [Configuration Reference](docs/configuration.md) for all options.

## Quick Start

### 1. Create Your Test Base Class

```php
<?php

namespace App\Tests\E2E;

use Playwright\Symfony\Test\PlaywrightTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

abstract class E2ETest extends PlaywrightTestCase
{
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new \App\Kernel('test', false);
    }
}
```

### 2. Write Your First Test

```php
<?php

namespace App\Tests\E2E;

class LoginTest extends E2ETest
{
    public function testUserLogin(): void
    {
        // Navigate to login page
        $page = $this->visit('/login');

        // Fill form using Playwright API
        $page->fill('#email', 'user@example.com');
        $page->fill('#password', 'secret');
        $page->click('button[type="submit"]');

        // Assert success
        $this->assertPageContains('Dashboard');

        // Inspect intercepted response
        $this->assertSame(200, $this->getLastResponse()->getStatusCode());
    }
}
```

### 3. Run Your Tests

```bash
# Enable E2E tests and run
PLAYWRIGHT_E2E=1 vendor/bin/phpunit tests/E2E

# Run with visible browser for debugging
PLAYWRIGHT_E2E=1 PLAYWRIGHT_HEADLESS=false vendor/bin/phpunit tests/E2E
```

> **Note:** Tests are skipped by default unless `PLAYWRIGHT_E2E=1` is set.

## How It Works

### Request Interception Flow

1. Test calls `visit('/login')` → browser navigates to `http://localhost/login`
2. Bundle intercepts the request via Playwright's routing API
3. Request is converted to Symfony Request and handled by your kernel **in the same PHP process**
4. Response is sent back to the browser
5. Test continues with the rendered page

### Key Benefits

- **Access Symfony Services**: Query the database, inspect services, verify event dispatch
- **Set Breakpoints**: Debug your controllers while the browser waits
- **No Separate Server**: No need to start `symfony serve` or configure ports
- **Fast Assets**: Static files and AssetMapper assets are served directly, bypassing the kernel
- **Inspect Everything**: Access `getLastRequest()` and `getLastResponse()` in your tests

## Usage Notes

- `PlaywrightTestCase` drives a real browser. Calling `request()` (the traditional BrowserKit API) on the underlying client performs a plain GET navigation: POST requests, parameters and request bodies are not routed through the kernel yet. Always prefer `visit()` and the Playwright Page API. If you need classic BrowserKit semantics against real HTTP, use [`Playwright\Symfony\BrowserKit\PlaywrightClient`](docs/bridge/browserkit.md) from the container; it reuses the bundle's Playwright context.
- Set `PLAYWRIGHT_BASE_URL` (or the `playwright.base_url` config) to match the hostnames you intercept; this also controls which cookies are set when calling helper methods like `authenticate()`.

## Asset Dev Server

Static files (including AssetMapper output) are served by the in-process `AssetServer`, so requests under `/assets`, `/build`, and other configured prefixes never touch the kernel. Customize prefixes, additional `public_roots`, and cache behavior via `playwright.assets`: see [`docs/asset-dev-server.md`](docs/asset-dev-server.md) for a full walkthrough and troubleshooting tips.

## Common Scenarios

### Authentication & Cookies

```php
public function testAdminAccess(): void
{
    // Set authentication cookie
    $this->authenticate('admin@example.com', ['roles' => ['ROLE_ADMIN']]);

    $page = $this->visit('/admin');
    $this->assertPageContains('Admin Dashboard');

    // Logout and verify access denied
    $this->logout();
    $this->visit('/admin');
    $this->assertPageContains('Access Denied');
}

public function testCookiePreferences(): void
{
    $this->setCookie('theme', 'dark');
    $this->visit('/');
    $this->assertSelectorExists('.dark-theme');
}
```

### JavaScript & Async Interactions

```php
public function testDynamicContent(): void
{
    $page = $this->visit('/dashboard');

    // Execute JavaScript
    $version = $page->evaluate('() => window.appVersion');
    $this->assertEquals('2.0.0', $version);

    // Click and wait for AJAX
    $page->click('#load-more');
    $page->waitForSelector('.item-5');
    $this->assertPageContains('Item 5');
}
```

### Form Submissions & File Uploads

```php
public function testFormSubmission(): void
{
    $page = $this->visit('/contact');

    $page->fill('input[name="name"]', 'John Doe');
    $page->fill('textarea[name="message"]', 'Hello');
    $page->setInputFiles('input[type="file"]', '/path/to/file.pdf');
    $page->click('button[type="submit"]');

    $this->assertPageContains('Message sent');
    $this->assertSame(200, $this->getLastResponse()->getStatusCode());
}
```

## Documentation

- **[Getting Started Guide](docs/getting-started.md)** - Detailed installation and setup
- **[Helper & Assertion Reference](docs/helpers.md)** - Complete API documentation
- **[Configuration Reference](docs/configuration.md)** - All configuration options
- **[Architecture Overview](docs/architecture.md)** - How the bundle works internally
- **[Logging Guide](docs/logging.md)** - Debug request interception

## Testing Helpers

The bundle provides many helpers for common testing scenarios:

| Helper | Purpose |
|--------|---------|
| `visit($path)` | Navigate to a path and return the Playwright Page |
| `authenticate($user, $context)` | Set authentication cookie |
| `setCookie($name, $value)` | Set a cookie in the browser |
| `getLastRequest()` | Access the intercepted Symfony Request |
| `getLastResponse()` | Access the intercepted Symfony Response |
| `assertPageContains($text)` | Assert text appears in page HTML |
| `assertSelectorExists($selector)` | Assert CSS selector exists |

See [Helper Reference](docs/helpers.md) for the complete list.

## Advanced Features

### Request/Response Hooks

Customize request handling by overriding hooks in your test class:

```php
public function beforeRequest(SymfonyRequest $request): void
{
    // Modify requests before they reach the kernel
    $request->headers->set('X-Test-User', 'test-123');
}

public function afterResponse(SymfonyResponse $response): void
{
    // Inspect or modify responses
    $this->assertLessThan(300, $response->headers->get('X-Response-Time'));
}
```

### Fixture Loading

Override `loadFixtures()` to centralize test data setup:

```php
protected function loadFixtures(array $fixtures): void
{
    $em = static::getContainer()->get('doctrine.orm.entity_manager');

    foreach ($fixtures['users'] ?? [] as $userData) {
        $user = new User($userData['email'], $userData['password']);
        $em->persist($user);
    }

    $em->flush();
}

public function testWithFixtures(): void
{
    $this->loadFixtures([
        'users' => [['email' => 'test@example.com', 'password' => 'secret']],
    ]);

    // Test with loaded data...
}
```

## Environment Variables

Control test behavior with environment variables:

```bash
# Required to run E2E tests
PLAYWRIGHT_E2E=1

# Show browser window (default: headless)
PLAYWRIGHT_HEADLESS=false

# Choose browser engine (default: chromium)
PLAYWRIGHT_BROWSER=firefox  # or webkit

# Enable verbose logging
PLAYWRIGHT_VERBOSE=1

# Custom kernel class
KERNEL_CLASS=App\\CustomKernel
```

## Debugging

### Visible Browser Mode

Run tests with a visible browser to see what's happening:

```bash
PLAYWRIGHT_E2E=1 PLAYWRIGHT_HEADLESS=false vendor/bin/phpunit tests/E2E
```

### Inspect Requests & Responses

Access intercepted HTTP traffic in your tests:

```php
public function testDebugRequest(): void
{
    $page = $this->visit('/api/users');

    // Inspect the intercepted request
    $request = $this->getLastRequest();
    dump($request->headers->all());

    // Inspect the intercepted response
    $response = $this->getLastResponse();
    dump($response->getContent());
}
```

### Enable Request Logging

See [Logging Guide](docs/logging.md) for detailed request logs:

```yaml
# config/packages/test/playwright.yaml
playwright:
    debug_logging: true
```

## CI/CD Integration

Example GitHub Actions workflow:

```yaml
# .github/workflows/e2e.yml
name: E2E Tests
on: [push, pull_request]

jobs:
  e2e:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'

      - run: composer install
      - run: vendor/bin/playwright-install --with-deps

      - name: Run E2E tests
        run: vendor/bin/phpunit tests/E2E
        env:
          PLAYWRIGHT_E2E: '1'
          PLAYWRIGHT_HEADLESS: 'true'
```

## Comparison with other tools

| Feature | WebTestCase | Panther | PlaywrightTestCase |
|---------|-------------|---------|-------------------|
| Real browser | No | Yes | Yes |
| JavaScript execution | No | Yes | Yes |
| In-process requests | Yes | No | Yes |
| No external web server | Yes | No | Yes |
| CSS rendering | No | Yes | Yes |
| AJAX & async requests | Limited | Full support | Full support |
| Screenshots & videos | No | Yes | Yes |
| Performance | Fast | Slow | Fast (optimized) |

**Use WebTestCase for:** API testing, simple form submissions, fast unit-like functional tests.

**Use Panther for:** Standard Selenium-style E2E testing when you need a real web server.

**Use PlaywrightTestCase for:** Complex UIs, JavaScript-heavy apps, and high-performance E2E testing with full kernel access.

## Known Limitations

- **Browser-managed redirects:** When a navigation triggers an HTTP redirect, the browser sometimes follows it before the bundle can intercept the follow-up request. If the redirect target points to a host that is not intercepted, you may see `ERR_CONNECTION_REFUSED`. Work around this by keeping redirects on intercepted hosts or by handling the redirect inside your application logic.
- **AssetMapper vs. Kernel bridge:** Both the in-process AssetServer and the legacy KernelBrowser-based bridge exist. Prefer AssetServer for deterministic tests; only rely on the bridge if you explicitly need KernelBrowser semantics.

## Requirements

- PHP 8.3+
- Symfony 7.0+ or 8.0+
- Node.js 20+
- Playwright browsers

## Contributing

This bundle is in alpha. Please report issues and contribute improvements at:
https://github.com/playwright-php/playwright-symfony

## License

MIT License - see [LICENSE](LICENSE) file for details
