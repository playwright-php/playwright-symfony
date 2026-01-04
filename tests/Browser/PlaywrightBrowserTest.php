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

#[CoversClass(PlaywrightBrowser::class)]
class PlaywrightBrowserTest extends TestCase
{
    public function testFromEnvironmentDefaultsToChromiumOnInvalidBrowser(): void
    {
        putenv('PLAYWRIGHT_BROWSER=invalid');
        putenv('PLAYWRIGHT_HEADLESS=1');

        $browser = PlaywrightBrowser::fromEnvironment();

        $this->assertSame('chromium', $browser->getBrowserType());
        $this->assertTrue($browser->isHeadless());
    }

    public function testFromEnvironmentRespectsValidBrowserTypesAndHeadlessFlag(): void
    {
        putenv('PLAYWRIGHT_BROWSER=firefox');
        putenv('PLAYWRIGHT_HEADLESS=false');

        $browser = PlaywrightBrowser::fromEnvironment();

        $this->assertSame('firefox', $browser->getBrowserType());
        $this->assertFalse($browser->isHeadless());

        putenv('PLAYWRIGHT_BROWSER=webkit');
        putenv('PLAYWRIGHT_HEADLESS=1');

        $browser = PlaywrightBrowser::fromEnvironment();

        $this->assertSame('webkit', $browser->getBrowserType());
        $this->assertTrue($browser->isHeadless());
    }
}
