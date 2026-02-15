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

namespace Playwright\Symfony\Tests\Fixtures\Browser;

use Playwright\Browser\BrowserContextInterface;
use Playwright\Page\PageInterface;
use Playwright\Symfony\Browser\PlaywrightBrowser;

final class FakePlaywrightBrowser extends PlaywrightBrowser
{
    public bool $stopped = false;

    public function __construct(
        private readonly string $browserType = 'chromium',
        private readonly bool $headless = true,
    ) {
        // Do not call parent constructor to avoid real Playwright usage
    }

    public function start(): void
    {
        // no-op for tests
    }

    public function stop(): void
    {
        $this->stopped = true;
    }

    public function getContext(): ?BrowserContextInterface
    {
        return null;
    }

    public function getPage(): ?PageInterface
    {
        return null;
    }

    public function setupRouting(callable $routeHandler): void
    {
        // no-op for tests
    }

    public function isHeadless(): bool
    {
        return $this->headless;
    }

    public function getBrowserType(): string
    {
        return $this->browserType;
    }
}
