<?php

declare(strict_types=1);

/*
 * This file is part of the community-maintained Playwright PHP project.
 * It is not affiliated with or endorsed by Microsoft.
 *
 * (c) 2025-Present - Playwright PHP - https://github.com/playwright-php
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Playwright\Symfony\Tests\Client;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Playwright\Browser\BrowserContextInterface;
use Playwright\PlaywrightClient;
use Playwright\Symfony\Client\BrowserRegistry;
use Playwright\Symfony\Client\BrowserSession;
use Playwright\Symfony\Tests\Fixtures\Browser\DummyBrowserContext;
use Playwright\Symfony\Tests\Fixtures\Browser\DummyPage;

#[CoversClass(BrowserRegistry::class)]
class BrowserRegistryTest extends TestCase
{
    private ?string $originalBrowser;

    protected function setUp(): void
    {
        $this->originalBrowser = getenv('PLAYWRIGHT_BROWSER') ?: null;
    }

    protected function tearDown(): void
    {
        if (null === $this->originalBrowser) {
            putenv('PLAYWRIGHT_BROWSER');
        } else {
            putenv('PLAYWRIGHT_BROWSER='.$this->originalBrowser);
        }
    }

    public function testFromEnvironmentDefaultsToChromiumOnInvalidBrowser(): void
    {
        putenv('PLAYWRIGHT_BROWSER=invalid');
        putenv('PLAYWRIGHT_HEADLESS=1');

        $browser = BrowserRegistry::fromEnvironment();

        $this->assertSame('chromium', $browser->getBrowserType());
        $this->assertTrue($browser->isHeadless());
    }

    public function testFromEnvironmentRespectsValidBrowserTypesAndHeadlessFlag(): void
    {
        putenv('PLAYWRIGHT_BROWSER=firefox');
        putenv('PLAYWRIGHT_HEADLESS=false');

        $browser = BrowserRegistry::fromEnvironment();

        $this->assertSame('firefox', $browser->getBrowserType());
        $this->assertFalse($browser->isHeadless());

        putenv('PLAYWRIGHT_BROWSER=webkit');
        putenv('PLAYWRIGHT_HEADLESS=1');

        $browser = BrowserRegistry::fromEnvironment();

        $this->assertSame('webkit', $browser->getBrowserType());
        $this->assertTrue($browser->isHeadless());
    }

    public function testGetPageReturnsExistingPageWithoutStarting(): void
    {
        $page = new DummyPage();
        $context = new DummyBrowserContext($page);

        $browser = new BrowserRegistry('chromium', true);

        $refSession = new \ReflectionProperty(BrowserRegistry::class, 'session');
        $refSession->setValue($browser, new BrowserSession($context));

        $result = $browser->getPage();

        $this->assertSame($page, $result);
    }

    public function testSetupRoutingRegistersRouteOnPage(): void
    {
        $page = new DummyPage();
        $context = new DummyBrowserContext($page);

        $browser = new BrowserRegistry('chromium', true);

        $refSession = new \ReflectionProperty(BrowserRegistry::class, 'session');
        $refSession->setValue($browser, new BrowserSession($context));

        $handler = static function (): void {
        };

        $browser->setupRouting($handler);

        $this->assertNotEmpty($page->routes);
        $this->assertSame('**/*', $page->routes[0][0]);
        $this->assertSame($handler, $page->routes[0][1]);
    }

    public function testStopClosesContextAndClearsState(): void
    {
        $page = new DummyPage();
        $context = new DummyBrowserContext($page);

        $browser = new BrowserRegistry('chromium', true);

        $refSession = new \ReflectionProperty(BrowserRegistry::class, 'session');
        $refSession->setValue($browser, new BrowserSession($context));

        $browser->stop();

        $this->assertTrue($context->closed);
        $this->assertNull($refSession->getValue($browser));
    }

    public function testStopClosesTheClientWhenTheContextCannotBeClosed(): void
    {
        $context = $this->createMock(BrowserContextInterface::class);
        $context->method('close')->willThrowException(new \RuntimeException('connection is broken'));

        $client = $this->createMock(PlaywrightClient::class);
        $client->expects($this->once())->method('close');

        $browser = new BrowserRegistry('chromium', true);

        $refSession = new \ReflectionProperty(BrowserRegistry::class, 'session');
        $refSession->setValue($browser, new BrowserSession($context));

        $refClient = new \ReflectionProperty(BrowserRegistry::class, 'client');
        $refClient->setValue($browser, $client);

        $browser->stop();

        $this->assertNull($refSession->getValue($browser));
        $this->assertNull($refClient->getValue($browser));
    }

    public function testEqualsReturnsTrueForSameBrowserConfiguration(): void
    {
        $browser1 = new BrowserRegistry('chromium', true, ['arg' => 'value']);
        $browser2 = new BrowserRegistry('chromium', true, ['arg' => 'value']);

        $this->assertTrue($browser1->equals($browser2));
    }

    public function testEqualsReturnsFalseForDifferentBrowserType(): void
    {
        $browser1 = new BrowserRegistry('chromium', true);
        $browser2 = new BrowserRegistry('firefox', true);

        $this->assertFalse($browser1->equals($browser2));
    }

    public function testEqualsReturnsFalseForDifferentHeadlessMode(): void
    {
        $browser1 = new BrowserRegistry('chromium', true);
        $browser2 = new BrowserRegistry('chromium', false);

        $this->assertFalse($browser1->equals($browser2));
    }

    public function testEqualsReturnsFalseForDifferentLaunchOptions(): void
    {
        $browser1 = new BrowserRegistry('chromium', true, ['option1' => 'value1']);
        $browser2 = new BrowserRegistry('chromium', true, ['option2' => 'value2']);

        $this->assertFalse($browser1->equals($browser2));
    }
}
