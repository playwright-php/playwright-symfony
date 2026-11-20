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

final class VisitTest extends PlaywrightTestCase
{
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', false);
    }

    public function testAbsoluteUrlReachesTheKernelOnAnotherInterceptedHost(): void
    {
        $this->visit('http://testapp.local/cookie?set=1');

        self::assertSame('http://testapp.local', $this->getLastRequest()?->getSchemeAndHttpHost());
        self::assertSame('testapp.local', static::getPlaywrightClient()->getCookieJar()->get('set_by', '/', 'testapp.local')?->getValue());
    }
}
