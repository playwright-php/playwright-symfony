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

use Playwright\Symfony\Test\PlaywrightTestCase;
use Playwright\Symfony\Tests\Fixtures\App\TestKernel;
use Symfony\Component\HttpKernel\KernelInterface;

final class UxRedirectTest extends PlaywrightTestCase
{
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', false);
    }

    public function testTurboNavigationAndFrameFollowKernelRedirects(): void
    {
        $page = $this->visit('/ux/turbo');

        $page->locator('#turbo-drive-link')->click();

        self::assertSame('Turbo navigation followed the redirect.', $page->locator('#turbo-final')->textContent());
        $page->waitForURL('**/ux/turbo/final');
        self::assertSame($this->getBaseUrl().'/ux/turbo/final', $page->url());

        $this->visit('/ux/turbo');
        $page->locator('#turbo-frame-link')->click();

        self::assertSame($this->getBaseUrl().'/ux/turbo', $page->url());
        self::assertSame('Turbo Frame followed the redirect.', $page->locator('#turbo-frame-final')->textContent());
        self::assertTrue($page->locator('#turbo-page')->isVisible());
    }

    public function testLiveComponentActionFollowsKernelRedirect(): void
    {
        $page = $this->visit('/ux/live');

        $page->locator('#live-redirect')->click();

        self::assertSame('LiveComponent followed the redirect.', $page->locator('#live-final')->textContent());
        $page->waitForURL('**/ux/live/final');
        self::assertSame($this->getBaseUrl().'/ux/live/final', $page->url());
    }
}
