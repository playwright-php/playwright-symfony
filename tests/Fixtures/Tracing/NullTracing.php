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

namespace Playwright\Symfony\Tests\Fixtures\Tracing;

use Playwright\Tracing\Options\StartChunkOptions;
use Playwright\Tracing\Options\StartHarOptions;
use Playwright\Tracing\Options\StartOptions;
use Playwright\Tracing\Options\StopChunkOptions;
use Playwright\Tracing\Options\StopOptions;
use Playwright\Tracing\TracingInterface;

/**
 * No-op tracing, returned by the browser context test doubles.
 *
 * The bundle never traces: it only has to satisfy the interface.
 */
final class NullTracing implements TracingInterface
{
    public function start(array|StartOptions $options = []): void
    {
    }

    public function startChunk(array|StartChunkOptions $options = []): void
    {
    }

    public function stop(array|StopOptions $options = []): void
    {
    }

    /**
     * @param array<string, mixed>|StopChunkOptions $options
     */
    public function stopChunk(array|StopChunkOptions $options = []): void
    {
    }

    public function group(string $name, ?string $location = null): void
    {
    }

    public function groupEnd(): void
    {
    }

    public function startHar(string $path, StartHarOptions|array $options = []): void
    {
    }

    public function stopHar(): void
    {
    }
}
