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
    /**
     * Returns the isolated browser context, or null after the session is closed.
     */
    public function getContext(): ?BrowserContextInterface;

    /**
     * Returns the session's page, or null after the session is closed.
     */
    public function getPage(): ?PageInterface;

    /**
     * Registers a request handler on the session's page.
     */
    public function setupRouting(callable $routeHandler): void;

    /**
     * Closes the isolated browser context.
     *
     * Use BrowserRegistry::closeSession() for sessions managed by a registry so it can release
     * its reference to the session immediately.
     */
    public function close(): void;
}
