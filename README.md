<div align="center">
<a href="https://github.com/playwright-php"><img src="https://github.com/playwright-php/.github/raw/main/profile/playwright-php.png" alt="Playwright PHP" /></a>

&nbsp; ![PHP Version](https://img.shields.io/badge/PHP-8.2+-05971B?labelColor=09161E&color=1D8D23&logoColor=FFFFFF)
&nbsp; ![CI](https://img.shields.io/github/actions/workflow/status/playwright-php/playwright-symfony/CI.yml?branch=main&label=Tests&color=1D8D23&labelColor=09161E&logoColor=FFFFFF)
&nbsp; [![Release](https://img.shields.io/github/v/release/playwright-php/playwright-symfony?label=Stable&labelColor=09161E&color=1D8D23&logoColor=FFFFFF)](https://packagist.org/packages/playwright-php/playwright-symfony)
&nbsp; ![License](https://img.shields.io/github/license/playwright-php/playwright-symfony?label=License&labelColor=09161E&color=1D8D23&logoColor=FFFFFF)

</div>

# Playwright PHP for Symfony

Run real-browser Symfony tests while routing application requests through the kernel in the same PHP process.

> [!IMPORTANT]
> This package is in active development. Its public API may change before 1.0.

## Installation

```bash
composer require --dev playwright-php/playwright-symfony
vendor/bin/playwright-install --browsers
```

Requirements:

- PHP 8.2+
- Symfony 6.4, 7.x, or 8.x
- Node.js 20+

Register the bundle for the test environment:

```php
// config/bundles.php
return [
    // ...
    Playwright\Symfony\PlaywrightSymfonyBundle::class => ['test' => true],
];
```

The bundle works without additional configuration. To change the base URL or intercepted hosts:

```yaml
# config/packages/test/playwright.yaml
playwright:
    base_url: 'http://localhost'
    intercepted_hosts: ['localhost', '127.0.0.1']
```

## Quick Start

Extend `PlaywrightTestCase`, visit an application route, and use the regular Playwright page API:

```php
<?php

namespace App\Tests\E2E;

use Playwright\Symfony\Test\PlaywrightTestCase;

final class HomepageTest extends PlaywrightTestCase
{
    public function testNavigation(): void
    {
        $page = $this->visit('/');

        self::assertResponseIsSuccessful();
        $page->getByRole('link', ['name' => 'About'])->click();

        $this->assertPageContains('About');
    }
}
```

Run the test with PHPUnit:

```bash
vendor/bin/phpunit tests/E2E
```

Set `PLAYWRIGHT_HEADLESS=false` to see the browser, or `PLAYWRIGHT_BROWSER=firefox` to use another engine.

## How It Works

For requests to an intercepted host, the package:

1. Intercepts the browser request through Playwright.
2. Converts it to a Symfony request.
3. Handles it with the application kernel.
4. Returns the Symfony response to the browser.

This keeps JavaScript, CSS, navigation, cookies, and browser storage in a real browser while preserving access to the Symfony test container, request, response, and profiler.

The kernel and browser start lazily when a browser helper or client is first used.

```php
$this->visit('/admin');

self::assertSame(200, $this->getLastResponse()?->getStatusCode());
$service = static::getContainer()->get(App\Service\AuditLog::class);
```

Static files and AssetMapper output can be served directly by the asset bridge without passing through the kernel.

## Multiple browser clients

Use the primary client alongside fresh clients when a test needs isolated browser contexts:

```php
$alice = static::getPlaywrightClient();
$bob = static::createPlaywrightClient();
```

The clients share the browser process and Symfony kernel, but not cookies or browser storage.

## Authentication

Use `loginUser()` when login itself is not under test:

```php
$this->loginUser($user);

$page = $this->visit('/account');
$this->assertPageContains($user->getUserIdentifier());
```

The package also provides cookie helpers and access to the last intercepted request and response.

## Limits

- `PlaywrightTestCase` is for browser navigation. Prefer `visit()` and the Playwright page API over direct BrowserKit requests.
- Only configured hosts are routed through the Symfony kernel. Other requests use the browser network normally.
- Browser tests are slower than unit and functional tests. Keep them in a dedicated PHPUnit suite or group.

## Documentation

- [Getting started](docs/getting-started.md)
- [Configuration](docs/configuration.md)
- [Test helpers](docs/helpers.md)
- [Asset development server](docs/asset-dev-server.md)
- [Continuous integration](docs/ci.md)
- [Architecture](docs/architecture.md)

## Contributing

Contributions are welcome. Before submitting a pull request, run:

```bash
composer install
vendor/bin/playwright-install --browsers
composer cs-check
vendor/bin/phpstan analyse
composer test
```

Changes to public behavior should include tests and documentation.

## License

Playwright PHP for Symfony is released under the [MIT License](LICENSE).
