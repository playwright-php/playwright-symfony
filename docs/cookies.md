# Cookie Implementation Guide

## Overview

This document explains how cookies work in the Playwright Symfony bundle, specifically how cookies are synchronized
between the browser context and Symfony's kernel.

## Architecture

```
Browser Context (Playwright)
         ↓ setCookie()
    addCookies() with domain parameter
         ↓
    Cookie stored in browser
         ↓ page.goto()
    Cookie sent in HTTP headers
         ↓ Route interception
    RequestConverter parses Cookie header
         ↓
    Symfony Request with cookies
         ↓
    Controller accesses $request->cookies
```

## Key Components

### 1. BrowserRegistry (`src/Client/BrowserRegistry.php`)

Manages the browser instance and context.

**Key Methods:**

- `getContext()` - Returns browser context (auto-starts browser if needed)
- `getPage()` - Returns current page (auto-starts browser if needed)
- `start()` - Explicitly starts the browser

**Important:** `getContext()` now calls `ensureStarted()` to prevent null context issues.

### 2. PlaywrightKernelClient (`src/Client/PlaywrightKernelClient.php`)

Provides cookie helper methods.

**Key Methods:**

#### `setCookie(string $name, string $value, array $options = []): void`

Sets a cookie in the browser context.

**Parameters:**

- `$name` - Cookie name
- `$value` - Cookie value
- `$options` - Optional array with:
    - `domain` - Cookie domain (auto-extracted from baseUrl if not provided)
    - `path` - Cookie path (default: `/`)
    - `expires` - Expiration timestamp (int)
    - `httpOnly` - HTTP only flag (bool)
    - `secure` - Secure flag (bool)
    - `sameSite` - SameSite attribute ('Lax', 'None', 'Strict')

**Implementation:**

```php
public function setCookie(string $name, string $value, array $options = []): void
{
    // Extract domain from baseUrl if not provided
    $domain = $options['domain'] ?? parse_url($this->getBaseUrl(), PHP_URL_HOST) ?? 'localhost';
    
    $cookie = array_merge([
        'name' => $name,
        'value' => $value,
        'domain' => $domain, // ⚠️ Must use 'domain', not 'url'
        'path' => $options['path'] ?? '/',
    ], $options);
    
    $context = $this->browser->getContext();
    $context->addCookies([$cookie]);
}
```

#### `getCookie(string $name, ?string $url = null): ?string`

Retrieves a cookie value from the browser context.

**Returns:** Cookie value or null if not found

#### `clearCookie(string $name, ?string $domain = null, string $path = '/'): void`

Removes a specific cookie from the browser context by setting its expiration to the past.

**Implementation:**

```php
public function clearCookie(string $name, ?string $domain = null, string $path = '/'): void
{
    $options = [
        'domain' => $domain ?? parse_url($this->getBaseUrl(), PHP_URL_HOST) ?? 'localhost',
        'path' => $path,
        'expires' => 0, // Force expiration
    ];

    $this->setCookie($name, '', $options);
}
```

#### `clearCookies(): void`

Removes all cookies from the browser context.

### 3. RequestConverter (`src/Client/RequestConverter.php`)

Converts Playwright requests to Symfony requests, including cookie parsing.

**Cookie Parsing:**

```php
// Parse cookies from Cookie header
if (isset($lower['cookie'])) {
    $cookies = $this->parseCookieHeader($lower['cookie']);
}

// Cookie header format: "name1=value1; name2=value2"
private function parseCookieHeader(string $cookieHeader): array
{
    $cookies = [];
    $cookiePairs = array_map('trim', explode(';', $cookieHeader));
    
    foreach ($cookiePairs as $pair) {
        [$name, $value] = explode('=', $pair, 2);
        $cookies[$name] = urldecode($value);
    }
    
    return $cookies;
}
```

## Critical Implementation Details

### ⚠️ Use 'domain' Parameter, Not 'url'

**WRONG:**

```php
$context->addCookies([
    [
        'name' => 'test',
        'value' => 'value',
        'url' => 'http://localhost', // ❌ Won't work
    ]
]);
```

**CORRECT:**

```php
$context->addCookies([
    [
        'name' => 'test',
        'value' => 'value',
        'domain' => 'localhost', // ✅ Works
    ]
]);
```

**Why:** While Playwright's documentation mentions both parameters, in practice only `domain` reliably works for cookie
persistence.

### Domain Extraction

Extract domain from URL using `parse_url()`:

```php
$domain = parse_url('http://localhost:8080/path', PHP_URL_HOST);
// Result: 'localhost'
```

### Browser Auto-Start

Both `getContext()` and `getPage()` auto-start the browser:

```php
$browser = BrowserRegistry::fromEnvironment();
$context = $browser->getContext(); // Browser starts automatically
```

## Common Patterns

### Setting a Cookie Before Navigation

```php
$this->setCookie('session_id', 'abc123');
$this->visit('/protected-page');
// Cookie is sent with the request
```

### Authentication Flow

```php
// Set auth cookie
$this->setCookie('AUTH', base64_encode('user:pass'));

// Visit protected page
$this->visit('/admin');

// Verify access
$this->assertPageContains('Welcome Admin');

// Clear auth
$this->clearCookie('AUTH');
```

### Testing Cookie Persistence

```php
// Set cookie
$this->setCookie('notice', '1');

// Visit page that reads cookies
$this->visit('/cookie-echo');

// Verify cookie was received
$this->assertPageContains('"notice":"1"');

// Verify we can read it back
self::assertSame('1', $this->getCookie('notice'));
```

## Troubleshooting

### Cookies Not Appearing in Request

**Symptoms:**

- `$request->cookies->all()` returns empty array
- Controller doesn't see expected cookies

**Causes & Solutions:**

1. **Browser not started**
    - Solution: Call `getContext()` or `getPage()` (auto-starts)

2. **Using 'url' instead of 'domain'**
    - Solution: Use `domain` parameter in cookie array

3. **Domain mismatch**
    - Cookie domain: `localhost`
    - Request host: `127.0.0.1`
    - Solution: Ensure domain matches or use wildcard

4. **Path mismatch**
    - Cookie path: `/admin`
    - Request path: `/`
    - Solution: Set cookie path to `/` or match request path

### Cookies Persisting Between Tests

**Symptoms:**

- Test B sees cookies from Test A
- Unexpected authentication state

**Solution:**

```php
protected function tearDown(): void
{
    $this->clearCookies(); // Clear all cookies
    parent::tearDown();
}
```

### Cookie Not Sent with Request

**Check:**

1. Cookie domain matches request host
2. Cookie path matches request path
3. Cookie isn't expired
4. Secure flag matches protocol (HTTPS/HTTP)

**Debug:**

```php
// Check what cookies browser has
$cookies = $this->browser->getContext()->cookies();
foreach ($cookies as $cookie) {
    dump($cookie);
}
```

## Testing Cookies

### Unit Test Example

```php
public function testCookieCanBeSetAndRetrieved(): void
{
    $this->client->setCookie('test', 'value');
    
    $cookies = $this->browser->getContext()->cookies();
    
    $this->assertCount(1, $cookies);
    $this->assertSame('test', $cookies[0]['name']);
    $this->assertSame('value', $cookies[0]['value']);
}
```

### E2E Test Example

```php
public function testCookieAuthenticationFlow(): void
{
    // Set auth cookie
    $this->setCookie('AUTH', 'admin-token');
    
    // Visit protected page
    $this->visit('/admin/dashboard');
    
    // Should be authenticated
    $this->assertPageContains('Admin Dashboard');
    $this->assertPageNotContains('Login Required');
    
    // Logout clears cookie
    $this->clearCookie('AUTH');
    
    // Should be logged out
    $this->visit('/admin/dashboard');
    $this->assertPageContains('Login Required');
}
```

## API Reference

### PlaywrightTestCase Methods

All cookie methods are available in test classes extending `PlaywrightTestCase`:

```php
// Set a cookie
$this->setCookie(string $name, string $value, array $options = []);

// Get a cookie value
$value = $this->getCookie(string $name, ?string $url = null): ?string;

// Clear specific cookie
$this->clearCookie(string $name, ?string $domain = null, string $path = '/');

// Clear all cookies
$this->clearCookies();
```

### Playwright Browser Context Methods

Direct access via `$this->browser->getContext()`:

```php
// Add cookies
$context->addCookies(array $cookies);

// Get cookies
$cookies = $context->cookies(?array $urls = null);

// Delete all cookies with the given name (across domain and path variants)
$context->deleteCookie(string $name);

// Clear all
$context->clearCookies();
```

## Best Practices

1. ✅ **Always use domain parameter** instead of url
2. ✅ **Clear cookies in tearDown()** for test isolation
3. ✅ **Set cookies before navigation** to ensure they're sent
4. ✅ **Use helper methods** (`setCookie()`, `getCookie()`) instead of direct browser context access
5. ✅ **Test in isolation** first, then with full suite
6. ✅ **Match domain exactly** - `localhost` ≠ `127.0.0.1`

---

## See Also

- [Helper & Assertion Reference](helpers.md) - Cookie helper methods on `PlaywrightTestCase`
- [Playwright Cookie API](https://playwright.dev/docs/api/class-browsercontext#browser-context-add-cookies) - Upstream
  documentation

