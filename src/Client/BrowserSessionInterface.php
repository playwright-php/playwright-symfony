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

namespace Playwright\Symfony\Client;

use Playwright\Browser\BrowserContextInterface;
use Playwright\Page\PageInterface;

/**
 * An isolated browser context and page managed by a browser registry.
 */
interface BrowserSessionInterface
{
    public function getContext(): ?BrowserContextInterface;

    public function getPage(): ?PageInterface;

    public function setupRouting(callable $routeHandler): void;

    public function close(): void;
}
