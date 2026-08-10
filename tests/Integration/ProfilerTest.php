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

use PHPUnit\Framework\Attributes\CoversNothing;
use Playwright\Symfony\Test\PlaywrightTestCase;
use Playwright\Symfony\Tests\Fixtures\App\TestKernel;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\HttpKernel\Profiler\Profiler;

#[CoversNothing]
final class ProfilerTest extends PlaywrightTestCase
{
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', false);
    }

    public function testNoProfileIsAvailableByDefault(): void
    {
        $this->client->visit('/hello');

        self::assertNull($this->client->getProfile());
    }

    public function testEnableProfilerMakesTheProfileAvailable(): void
    {
        $this->client->enableProfiler();
        $this->client->visit('/hello');

        self::assertInstanceOf(Profile::class, $this->client->getProfile());
    }

    public function testProfilerAppliesToASingleRequest(): void
    {
        $this->client->enableProfiler();
        $this->client->visit('/hello');

        self::assertInstanceOf(Profile::class, $this->client->getProfile());

        $this->client->visit('/hello');

        self::assertNull($this->client->getProfile(), 'expected profiling to apply to one request only');
    }

    public function testGloballyEnabledProfilerStaysEnabled(): void
    {
        $profiler = self::$kernel?->getContainer()->get('profiler');

        self::assertInstanceOf(Profiler::class, $profiler);
        $profiler->enable();

        $this->client->enableProfiler();
        $this->client->visit('/hello');

        self::assertTrue($profiler->isEnabled());
    }
}
