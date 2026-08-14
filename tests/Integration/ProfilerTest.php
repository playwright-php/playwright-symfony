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
        static::getPlaywrightClient()->visit('/hello');

        self::assertNull(static::getPlaywrightClient()->getProfile());
    }

    public function testEnableProfilerMakesTheProfileAvailable(): void
    {
        static::getPlaywrightClient()->enableProfiler();
        static::getPlaywrightClient()->visit('/hello');

        self::assertInstanceOf(Profile::class, static::getPlaywrightClient()->getProfile());
    }

    public function testProfilerAppliesToASingleRequest(): void
    {
        static::getPlaywrightClient()->enableProfiler();
        static::getPlaywrightClient()->visit('/hello');

        self::assertInstanceOf(Profile::class, static::getPlaywrightClient()->getProfile());

        static::getPlaywrightClient()->visit('/hello');

        self::assertNull(static::getPlaywrightClient()->getProfile(), 'expected profiling to apply to one request only');
    }

    public function testProfileSurvivesAFollowedRedirectChain(): void
    {
        static::getPlaywrightClient()->enableProfiler();
        static::getPlaywrightClient()->visit('/redirect?status=302&hops=2');

        $profile = static::getPlaywrightClient()->getProfile();

        // the hops after the profiled request must not erase its token
        self::assertInstanceOf(Profile::class, $profile);
        self::assertSame('/redirect', $profile->getUrl() ? parse_url($profile->getUrl(), \PHP_URL_PATH) : null);
        self::assertSame(302, $profile->getStatusCode());
    }

    public function testGloballyEnabledProfilerStaysEnabled(): void
    {
        $profiler = self::getContainer()->get('profiler');

        self::assertInstanceOf(Profiler::class, $profiler);
        $profiler->enable();

        static::getPlaywrightClient()->enableProfiler();
        static::getPlaywrightClient()->visit('/hello');

        self::assertTrue($profiler->isEnabled());
    }
}
