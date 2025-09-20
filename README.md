<div align="center">
<img src="https://github.com/playwright-php/.github/raw/main/profile/playwright-php.png" alt="Playwright for PHP" />
</div>

# Playwright PHP Symfony Bundle

Symfony integration for Playwright PHP that enables E2E testing with control over the Symfony kernel, allowing you
to run browser tests while intercepting HTTP requests and handling them through your application's HttpKernel in the
same thread.

> [!IMPORTANT]
> **⚠️ Alpha Status** 
> This bundle is in active development. Core features work well, but API may change before stable release.

## Key Features

- **In-Process Request Handling**: All HTTP requests from Playwright are intercepted and handled by your Symfony kernel
  in the same PHP process
- **Symfony Integration**: Full access to Symfony kernel, services, and components during tests
- **Cookie Management**: Set, get, and clear cookies for authentication testing
- **Form Testing**: Handle form submissions including multipart file uploads
- **JavaScript Execution**: Run JavaScript in real browser contexts
- **Request/Response Access**: Inspect intercepted HTTP requests and responses

## Installation

```bash
composer require --dev playwright-php/playwright-symfony
```

### Prerequisites

Before running tests, install Playwright browsers:

```bash
# Install Node.js and Playwright browsers
npx playwright install
```

**Requirements:**
- PHP 8.3+
- Symfony 7.0+ or 8.0+
- Node.js (for Playwright browser communication)
- Playwright browsers installed

## Basic Usage

### 1. Create Your Test Case

```php
use Playwright\Symfony\Test\PlaywrightTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

class E2ETestCase extends PlaywrightTestCase
{
    protected static function createKernel(array $options = []): KernelInterface
    {
        $kernel = getenv('KERNEL_CLASS') ?: 'App\Kernel';
        return new $kernel(
            $options['environment'] ?? 'test',
            $options['debug'] ?? true
        );
    }
}
```

### 2. Write Your Tests

```php
class UserFlowTest extends E2ETestCase
{
    public function testCompleteUserJourney(): void
    {
        // Visit page and get Playwright Page instance
        $page = $this->visit('/login');
        
        // Use Playwright Page API for interactions
        $page->locator('#email')->fill('test@example.com');
        $page->locator('#password')->fill('password123');
        $page->click('button[type="submit"]');
        
        // Wait for navigation
        $page->waitForLoadState();
        
        // Use custom assertions
        $this->assertPageContains('Welcome back');
        
        // Access intercepted request/response
        $response = $this->getLastResponse();
        $this->assertSame(200, $response->getStatusCode());
    }
}
```

## Architecture

### Request Flow

1. **Playwright Browser** makes HTTP request to `http://localhost/path`
2. **PlaywrightTestCase** intercepts the request via Playwright's route API
3. **Request Converter** transforms Playwright request to Symfony Request
4. **Symfony HttpKernel** handles the request in the same PHP process
5. **Response** is sent back to Playwright browser

### Benefits of In-Process Handling

- **Services**: Access any Symfony service during test execution
- **Debugging**: Set breakpoints in your controllers during E2E tests  
- **Performance**: No network overhead between test and application
- **State Access**: Inspect application state during browser interactions
- **Custom Logic**: Add hooks before/after requests via `beforeRequest()` and `afterResponse()` methods

### Authentication

```php
public function testAuthenticatedArea(): void
{
    // Authenticate user via cookie
    $this->authenticate('user123', ['role' => 'admin']);
    
    // Access protected area
    $page = $this->visit('/admin');
    $this->assertPageContains('Admin Dashboard');
    
    // Logout
    $this->logout();
    $page = $this->visit('/admin');
    $this->assertPageContains('Access Denied');
}
```

### JavaScript Execution

```php
public function testJavaScriptInteraction(): void
{
    $page = $this->visit('/interactive');
    
    // Execute custom JavaScript using Playwright Page API
    $result = $page->evaluate('() => window.appVersion');
    $this->assertEquals('1.2.3', $result);
    
    // Handle form interactions
    $page->click('#load-more');
    $page->waitForLoadState();
    $this->assertPageContains('Additional content');
}
```

### Cookie Management

```php
public function testCookieHandling(): void
{
    // Set cookie
    $this->setCookie('preference', 'dark-mode', [
        'expires' => time() + 86400
    ]);
    
    $this->visit('/');
    $this->assertSelectorExists('.dark-theme');
    
    // Read cookie
    $value = $this->getCookie('preference');
    $this->assertEquals('dark-mode', $value);
    
    // Clear cookies
    $this->clearCookies();
    $this->visit('/');
    $this->assertSelectorNotExists('.dark-theme');
}
```

## Real Application Integration

### Setting up the Bundle in Your Symfony App

1. **Add the bundle to your `config/bundles.php`**:

```php
return [
    // ... your bundles
    Playwright\Symfony\PlaywrightSymfonyBundle::class => ['test' => true],
];
```

2. **Configure intercepted hosts** (optional, in `config/packages/test/playwright.yaml`):

```yaml
playwright:
    intercepted_hosts:
        - 'localhost'
        - '127.0.0.1'
        - 'testapp.local'
    debug: '%kernel.debug%'
    default_browser: 'default'
    browsers:
        default:
            type: 'chromium'
            headless: true
            timeout_ms: 30000
            slowmo_ms: 0
        firefox:
            type: 'firefox'
            headless: true
```

3. **Create your test base class**:

```php
<?php

namespace App\Tests\E2E;

use Playwright\Symfony\Test\PlaywrightTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

abstract class BaseE2ETest extends PlaywrightTestCase
{
    protected static function createKernel(array $options = []): KernelInterface
    {
        $class = $_SERVER['KERNEL_CLASS'] ?? 'App\Kernel';
        
        return new $class(
            $options['environment'] ?? 'test',
            $options['debug'] ?? false
        );
    }

    protected function loadFixtures(array $fixtures): void
    {
        // Load your test fixtures here
        // Example with Doctrine:
        // $container = static::$kernel->getContainer();
        // $entityManager = $container->get('doctrine.orm.entity_manager');
        // ... your fixture loading logic
    }
}
```

4. **Write your E2E tests**:

```php
<?php

namespace App\Tests\E2E;

class LoginTest extends BaseE2ETest
{
    public function testUserCanLogin(): void
    {
        // Set up test data
        $this->loadFixtures([
            'user' => ['email' => 'user@example.com', 'password' => 'password123']
        ]);

        // Navigate to login page using the visit helper
        $page = $this->visit('/login');

        // Use native Playwright Page API for interactions
        $page->fill('#email', 'user@example.com');
        $page->fill('#password', 'password123');
        $page->click('button[type="submit"]');

        // Wait for navigation and verify result
        $page->waitForLoadState();
        $this->assertPageContains('Dashboard');

        // Access intercepted request/response
        $response = $this->getLastResponse();
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testCookieAuthentication(): void
    {
        // Use authentication helper
        $this->authenticate('user123', ['role' => 'admin']);
        
        $page = $this->visit('/admin/dashboard');
        
        // Verify the auth cookie was used
        $authCookie = $this->getCookie('AUTH');
        $this->assertNotNull($authCookie);
        
        $this->assertPageContains('Admin Panel');
    }
}
```

### Integration with Existing Applications

**For applications with existing test infrastructure**:

1. Place E2E tests in `tests/Integration/E2E/` directory (this repository’s convention)
2. Configure separate test database for E2E tests
3. Use environment variables to control test behavior:
   - `KERNEL_CLASS` - Custom kernel class
   - `PLAYWRIGHT_HEADLESS=false` - Show browser during tests
   - `PLAYWRIGHT_BROWSER=firefox` - Use different browser

**Continuous Integration Setup**:

```yaml
# .github/workflows/e2e.yml
name: E2E Tests
on: [push, pull_request]

jobs:
  e2e:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
      
      - name: Install dependencies
        run: composer install
      
      - name: Install Playwright
        run: npx playwright install chromium --with-deps
      
      - name: Run E2E tests
        run: vendor/bin/phpunit tests/Integration/E2E/
        env:
          PLAYWRIGHT_HEADLESS: true
```

### Current API Reference

The bundle provides these methods in `PlaywrightTestCase`:

**Navigation:**
- `visit(string $path): PageInterface` - Navigate to a path and return Playwright Page

**Cookies:**
- `setCookie(string $name, string $value, array $options = []): void`
- `getCookie(string $name, ?string $url = null): ?string`  
- `clearCookies(): void`
- `clearCookie(string $name, ?string $domain = null, string $path = '/'): void`

**Authentication:**
- `authenticate(string $identifier = 'user', array $context = []): void`
- `logout(): void`

**Request/Response Access:**
- `getLastRequest(): ?SymfonyRequest` - Get last intercepted request
- `getLastResponse(): ?SymfonyResponse` - Get last intercepted response

**Hooks (public methods you can override):**
- `beforeRequest(SymfonyRequest $request): void` - Called before each request
- `afterResponse(SymfonyResponse $response): void` - Called after each response

All browser interactions use the native [Playwright PHP Page API](https://github.com/playwright-php/playwright).

## Environment Variables

- `PLAYWRIGHT_HEADLESS`: Set to `false` to see browser during tests
- `PLAYWRIGHT_BROWSER`: Choose browser (chromium, firefox, webkit)
- `KERNEL_CLASS`: Specify custom kernel class (default: `App\Kernel`)

## Running Tests

```bash
# Run all E2E tests (repository convention)
vendor/bin/phpunit tests/Integration/E2E

# Run with visible browser
PLAYWRIGHT_HEADLESS=false vendor/bin/phpunit tests/Integration/E2E

# Run specific test file
vendor/bin/phpunit tests/Integration/E2E/HelloE2ETest.php
```

## Tips and Best Practices

1. **Wait for Elements**: Always wait for elements before interacting with `page->waitForSelector()`
2. **Use Data Attributes**: Add `data-test` attributes for reliable selectors  
3. **One Assertion Focus**: Each test should focus on one user journey
4. **Clean State**: Each test gets fresh browser context for isolation
5. **Use Page API**: Leverage full Playwright Page API for complex interactions

## Debugging

### Inspect Network Traffic

```php
public function testApiCalls(): void
{
    $page = $this->visit('/spa-app');
    
    // Get last intercepted request/response
    $request = $this->getLastRequest();
    $response = $this->getLastResponse();
    
    // Inspect headers and content
    $this->assertSame('POST', $request->getMethod());
    $this->assertSame(200, $response->getStatusCode());
}
```

### Browser Debug Mode

```bash
# Show browser during tests
PLAYWRIGHT_HEADLESS=false vendor/bin/phpunit tests/Integration/E2E/

# Use different browser
PLAYWRIGHT_BROWSER=firefox vendor/bin/phpunit tests/Integration/E2E/
```

## Comparison with WebTestCase

| Feature              | WebTestCase | PlaywrightTestCase |
|----------------------|-------------|--------------------|
| JavaScript Execution | ❌           | ✅                  |
| Real Browser         | ❌           | ✅                  |
| Visual Testing       | ❌           | ✅                  |
| AJAX Testing         | Limited     | ✅                  |
| CSS Animations       | ❌           | ✅                  |
| File Downloads       | Limited     | ✅                  |
| Multiple Tabs        | ❌           | ✅                  |
| Request Interception | ✅           | ✅                  |
| Symfony Integration  | ✅           | ✅                  |
| Transaction Rollback | Depends     | Depends            |

## Contributing

This is an alpha release. Please report issues and contribute improvements!

## License

MIT
