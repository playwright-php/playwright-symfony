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

namespace Playwright\Symfony\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Playwright\Symfony\Client\BrowserRegistry;

/**
 * BrowserRegistry behaviour that launches a real browser.
 */
#[CoversClass(BrowserRegistry::class)]
class BrowserRegistryTest extends TestCase
{
    public function testStartInitializesContextAndPage(): void
    {
        $browser = new BrowserRegistry('chromium', true);
        $browser->start();

        $context = $browser->getContext();
        $page = $browser->getPage();

        $this->assertNotNull($context);
        $this->assertNotNull($page);

        $browser->stop();
    }

    public function testStartIsIdempotent(): void
    {
        $browser = new BrowserRegistry('chromium', true);
        $browser->start();

        $firstContext = $browser->getContext();

        // Calling start again should not create new context
        $browser->start();

        $secondContext = $browser->getContext();

        $this->assertSame($firstContext, $secondContext);

        $browser->stop();
    }

    public function testGetContextAutoStartsIfNotStarted(): void
    {
        $browser = new BrowserRegistry('chromium', true);
        $context = $browser->getContext();

        $this->assertNotNull($context);

        $browser->stop();
    }

    public function testRestartContextClosesOldContextAndStartsNew(): void
    {
        $browser = new BrowserRegistry('chromium', true);
        $browser->start();

        $firstContext = $browser->getContext();
        $firstPage = $browser->getPage();

        $browser->restartContext();

        $secondContext = $browser->getContext();
        $secondPage = $browser->getPage();

        // Context and page should be different instances after restart
        $this->assertNotSame($firstContext, $secondContext);
        $this->assertNotSame($firstPage, $secondPage);

        $browser->stop();
    }
}
