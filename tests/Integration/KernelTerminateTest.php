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
use Playwright\Symfony\Tests\Fixtures\App\EventListener\TerminateRecorder;
use Playwright\Symfony\Tests\Fixtures\App\TestKernel;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\HttpKernel\Profiler\Profiler;

#[CoversNothing]
final class KernelTerminateTest extends PlaywrightTestCase
{
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', false);
    }

    public function testTerminateListenersRunForInterceptedRequests(): void
    {
        $recorder = self::getContainer()->get(TerminateRecorder::class);

        self::assertInstanceOf(TerminateRecorder::class, $recorder);
        self::assertSame([], $recorder->paths());

        static::getPlaywrightClient()->visit('/hello');

        self::assertSame(['/hello'], $recorder->paths());
    }

    public function testProfileIsSavedForInterceptedRequests(): void
    {
        $profiler = self::getContainer()->get('profiler');

        self::assertInstanceOf(Profiler::class, $profiler);
        $profiler->enable();

        static::getPlaywrightClient()->visit('/hello');

        // the profile is only written to storage on kernel.terminate
        self::assertInstanceOf(Profile::class, static::getPlaywrightClient()->getProfile());
    }
}
