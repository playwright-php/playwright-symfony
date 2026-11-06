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

final class HelloTest extends PlaywrightTestCase
{
    use PlaywrightTestAssertionsTrait;

    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', true);
    }

    public function testHelloPageIsServedInProcess(): void
    {
        $this->visit('/hello');
        $this->assertPageContains('hello from app');
    }

    public function testRequestRetainsNonStandardPort(): void
    {
        $client = static::getPlaywrightClient();
        $client->setInterceptedHosts(['localhost:82']);
        $page = $client->getPage();

        self::assertNotNull($page);

        $page->goto('http://localhost:82/hello');

        $this->assertPageContains('hello from app');
        $request = $this->getLastRequest();

        self::assertNotNull($request);
        self::assertSame('http://localhost:82', $request->getSchemeAndHttpHost());
    }
}
