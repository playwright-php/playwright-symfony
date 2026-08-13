# Getting Started

## Prerequisites

- **PHP**: 8.3 or higher.
- **Node.js**: 20 or higher.
- **Composer**: Latest version recommended.

## Installation

### 1. Install the Bundle

Run the following command to add the bundle to your project's development dependencies:

```bash
composer require --dev playwright-php/playwright-symfony
```

### 2. Set Up Playwright

After installing the bundle via Composer, you must initialize the Playwright environment. The bundle leverages the core
`playwright-php` installation scripts.

#### A. Initialize Playwright PHP

This command sets up the necessary Node.js bridge and internal dependencies:

```bash
vendor/bin/playwright-install
```

#### B. Install Browsers

Download the required browser binaries (Chromium, Firefox, WebKit). You have two options depending on your environment:

**For Local Development:**

```bash
vendor/bin/playwright-install --browsers
```

**For CI or Fresh Servers (Includes OS Dependencies):**

```bash
vendor/bin/playwright-install --with-deps
```

## Creating Your First Test

The bundle provides a `PlaywrightTestCase` class that integrates the browser lifecycle with the Symfony Kernel.

```php
// tests/Controller/HomepageTest.php
namespace App\Tests\Controller;

use Playwright\Symfony\Test\PlaywrightTestCase;

class HomepageTest extends PlaywrightTestCase
{
    public function testHomepageIsAccessible(): void
    {
        // Visit your app (handled in-process via Kernel interception)
        $this->visit('/');

        // Assert using the built-in helpers
        $this->assertResponseIsSuccessful();
        $this->assertPageContains('Welcome to Symfony');
        
        // Interact with the real browser using the Playwright Page API
        $this->getPage()->locator('a.btn-primary')->click();
        $this->assertStringContainsString('/dashboard', $this->getPage()->url());
    }
}
```

## Running the Suite

Browser tests run like any other test:

```bash
vendor/bin/phpunit
```

They are slower than the rest of your suite, so it is worth being able to run without them. Use PHPUnit's own
selection: put them in their own `<testsuite>` (or tag them with `#[Group('e2e')]`) and skip them with
`--testsuite=unit` or `--exclude-group=e2e`.

### Debugging & Visualization

To see what is happening inside the browser during a test run, disable headless mode:

```bash
PLAYWRIGHT_HEADLESS=false vendor/bin/phpunit
```

To use a specific browser engine (default is `chromium`):

```bash
PLAYWRIGHT_BROWSER=firefox vendor/bin/phpunit
```
