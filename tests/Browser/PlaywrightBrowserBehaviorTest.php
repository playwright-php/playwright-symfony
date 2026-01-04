<?php

declare(strict_types=1);

/*
 * This file is part of the community-maintained Playwright PHP project.
 * It is not affiliated with or endorsed by Microsoft.
 *
 * (c) 2025-Present - Playwright PHP <https://github.com/playwright-php>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Playwright\Symfony\Tests\Browser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Playwright\Symfony\Browser\PlaywrightBrowser;
use Playwright\Symfony\Tests\Fixtures\Browser\DummyBrowserContext;
use Playwright\Symfony\Tests\Fixtures\Browser\DummyPage;

#[CoversClass(PlaywrightBrowser::class)]
class PlaywrightBrowserBehaviorTest extends TestCase
{
    public function testGetPageReturnsExistingPageWithoutStarting(): void
    {
        $page = new DummyPage();
        $context = new DummyBrowserContext($page);

        $browser = new PlaywrightBrowser('chromium', true);

        $refContext = new \ReflectionProperty(PlaywrightBrowser::class, 'context');

        $refContext->setValue($browser, $context);

        $refPage = new \ReflectionProperty(PlaywrightBrowser::class, 'page');
        $refPage->setValue($browser, $page);

        $result = $browser->getPage();

        $this->assertSame($page, $result);
    }

    public function testSetupRoutingRegistersRouteOnPage(): void
    {
        $page = new DummyPage();
        $context = new DummyBrowserContext($page);

        $browser = new PlaywrightBrowser('chromium', true);

        $refContext = new \ReflectionProperty(PlaywrightBrowser::class, 'context');

        $refContext->setValue($browser, $context);

        $refPage = new \ReflectionProperty(PlaywrightBrowser::class, 'page');
        $refPage->setValue($browser, $page);

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

        $browser = new PlaywrightBrowser('chromium', true);

        $refContext = new \ReflectionProperty(PlaywrightBrowser::class, 'context');
        $refContext->setValue($browser, $context);

        $refPage = new \ReflectionProperty(PlaywrightBrowser::class, 'page');
        $refPage->setValue($browser, $page);

        $browser->stop();

        $this->assertTrue($context->closed);
        $this->assertNull($refContext->getValue($browser));
        $this->assertNull($refPage->getValue($browser));
    }
}
