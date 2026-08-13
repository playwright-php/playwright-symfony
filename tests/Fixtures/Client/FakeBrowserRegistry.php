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

namespace Playwright\Symfony\Tests\Fixtures\Client;

use Playwright\Browser\BrowserContextInterface;
use Playwright\Page\PageInterface;
use Playwright\Symfony\Client\BrowserRegistry;
use Playwright\Symfony\Client\BrowserSession;
use Playwright\Symfony\Tests\Fixtures\Browser\DummyBrowserContext;
use Playwright\Symfony\Tests\Fixtures\Browser\DummyPage;

final class FakeBrowserRegistry extends BrowserRegistry
{
    public bool $stopped = false;
    public bool $started = false;
    public int $resetCount = 0;
    public int $sessionCount = 0;

    public function __construct(
        private readonly string $browserType = 'chromium',
        private readonly bool $headless = true,
    ) {
        // Do not call parent constructor to avoid real Playwright usage
    }

    public function start(): void
    {
        $this->started = true;
    }

    public function stop(): void
    {
        $this->stopped = true;
    }

    public function resetSessions(): void
    {
        ++$this->resetCount;
    }

    public function createSession(): BrowserSession
    {
        ++$this->sessionCount;

        return new BrowserSession(new DummyBrowserContext(new DummyPage()));
    }

    public function equals(BrowserRegistry $other): bool
    {
        return true;
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
