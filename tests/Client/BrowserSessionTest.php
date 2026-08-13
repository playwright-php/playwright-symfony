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
use Playwright\Symfony\Client\BrowserSession;
use Playwright\Symfony\Tests\Fixtures\Browser\DummyBrowserContext;
use Playwright\Symfony\Tests\Fixtures\Browser\DummyPage;

#[CoversClass(BrowserSession::class)]
class BrowserSessionTest extends TestCase
{
    public function testOwnsContextAndPage(): void
    {
        $page = new DummyPage();
        $context = new DummyBrowserContext($page);
        $session = new BrowserSession($context);

        $this->assertSame($context, $session->getContext());
        $this->assertSame($page, $session->getPage());
    }

    public function testSetupRoutingRegistersRouteOnPage(): void
    {
        $page = new DummyPage();
        $session = new BrowserSession(new DummyBrowserContext($page));
        $handler = static function (): void {
        };

        $session->setupRouting($handler);

        $this->assertSame([['**/*', $handler]], $page->routes);
    }

    public function testCloseClosesContextAndClearsState(): void
    {
        $context = new DummyBrowserContext(new DummyPage());
        $session = new BrowserSession($context);

        $session->close();

        $this->assertTrue($context->closed);
        $this->assertNull($session->getContext());
        $this->assertNull($session->getPage());
    }
}
