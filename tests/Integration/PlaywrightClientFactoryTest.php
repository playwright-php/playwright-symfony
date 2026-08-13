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
use Playwright\Symfony\Test\PlaywrightTestCase;
use Playwright\Symfony\Tests\Fixtures\App\TestKernel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;

#[CoversClass(PlaywrightTestCase::class)]
final class PlaywrightClientFactoryTest extends PlaywrightTestCase
{
    private int $beforeRequestCount = 0;
    private int $afterResponseCount = 0;

    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', false);
    }

    public function testSetUpLeavesKernelAndBrowserDormant(): void
    {
        $this->assertNull(self::$kernel);
        $this->assertNull(self::$sharedBrowser);
    }

    public function testCreatedClientsHaveIsolatedCookiesAndStorage(): void
    {
        $primary = self::getPlaywrightClient();
        $additional = self::createPlaywrightClient();

        $primaryContext = $primary->context();
        $additionalContext = $additional->context();
        $this->assertNotNull($primaryContext);
        $this->assertNotNull($additionalContext);
        $this->assertNotSame($primaryContext, $additionalContext);

        $primaryContext->addCookies([
            ['name' => 'client', 'value' => 'primary', 'domain' => 'localhost', 'path' => '/'],
        ]);

        $this->assertSame('primary', $primaryContext->cookies()[0]['value']);
        $this->assertSame([], $additionalContext->cookies());

        $primaryPage = $primary->visit('/');
        $additionalPage = $additional->visit('/');
        $primaryPage->evaluate("localStorage.setItem('client', 'primary')");

        $this->assertSame('primary', $primaryPage->evaluate("localStorage.getItem('client')"));
        $this->assertNull($additionalPage->evaluate("localStorage.getItem('client')"));
    }

    public function testLazyClientPreservesRequestHooks(): void
    {
        self::getPlaywrightClient()->visit('/hello');

        $this->assertSame(1, $this->beforeRequestCount);
        $this->assertSame(1, $this->afterResponseCount);
    }

    public function beforeRequest(Request $request): void
    {
        ++$this->beforeRequestCount;
    }

    public function afterResponse(Response $response): void
    {
        ++$this->afterResponseCount;
    }
}
