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

use Playwright\Network\RouteInterface;
use Playwright\Symfony\Test\Assert\PlaywrightTestAssertionsTrait;
use Playwright\Symfony\Test\PlaywrightTestCase;
use Playwright\Symfony\Tests\Fixtures\App\TestKernel;
use Symfony\Component\HttpKernel\KernelInterface;

final class RouteOrderTest extends PlaywrightTestCase
{
    use PlaywrightTestAssertionsTrait;

    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', true);
    }

    public function testRouteAddedBeforeTheFirstVisitCanFallBackToTheKernel(): void
    {
        $seen = [];
        $this->getPage()->route('**/*', static function (RouteInterface $route) use (&$seen): void {
            $seen[] = $route->request()->url();
            $route->fallback();
        });

        $this->visit('/hello');

        $this->assertPageContains('hello from app');
        self::assertContains('http://localhost/hello', $seen);
    }

    public function testRouteAddedBeforeTheFirstVisitWinsOverTheKernel(): void
    {
        $this->getPage()->route('**/hello', static function (RouteInterface $route): void {
            $route->fulfill(['status' => 200, 'contentType' => 'text/html', 'body' => '<p>from the test route</p>']);
        });

        $this->visit('/hello');

        $this->assertPageContains('from the test route');
        self::assertNull($this->getLastResponse());
    }

    public function testRouteAddedAfterTheFirstVisitCanFallBackToTheKernel(): void
    {
        $this->visit('/hello');
        $seen = [];
        $this->getPage()->route('**/*', static function (RouteInterface $route) use (&$seen): void {
            $seen[] = $route->request()->url();
            $route->fallback();
        });

        $this->visit('/hello?again=1');

        $this->assertPageContains('hello from app');
        self::assertContains('http://localhost/hello?again=1', $seen);
        self::assertSame('1', $this->getLastRequest()?->query->get('again'));
    }

    public function testContextRoutesOnlySeeRequestsTheKernelDoesNotServe(): void
    {
        $hosts = [];
        $this->getPlaywrightClient()->context()?->route('**/*', static function (RouteInterface $route) use (&$hosts): void {
            $host = parse_url($route->request()->url(), \PHP_URL_HOST);
            $hosts[] = $host;
            if ('outside.test' !== $host) {
                $route->abort();

                return;
            }
            $route->fulfill(['status' => 200, 'contentType' => 'text/html', 'body' => '<p>from the context route</p>']);
        });

        $this->visit('/hello');
        $this->assertPageContains('hello from app');

        $this->getPage()->goto('http://outside.test/');

        $this->assertPageContains('from the context route');
        self::assertSame(['outside.test'], array_unique($hosts));
    }
}
