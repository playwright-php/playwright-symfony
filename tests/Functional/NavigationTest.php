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

namespace Playwright\Symfony\Tests\Functional;

use Playwright\Symfony\Test\Assert\PlaywrightTestAssertionsTrait;
use Playwright\Symfony\Test\PlaywrightTestCase;
use Playwright\Symfony\Tests\Fixtures\App\TestKernel;
use Symfony\Component\HttpKernel\KernelInterface;

final class NavigationTest extends PlaywrightTestCase
{
    use PlaywrightTestAssertionsTrait;

    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', false);
    }

    public function testClickLinkNavigatesToNewPage(): void
    {
        $this->visit('/');

        // Click first link
        $this->getPage()->locator('#link-1')->click();

        // Should navigate to /1/
        self::assertStringContainsString('/1/', $this->getPage()->url());
        $this->assertPageContains('Current path: <strong id="current-path">1</strong>');
    }

    public function testNavigationChainFollowsMultipleClicks(): void
    {
        $this->visit('/');

        // Click through navigation chain
        $this->getPage()->locator('#link-1')->click(); // Go to /1/
        $this->getPage()->locator('#link-2')->click(); // Go to /12/

        // Should be at /12/
        self::assertStringContainsString('/12/', $this->getPage()->url());
        $this->assertPageContains('Current path: <strong id="current-path">12</strong>');
    }

    public function testBrowserBackNavigation(): void
    {
        $this->visit('/');

        // Navigate forward
        $this->getPage()->locator('#link-1')->click();
        self::assertStringContainsString('/1/', $this->getPage()->url());

        // Navigate back
        $this->getPage()->goBack();

        // Should be back at root
        self::assertStringContainsString(':/', $this->getPage()->url());
        $this->assertPageContains('Current path: <strong id="current-path"></strong>');
    }

    public function testBrowserForwardNavigation(): void
    {
        $this->visit('/');

        // Navigate forward
        $this->getPage()->locator('#link-1')->click();

        // Navigate back
        $this->getPage()->goBack();

        // Navigate forward again
        $this->getPage()->goForward();

        // Should be at /1/ again
        self::assertStringContainsString('/1/', $this->getPage()->url());
    }
}
