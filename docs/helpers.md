# Helper & Assertion Reference

This bundle provides a comprehensive set of test helpers to make E2E testing straightforward and productive. All helpers
are available on `Playwright\Symfony\Test\PlaywrightTestCase`.

## Navigation Helpers

### `visit(string $path): PageInterface`

Navigate to a path and return the Playwright Page instance.

**Parameters:**

- `$path` - Path relative to the configured `base_url`

**Returns:** `PageInterface` - The Playwright Page object

**Example:**

```php
public function testNavigate(): void
{
    $page = $this->visit('/login');
    $this->assertPageContains('Login');
}
```

### `getPage(): PageInterface`

Get the current Playwright Page instance.

**Returns:** `PageInterface` - The active Page object

**Example:**

```php
public function testGetPage(): void
{
    $this->visit('/dashboard');
    $page = $this->getPage();

    $page->click('#menu-toggle');
}
```

### Magic `$this->page` Property

Convenience property for accessing the Page without calling `getPage()`.

**Example:**

```php
public function testMagicProperty(): void
{
    $this->visit('/form');
    $this->page->fill('#email', 'user@example.com');
}
```

## Assertion Helpers

These assertions are provided by `PlaywrightTestAssertionsTrait` which is included in `PlaywrightTestCase`.

### `assertPageContains(string $text): void`

Assert that the page HTML contains the specified text.

**Example:**

```php
public function testContent(): void
{
    $this->visit('/welcome');
    $this->assertPageContains('Welcome to our site');
}
```

### `assertPageNotContains(string $text): void`

Assert that the page HTML does not contain the specified text.

**Example:**

```php
public function testNoErrors(): void
{
    $this->visit('/dashboard');
    $this->assertPageNotContains('Error');
}
```

### `assertSelectorExists(string $selector): void`

Assert that a CSS selector exists on the page.

**Example:**

```php
public function testElement(): void
{
    $this->visit('/profile');
    $this->assertSelectorExists('.user-avatar');
    $this->assertSelectorExists('button[type="submit"]');
}
```

### `assertSelectorNotExists(string $selector): void`

Assert that a CSS selector does not exist on the page.

**Example:**

```php
public function testHidden(): void
{
    $this->visit('/public-page');
    $this->assertSelectorNotExists('.admin-menu');
}
```

### `waitForSelector(string $selector, array $options = []): void`

Wait for a selector to appear on the page.

**Example:**

```php
public function testAsync(): void
{
    $page = $this->visit('/products');
    $page->click('#load-more');

    $this->waitForSelector('.product-10');
}
```

## Additional Test Helpers

These are convenience methods that forward to the Playwright Page API:

### `click(string $selector): void`

Click an element matching the selector.

**Example:**

```php
public function testClick(): void
{
    $this->visit('/form');
    $this->click('button[type="submit"]');
}
```

### `fill(string $selector, string $value): void`

Fill an input field with a value.

**Example:**

```php
public function testFill(): void
{
    $this->visit('/contact');
    $this->fill('#name', 'John Doe');
    $this->fill('#email', 'john@example.com');
}
```

### `select(string $selector, string $value): void`

Select an option from a dropdown.

**Example:**

```php
public function testSelect(): void
{
    $this->visit('/register');
    $this->select('#country', 'US');
}
```

### `check(string $selector): void`

Check a checkbox.

**Example:**

```php
public function testCheck(): void
{
    $this->visit('/preferences');
    $this->check('#newsletter');
}
```

### `uncheck(string $selector): void`

Uncheck a checkbox.

**Example:**

```php
public function testUncheck(): void
{
    $this->visit('/preferences');
    $this->uncheck('#notifications');
}
```

### `screenshot(string $path): void`

Take a screenshot and save it to the specified path.

**Example:**

```php
public function testScreenshot(): void
{
    $this->visit('/dashboard');
    $this->screenshot('/tmp/dashboard.png');
}
```

## Cookie & Session Helpers

### `setCookie(string $name, string $value, array $options = []): void`

Set a cookie in the browser context.

**Parameters:**

- `$name` - Cookie name
- `$value` - Cookie value
- `$options` - Optional cookie attributes (path, domain, expires, etc.)

**Example:**

```php
public function testSetCookie(): void
{
    $this->setCookie('theme', 'dark');
    $this->setCookie('session_id', 'abc123', [
        'path' => '/',
        'expires' => time() + 3600,
    ]);

    $page = $this->visit('/');
}
```

### `getCookie(string $name, ?string $url = null): ?string`

Get a cookie value from the browser context.

**Parameters:**

- `$name` - Cookie name
- `$url` - Optional URL to scope the cookie (defaults to base_url)

**Returns:** `string|null` - Cookie value or null if not found

**Example:**

```php
public function testGetCookie(): void
{
    $this->visit('/login');
    // Assume login sets a cookie

    $sessionId = $this->getCookie('PHPSESSID');
    $this->assertNotNull($sessionId);
}
```

### `clearCookie(string $name, ?string $domain = null, string $path = '/'): void`

Remove a specific cookie.

**Example:**

```php
public function testClearCookie(): void
{
    $this->setCookie('temp', 'value');
    $this->clearCookie('temp');

    $value = $this->getCookie('temp');
    $this->assertNull($value);
}
```

### `clearCookies(): void`

Remove all cookies from the browser context.

**Example:**

```php
public function testClearAllCookies(): void
{
    $this->setCookie('cookie1', 'value1');
    $this->setCookie('cookie2', 'value2');

    $this->clearCookies();

    $this->assertNull($this->getCookie('cookie1'));
    $this->assertNull($this->getCookie('cookie2'));
}
```

### `authenticate(string $identifier = 'user', array $context = []): void`

Convenience helper that sets an `AUTH` cookie with user information. This is intended as a starting point - you'll
likely override this or use `beforeRequest()` for your authentication scheme.

**Parameters:**

- `$identifier` - User identifier (email, username, ID, etc.)
- `$context` - Additional context (roles, permissions, etc.)

**Example:**

```php
public function testAuthenticatedAccess(): void
{
    $this->authenticate('admin@example.com', [
        'roles' => ['ROLE_ADMIN'],
        'id' => 123,
    ]);

    $page = $this->visit('/admin/users');
    $this->assertPageContains('User Management');
}
```

**Note:** The `authenticate()` method stores data in an `AUTH` cookie as JSON. You'll need to handle this cookie in your
application's security layer or override this method for your authentication mechanism.

### `logout(): void`

Remove the authentication cookie.

**Example:**

```php
public function testLogout(): void
{
    $this->authenticate('user@example.com');
    $this->visit('/dashboard');
    $this->assertPageContains('Dashboard');

    $this->logout();
    $this->visit('/dashboard');
    $this->assertPageContains('Please log in');
}
```

### Complete Authentication Example

```php
public function testAdminWorkflow(): void
{
    // Set up test data
    $this->loadFixtures([
        'users' => [
            ['email' => 'admin@example.test', 'roles' => ['ROLE_ADMIN']],
        ],
    ]);

    // Authenticate as admin
    $this->authenticate('admin@example.test', ['roles' => ['ROLE_ADMIN']]);

    // Access protected page
    $page = $this->visit('/admin/dashboard');
    $this->assertPageContains('Admin Dashboard');

    // Verify response
    $response = $this->getLastResponse();
    $this->assertSame(200, $response->getStatusCode());

    // Logout and verify access denied
    $this->logout();
    $this->visit('/admin/dashboard');
    $this->assertPageContains('Access Denied');
}
```

### Custom Authentication

If you need more control over authentication, override `beforeRequest()`. The method must stay
`public` (it is declared `public` in `PlaywrightTestCase`):

```php
public function beforeRequest(SymfonyRequest $request): void
{
    // Modify headers before the kernel handles the request
    $request->headers->set('Authorization', 'Bearer token123');

    // Or add cookies the firewall can read
    $request->cookies->set('AUTH', 'admin-token');
}
```

> **Note**
> The request has no session at this stage: the session is started later by the kernel while
> handling the request. Calling `$request->getSession()` here throws `SessionNotFoundException`.

## Request/Response Introspection

### `getLastRequest(): ?SymfonyRequest`

Get the last Symfony Request that was intercepted and handled by the kernel.

**Returns:** `SymfonyRequest|null` - The last request or null if no request yet

**Example:**

```php
public function testRequestHeaders(): void
{
    $page = $this->visit('/api/users');

    $request = $this->getLastRequest();
    $this->assertSame('GET', $request->getMethod());
    $this->assertSame('/api/users', $request->getPathInfo());
}
```

### `getLastResponse(): ?SymfonyResponse`

Get the last Symfony Response returned by the kernel.

**Returns:** `SymfonyResponse|null` - The last response or null if no response yet

**Example:**

```php
public function testResponseHeaders(): void
{
    $page = $this->visit('/api/users');

    $response = $this->getLastResponse();
    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('application/json', $response->headers->get('Content-Type'));

    $data = json_decode($response->getContent(), true);
    $this->assertArrayHasKey('users', $data);
}
```

### `getBaseUrl(): string`

Get the configured base URL for the application.

**Returns:** `string` - The base URL (e.g., `http://localhost`)

**Example:**

```php
public function testBaseUrl(): void
{
    $baseUrl = $this->getBaseUrl();
    $this->assertSame('http://localhost', $baseUrl);
}
```

## Hooks & Lifecycle Methods

### `beforeRequest(SymfonyRequest $request): void`

Override this method to customize requests before they reach the kernel.

**Called:** Right before the kernel handles each intercepted request

**Use Cases:**

- Modify request headers
- Add authentication tokens
- Change locale

**Example:**

```php
public function beforeRequest(SymfonyRequest $request): void
{
    // Add custom header to all requests
    $request->headers->set('X-Test-Mode', 'true');

    // Set locale
    $request->setLocale('fr');
}
```

### `afterResponse(SymfonyResponse $response): void`

Override this method to inspect or modify responses after the kernel returns them.

**Called:** Immediately after the kernel responds to each intercepted request

**Use Cases:**

- Assert on response headers
- Collect performance metrics
- Log response data
- Verify security headers

**Example:**

```php
public function afterResponse(SymfonyResponse $response): void
{
    // Assert all responses have security headers
    $this->assertTrue($response->headers->has('X-Frame-Options'));

    // Log slow responses
    $time = $response->headers->get('X-Response-Time');
    if ($time && $time > 1000) {
        $this->fail("Response too slow: {$time}ms");
    }
}
```

### `loadFixtures(array $fixtures): void`

Override this method to centralize test data loading.

**Called:** When you explicitly call it from your tests

**Use Cases:**

- Load Doctrine fixtures
- Seed database data
- Create test files
- Set up test environment

**Example:**

```php
protected function loadFixtures(array $fixtures): void
{
    $em = static::getContainer()->get('doctrine.orm.entity_manager');

    // Load users
    foreach ($fixtures['users'] ?? [] as $userData) {
        $user = new User();
        $user->setEmail($userData['email']);
        $user->setPassword($userData['password']);
        $em->persist($user);
    }

    // Load products
    foreach ($fixtures['products'] ?? [] as $productData) {
        $product = new Product();
        $product->setName($productData['name']);
        $product->setPrice($productData['price']);
        $em->persist($product);
    }

    $em->flush();
}

public function testWithFixtures(): void
{
    $this->loadFixtures([
        'users' => [
            ['email' => 'user@example.com', 'password' => 'secret'],
        ],
        'products' => [
            ['name' => 'Product 1', 'price' => 19.99],
            ['name' => 'Product 2', 'price' => 29.99],
        ],
    ]);

    $page = $this->visit('/products');
    $this->assertPageContains('Product 1');
}
```

### Lifecycle Considerations

**Important:** If you override `setUp()` or `tearDown()`, always call the parent methods. Note that the browser process
is managed at the class level and stopped in `tearDownAfterClass()`.

```php
protected function setUp(): void
{
    parent::setUp(); // REQUIRED - starts/restarts the browser session

    // Your custom setup here
}

protected function tearDown(): void
{
    // Your custom cleanup here

    parent::tearDown(); // REQUIRED - handles exception restoration
}
```

## Complete Example

Here's a comprehensive example using many of the helpers:

```php
<?php

namespace App\Tests\E2E;

use Playwright\Symfony\Test\PlaywrightTestCase;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\KernelInterface;

class OrderFlowTest extends PlaywrightTestCase
{
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new \App\Kernel('test', false);
    }

    protected function loadFixtures(array $fixtures): void
    {
        // Load test data
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        foreach ($fixtures['products'] ?? [] as $data) {
            $product = new \App\Entity\Product();
            $product->setName($data['name']);
            $product->setPrice($data['price']);
            $em->persist($product);
        }

        $em->flush();
    }

    public function beforeRequest(SymfonyRequest $request): void
    {
        // Add test header to all requests
        $request->headers->set('X-Test-Run', 'e2e');
    }

    public function afterResponse(SymfonyResponse $response): void
    {
        // Ensure no errors in responses
        $this->assertLessThan(400, $response->getStatusCode());
    }

    public function testCompleteOrderFlow(): void
    {
        // Set up test data
        $this->loadFixtures([
            'products' => [
                ['name' => 'Test Product', 'price' => 99.99],
            ],
        ]);

        // Authenticate user
        $this->authenticate('user@example.com', ['roles' => ['ROLE_USER']]);

        // Browse products
        $page = $this->visit('/products');
        $this->assertPageContains('Test Product');

        // Add to cart
        $this->click('button.add-to-cart');
        $this->waitForSelector('.cart-updated');

        // Go to checkout
        $page->click('a[href="/checkout"]');
        $page->waitForLoadState();

        // Fill checkout form
        $this->fill('#billing_name', 'John Doe');
        $this->fill('#billing_address', '123 Main St');
        $this->fill('#card_number', '4111111111111111');

        // Submit order
        $this->click('button[type="submit"]');
        $page->waitForLoadState();

        // Verify order success
        $this->assertPageContains('Order confirmed');
        $this->assertSelectorExists('.order-number');

        // Verify response
        $response = $this->getLastResponse();
        $this->assertSame(200, $response->getStatusCode());
    }
}
```

## Next Steps

- Explore [Configuration Options](configuration.md)
- Understand the [Architecture](architecture.md)
- Enable [Request Logging](logging.md) for debugging
